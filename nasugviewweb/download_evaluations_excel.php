<?php
session_start();

require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$columns = [
    'id' => 'ID',
    'event_id' => 'Event ID',
    'event_code' => 'Event Code',
    'user_id' => 'User ID',
    'account_type' => 'Account Type',
    'full_name' => 'Full Name',
    'email' => 'Email',
    'contact_number' => 'Contact Number',
    'client_type' => 'Client Type',
    'sex' => 'Sex',
    'age_group' => 'Age Group',
    'cc1' => 'CC1',
    'cc2' => 'CC2',
    'cc3' => 'CC3',
    'overall_rating' => 'Overall Rating',
    'content_rating' => 'Content Rating',
    'speaker_rating' => 'Speaker Rating',
    'responsiveness_rating' => 'Responsiveness',
    'reliability_rating' => 'Reliability',
    'access_facilities_rating' => 'Access and Facilities',
    'communication_rating' => 'Communication',
    'integrity_rating' => 'Integrity',
    'assurance_rating' => 'Assurance',
    'outcome_rating' => 'Outcome',
    'comment' => 'Comment',
    'improvement_reason' => 'Improvement Reason',
    'service_suggestions' => 'Service Suggestions',
    'consent_given' => 'Consent Given',
    'created_at' => 'Created At'
];

$fieldList = implode(', ', array_keys($columns));
$selectedEventCode = isset($_GET['event_code']) ? trim((string) $_GET['event_code']) : '';
$result = null;

if ($selectedEventCode !== '') {
    $stmt = $conn->prepare("SELECT {$fieldList} FROM event_evaluations WHERE event_code = ? ORDER BY created_at DESC, id DESC");
    if ($stmt) {
        $stmt->bind_param("s", $selectedEventCode);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    $result = $conn->query("SELECT {$fieldList} FROM event_evaluations ORDER BY created_at DESC, id DESC");
}

$evaluationRows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $evaluationRows[] = $row;
    }
}

if (isset($stmt) && $stmt) {
    $stmt->close();
}

function exportCell($value) {
    return ($value === null || $value === '') ? '-' : $value;
}

function exportPercent($count, $total) {
    return $total > 0 ? number_format(($count / $total) * 100, 2) . '%' : '0.00%';
}

function exportRatingLabel($percent) {
    if ($percent >= 95) {
        return 'Outstanding';
    }
    if ($percent >= 80) {
        return 'Very Satisfactory';
    }
    if ($percent >= 60) {
        return 'Satisfactory';
    }
    if ($percent > 0) {
        return 'Fair';
    }
    return 'N/A';
}

function normalizeExportValue($value) {
    $value = trim((string) $value);
    return $value === '' ? 'Did not specify' : $value;
}

function countExportOptions($rows, $field, $options, $normalizer = null) {
    $counts = array_fill_keys(array_keys($options), 0);

    foreach ($rows as $row) {
        $value = $normalizer ? $normalizer($row[$field] ?? '') : normalizeExportValue($row[$field] ?? '');
        if (!array_key_exists($value, $counts)) {
            $value = 'Did not specify';
        }
        $counts[$value]++;
    }

    $total = count($rows);
    $tableRows = [];
    foreach ($options as $key => $label) {
        $count = $counts[$key] ?? 0;
        $tableRows[] = [
            'label' => $label,
            'count' => $count,
            'percent' => exportPercent($count, $total),
            'percent_value' => $total > 0 ? ($count / $total) * 100 : 0
        ];
    }

    return $tableRows;
}

function countExportRatingLevels($rows, $fields) {
    $counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $total = 0;

    foreach ($rows as $row) {
        foreach ($fields as $field) {
            if (($row[$field] ?? '') === '' || $row[$field] === null) {
                continue;
            }

            $rating = (int) $row[$field];
            if (isset($counts[$rating])) {
                $counts[$rating]++;
                $total++;
            }
        }
    }

    return [$counts, $total];
}

function buildExportDimensionSummary($rows, $field, $label) {
    [$counts, $total] = countExportRatingLevels($rows, [$field]);
    $satisfied = ($counts[5] ?? 0) + ($counts[4] ?? 0);
    $percentValue = $total > 0 ? ($satisfied / $total) * 100 : 0;

    return [
        'label' => $label,
        'rating' => number_format($percentValue, 2) . '%',
        'rating_value' => $percentValue,
        'adjectival' => exportRatingLabel($percentValue),
        'counts' => $counts,
        'total' => $total
    ];
}

