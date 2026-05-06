<?php
session_start();

$conn = new mysqli("localhost", "root", "", "nasugview2");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id = intval($_GET['id'] ?? 0);
if (!$id) die("Invalid event.");

$stmt = $conn->prepare("
    SELECT *,
        CASE
            WHEN status = 'Canceled' THEN 'Canceled'
            WHEN NOW() < start_date_and_time THEN 'For Implementation'
            WHEN NOW() BETWEEN start_date_and_time AND end_date_and_time THEN 'Ongoing'
            WHEN NOW() > end_date_and_time THEN 'Implemented'
            ELSE status
        END AS calculated_status
    FROM events
    WHERE id=?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
if (!$event) die("Event not found.");

function pdfCleanText($value) {
    $value = trim((string)($value ?? ''));
    if ($value === '') return 'N/A';
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
    if ($converted !== false) $value = $converted;
    return str_replace(["\\", "(", ")", "\r"], ["\\\\", "\\(", "\\)", ""], $value);
}

function pdfWrapText($value, $maxChars = 82) {
    $value = trim((string)($value ?? ''));
    if ($value === '') return ['N/A'];
    $lines = [];
    foreach (explode("\n", $value) as $paragraph) {
        $wrapped = wordwrap($paragraph, $maxChars, "\n", true);
        foreach (explode("\n", $wrapped) as $line) {
            $lines[] = $line;
        }
    }
    return $lines;
}

function pdfTextWidth($text, $size) {
    return strlen((string)$text) * $size * 0.48;
}

function pdfFitText($value, $size, $maxWidth) {
    $value = trim((string)($value ?? ''));
    if ($value === '') return 'N/A';
    $original = $value;
    while (pdfTextWidth($value, $size) > $maxWidth && strlen($value) > 4) {
        $value = substr($value, 0, -1);
    }
    return strlen($value) < strlen($original) ? rtrim($value, " .") . "..." : $value;
}

function pdfBuild($event, $eventCode, $duration, $status, $remarks, $filename, $download = false) {
    $commands = [];
    $start = strtotime($event['start_date_and_time']);
    $end = strtotime($event['end_date_and_time']);

    $setStroke = function($gray = 0, $width = 0.7) use (&$commands) {
        $commands[] = "{$gray} G {$width} w";
    };

    $text = function($value, $x, $y, $size = 10, $font = 'F1', $align = 'left', $width = 0) use (&$commands) {
        $value = pdfCleanText($value);
        if ($align === 'center') {
            $x += max(0, ($width - pdfTextWidth($value, $size)) / 2);
        } elseif ($align === 'right') {
            $x += max(0, $width - pdfTextWidth($value, $size));
        }
        $commands[] = "BT /{$font} {$size} Tf {$x} {$y} Td ({$value}) Tj ET";
    };

    $colorText = function($value, $x, $y, $size, $font, $r, $g, $b, $align = 'left', $width = 0) use (&$commands, $text) {
        $commands[] = "{$r} {$g} {$b} rg";
        $text($value, $x, $y, $size, $font, $align, $width);
        $commands[] = "0 0 0 rg";
    };

    $line = function($x1, $y1, $x2, $y2) use (&$commands) {
        $commands[] = "{$x1} {$y1} m {$x2} {$y2} l S";
    };

    $rect = function($x, $y, $w, $h) use (&$commands) {
        $commands[] = "{$x} {$y} {$w} {$h} re S";
    };

    $filledRect = function($x, $y, $w, $h, $gray = 0.88) use (&$commands) {
        $commands[] = "{$gray} g {$x} {$y} {$w} {$h} re f 0 g";
    };

    $checkbox = function($x, $y, $checked = false) use (&$commands, $rect, $line) {
        $rect($x, $y, 9, 9);
        if ($checked) {
            $line($x + 2, $y + 5, $x + 4, $y + 2);
            $line($x + 4, $y + 2, $x + 8, $y + 8);
        }
    };

    $field = function($label, $value, $x, $y, $labelWidth = 105, $lineWidth = 410, $size = 9.5) use ($text, $line) {
        $text(strtoupper($label), $x, $y, 9, 'F2');
        $line($x + $labelWidth, $y - 2, $x + $labelWidth + $lineWidth, $y - 2);
        $text(pdfFitText($value, $size, $lineWidth - 8), $x + $labelWidth + 4, $y + 1, $size, 'F1');
    };

    $setStroke(0, 0.8);
    $rect(16, 18, 580, 756);

    // Header
    $rect(28, 675, 556, 86);
    $commands[] = "0.65 0 0 rg 58 714 42 42 re S 0 0 0 rg";
    $text("NV", 66, 731, 14, 'F2', 'center', 24);
    $text("Republic of the Philippines", 132, 744, 11, 'F2', 'center', 360);
    $text("NASUGVIEW EVENT MANAGEMENT", 132, 728, 16, 'F2', 'center', 360);
    $colorText("The Local Business Support and Event Monitoring System", 132, 713, 10, 'F2', 0.65, 0, 0, 'center', 360);
    $text("Nasugbu, Batangas, Philippines", 132, 700, 9, 'F1', 'center', 360);
    $text("E-mail Address: nasugview@example.com | Website Address: nasugview.local", 132, 687, 8.5, 'F1', 'center', 360);

    $text("Extension Services Office", 36, 656, 12, 'F2');
    $text("EVENT RESERVATION FORM", 36, 626, 15, 'F2', 'center', 540);
    $text("NO.", 36, 601, 10, 'F2');
    $line(62, 599, 185, 599);
    $text($eventCode, 70, 603, 10, 'F1');
    $text("DATE", 398, 601, 10, 'F2');
    $line(432, 599, 560, 599);
    $text(date("F j, Y"), 452, 603, 10, 'F1');

    // Delivery section
    $rect(28, 500, 556, 82);
    $text("REQUESTED DELIVERY:", 40, 564, 11, 'F2');
    $checkbox(54, 538, strcasecmp((string)$event['mode_of_delivery'], 'Seminar') === 0);
    $text("Seminar / Face-to-face", 69, 540, 10, 'F1');
    $checkbox(238, 538, strcasecmp((string)$event['mode_of_delivery'], 'Webinar') === 0);
    $text("Webinar / Online", 253, 540, 10, 'F1');
    $checkbox(398, 538, !in_array(strtolower((string)$event['mode_of_delivery']), ['seminar', 'webinar'], true));
    $text("Other", 413, 540, 10, 'F1');
    $field("Venue / Link", trim((string)$event['google_meet_link']) !== '' ? $event['google_meet_link'] : $event['address'], 44, 516, 82, 414, 9);

    // Event details section
    $rect(28, 332, 556, 150);
    $filledRect(28, 462, 556, 20, 0.90);
    $text("EVENT DETAILS", 40, 468, 11, 'F2');
    $field("Event Name", $event['title'], 44, 445, 92, 402, 9.3);
    $field("Target Audience", $event['audience'], 44, 422, 92, 402, 9.3);
    $field("Resource Speaker", $event['speaker'], 44, 399, 92, 402, 9.3);
    $field("Funding Source", $event['funding_source'], 44, 376, 92, 222, 9.3);
    $text("BUDGET", 388, 376, 9, 'F2');
    $line(432, 374, 552, 374);
    $text($event['budget'], 438, 377, 9.3, 'F1');
    $field("Description", $event['description'], 44, 353, 92, 402, 8.7);

    // Schedule table
    $rect(28, 238, 556, 76);
    $filledRect(28, 294, 556, 20, 0.90);
    $line(244, 238, 244, 314);
    $line(396, 238, 396, 314);
    $line(28, 274, 584, 274);
    $text("FACILITY / MODE", 46, 300, 10, 'F2', 'center', 170);
    $text("DATE", 268, 300, 10, 'F2', 'center', 100);
    $text("TIME", 426, 300, 10, 'F2', 'center', 116);
    $facility = trim((string)$event['address']) !== '' ? $event['address'] : $event['mode_of_delivery'];
    $text($facility, 38, 258, 9.5, 'F1', 'center', 196);
    $text($start ? date("F j, Y", $start) : "N/A", 254, 258, 9.5, 'F1', 'center', 132);
    $timeRange = ($start ? date("g:i A", $start) : "N/A") . " - " . ($end ? date("g:i A", $end) : "N/A");
    $text($timeRange, 406, 258, 9.5, 'F1', 'center', 168);

    // Status and remarks
    $rect(28, 141, 556, 76);
    $filledRect(28, 197, 556, 20, 0.90);
    $text("STATUS / REMARKS", 40, 203, 11, 'F2');
    $field("Duration", $duration, 44, 177, 70, 150, 9.5);
    $field("Status", $status, 310, 177, 58, 176, 9.5);
    $field("Remarks", $remarks, 44, 154, 70, 430, 9.3);

    // Signatures
    $rect(28, 66, 556, 56);
    $line(48, 87, 250, 87);
    $line(360, 87, 562, 87);
    $text("Prepared by / Requesting Personnel", 72, 73, 8.5, 'F1');
    $text("Approved by", 430, 73, 8.5, 'F1');
    $colorText("Leading Innovations, Transforming Lives, Building the Nation", 28, 38, 10, 'F2', 0.65, 0, 0, 'center', 556);

    $pages = [implode("\n", $commands)];

    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";

    $pageObjectNumbers = [];
    $contentObjectNumbers = [];
    $nextObject = 5;
    foreach ($pages as $unused) {
        $contentObjectNumbers[] = $nextObject++;
        $pageObjectNumbers[] = $nextObject++;
    }

    $kids = implode(' ', array_map(fn($num) => "{$num} 0 R", $pageObjectNumbers));
    $objects[] = "<< /Type /Pages /Kids [ {$kids} ] /Count " . count($pages) . " >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

    foreach ($pages as $index => $content) {
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents " . $contentObjectNumbers[$index] . " 0 R >>";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $number = $index + 1;
        $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$start = strtotime($event['start_date_and_time']);
$end = strtotime($event['end_date_and_time']);
$duration = "N/A";
if ($start && $end) {
    $seconds = $end - $start;
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $duration = trim(($days > 0 ? $days . "d " : "") . ($hours > 0 ? $hours . "h " : "") . ($minutes > 0 ? $minutes . "m" : ""));
    if ($duration === '') $duration = "0m";
}

$status = $event['calculated_status'];
$defaultRemarks = ($status == 'For Implementation') ? "Incoming" : (($status == 'Ongoing') ? "In Progress" : "Done");
$remarks = trim($event['remarks'] ?? '') !== '' ? $event['remarks'] : $defaultRemarks;
$eventCode = trim($event['event_code'] ?? '') !== ''
    ? $event['event_code']
    : "EVT" . str_pad($event['id'], 4, "0", STR_PAD_LEFT);
$safeTitle = preg_replace('/[^A-Za-z0-9_-]+/', '_', $event['title']);
$filename = $eventCode . "_" . trim($safeTitle, "_") . ".pdf";

pdfBuild($event, $eventCode, $duration, $status, $remarks, $filename, isset($_GET['download']));
?>