function leadingExportRow($rows) {
    $leading = null;

    foreach ($rows as $row) {
        if (($row['label'] ?? '') === 'Did not specify') {
            continue;
        }

        if ($leading === null || (int) $row['count'] > (int) $leading['count']) {
            $leading = $row;
        }
    }

    return $leading;
}

function formatExportEventSchedule($event) {
    if (!$event || empty($event['start_date_and_time'])) {
        return '';
    }

    $start = strtotime($event['start_date_and_time']);
    $end = !empty($event['end_date_and_time']) ? strtotime($event['end_date_and_time']) : null;

    if (!$start) {
        return '';
    }

    if ($end && date('Y-m-d', $start) !== date('Y-m-d', $end)) {
        return date('F j, Y', $start) . ' to ' . date('F j, Y', $end);
    }

    $schedule = date('F j, Y', $start);
    if ($end) {
        $schedule .= ' (' . date('g:i A', $start) . ' - ' . date('g:i A', $end) . ')';
    }

    return $schedule;
}

$selectedEvent = null;
if ($selectedEventCode !== '') {
    $eventStmt = $conn->prepare("SELECT event_code, title, start_date_and_time, end_date_and_time FROM events WHERE event_code = ? LIMIT 1");
    if ($eventStmt) {
        $eventStmt->bind_param("s", $selectedEventCode);
        $eventStmt->execute();
        $selectedEvent = $eventStmt->get_result()->fetch_assoc();
        $eventStmt->close();
    }
}

$totalResponses = count($evaluationRows);
$firstResponseAt = $totalResponses ? end($evaluationRows)['created_at'] : null;
$lastResponseAt = $totalResponses ? $evaluationRows[0]['created_at'] : null;
$eventTitle = $selectedEvent && !empty($selectedEvent['title']) ? $selectedEvent['title'] : ($selectedEventCode !== '' ? $selectedEventCode : 'All Evaluations');
$eventSchedule = formatExportEventSchedule($selectedEvent);
$responsePeriod = 'No responses yet';
if ($firstResponseAt && $lastResponseAt) {
    $responsePeriod = date('F j, Y', strtotime($firstResponseAt));
    if (date('Y-m-d', strtotime($firstResponseAt)) !== date('Y-m-d', strtotime($lastResponseAt))) {
        $responsePeriod .= ' to ' . date('F j, Y', strtotime($lastResponseAt));
    }
}

$dimensionFields = [
    'responsiveness_rating',
    'reliability_rating',
    'access_facilities_rating',
    'communication_rating',
    'integrity_rating',
    'assurance_rating',
    'outcome_rating'
];
[$overallCounts, $overallRatingTotal] = countExportRatingLevels($evaluationRows, array_merge(['overall_rating'], $dimensionFields));
$overallSatisfied = ($overallCounts[5] ?? 0) + ($overallCounts[4] ?? 0);
$overallPercentValue = $overallRatingTotal > 0 ? ($overallSatisfied / $overallRatingTotal) * 100 : 0;
$overallSatisfaction = number_format($overallPercentValue, 2) . '%';
$overallAdjectival = exportRatingLabel($overallPercentValue);

$sexRows = countExportOptions($evaluationRows, 'sex', [
    'Male' => 'Male',
    'Female' => 'Female',
    'Did not specify' => 'Did not specify'
], function ($value) {
    $value = strtolower(trim((string) $value));
    if (in_array($value, ['m', 'male'], true)) {
        return 'Male';
    }
    if (in_array($value, ['f', 'female'], true)) {
        return 'Female';
    }
    return 'Did not specify';
});
$ageRows = countExportOptions($evaluationRows, 'age_group', [
    '19 or lower' => '19 or lower',
    '20-34' => '20-34',
    '35-49' => '35-49',
    '50-64' => '50-64',
    '65 or higher' => '65 or higher',
    'Did not specify' => 'Did not specify'
]);
$clientTypeRows = countExportOptions($evaluationRows, 'client_type', [
    'Citizen' => 'Citizen',
    'Business' => 'Business',
    'Government' => 'Government',
    'Did not specify' => 'Did not specify'
]);
$cc1Rows = countExportOptions($evaluationRows, 'cc1', [
    '1' => "(1) I know what a CC is and I saw this office's CC.",
    '2' => "(2) I know what a CC is but I did not see this office's CC.",
    '3' => "(3) I learned of the CC only when I saw this office's CC.",
    '4' => '(4) I do not know what a CC is and I did not see one.',
    'Did not specify' => '(-) Not Applicable'
]);
$cc2Rows = countExportOptions($evaluationRows, 'cc2', [
    '1' => '(1) Easy to see',
    '2' => '(2) Somewhat easy to see',
    '3' => '(3) Difficult to see',
    '4' => '(4) Not visible',
    '5' => '(5) N/A',
    'Did not specify' => '(-) Not Applicable'
]);
$cc3Rows = countExportOptions($evaluationRows, 'cc3', [
    '1' => '(1) Helped very much',
    '2' => '(2) Somewhat helped',
    '3' => '(3) Did not help',
    'Did not specify' => '(-) Not Applicable'
]);

$satisfactionLabels = [
    5 => 'Strongly Agree',
    4 => 'Agree',
    3 => 'Neither Agree nor Disagree',
    2 => 'Disagree',
    1 => 'Strongly Disagree'
];
$satisfactionRows = [];
foreach ($satisfactionLabels as $rating => $label) {
    $count = $overallCounts[$rating] ?? 0;
    $satisfactionRows[] = [
        'label' => $label,
        'count' => $count,
        'percent' => exportPercent($count, $overallRatingTotal),
        'percent_value' => $overallRatingTotal > 0 ? ($count / $overallRatingTotal) * 100 : 0
    ];
}

$dimensionSummaries = [
    buildExportDimensionSummary($evaluationRows, 'responsiveness_rating', 'Responsiveness'),
    buildExportDimensionSummary($evaluationRows, 'reliability_rating', 'Reliability'),
    buildExportDimensionSummary($evaluationRows, 'access_facilities_rating', 'Access & Facilities'),
    buildExportDimensionSummary($evaluationRows, 'communication_rating', 'Communication'),
    ['label' => 'Costs', 'rating' => '0.00%', 'rating_value' => 0, 'adjectival' => 'N/A', 'counts' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], 'total' => 0],
    buildExportDimensionSummary($evaluationRows, 'integrity_rating', 'Integrity'),
    buildExportDimensionSummary($evaluationRows, 'assurance_rating', 'Assurance'),
    buildExportDimensionSummary($evaluationRows, 'outcome_rating', 'Outcome')
];
$sqd0Summary = buildExportDimensionSummary($evaluationRows, 'overall_rating', 'Overall Rating/SQD 0');
$reportDimensionSummaries = array_merge([$sqd0Summary], $dimensionSummaries);

$tabulationRows = [];
$speakerRows = [];
foreach ($evaluationRows as $index => $row) {
    $clientName = trim((string) ($row['full_name'] ?? ''));
    if ($clientName === '') {
        $clientName = 'Client ' . ($index + 1);
    }

    $tabulationRows[] = [
        $index + 1,
        $clientName,
        exportCell($row['age_group'] ?? null),
        exportCell($row['sex'] ?? null),
        exportCell($row['client_type'] ?? null),
        exportCell($row['cc1'] ?? null),
        exportCell($row['cc2'] ?? null),
        exportCell($row['cc3'] ?? null),
        exportCell($row['overall_rating'] ?? null),
        exportCell($row['responsiveness_rating'] ?? null),
        exportCell($row['reliability_rating'] ?? null),
        exportCell($row['access_facilities_rating'] ?? null),
        exportCell($row['communication_rating'] ?? null),
        '-',
        exportCell($row['integrity_rating'] ?? null),
        exportCell($row['assurance_rating'] ?? null),
        exportCell($row['outcome_rating'] ?? null)
    ];

    $speakerRows[] = [
        $index + 1,
        $clientName,
        exportCell($row['age_group'] ?? null),
        exportCell($row['sex'] ?? null),
        exportCell($row['client_type'] ?? null),
        exportCell($row['content_rating'] ?? null),
        exportCell($row['speaker_rating'] ?? null)
    ];
}

$reportMatrixRows = [];
foreach ($satisfactionLabels as $rating => $label) {
    $matrixRow = [$label, $overallCounts[$rating] ?? 0];
    foreach ($reportDimensionSummaries as $dimension) {
        $matrixRow[] = $dimension['counts'][$rating] ?? 0;
    }
    $reportMatrixRows[] = $matrixRow;
}
$notApplicableOverall = max(0, ($totalResponses * count($reportDimensionSummaries)) - $overallRatingTotal);
$notApplicableRow = ['Not Applicable', $notApplicableOverall];
foreach ($reportDimensionSummaries as $dimension) {
    $notApplicableRow[] = max(0, $totalResponses - (int) $dimension['total']);
}
$reportMatrixRows[] = $notApplicableRow;

$reportMatrixTotals = ['Total', $overallRatingTotal];
foreach ($reportDimensionSummaries as $dimension) {
    $reportMatrixTotals[] = $dimension['total'];
}

$speakerDimensionSummaries = [
    buildExportDimensionSummary($evaluationRows, 'content_rating', 'Content Rating'),
    buildExportDimensionSummary($evaluationRows, 'speaker_rating', 'Speaker Rating')
];
[$speakerOverallCounts, $speakerOverallTotal] = countExportRatingLevels($evaluationRows, ['content_rating', 'speaker_rating']);
$speakerSatisfied = ($speakerOverallCounts[5] ?? 0) + ($speakerOverallCounts[4] ?? 0);
$speakerPercentValue = $speakerOverallTotal > 0 ? ($speakerSatisfied / $speakerOverallTotal) * 100 : 0;
$speakerSatisfaction = number_format($speakerPercentValue, 2) . '%';
$speakerAdjectival = exportRatingLabel($speakerPercentValue);
$speakerMatrixRows = [];
foreach ($satisfactionLabels as $rating => $label) {
    $speakerMatrixRow = [$label, $speakerOverallCounts[$rating] ?? 0];
    foreach ($speakerDimensionSummaries as $dimension) {
        $speakerMatrixRow[] = $dimension['counts'][$rating] ?? 0;
    }
    $speakerMatrixRows[] = $speakerMatrixRow;
}
$speakerNotApplicableOverall = max(0, ($totalResponses * count($speakerDimensionSummaries)) - $speakerOverallTotal);
$speakerNotApplicableRow = ['Not Applicable', $speakerNotApplicableOverall];
foreach ($speakerDimensionSummaries as $dimension) {
    $speakerNotApplicableRow[] = max(0, $totalResponses - (int) $dimension['total']);
}
$speakerMatrixRows[] = $speakerNotApplicableRow;
$speakerMatrixTotals = ['Total', $speakerOverallTotal];
foreach ($speakerDimensionSummaries as $dimension) {
    $speakerMatrixTotals[] = $dimension['total'];
}

$leadingClientType = leadingExportRow($clientTypeRows);
$leadingAgeGroup = leadingExportRow($ageRows);
$descriptiveAnalysis = $totalResponses > 0
    ? 'For event ' . ($selectedEventCode !== '' ? $selectedEventCode . ' - ' : '') . $eventTitle . ', ' . ($eventSchedule !== '' ? 'held on ' . $eventSchedule : 'with no event schedule recorded') . ', ' . $totalResponses . ' clients completed the Client Satisfaction Feedback form. The computed CSF rating is ' . $overallSatisfaction . ', equivalent to a ' . $overallAdjectival . ' adjectival rating. ' . ($leadingClientType ? $leadingClientType['label'] . ' clients make up the largest segment with ' . $leadingClientType['count'] . ' responses (' . $leadingClientType['percent'] . ')' : 'No dominant client type is available') . ', while ' . ($leadingAgeGroup ? 'the ' . $leadingAgeGroup['label'] . ' age group has the highest share with ' . $leadingAgeGroup['count'] . ' responses (' . $leadingAgeGroup['percent'] . ')' : 'no dominant age group is available') . '.'
    : 'No descriptive analysis is available because there are no submitted evaluations for this selection.';

$clientComments = [];
foreach ($evaluationRows as $index => $row) {
    $comment = trim((string) ($row['comment'] ?? ''));
    if ($comment === '') {
        continue;
    }

    $clientName = trim((string) ($row['full_name'] ?? ''));
    $clientComments[] = [
        'no' => count($clientComments) + 1,
        'client' => $clientName !== '' ? $clientName : 'Client ' . ($index + 1),
        'comment' => $comment
    ];
}

$improvementActions = [];
foreach ($evaluationRows as $row) {
    $source = trim((string) ($row['improvement_reason'] ?? ''));
    $suggestion = trim((string) ($row['service_suggestions'] ?? ''));

    if ($source === '' && $suggestion === '') {
        continue;
    }

    $improvementActions[] = [
        'source' => $source !== '' ? $source : 'Client Suggestions',
        'action' => $suggestion !== '' ? $suggestion : 'Review concern and identify corrective action.',
        'responsibility' => 'Process Owner',
        'timeline' => 'Next review cycle'
    ];
}

if (empty($improvementActions)) {
    $improvementActions[] = [
        'source' => 'Client Satisfaction Feedback',
        'action' => 'Review evaluation results and identify service improvements.',
        'responsibility' => 'Process Owner',
        'timeline' => 'Next review cycle'
    ];
}

$miniTables = [
    "CC1 - Awareness of the Citizen's Charter" => $cc1Rows,
    'Sex Disaggregation' => $sexRows,
    'Level of Satisfaction' => $satisfactionRows,
    "CC2 - Visibility of the Citizen's Charter" => $cc2Rows,
    'Age' => $ageRows,
    "CC3 - Usefulness of the Citizen's Charter" => $cc3Rows,
    'Client Type' => $clientTypeRows
];

function xlsText($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsCell($value = '', $style = '', $mergeAcross = 0, $type = 'String') {
    $attrs = $style !== '' ? ' ss:StyleID="' . xlsText($style) . '"' : '';
    if ($mergeAcross > 0) {
        $attrs .= ' ss:MergeAcross="' . (int) $mergeAcross . '"';
    }

    if ($type === 'Number' && is_numeric($value)) {
        return '<Cell' . $attrs . '><Data ss:Type="Number">' . xlsText($value) . '</Data></Cell>';
    }

    return '<Cell' . $attrs . '><Data ss:Type="String">' . xlsText($value) . '</Data></Cell>';
}

function xlsRow($cells = [], $height = null) {
    $attrs = $height ? ' ss:Height="' . (float) $height . '"' : '';
    return '<Row' . $attrs . '>' . implode('', $cells) . '</Row>';
}

function xlsEmptyRow() {
    return '<Row><Cell><Data ss:Type="String"></Data></Cell></Row>';
}

function xlsBar($percentValue) {
    $filled = (int) round(max(0, min(100, (float) $percentValue)) / 10);
    return str_repeat('|', $filled) . str_repeat('.', 10 - $filled);
}

$safeEventCode = $selectedEventCode !== '' ? preg_replace('/[^A-Za-z0-9_-]+/', '_', $selectedEventCode) . '_' : '';
$filename = 'event_evaluations_' . $safeEventCode . date('Y-m-d_H-i-s') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
<Styles>
    <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Top"/><Font ss:FontName="Arial" ss:Size="10"/></Style>
    <Style ss:ID="Title"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="14" ss:Bold="1"/></Style>
    <Style ss:ID="Center"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>
    <Style ss:ID="Header"><Font ss:FontName="Arial" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1F4E78" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
    <Style ss:ID="SubHeader"><Font ss:FontName="Arial" ss:Bold="1"/><Interior ss:Color="#D9EAD3" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
    <Style ss:ID="Label"><Font ss:FontName="Arial" ss:Bold="1"/></Style>
    <Style ss:ID="CellBorder"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
    <Style ss:ID="Total"><Font ss:FontName="Arial" ss:Bold="1"/><Interior ss:Color="#D9EAD3" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
    <Style ss:ID="Note"><Alignment ss:WrapText="1" ss:Vertical="Top"/></Style>
    <Style ss:ID="Signature"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
</Styles>

<Worksheet ss:Name="Tabulation Sheet">
<Table>
    <Column ss:Width="36"/><Column ss:Width="140"/><Column ss:Width="90"/><Column ss:Width="60"/><Column ss:Width="90"/>
    <Column ss:Width="50"/><Column ss:Width="50"/><Column ss:Width="50"/><Column ss:Width="110"/><Column ss:Width="100"/>
    <Column ss:Width="90"/><Column ss:Width="120"/><Column ss:Width="100"/><Column ss:Width="60"/><Column ss:Width="80"/>
    <Column ss:Width="80"/><Column ss:Width="80"/>
<?php
echo xlsRow([xlsCell('CUSTOMER SATISFACTION FEEDBACK - TABULATION SHEET', 'Title', 16)], 24);
echo xlsRow([xlsCell('Event Code', 'Label'), xlsCell($selectedEventCode !== '' ? $selectedEventCode : 'All event codes'), xlsCell('Event Date', 'Label'), xlsCell($eventSchedule !== '' ? $eventSchedule : 'No date recorded', '', 3)]);
echo xlsRow([xlsCell('Title of Activity', 'Label'), xlsCell($eventTitle, '', 6), xlsCell('Total Responses', 'Label'), xlsCell($totalResponses, '', 0, 'Number')]);
echo xlsEmptyRow();
$tabHeaders = ['No.', 'Name/Codename', 'Age Group', 'Sex', 'Client Type', 'CC1', 'CC2', 'CC3', 'Overall Rating/SQD 0', 'Responsiveness', 'Reliability', 'Access and Facilities', 'Communication', 'Costs', 'Integrity', 'Assurance', 'Outcome'];
echo xlsRow(array_map(fn($header) => xlsCell($header, 'Header'), $tabHeaders));
if (!empty($tabulationRows)) {
    foreach ($tabulationRows as $row) {
        echo xlsRow(array_map(fn($cell) => xlsCell($cell, 'CellBorder', 0, is_numeric($cell) ? 'Number' : 'String'), $row));
    }
} else {
    echo xlsRow([xlsCell('No evaluation responses found.', 'CellBorder', 16)]);
}
?>
</Table>
</Worksheet>

<Worksheet ss:Name="Supplemental">
<Table>
    <Column ss:Width="240"/><Column ss:Width="90"/><Column ss:Width="100"/><Column ss:Width="140"/>
<?php
echo xlsRow([xlsCell('SUPPLEMENTAL TABLES', 'Title', 3)], 24);
foreach ($miniTables as $tableTitle => $tableRows) {
    echo xlsEmptyRow();
    echo xlsRow([xlsCell($tableTitle, 'SubHeader', 3)]);
    echo xlsRow([xlsCell('Category', 'Header'), xlsCell('# of Responses', 'Header'), xlsCell('% Distribution', 'Header'), xlsCell('Visual', 'Header')]);
    foreach ($tableRows as $row) {
        echo xlsRow([
            xlsCell($row['label'], 'CellBorder'),
            xlsCell($row['count'], 'CellBorder', 0, 'Number'),
            xlsCell($row['percent'], 'CellBorder'),
            xlsCell(xlsBar($row['percent_value']), 'CellBorder')
        ]);
    }
    echo xlsRow([xlsCell('Total Responses', 'Total'), xlsCell($totalResponses, 'Total', 0, 'Number'), xlsCell(exportPercent($totalResponses, $totalResponses), 'Total'), xlsCell('', 'Total')]);
}
echo xlsEmptyRow();
echo xlsRow([xlsCell('RAW EVALUATION DATA', 'SubHeader', count($columns) - 1)]);
echo xlsRow(array_map(fn($label) => xlsCell($label, 'Header'), array_values($columns)));
if (!empty($evaluationRows)) {
    foreach ($evaluationRows as $row) {
        $cells = [];
        foreach (array_keys($columns) as $field) {
            $cells[] = xlsCell($row[$field] ?? '', 'CellBorder');
        }
        echo xlsRow($cells);
    }
} else {
    echo xlsRow([xlsCell('No evaluation responses found.', 'CellBorder', count($columns) - 1)]);
}
?>
</Table>
</Worksheet>

<Worksheet ss:Name="Report">
<Table>
    <Column ss:Width="180"/><Column ss:Width="95"/><Column ss:Width="110"/><Column ss:Width="120"/><Column ss:Width="120"/><Column ss:Width="95"/><Column ss:Width="100"/><Column ss:Width="95"/><Column ss:Width="95"/><Column ss:Width="95"/>
<?php
echo xlsRow([xlsCell('CUSTOMER SATISFACTION FEEDBACK REPORT', 'Title', 9)], 24);
echo xlsRow([xlsCell('[Bureau/Office]', 'Center', 9)]);
echo xlsRow([xlsCell('For the period ' . ($eventSchedule !== '' ? $eventSchedule : $responsePeriod), 'Center', 9)]);
echo xlsEmptyRow();
echo xlsRow([xlsCell('Document Code:', 'CellBorder'), xlsCell('FM-CSF-ACT-RPT', 'CellBorder'), xlsCell('', '', 6), xlsCell('Total Responses', 'Header'), xlsCell($totalResponses, 'CellBorder', 0, 'Number')]);
echo xlsRow([xlsCell('Version No.:', 'CellBorder'), xlsCell('0', 'CellBorder'), xlsCell('', '', 6), xlsCell('Total Number of Clients', 'Header'), xlsCell($totalResponses, 'CellBorder', 0, 'Number')]);
echo xlsRow([xlsCell('Effectivity Date:', 'CellBorder'), xlsCell('September 1, 2025', 'CellBorder'), xlsCell('', '', 6), xlsCell('Retrieval Rate', 'Header'), xlsCell($totalResponses > 0 ? '100.00%' : '0.00%', 'CellBorder')]);
echo xlsRow([xlsCell('Process', 'Label'), xlsCell($eventTitle, '', 5), xlsCell('', '', 1), xlsCell('Overall Satisfaction', 'Header'), xlsCell($overallSatisfaction, 'CellBorder')]);
echo xlsRow([xlsCell('Title of Activity:', 'Label'), xlsCell($eventTitle, '', 5), xlsCell('', '', 1), xlsCell('Adjectival Rating', 'Header'), xlsCell($overallAdjectival, 'CellBorder')]);
echo xlsEmptyRow();
echo xlsRow([xlsCell('I. Report Summary', 'Label', 9)]);
$matrixHeaders = ['Level of Satisfaction', 'OVERALL SCORING', 'Overall Rating/SQD 0', 'Responsiveness', 'Reliability', 'Access & Facilities', 'Communication', 'Costs', 'Integrity', 'Assurance', 'Outcome'];
echo xlsRow(array_map(fn($header) => xlsCell($header, 'Header'), $matrixHeaders));
foreach ($reportMatrixRows as $matrixRow) {
    echo xlsRow(array_map(fn($cell) => xlsCell($cell, 'CellBorder', 0, is_numeric($cell) ? 'Number' : 'String'), $matrixRow));
}
echo xlsRow(array_map(fn($cell) => xlsCell($cell, 'Total', 0, is_numeric($cell) ? 'Number' : 'String'), $reportMatrixTotals));
echo xlsRow(array_merge([xlsCell('CSF Rating', 'Total'), xlsCell($overallSatisfaction, 'Total')], array_map(fn($dimension) => xlsCell($dimension['rating'], 'Total'), $reportDimensionSummaries)));
echo xlsRow(array_merge([xlsCell('Adjectival Rating', 'Total'), xlsCell($overallAdjectival, 'Total')], array_map(fn($dimension) => xlsCell($dimension['adjectival'], 'Total'), $reportDimensionSummaries)));
echo xlsEmptyRow();
echo xlsRow([xlsCell('Speaker Evaluation Summary', 'Label', 9)]);
$speakerMatrixHeaders = ['Level of Satisfaction', 'OVERALL SCORING', 'Content Rating', 'Speaker Rating'];
echo xlsRow(array_map(fn($header) => xlsCell($header, 'Header'), $speakerMatrixHeaders));
foreach ($speakerMatrixRows as $speakerMatrixRow) {
    echo xlsRow(array_map(fn($cell) => xlsCell($cell, 'CellBorder', 0, is_numeric($cell) ? 'Number' : 'String'), $speakerMatrixRow));
}
echo xlsRow(array_map(fn($cell) => xlsCell($cell, 'Total', 0, is_numeric($cell) ? 'Number' : 'String'), $speakerMatrixTotals));
echo xlsRow(array_merge([xlsCell('CSF Rating', 'Total'), xlsCell($speakerSatisfaction, 'Total')], array_map(fn($dimension) => xlsCell($dimension['rating'], 'Total'), $speakerDimensionSummaries)));
echo xlsRow(array_merge([xlsCell('Adjectival Rating', 'Total'), xlsCell($speakerAdjectival, 'Total')], array_map(fn($dimension) => xlsCell($dimension['adjectival'], 'Total'), $speakerDimensionSummaries)));
echo xlsEmptyRow();
echo xlsRow([xlsCell('II. Descriptive Analysis', 'Label', 9)]);
echo xlsRow([xlsCell($descriptiveAnalysis, 'Note', 9)], 54);
echo xlsEmptyRow();
echo xlsRow([xlsCell('III. Clients Comments', 'Label', 9)]);
echo xlsRow([xlsCell('No.', 'Header'), xlsCell('Client', 'Header'), xlsCell('Comment', 'Header', 7)]);
if (!empty($clientComments)) {
    foreach ($clientComments as $commentRow) {
        echo xlsRow([xlsCell($commentRow['no'], 'CellBorder', 0, 'Number'), xlsCell($commentRow['client'], 'CellBorder'), xlsCell($commentRow['comment'], 'CellBorder', 7)], 36);
    }
} else {
    echo xlsRow([xlsCell('No client comments submitted.', 'CellBorder', 9)]);
}
echo xlsEmptyRow();
echo xlsRow([xlsCell('IV. Improvement Action', 'Label', 9)]);
echo xlsRow([xlsCell('*Note: Monitoring of this action will be in Sheet no. 9 of the Planning Tools', 'Note', 9)]);
echo xlsRow([xlsCell('Source of Improvement', 'Header'), xlsCell('Improvement Action', 'Header', 4), xlsCell('Responsibility', 'Header', 1), xlsCell('Timeline', 'Header', 2)]);
foreach ($improvementActions as $actionRow) {
    echo xlsRow([
        xlsCell($actionRow['source'], 'CellBorder'),
        xlsCell($actionRow['action'], 'CellBorder', 4),
        xlsCell($actionRow['responsibility'], 'CellBorder', 1),
        xlsCell($actionRow['timeline'], 'CellBorder', 2)
    ], 36);
}
echo xlsEmptyRow();
echo xlsEmptyRow();
echo xlsRow([xlsCell('', ''), xlsCell('', 'Signature', 2), xlsCell('', ''), xlsCell('', 'Signature', 2), xlsCell('', ''), xlsCell('', 'Signature', 2)]);
echo xlsRow([xlsCell('Prepared by:', 'Label'), xlsCell('Process Owner', 'Center', 2), xlsCell('Reviewed by:', 'Label'), xlsCell('Immediate Supervisor/Division Chief', 'Center', 2), xlsCell('Approved by:', 'Label'), xlsCell('Head of Office', 'Center', 2)]);
?>
</Table>
</Worksheet>

<Worksheet ss:Name="Graph">
<Table>
    <Column ss:Width="220"/><Column ss:Width="90"/><Column ss:Width="100"/><Column ss:Width="170"/><Column ss:Width="120"/>
<?php
echo xlsRow([xlsCell('GRAPH SHEET', 'Title', 4)], 24);
echo xlsRow([xlsCell('Pie graph source data and Excel-friendly visual bars for ' . $eventTitle, 'Center', 4)]);
foreach ([
    'Sex Disaggregation Pie Graph' => $sexRows,
    'Age Pie Graph' => $ageRows,
    'Client Type Pie Graph' => $clientTypeRows,
    'Level of Satisfaction Pie Graph' => $satisfactionRows
] as $graphTitle => $graphRows) {
    echo xlsEmptyRow();
    echo xlsRow([xlsCell($graphTitle, 'SubHeader', 4)]);
    echo xlsRow([xlsCell('Slice', 'Header'), xlsCell('Value', 'Header'), xlsCell('Percent', 'Header'), xlsCell('Pie Visual', 'Header'), xlsCell('Chart Data Label', 'Header')]);
    foreach ($graphRows as $row) {
        echo xlsRow([
            xlsCell($row['label'], 'CellBorder'),
            xlsCell($row['count'], 'CellBorder', 0, 'Number'),
            xlsCell($row['percent'], 'CellBorder'),
            xlsCell(xlsBar($row['percent_value']), 'CellBorder'),
            xlsCell($row['label'] . ' - ' . $row['percent'], 'CellBorder')
        ]);
    }
}
echo xlsEmptyRow();
echo xlsRow([xlsCell('Service Quality Dimension Graphs', 'SubHeader', 4)]);
echo xlsRow([xlsCell('Dimension', 'Header'), xlsCell('CSF Rating', 'Header'), xlsCell('Adjectival Rating', 'Header'), xlsCell('Visual', 'Header'), xlsCell('Total Ratings', 'Header')]);
foreach ($dimensionSummaries as $dimension) {
    echo xlsRow([
        xlsCell($dimension['label'], 'CellBorder'),
        xlsCell($dimension['rating'], 'CellBorder'),
        xlsCell($dimension['adjectival'], 'CellBorder'),
        xlsCell(xlsBar($dimension['rating_value']), 'CellBorder'),
        xlsCell($dimension['total'], 'CellBorder', 0, 'Number')
    ]);
}
?>
</Table>
</Worksheet>
</Workbook>
