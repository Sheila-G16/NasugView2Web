<?php
session_start();

$conn = new mysqli("localhost", "root", "", "nasugview2");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$admin_fullname = "User";
$designation = "Admin";

if (isset($_SESSION['user_id'])) {
    $id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT fname, lname, designation FROM negosyo_center_users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $admin_fullname = trim($row['fname'] . " " . $row['lname']);
        $designation = $row['designation'];
    }
}

$saveDir = __DIR__ . "/saved_templates/";
$saveUrl = "saved_templates/";

if (!is_dir($saveDir)) {
    mkdir($saveDir, 0777, true);
}

if (isset($_POST['save_layout'])) {
    $layout = $_POST['layout'] ?? '';
    $image = $_POST['image'] ?? '';
    $customName = trim($_POST['template_name'] ?? '');
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $customName);
    $name = $safeName !== '' ? $safeName . "_" . time() : "template_" . time();

    file_put_contents($saveDir . $name . ".json", $layout);

    if (strpos($image, 'data:image/png;base64,') === 0) {
        $image = str_replace('data:image/png;base64,', '', $image);
        file_put_contents($saveDir . $name . ".png", base64_decode($image));
    }

    header("Content-Type: application/json");
    echo json_encode(["status" => "saved", "name" => $name]);
    exit;
}

if (isset($_GET['load'])) {
    $name = basename($_GET['load']);
    $file = $saveDir . $name . ".json";

    header("Content-Type: application/json");

    if (is_file($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(["board" => [], "elements" => []]);
    }
    exit;
}

$templateFiles = glob($saveDir . "*.png") ?: [];
$templates = [];

foreach ($templateFiles as $file) {
    $base = basename($file, ".png");
    $templates[] = [
        "name" => $base,
        "preview" => $saveUrl . basename($file),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate Designer</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Great+Vibes&family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
:root{
    --navy:#001a47;
    --navy-deep:#00308a;
    --gold:#d4af37;
    --gold-soft:#f4d889;
    --cream:#f7f2e8;
    --secondary:#f8f9fa;
    --paper:#fffdf8;
    --panel:#ffffff;
    --ink:#1e293b;
    --muted:#64748b;
    --line:#d9e0ea;
    --shadow:0 22px 55px rgba(0,26,71,.14);
    --sidebar-width:250px;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background:linear-gradient(135deg, var(--navy) 0%, var(--navy-deep) 100%);
    color:var(--ink);
    overflow-x:hidden;
}

.main-content{
    margin-left:var(--sidebar-width);
    min-height:100vh;
    padding:12px;
    background:var(--secondary);
    overflow-x:hidden;
}

body.left-panel-hidden .main-content{
    padding-top:64px;
}

.page-shell{
    display:grid;
    grid-template-columns:212px minmax(0, 1fr) 244px;
    gap:10px;
    align-items:start;
}

.page-shell.inspector-hidden{
    grid-template-columns:212px minmax(0, 1fr);
}

.page-shell.left-panel-hidden{
    grid-template-columns:minmax(0, 1fr) 244px;
}

.page-shell.left-panel-hidden.inspector-hidden{
    grid-template-columns:minmax(0, 1fr);
}

.panel{
    background:rgba(255,255,255,.9);
    border:1px solid rgba(0,26,71,.08);
    border-radius:20px;
    box-shadow:var(--shadow);
    backdrop-filter:blur(10px);
}

.panel-head{
    padding:16px 16px 8px;
}

.panel-head.compact-head{
    display:block;
}

.panel-head-main{
    min-width:0;
    flex:1;
}

.panel-head-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:10px;
}

.panel-head h1,
.panel-head h2,
.panel-head h3{
    margin:0;
}

.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 12px;
    border-radius:999px;
    background:rgba(0,26,71,.07);
    color:var(--navy);
    font-size:12px;
    font-weight:600;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.subtitle{
    margin-top:8px;
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
}

.panel-body{
    padding:0 16px 16px;
}

.tool-group{
    margin-top:12px;
}

.tool-group:first-child{
    margin-top:0;
}

.group-label{
    display:block;
    margin-bottom:8px;
    color:var(--muted);
    font-size:11px;
    font-weight:700;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.tool-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:8px;
}

.tool-btn,
.action-btn,
.template-card,
.align-btn{
    border:none;
    cursor:pointer;
    transition:.2s ease;
}

.tool-btn{
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    justify-content:space-between;
    min-height:74px;
    padding:10px;
    border-radius:14px;
    background:linear-gradient(135deg, rgba(0,26,71,.94) 0%, rgba(0,48,138,.94) 100%);
    border:1px solid rgba(0,26,71,.08);
    box-shadow:0 12px 24px rgba(0,26,71,.18);
    color:#fff;
    text-align:left;
}

.tool-btn strong{
    font-size:12px;
}

.tool-btn span{
    font-size:11px;
    color:rgba(255,255,255,.8);
    line-height:1.3;
}

.tool-btn:hover,
.template-card:hover,
.action-btn:hover,
.align-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 28px rgba(0,26,71,.12);
}

.workspace{
    display:flex;
    flex-direction:column;
    gap:10px;
}

.workspace-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
    padding:14px 14px 0;
}

.workspace-title h2{
    font-size:22px;
    line-height:1.1;
    color:var(--navy-deep);
}

.workspace-title p{
    margin:6px 0 0;
    color:var(--muted);
    font-size:12px;
}

.workspace-actions{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    justify-content:flex-end;
}

.download-menu-wrap{
    position:relative;
}

.action-btn{
    position:relative;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-width:44px;
    height:44px;
    padding:0 14px;
    border-radius:14px;
    font-size:13px;
    font-weight:600;
    background:linear-gradient(135deg, rgba(0,26,71,.94) 0%, rgba(0,48,138,.94) 100%);
    color:#fff;
    box-shadow:0 10px 22px rgba(0,26,71,.16);
}

.action-btn i{
    font-size:15px;
}

.action-btn.icon-only{
    width:44px;
    min-width:44px;
    padding:0;
}

.action-btn .btn-text{
    display:none;
}

.action-btn .btn-tooltip{
    position:absolute;
    left:50%;
    bottom:calc(100% + 10px);
    transform:translateX(-50%) translateY(4px);
    padding:8px 10px;
    border-radius:10px;
    background:rgba(7,22,38,.96);
    color:#fff;
    font-size:11px;
    font-weight:600;
    letter-spacing:.02em;
    white-space:nowrap;
    opacity:0;
    pointer-events:none;
    transition:opacity .18s ease, transform .18s ease;
    box-shadow:0 14px 26px rgba(7,22,38,.22);
    z-index:30;
}

.action-btn .btn-tooltip::after{
    content:"";
    position:absolute;
    left:50%;
    top:100%;
    width:8px;
    height:8px;
    background:rgba(7,22,38,.96);
    transform:translateX(-50%) rotate(45deg);
}

.action-btn:hover .btn-tooltip,
.action-btn:focus-visible .btn-tooltip{
    opacity:1;
    transform:translateX(-50%) translateY(0);
}

.action-btn.primary{
    background:linear-gradient(135deg, var(--navy) 0%, var(--navy-deep) 100%);
    color:#fff;
}

.action-btn.gold{
    background:linear-gradient(135deg, #f7e7b2 0%, var(--gold) 100%);
    color:#3a2b03;
}

.download-menu{
    position:absolute;
    right:0;
    top:calc(100% + 8px);
    min-width:180px;
    padding:8px;
    border-radius:14px;
    border:1px solid rgba(0,26,71,.12);
    background:rgba(255,255,255,.98);
    box-shadow:0 18px 40px rgba(15,23,42,.18);
    z-index:80;
}

.download-menu[hidden]{
    display:none;
}

.download-option{
    width:100%;
    height:40px;
    display:flex;
    align-items:center;
    gap:10px;
    padding:0 12px;
    border:none;
    border-radius:10px;
    background:transparent;
    color:var(--ink);
    font:inherit;
    font-size:12px;
    font-weight:600;
    text-align:left;
    cursor:pointer;
}

.download-option i{
    width:16px;
    color:var(--navy-deep);
}

.download-option:hover,
.download-option:focus-visible{
    background:#eef3fa;
    color:var(--navy);
    outline:none;
}

.board-toolbar{
    margin:0 14px;
    padding:8px 10px;
    border-radius:14px;
    background:linear-gradient(180deg, #ffffff 0%, #eef3fa 100%);
    border:1px solid rgba(0,26,71,.08);
    display:flex;
    flex-wrap:wrap;
    gap:6px 10px;
    align-items:center;
}

.toolbar-cluster{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}

.toolbar-cluster label{
    font-size:11px;
    font-weight:700;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.06em;
}

.toolbar-cluster input[type="color"]{
    width:40px;
    height:40px;
    border:none;
    background:none;
    padding:0;
    cursor:pointer;
}

.toolbar-cluster input[type="text"]{
    width:140px;
}

.toolbar-cluster input[type="text"],
.toolbar-cluster select,
.inspector input[type="text"],
.inspector input[type="number"],
.inspector textarea,
.inspector select{
    height:40px;
    padding:0 12px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
    font:inherit;
    color:var(--ink);
}

.inspector textarea{
    height:72px;
    padding:12px;
    resize:vertical;
}

.board-wrap{
    padding:0 14px 14px;
}

.board-stage{
    position:relative;
    padding:10px;
    border-radius:20px;
    background:
        linear-gradient(180deg, rgba(255,255,255,.94), rgba(238,243,250,.96)),
        repeating-linear-gradient(0deg, rgba(0,26,71,.04) 0, rgba(0,26,71,.04) 1px, transparent 1px, transparent 34px),
        repeating-linear-gradient(90deg, rgba(0,26,71,.04) 0, rgba(0,26,71,.04) 1px, transparent 1px, transparent 34px);
    border:1px solid rgba(0,26,71,.08);
    overflow:hidden;
    display:flex;
    align-items:flex-start;
    justify-content:flex-start;
    min-height:340px;
}

.board-stage.is-centered{
    justify-content:center;
}

.canvas-viewport{
    position:relative;
    width:1123px;
    height:794px;
    transform-origin:top left;
    transition:width .18s ease, height .18s ease;
}

#canvas{
    position:relative;
    width:1123px;
    height:794px;
    background-color:var(--paper);
    border-radius:10px;
    overflow:hidden;
    box-shadow:0 28px 60px rgba(15,23,42,.18);
    background-size:cover;
    background-position:center;
    transform-origin:top left;
}

#canvas::before{
    content:"";
    position:absolute;
    inset:18px;
    border:1px dashed rgba(0,26,71,.12);
    pointer-events:none;
}

.design-item{
    position:absolute;
    min-width:60px;
    min-height:30px;
    cursor:move;
    user-select:none;
    touch-action:none;
}

.item-shape{
    min-height:1px;
}

.item-shape.selected{
    cursor:move;
}

.item-shape.selected::before{
    content:"";
    position:absolute;
    inset:-6px;
    border:1px solid rgba(21,66,111,.75);
    pointer-events:none;
}

.design-item.selected{
    box-shadow:0 0 0 2px rgba(21,66,111,.4);
}

.design-item.locked{
    cursor:not-allowed;
}

.design-item.locked::after{
    content:"\f023";
    position:absolute;
    right:-10px;
    top:-10px;
    width:22px;
    height:22px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    background:var(--navy);
    color:#fff;
    font-family:"Font Awesome 6 Free";
    font-size:10px;
    font-weight:900;
    box-shadow:0 8px 18px rgba(0,26,71,.25);
    z-index:7;
}

.design-item.locked .resize-handle,
.design-item.locked .rotate-handle{
    display:none !important;
}

.item-content{
    width:100%;
    height:100%;
}

.item-text .item-content{
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    line-height:1.25;
    white-space:pre-wrap;
    word-break:break-word;
    padding:4px 8px;
}

.item-image .item-content{
    overflow:hidden;
    border-radius:inherit;
}

.item-image img{
    display:block;
    width:100%;
    height:100%;
    object-fit:contain;
    pointer-events:none;
}

.crop-overlay{
    position:absolute;
    inset:0;
    display:none;
    pointer-events:none;
    border:2px solid rgba(21,66,111,.9);
    box-shadow:0 0 0 1px rgba(255,255,255,.9);
}

.item-image.selected .crop-overlay{
    display:block;
}

.item-image.selected{
    cursor:move;
}

.item-image.selected::before{
    content:"";
    position:absolute;
    inset:-6px;
    border:1px solid rgba(21,66,111,.75);
    pointer-events:none;
}

.rotate-handle{
    position:absolute;
    top:-34px;
    left:50%;
    width:24px;
    height:24px;
    margin-left:-12px;
    border-radius:50%;
    background:var(--navy);
    color:#fff;
    display:none;
    align-items:center;
    justify-content:center;
    font-size:12px;
    box-shadow:0 10px 24px rgba(0,26,71,.25);
    cursor:grab;
}

.design-item.selected .rotate-handle{
    display:flex;
}

.resize-handle{
    position:absolute;
    width:12px;
    height:12px;
    border:2px solid #fff;
    border-radius:50%;
    background:var(--navy);
    box-shadow:0 6px 14px rgba(0,26,71,.28);
    display:none;
    z-index:5;
}

.design-item.selected .resize-handle{
    display:block;
}

.resize-top{
    top:-7px;
    left:50%;
    transform:translateX(-50%);
    cursor:ns-resize;
}

.resize-right{
    top:50%;
    right:-7px;
    transform:translateY(-50%);
    cursor:ew-resize;
}

.resize-bottom{
    bottom:-7px;
    left:50%;
    transform:translateX(-50%);
    cursor:ns-resize;
}

.resize-left{
    top:50%;
    left:-7px;
    transform:translateY(-50%);
    cursor:ew-resize;
}

.resize-top.resize-left,
.resize-top.resize-right,
.resize-bottom.resize-left,
.resize-bottom.resize-right{
    transform:none;
}

.resize-top.resize-left{
    top:-7px;
    left:-7px;
    cursor:nwse-resize;
}

.resize-top.resize-right{
    top:-7px;
    right:-7px;
    cursor:nesw-resize;
}

.resize-bottom.resize-left{
    bottom:-7px;
    left:-7px;
    cursor:nesw-resize;
}

.resize-bottom.resize-right{
    right:-7px;
    bottom:-7px;
    cursor:nwse-resize;
}

.empty-state{
    padding:12px 14px;
    border:1px dashed rgba(0,26,71,.15);
    border-radius:14px;
    color:rgba(255,255,255,.88);
    background:linear-gradient(135deg, rgba(0,26,71,.92) 0%, rgba(0,48,138,.92) 100%);
    font-size:12px;
    line-height:1.5;
}

.template-list{
    display:grid;
    gap:8px;
}

.template-card{
    width:100%;
    padding:8px;
    border-radius:14px;
    background:linear-gradient(135deg, rgba(0,26,71,.94) 0%, rgba(0,48,138,.94) 100%);
    border:1px solid rgba(0,26,71,.08);
    text-align:left;
}

.template-card img{
    width:100%;
    aspect-ratio:1.6/1;
    object-fit:cover;
    border-radius:10px;
    display:block;
    margin-bottom:8px;
}

.template-card strong{
    display:block;
    font-size:12px;
    color:#fff;
}

.template-card span{
    font-size:11px;
    color:rgba(255,255,255,.8);
}

.inspector{
    padding-bottom:24px;
}

.inspector .panel-head{
    padding-bottom:14px;
}

.selection-name{
    margin-top:8px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 10px;
    border-radius:999px;
    background:linear-gradient(135deg, rgba(0,26,71,.94) 0%, rgba(0,48,138,.94) 100%);
    color:#fff;
    font-size:12px;
    font-weight:600;
}

.inspector-section{
    padding:0 16px;
    margin-top:14px;
}

.field{
    margin-top:10px;
}

.field:first-child{
    margin-top:0;
}

.field label{
    display:block;
    margin-bottom:6px;
    font-size:11px;
    color:var(--muted);
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.06em;
}

.field.two-col{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:8px;
}

.field.two-col > div label{
    margin-bottom:7px;
}

.range-wrap{
    display:flex;
    align-items:center;
    gap:8px;
}

.range-wrap input[type="range"]{
    flex:1;
    -webkit-appearance:none;
    appearance:none;
    height:8px;
    border-radius:999px;
    background:linear-gradient(135deg, var(--navy) 0%, var(--navy-deep) 100%);
    outline:none;
}

.range-wrap input[type="range"]::-webkit-slider-thumb{
    -webkit-appearance:none;
    appearance:none;
    width:18px;
    height:18px;
    border-radius:50%;
    background:linear-gradient(135deg, #f7e7b2 0%, var(--gold) 100%);
    border:2px solid #fff;
    box-shadow:0 4px 12px rgba(0,26,71,.25);
    cursor:pointer;
}

.range-wrap input[type="range"]::-moz-range-track{
    height:8px;
    border:none;
    border-radius:999px;
    background:linear-gradient(135deg, var(--navy) 0%, var(--navy-deep) 100%);
}

.range-wrap input[type="range"]::-moz-range-thumb{
    width:18px;
    height:18px;
    border-radius:50%;
    background:linear-gradient(135deg, #f7e7b2 0%, var(--gold) 100%);
    border:2px solid #fff;
    box-shadow:0 4px 12px rgba(0,26,71,.25);
    cursor:pointer;
}

.align-row{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:6px;
}

.align-btn{
    height:36px;
    border-radius:10px;
    background:linear-gradient(135deg, rgba(0,26,71,.94) 0%, rgba(0,48,138,.94) 100%);
    color:#fff;
    font-weight:700;
    font-size:12px;
}

.align-btn.active{
    background:linear-gradient(135deg, #f7e7b2 0%, var(--gold) 100%);
    color:#3a2b03;
    box-shadow:0 10px 20px rgba(212,175,55,.28);
}

.hidden{
    display:none !important;
}

.footer-note{
    margin:14px 16px 0;
    padding:12px 14px;
    border-radius:14px;
    background:linear-gradient(180deg, rgba(212,175,55,.12), rgba(212,175,55,.04));
    color:#5e4a0a;
    font-size:12px;
    line-height:1.5;
}

.shortcut-note{
    margin:6px 16px 0;
    color:var(--muted);
    font-size:11px;
}

.zoom-controls{
    display:flex;
    align-items:center;
    gap:8px;
}

.zoom-badge{
    min-width:72px;
    height:36px;
    padding:0 12px;
    border-radius:12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg, rgba(0,26,71,.94) 0%, rgba(0,48,138,.94) 100%);
    border:1px solid var(--line);
    color:#fff;
    font-size:12px;
    font-weight:700;
    letter-spacing:.04em;
}

.collapsible{
    margin-top:10px;
    border:1px solid rgba(0,26,71,.08);
    border-radius:14px;
    background:linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(238,243,250,.95) 100%);
    overflow:hidden;
}

.board-toolbar .collapsible{
    margin-top:0;
}

.inspector-section.collapsible{
    padding:0;
}

.collapsible summary{
    list-style:none;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:10px 12px;
    font-size:12px;
    font-weight:700;
    background:linear-gradient(135deg, rgba(0,26,71,.94) 0%, rgba(0,48,138,.94) 100%);
    color:#fff;
}

.collapsible summary::-webkit-details-marker{
    display:none;
}

.collapsible summary::after{
    content:"+";
    font-size:16px;
    color:rgba(255,255,255,.78);
}

.collapsible[open] summary::after{
    content:"-";
}

.collapsible-body{
    padding:0 12px 12px;
}

.collapsible-body .tool-grid,
.collapsible-body .template-list{
    margin-top:2px;
}

.panel-head h1{
    font-size:22px;
}

.panel-head h3{
    font-size:18px;
}

.panel-toggle{
    width:42px;
    height:42px;
    min-width:42px;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg, rgba(0,26,71,.94) 0%, rgba(0,48,138,.94) 100%);
    color:#fff;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    transition:.2s ease;
    flex-shrink:0;
}

.panel-toggle i{
    pointer-events:none;
}

.panel-toggle:hover{
    transform:translateY(-1px);
    box-shadow:0 12px 24px rgba(0,26,71,.12);
}

.panel-toggle-float{
    position:fixed;
    top:14px;
    left:calc(var(--sidebar-width) + 14px);
    z-index:1200;
    display:none;
    box-shadow:0 12px 24px rgba(0,26,71,.12);
    border-radius:12px;
}

.panel-toggle-float::after{
    content:"Tools";
    position:absolute;
    left:calc(100% + 8px);
    top:50%;
    transform:translateY(-50%);
    padding:7px 10px;
    border-radius:999px;
    background:rgba(255,255,255,.96);
    color:var(--navy);
    font-size:11px;
    font-weight:700;
    box-shadow:0 10px 22px rgba(0,26,71,.12);
    pointer-events:none;
}

.page-shell.left-panel-hidden ~ .panel-toggle-float,
body.left-panel-hidden .panel-toggle-float{
    display:inline-flex;
}

.left-tools-panel{
    transition:opacity .2s ease, transform .2s ease;
}

body.left-panel-hidden .left-tools-panel{
    display:none;
}

.inspector-panel{
    transition:opacity .2s ease, transform .2s ease;
}

body.inspector-hidden .inspector-panel{
    display:none;
}

.context-menu{
    position:fixed;
    min-width:220px;
    padding:8px;
    border-radius:18px;
    border:1px solid rgba(0,26,71,.12);
    background:rgba(255,255,255,.98);
    box-shadow:0 22px 55px rgba(15,23,42,.18);
    z-index:5000;
    display:none;
}

.context-menu.open{
    display:block;
}

.context-title{
    padding:8px 10px 10px;
    color:var(--muted);
    font-size:11px;
    font-weight:700;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.context-item{
    width:100%;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:11px 12px;
    border:none;
    border-radius:12px;
    background:transparent;
    color:var(--ink);
    font:inherit;
    text-align:left;
    cursor:pointer;
}

.context-item:hover{
    background:#eef3fa;
    color:var(--navy);
}

.context-item span{
    color:var(--muted);
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.05em;
}

.context-divider{
    height:1px;
    margin:6px 4px;
    background:rgba(0,26,71,.08);
}

body.exporting .rotate-handle,
body.exporting .design-item.selected{
    box-shadow:none !important;
}

body.exporting #canvas::before{
    display:none;
}

@media (max-width:1500px){
    .page-shell{
        grid-template-columns:180px minmax(0, 1fr);
    }

    .page-shell.inspector-hidden{
        grid-template-columns:180px minmax(0, 1fr);
    }

    .page-shell.left-panel-hidden{
        grid-template-columns:1fr;
    }

    .page-shell.left-panel-hidden.inspector-hidden{
        grid-template-columns:1fr;
    }

    .inspector{
        grid-column:1 / -1;
    }
}

@media (max-width:1080px){
    .main-content{
        margin-left:0;
        padding:76px 10px 10px;
    }

    body.left-panel-hidden .main-content{
        padding-top:76px;
    }

    .page-shell{
        grid-template-columns:minmax(0, 1fr);
        gap:8px;
    }

    .page-shell.inspector-hidden{
        grid-template-columns:1fr;
    }

    .page-shell.left-panel-hidden{
        grid-template-columns:1fr;
    }

    .page-shell.left-panel-hidden.inspector-hidden{
        grid-template-columns:1fr;
    }

    .workspace-header{
        flex-direction:row;
        align-items:center;
    }

    .board-stage{
        padding:8px;
        min-height:0;
    }

    .panel-toggle-float{
        top:18px;
        left:70px;
        border-radius:12px;
    }
}

@media (max-width:768px){
    .page-shell{
        grid-template-columns:minmax(0, 1fr);
    }

    .panel{
        border-radius:14px;
        box-shadow:0 12px 30px rgba(0,26,71,.10);
    }

    .panel-head{
        padding:10px 10px 6px;
    }

    .panel-body{
        padding:0 10px 10px;
    }

    .left-tools-panel .subtitle,
    .workspace-title p,
    .shortcut-note,
    .footer-note,
    .tool-btn span,
    .template-card span{
        display:none;
    }

    .left-tools-panel .panel-head-main h1{
        font-size:15px;
        line-height:1.15;
        margin-top:6px;
    }

    .tool-grid{
        grid-template-columns:repeat(4, minmax(0, 1fr));
        gap:6px;
    }

    .tool-btn{
        min-height:42px;
        padding:8px 6px;
        align-items:center;
        justify-content:center;
        text-align:center;
        border-radius:10px;
    }

    .tool-btn strong{
        font-size:10px;
        line-height:1.15;
    }

    .workspace{
        gap:8px;
    }

    .workspace-header{
        padding:10px 10px 0;
        gap:8px;
    }

    .workspace-title h2{
        font-size:15px;
        margin-top:4px;
    }

    .eyebrow{
        font-size:10px;
        padding:5px 8px;
    }

    .workspace-actions{
        flex-wrap:nowrap;
        gap:4px;
    }

    .action-btn,
    .action-btn.icon-only,
    .panel-toggle{
        width:36px;
        min-width:36px;
        height:36px;
        border-radius:10px;
        padding:0;
    }

    .panel-toggle-float::after{
        display:none;
    }

    .board-toolbar{
        margin:0 10px;
        padding:6px;
        gap:6px;
    }

    .toolbar-cluster{
        gap:6px;
    }

    .toolbar-cluster input[type="text"]{
        width:150px;
        max-width:100%;
    }

    .zoom-badge{
        min-width:54px;
        height:36px;
        padding:0 8px;
        font-size:11px;
    }

    .board-wrap{
        padding:0 10px 10px;
    }

    #canvas{
        border-radius:6px;
        box-shadow:0 14px 28px rgba(15,23,42,.14);
    }
}

@media (max-width:480px){
    .main-content{
        padding-left:6px;
        padding-right:6px;
    }

    .tool-grid{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .workspace-header{
        flex-direction:column;
        align-items:stretch;
    }

    .workspace-actions{
        justify-content:space-between;
    }

    .workspace-actions .action-btn{
        flex:1;
    }

    .board-toolbar{
        flex-direction:column;
        align-items:stretch;
    }

    .toolbar-cluster,
    .zoom-controls{
        width:100%;
        justify-content:space-between;
    }

    .toolbar-cluster input[type="text"]{
        flex:1;
        width:auto;
    }
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="page-shell" id="pageShell">
        <aside class="panel left-tools-panel" id="leftToolsPanel">
            <div class="panel-head compact-head">
                <div class="panel-head-row">
                    <span class="eyebrow">Drag and Drop</span>
                    <button type="button" class="panel-toggle" id="hideLeftPanelBtn" aria-label="Hide drag and drop certificate builder" title="Hide tools">
                        <i class="fas fa-toolbox" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="panel-head-main">
                    <h1>Certificate Builder</h1>
                    <p class="subtitle">Compact layout with quick tools first, extra options hidden until you need them.</p>
                </div>
            </div>
            <div class="panel-body">
                <div class="tool-group">
                    <span class="group-label">Quick Start</span>
                    <div class="tool-grid">
                        <button class="tool-btn" data-add="title">
                            <strong>Title</strong>
                            <span>Large certificate heading</span>
                        </button>
                        <button class="tool-btn" data-add="recipient">
                            <strong>Recipient</strong>
                            <span>Name placeholder line</span>
                        </button>
                        <button class="tool-btn" data-add="body">
                            <strong>Body Text</strong>
                            <span>Event description paragraph</span>
                        </button>
                        <button class="tool-btn" data-add="signature">
                            <strong>Signature</strong>
                            <span>Role and signer block</span>
                        </button>
                    </div>
                </div>

                <details class="collapsible tool-group">
                    <summary>More Elements</summary>
                    <div class="collapsible-body">
                        <div class="tool-grid">
                            <button class="tool-btn" data-add="image">
                                <strong>Image</strong>
                                <span>Upload logo or seal</span>
                            </button>
                            <button class="tool-btn" data-add="border">
                                <strong>Border</strong>
                                <span>Elegant frame around page</span>
                            </button>
                            <button class="tool-btn" data-add="rectangle">
                                <strong>Rectangle</strong>
                                <span>Panels and highlights</span>
                            </button>
                            <button class="tool-btn" data-add="circle">
                                <strong>Circle</strong>
                                <span>Badge or stamp base</span>
                            </button>
                            <button class="tool-btn" data-add="line">
                                <strong>Line</strong>
                                <span>Divider or underline</span>
                            </button>
                        </div>
                    </div>
                </details>

                <details class="collapsible tool-group">
                    <summary>Saved Templates</summary>
                    <div class="collapsible-body">
                        <div class="template-list" id="templateList">
                            <?php if (empty($templates)): ?>
                                <div class="empty-state">Your saved designs will show here after you click <strong>Save Template</strong>.</div>
                            <?php else: ?>
                                <?php foreach ($templates as $template): ?>
                                    <button class="template-card" type="button" data-template="<?= htmlspecialchars($template['name']) ?>">
                                        <img src="<?= htmlspecialchars($template['preview']) ?>" alt="<?= htmlspecialchars($template['name']) ?>">
                                        <strong><?= htmlspecialchars(str_replace('_', ' ', $template['name'])) ?></strong>
                                        <span>Click to load this layout</span>
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            </div>
        </aside>

        <main class="panel workspace">
            <div class="workspace-header">
                <div class="workspace-title">
                    <span class="eyebrow">Certificate Studio</span>
                    <h2>Build your layout by dragging pieces into place</h2>
                    <p>Double-click text to edit it on the canvas, or use the inspector for more exact styling.</p>
                </div>
                <div class="workspace-actions">
                    <button class="action-btn icon-only" type="button" id="undoBtn" aria-label="Undo">
                        <i class="fas fa-rotate-left" aria-hidden="true"></i>
                        <span class="btn-tooltip">Undo (Ctrl + Z)</span>
                    </button>
                    <button class="action-btn icon-only" type="button" id="redoBtn" aria-label="Redo">
                        <i class="fas fa-rotate-right" aria-hidden="true"></i>
                        <span class="btn-tooltip">Redo (Ctrl + Y)</span>
                    </button>
                    <button class="action-btn icon-only" type="button" id="duplicateBtn" aria-label="Duplicate">
                        <i class="fas fa-clone" aria-hidden="true"></i>
                        <span class="btn-tooltip">Duplicate (Ctrl + D)</span>
                    </button>
                    <button class="action-btn icon-only" type="button" id="deleteBtn" aria-label="Delete">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <span class="btn-tooltip">Delete (Del)</span>
                    </button>
                    <button class="action-btn icon-only" type="button" id="lockBtn" aria-label="Lock selected">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <span class="btn-tooltip">Lock Selected</span>
                    </button>
                    <button class="action-btn icon-only" type="button" id="unlockBtn" aria-label="Unlock selected">
                        <i class="fas fa-unlock" aria-hidden="true"></i>
                        <span class="btn-tooltip">Unlock Selected</span>
                    </button>
                    <button class="action-btn icon-only gold" type="button" id="saveBtn" aria-label="Save template">
                        <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                        <span class="btn-tooltip">Save Template (Ctrl + S)</span>
                    </button>
                    <div class="download-menu-wrap">
                        <button class="action-btn icon-only primary" type="button" id="downloadBtn" aria-label="Download" aria-expanded="false" aria-controls="downloadMenu">
                            <i class="fas fa-download" aria-hidden="true"></i>
                            <span class="btn-tooltip">Download</span>
                        </button>
                        <div class="download-menu" id="downloadMenu" hidden>
                            <button class="download-option" type="button" data-download-format="pdf">
                                <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                <span>Save as PDF</span>
                            </button>
                            <button class="download-option" type="button" data-download-format="png">
                                <i class="fas fa-image" aria-hidden="true"></i>
                                <span>Save as Image</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="board-toolbar">
                <div class="toolbar-cluster">
                    <label for="templateName">Template Name</label>
                    <input type="text" id="templateName" placeholder="My certificate layout">
                </div>
                <div class="toolbar-cluster">
                    <label>Zoom</label>
                    <div class="zoom-controls">
                        <button class="action-btn icon-only" type="button" id="zoomOutBtn" aria-label="Zoom out">
                            <i class="fas fa-magnifying-glass-minus" aria-hidden="true"></i>
                            <span class="btn-tooltip">Zoom Out</span>
                        </button>
                        <button class="action-btn" type="button" id="zoomFitBtn" aria-label="Fit to screen">
                            <i class="fas fa-expand" aria-hidden="true"></i>
                            <span class="btn-text">Fit</span>
                            <span class="btn-tooltip">Fit to Screen</span>
                        </button>
                        <button class="action-btn icon-only" type="button" id="zoomInBtn" aria-label="Zoom in">
                            <i class="fas fa-magnifying-glass-plus" aria-hidden="true"></i>
                            <span class="btn-tooltip">Zoom In</span>
                        </button>
                        <div class="zoom-badge" id="zoomValue">100%</div>
                    </div>
                </div>
                <details class="collapsible">
                    <summary>More Board Options</summary>
                    <div class="collapsible-body">
                        <div class="toolbar-cluster">
                            <label for="canvasColor">Canvas Color</label>
                            <input type="color" id="canvasColor" value="#fffdf8">
                        </div>
                        <div class="toolbar-cluster" style="margin-top:8px;">
                            <button class="action-btn icon-only" type="button" id="backgroundImageBtn" aria-label="Add background image">
                                <i class="fas fa-image" aria-hidden="true"></i>
                                <span class="btn-tooltip">Background Image</span>
                            </button>
                            <button class="action-btn icon-only" type="button" id="clearBackgroundBtn" aria-label="Clear background">
                                <i class="fas fa-eraser" aria-hidden="true"></i>
                                <span class="btn-tooltip">Clear Background</span>
                            </button>
                        </div>
                    </div>
                </details>
            </div>

            <div class="board-wrap">
                <div class="board-stage">
                    <div class="canvas-viewport" id="canvasViewport">
                        <div id="canvas"></div>
                    </div>
                </div>
            </div>
            <div class="shortcut-note">Shortcuts: Ctrl + click multi-select, Ctrl + A select all, Ctrl + S save, Ctrl + Z undo, Ctrl + Y or Ctrl + Shift + Z redo, Ctrl + Plus zoom in, Ctrl + Minus zoom out, Delete remove, right-click any element for layer options.</div>
        </main>

        <aside class="panel inspector inspector-panel" id="inspectorPanel">
            <div class="panel-head">
                <span class="eyebrow">Inspector</span>
                <h3>Selected Element</h3>
                <div class="selection-name" id="selectionLabel">Nothing selected</div>
            </div>

            <div class="inspector-section">
                <div class="empty-state" id="inspectorEmpty">Pick any element on the canvas to edit text, colors, size, spacing, and position.</div>
            </div>

            <div id="inspectorFields" class="hidden">
                <details class="collapsible inspector-section" id="textFields" open>
                    <summary>Text Style</summary>
                    <div class="collapsible-body">
                        <div class="field">
                            <label for="textContent">Text</label>
                            <textarea id="textContent"></textarea>
                        </div>
                        <div class="field">
                            <label for="fontFamily">Font Family</label>
                            <select id="fontFamily">
                                <option value="'Cormorant Garamond', serif">Cormorant Garamond</option>
                                <option value="'Poppins', sans-serif">Poppins</option>
                                <option value="'Montserrat', sans-serif">Montserrat</option>
                                <option value="'Great Vibes', cursive">Great Vibes</option>
                            </select>
                        </div>
                        <div class="field two-col">
                            <div>
                                <label for="fontSize">Font Size</label>
                                <input type="number" id="fontSize" min="10" max="120">
                            </div>
                            <div>
                                <label for="fontWeight">Weight</label>
                                <select id="fontWeight">
                                    <option value="400">Regular</option>
                                    <option value="500">Medium</option>
                                    <option value="600">Semibold</option>
                                    <option value="700">Bold</option>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label>Text Align</label>
                            <div class="align-row">
                                <button class="align-btn" type="button" data-align="left">Left</button>
                                <button class="align-btn" type="button" data-align="center">Center</button>
                                <button class="align-btn" type="button" data-align="right">Right</button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="textColor">Text Color</label>
                            <input type="color" id="textColor" value="#001a47">
                        </div>
                    </div>
                </details>

                <details class="collapsible inspector-section" id="shapeFields">
                    <summary>Shape Style</summary>
                    <div class="collapsible-body">
                        <div class="field two-col">
                            <div>
                                <label for="fillColor">Fill</label>
                                <input type="color" id="fillColor" value="#d4af37">
                            </div>
                            <div>
                                <label for="borderColor">Border</label>
                                <input type="color" id="borderColor" value="#d4af37">
                            </div>
                        </div>
                        <div class="field two-col">
                            <div>
                                <label for="borderWidth">Border Width</label>
                                <input type="number" id="borderWidth" min="0" max="20">
                            </div>
                            <div>
                                <label for="cornerRadius">Radius</label>
                                <input type="number" id="cornerRadius" min="0" max="500">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="collapsible inspector-section" id="imageFields">
                    <summary>Image Options</summary>
                    <div class="collapsible-body">
                        <div class="field">
                            <button class="action-btn" type="button" id="replaceImageBtn">Replace Image</button>
                        </div>
                        <div class="field">
                            <label for="imageFit">Image Fit</label>
                            <select id="imageFit">
                                <option value="contain">Show Full Image</option>
                                <option value="cover">Crop to Box</option>
                            </select>
                        </div>
                        <div class="field two-col">
                            <div>
                                <label for="imageCropX">Crop X</label>
                                <input type="range" id="imageCropX" min="0" max="100" step="1" value="50">
                            </div>
                            <div>
                                <label for="imageCropY">Crop Y</label>
                                <input type="range" id="imageCropY" min="0" max="100" step="1" value="50">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="collapsible inspector-section" open>
                    <summary>Transform</summary>
                    <div class="collapsible-body">
                        <div class="field">
                            <label for="opacityRange">Opacity</label>
                            <div class="range-wrap">
                                <input type="range" id="opacityRange" min="0" max="1" step="0.05">
                                <span id="opacityValue">1</span>
                            </div>
                        </div>
                        <div class="field">
                            <label for="rotationRange">Rotation</label>
                            <div class="range-wrap">
                                <input type="range" id="rotationRange" min="-180" max="180" step="1">
                                <span id="rotationValue">0 deg</span>
                            </div>
                        </div>
                        <div class="field two-col">
                            <div>
                                <label for="posX">X</label>
                                <input type="number" id="posX">
                            </div>
                            <div>
                                <label for="posY">Y</label>
                                <input type="number" id="posY">
                            </div>
                        </div>
                        <div class="field two-col">
                            <div>
                                <label for="boxWidth">Width</label>
                                <input type="number" id="boxWidth" min="20">
                            </div>
                            <div>
                                <label for="boxHeight">Height</label>
                                <input type="number" id="boxHeight" min="2">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="collapsible inspector-section">
                    <summary>Layer</summary>
                    <div class="collapsible-body">
                        <div class="field">
                            <div class="align-row">
                                <button class="align-btn" type="button" id="bringFrontBtn">Front</button>
                                <button class="align-btn" type="button" id="bringForwardBtn">Up</button>
                                <button class="align-btn" type="button" id="sendBackwardBtn">Down</button>
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div class="footer-note">
                Tip: use the left panel for quick certificate blocks, then drag items on the page and polish them here.
            </div>
        </aside>
    </div>
    <button type="button" class="panel-toggle panel-toggle-float" id="showLeftPanelBtn" aria-label="Show drag and drop certificate builder" title="Show tools">
        <i class="fas fa-toolbox" aria-hidden="true"></i>
    </button>
</div>

<input type="file" id="imageInput" accept="image/*" class="hidden">
<input type="file" id="backgroundInput" accept="image/*" class="hidden">
<div id="contextMenu" class="context-menu">
    <div class="context-title">Element Options</div>
    <button type="button" class="context-item" data-menu-action="bring-front">Bring to front <span>Top</span></button>
    <button type="button" class="context-item" data-menu-action="bring-forward">Bring forward <span>Up</span></button>
    <button type="button" class="context-item" data-menu-action="send-backward">Send backward <span>Down</span></button>
    <button type="button" class="context-item" data-menu-action="send-back">Send to back <span>Bottom</span></button>
    <div class="context-divider"></div>
    <button type="button" class="context-item" data-menu-action="lock">Lock selected <span>Lock</span></button>
    <button type="button" class="context-item" data-menu-action="unlock">Unlock selected <span>Open</span></button>
    <div class="context-divider"></div>
    <button type="button" class="context-item" data-menu-action="duplicate">Duplicate <span>Ctrl+D</span></button>
    <button type="button" class="context-item" data-menu-action="delete">Delete <span>Del</span></button>
</div>

<script>
const canvas = document.getElementById('canvas');
const canvasViewport = document.getElementById('canvasViewport');
const boardStage = document.querySelector('.board-stage');
const pageShell = document.getElementById('pageShell');
const inspectorPanel = document.getElementById('inspectorPanel');
const hideLeftPanelBtn = document.getElementById('hideLeftPanelBtn');
const showLeftPanelBtn = document.getElementById('showLeftPanelBtn');
const templateNameInput = document.getElementById('templateName');
const imageInput = document.getElementById('imageInput');
const backgroundInput = document.getElementById('backgroundInput');
const selectionLabel = document.getElementById('selectionLabel');
const inspectorEmpty = document.getElementById('inspectorEmpty');
const inspectorFields = document.getElementById('inspectorFields');
const textFields = document.getElementById('textFields');
const shapeFields = document.getElementById('shapeFields');
const imageFields = document.getElementById('imageFields');
const textContent = document.getElementById('textContent');
const fontFamily = document.getElementById('fontFamily');
const fontSize = document.getElementById('fontSize');
const fontWeight = document.getElementById('fontWeight');
const textColor = document.getElementById('textColor');
const fillColor = document.getElementById('fillColor');
const borderColor = document.getElementById('borderColor');
const borderWidth = document.getElementById('borderWidth');
const cornerRadius = document.getElementById('cornerRadius');
const imageFit = document.getElementById('imageFit');
const imageCropX = document.getElementById('imageCropX');
const imageCropY = document.getElementById('imageCropY');
const opacityRange = document.getElementById('opacityRange');
const opacityValue = document.getElementById('opacityValue');
const rotationRange = document.getElementById('rotationRange');
const rotationValue = document.getElementById('rotationValue');
const posX = document.getElementById('posX');
const posY = document.getElementById('posY');
const boxWidth = document.getElementById('boxWidth');
const boxHeight = document.getElementById('boxHeight');
const canvasColor = document.getElementById('canvasColor');
const alignButtons = [...document.querySelectorAll('[data-align]')];
const contextMenu = document.getElementById('contextMenu');
const zoomValue = document.getElementById('zoomValue');
const downloadBtn = document.getElementById('downloadBtn');
const downloadMenu = document.getElementById('downloadMenu');

let selectedItem = null;
let selectedItems = new Set();
let historyStack = [];
let redoStack = [];
let highestZ = 10;
let pendingImageMode = 'element';
const BASE_CANVAS_WIDTH = 1123;
const BASE_CANVAS_HEIGHT = 794;
let currentCanvasScale = 1;
let fitCanvasScale = 1;
let userZoomLevel = 1;
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 2.5;
const ZOOM_STEP = 0.1;
const LEFT_PANEL_STORAGE_KEY = 'certificate_left_panel_hidden';

const presets = {
    logoDti: {
        type: 'image',
        x: 438,
        y: 70,
        width: 116,
        height: 72,
        rotation: 0,
        zIndex: 18,
        opacity: 1,
        src: 'assets/dti-philippines.png',
        html: '',
        styles: {
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: 'transparent',
            objectFit: 'contain',
            objectPosition: '50% 50%'
        }
    },
    logoNegosyo: {
        type: 'image',
        x: 562,
        y: 78,
        width: 158,
        height: 54,
        rotation: 0,
        zIndex: 18,
        opacity: 1,
        src: 'assets/negosyo-center.png',
        html: '',
        styles: {
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: 'transparent',
            objectFit: 'contain',
            objectPosition: '50% 50%'
        }
    },
    title: {
        type: 'text',
        x: 260,
        y: 150,
        width: 604,
        height: 72,
        rotation: 0,
        zIndex: 20,
        opacity: 1,
        html: 'CERTIFICATE',
        styles: {
            fontFamily: "'Cormorant Garamond', serif",
            fontSize: '58px',
            fontWeight: '700',
            color: '#000000',
            textAlign: 'center',
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: '#000000'
        }
    },
    subtitle: {
        type: 'text',
        x: 352,
        y: 220,
        width: 420,
        height: 40,
        rotation: 0,
        zIndex: 20,
        opacity: 1,
        html: 'OF PARTICIPATION',
        styles: {
            fontFamily: "'Cormorant Garamond', serif",
            fontSize: '28px',
            fontWeight: '600',
            color: '#000000',
            textAlign: 'center',
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: '#000000'
        }
    },
    awardText: {
        type: 'text',
        x: 342,
        y: 284,
        width: 440,
        height: 38,
        rotation: 0,
        zIndex: 20,
        opacity: 1,
        html: 'This certificate is awarded to',
        styles: {
            fontFamily: "'Cormorant Garamond', serif",
            fontSize: '22px',
            fontWeight: '600',
            color: '#000000',
            textAlign: 'center',
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: '#000000'
        }
    },
    recipient: {
        type: 'text',
        x: 322,
        y: 326,
        width: 480,
        height: 62,
        rotation: 0,
        zIndex: 21,
        opacity: 1,
        html: '&nbsp;',
        styles: {
            fontFamily: "'Montserrat', sans-serif",
            fontSize: '34px',
            fontWeight: '500',
            color: '#17356f',
            textAlign: 'center',
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: '#17356f'
        }
    },
    body: {
        type: 'text',
        x: 180,
        y: 432,
        width: 764,
        height: 112,
        rotation: 0,
        zIndex: 22,
        opacity: 1,
        html: 'for participating in the program/activity entitled "Program Title" held on Month Day, Year at Venue. This paragraph may be edited by the user to describe the purpose and details of the certificate.',
        styles: {
            fontFamily: "'Cormorant Garamond', serif",
            fontSize: '24px',
            fontWeight: '600',
            color: '#000000',
            textAlign: 'center',
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: '#334155'
        }
    },
    signature: {
        type: 'text',
        x: 440,
        y: 590,
        width: 244,
        height: 44,
        rotation: 0,
        zIndex: 23,
        opacity: 1,
        html: 'Signatory',
        styles: {
            fontFamily: "'Great Vibes', cursive",
            fontSize: '30px',
            fontWeight: '400',
            color: '#000000',
            textAlign: 'center',
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: '#000000'
        }
    },
    signatoryName: {
        type: 'text',
        x: 410,
        y: 636,
        width: 304,
        height: 34,
        rotation: 0,
        zIndex: 23,
        opacity: 1,
        html: 'NAME OF SIGNATORY',
        styles: {
            fontFamily: "'Cormorant Garamond', serif",
            fontSize: '20px',
            fontWeight: '700',
            color: '#000000',
            textAlign: 'center',
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: '#000000'
        }
    },
    signatoryPosition: {
        type: 'text',
        x: 432,
        y: 668,
        width: 260,
        height: 32,
        rotation: 0,
        zIndex: 23,
        opacity: 1,
        html: 'Position',
        styles: {
            fontFamily: "'Cormorant Garamond', serif",
            fontSize: '16px',
            fontWeight: '500',
            color: '#000000',
            textAlign: 'center',
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '0px',
            borderColor: '#000000'
        }
    },
    rectangle: {
        type: 'shape',
        x: 100,
        y: 100,
        width: 240,
        height: 120,
        rotation: 0,
        zIndex: 15,
        opacity: 1,
        html: '',
        styles: {
            backgroundColor: '#d4af37',
            borderRadius: '18px',
            borderWidth: '0px',
            borderColor: '#d4af37'
        }
    },
    circle: {
        type: 'shape',
        x: 130,
        y: 130,
        width: 140,
        height: 140,
        rotation: 0,
        zIndex: 15,
        opacity: 1,
        html: '',
        styles: {
            backgroundColor: '#001a47',
            borderRadius: '999px',
            borderWidth: '0px',
            borderColor: '#001a47'
        }
    },
    line: {
        type: 'shape',
        x: 322,
        y: 394,
        width: 480,
        height: 1,
        rotation: 0,
        zIndex: 14,
        opacity: 1,
        html: '',
        styles: {
            backgroundColor: '#000000',
            borderRadius: '999px',
            borderWidth: '0px',
            borderColor: '#000000'
        }
    },
    border: {
        type: 'shape',
        x: 52,
        y: 52,
        width: 1019,
        height: 690,
        rotation: 0,
        zIndex: 0,
        opacity: 1,
        html: '',
        lockedFill: true,
        styles: {
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '6px',
            borderColor: '#f0c52e'
        }
    },
    innerBorder: {
        type: 'shape',
        x: 66,
        y: 66,
        width: 991,
        height: 662,
        rotation: 0,
        zIndex: 0,
        opacity: 1,
        html: '',
        lockedFill: true,
        styles: {
            backgroundColor: 'transparent',
            borderRadius: '0px',
            borderWidth: '2px',
            borderColor: '#17356f'
        }
    },
    image: {
        type: 'image',
        x: 86,
        y: 90,
        width: 140,
        height: 140,
        rotation: 0,
        zIndex: 18,
        opacity: 1,
        src: '',
        html: '',
        styles: {
            backgroundColor: 'transparent',
            borderRadius: '14px',
            borderWidth: '0px',
            borderColor: '#001a47',
            objectFit: 'contain',
            objectPosition: '50% 50%'
        }
    }
};

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function deepClone(value) {
    return JSON.parse(JSON.stringify(value));
}

function snap(value) {
    return Math.round(value / 5) * 5;
}

function uid() {
    return 'item_' + Math.random().toString(36).slice(2, 10);
}

function getCanvasWidth() {
    return BASE_CANVAS_WIDTH;
}

function getCanvasHeight() {
    return BASE_CANVAS_HEIGHT;
}

function getMinimumItemHeight(item) {
    return item?.dataset.type === 'shape' ? 2 : 20;
}

function getMaximumItemWidth(item) {
    return item?.dataset.type === 'shape' ? getCanvasWidth() * 2 : getCanvasWidth();
}

function getMaximumItemHeight(item) {
    return item?.dataset.type === 'shape' ? getCanvasHeight() * 2 : getCanvasHeight();
}

function clampItemLeft(item, left, width = item?.offsetWidth || 0) {
    if (item?.dataset.type === 'shape') {
        return clamp(left, -width, getCanvasWidth());
    }

    return clamp(left, 0, getCanvasWidth() - width);
}

function clampItemTop(item, top, height = item?.offsetHeight || 0) {
    if (item?.dataset.type === 'shape') {
        return clamp(top, -height, getCanvasHeight());
    }

    return clamp(top, 0, getCanvasHeight() - height);
}

function isLocked(item) {
    return item?.dataset.locked === '1';
}

function getSelectedItems() {
    return [...selectedItems].filter((item) => item.isConnected);
}

function getEditableSelection() {
    return getSelectedItems().filter((item) => !isLocked(item));
}

function updateZoomBadge() {
    zoomValue.textContent = `${Math.round(userZoomLevel * 100)}%`;
}

function applyCanvasScale() {
    const style = window.getComputedStyle(boardStage);
    const horizontalPadding = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
    const verticalPadding = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
    const availableWidth = Math.max(160, boardStage.clientWidth - horizontalPadding);
    const viewportTop = boardStage.getBoundingClientRect().top;
    const availableHeight = Math.max(180, window.innerHeight - viewportTop - 12 - verticalPadding);

    fitCanvasScale = Math.min(
        availableWidth / BASE_CANVAS_WIDTH,
        availableHeight / BASE_CANVAS_HEIGHT,
        1
    );
    currentCanvasScale = fitCanvasScale * userZoomLevel;

    canvasViewport.style.width = `${Math.round(BASE_CANVAS_WIDTH * currentCanvasScale)}px`;
    canvasViewport.style.height = `${Math.round(BASE_CANVAS_HEIGHT * currentCanvasScale)}px`;
    canvas.style.transform = `scale(${currentCanvasScale})`;
    boardStage.style.minHeight = `${Math.round(BASE_CANVAS_HEIGHT * currentCanvasScale) + verticalPadding}px`;
    boardStage.classList.toggle('is-centered', canvasViewport.offsetWidth <= availableWidth + 1);
    boardStage.scrollLeft = 0;
    boardStage.scrollTop = 0;
    updateZoomBadge();
}

function setZoom(level) {
    userZoomLevel = clamp(Math.round(level * 100) / 100, MIN_ZOOM, MAX_ZOOM);
    applyCanvasScale();
}

function zoomIn() {
    setZoom(userZoomLevel + ZOOM_STEP);
}

function zoomOut() {
    setZoom(userZoomLevel - ZOOM_STEP);
}

function zoomToFit() {
    setZoom(1);
    boardStage.scrollLeft = 0;
    boardStage.scrollTop = 0;
}

function setLeftPanelHidden(hidden) {
    document.body.classList.toggle('left-panel-hidden', hidden);
    pageShell.classList.toggle('left-panel-hidden', hidden);
    localStorage.setItem(LEFT_PANEL_STORAGE_KEY, hidden ? '1' : '0');
    applyCanvasScale();
}

function setInspectorHidden(hidden) {
    document.body.classList.toggle('inspector-hidden', hidden);
    pageShell.classList.toggle('inspector-hidden', hidden);
    if (inspectorPanel) {
        inspectorPanel.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    }
    applyCanvasScale();
}

function readFileAsDataURL(file, callback) {
    const reader = new FileReader();
    reader.onload = () => callback(reader.result);
    reader.readAsDataURL(file);
}

function getSelectionName(item) {
    if (!item) {
        return 'Nothing selected';
    }

    const type = item.dataset.type;
    if (type === 'text') return 'Text block';
    if (type === 'image') return 'Image';
    return 'Shape';
}

function createItem(data, shouldSave = true) {
    const item = document.createElement('div');
    item.className = `design-item item-${data.type}`;
    item.dataset.id = data.id || uid();
    item.dataset.type = data.type;
    item.dataset.rotation = data.rotation || 0;
    item.dataset.lockedFill = data.lockedFill ? '1' : '0';
    const locked = data.locked === true || data.locked === '1';
    item.dataset.locked = locked ? '1' : '0';
    item.classList.toggle('locked', locked);
    item.style.left = `${data.x || 0}px`;
    item.style.top = `${data.y || 0}px`;
    item.style.width = `${data.width || 160}px`;
    item.style.height = `${data.height || 80}px`;
    item.style.opacity = data.opacity ?? 1;
    item.style.zIndex = data.zIndex || ++highestZ;

    const content = document.createElement('div');
    content.className = 'item-content';
    item.appendChild(content);

    if (data.type === 'image') {
        const img = document.createElement('img');
        img.src = data.src || 'assets/negosyo-center.png';
        content.appendChild(img);
        const cropOverlay = document.createElement('div');
        cropOverlay.className = 'crop-overlay';
        content.appendChild(cropOverlay);
    } else if (data.type === 'text') {
        content.innerHTML = data.html || 'Edit text';
    }

    applyStyles(item, data.styles || {});

    const rotateHandle = document.createElement('div');
    rotateHandle.className = 'rotate-handle';
    rotateHandle.innerHTML = '&#8635;';
    item.appendChild(rotateHandle);

    [
        'resize-top resize-left',
        'resize-top',
        'resize-top resize-right',
        'resize-right',
        'resize-bottom resize-right',
        'resize-bottom',
        'resize-bottom resize-left',
        'resize-left'
    ].forEach((classes) => {
        const handle = document.createElement('div');
        handle.className = `resize-handle ${classes}`;
        item.appendChild(handle);
    });

    canvas.appendChild(item);
    bindItem(item);
    setRotation(item, Number(item.dataset.rotation || 0));
    highestZ = Math.max(highestZ, Number(item.style.zIndex || 1));

    if (shouldSave) {
        selectItem(item);
        saveHistory();
    }

    return item;
}

function normalizeLegacyElement(item) {
    const probe = document.createElement('div');
    probe.style.cssText = item.style || '';

    return {
        id: uid(),
        type: item.tag === 'IMG' ? 'image' : 'text',
        x: Number(item.x || 0),
        y: Number(item.y || 0),
        width: parseInt(probe.style.width || '180', 10),
        height: parseInt(probe.style.height || (item.tag === 'IMG' ? '180' : '80'), 10),
        rotation: Number(item.rotate || 0),
        zIndex: Number(probe.style.zIndex || ++highestZ),
        opacity: parseFloat(probe.style.opacity || 1),
        src: item.src || '',
        html: item.html || 'Edit text',
        styles: {
            fontFamily: probe.style.fontFamily || "'Poppins', sans-serif",
            fontSize: probe.style.fontSize || '18px',
            fontWeight: probe.style.fontWeight || '400',
            color: probe.style.color || '#001a47',
            textAlign: probe.style.textAlign || 'center',
            backgroundColor: probe.style.backgroundColor || 'transparent',
            borderRadius: probe.style.borderRadius || '0px',
            borderWidth: probe.style.borderWidth || '0px',
            borderColor: probe.style.borderColor || '#001a47'
        }
    };
}

function applyStyles(item, styles) {
    const content = item.querySelector('.item-content');
    const type = item.dataset.type;

    if (type === 'text') {
        content.style.fontFamily = styles.fontFamily || "'Poppins', sans-serif";
        content.style.fontSize = styles.fontSize || '18px';
        content.style.fontWeight = styles.fontWeight || '400';
        content.style.color = styles.color || '#001a47';
        content.style.textAlign = styles.textAlign || 'center';
        content.style.backgroundColor = styles.backgroundColor || 'transparent';
        content.style.borderRadius = styles.borderRadius || '0px';
        content.style.borderStyle = 'solid';
        content.style.borderWidth = styles.borderWidth || '0px';
        content.style.borderColor = styles.borderColor || 'transparent';
    } else {
        content.style.backgroundColor = styles.backgroundColor || 'transparent';
        content.style.borderRadius = styles.borderRadius || '0px';
        content.style.borderStyle = 'solid';
        content.style.borderWidth = styles.borderWidth || '0px';
        content.style.borderColor = styles.borderColor || 'transparent';
    }

    if (type === 'image') {
        const img = content.querySelector('img');
        item.style.borderRadius = styles.borderRadius || '14px';
        item.style.overflow = 'hidden';
        content.style.borderRadius = 'inherit';
        item.style.border = `${styles.borderWidth || '0px'} solid ${styles.borderColor || 'transparent'}`;
        content.style.backgroundColor = 'transparent';
        if (img) {
            img.style.objectFit = styles.objectFit || 'contain';
            img.style.objectPosition = styles.objectPosition || '50% 50%';
        }
    } else {
        item.style.border = 'none';
    }
}

function bindItem(item) {
    const content = item.querySelector('.item-content');
    const rotateHandle = item.querySelector('.rotate-handle');

    item.addEventListener('mousedown', (event) => {
        if (event.target.closest('.rotate-handle')) {
            return;
        }

        const additiveSelection = event.ctrlKey || event.metaKey || event.shiftKey;
        if (!additiveSelection && selectedItems.size > 1 && selectedItems.has(item)) {
            selectedItem = item;
            refreshSelectionState();
            return;
        }

        selectItem(item, additiveSelection);
    });

    item.addEventListener('contextmenu', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (!selectedItems.has(item)) {
            selectItem(item);
        }
        openContextMenu(event.clientX, event.clientY);
    });

    item.addEventListener('dblclick', () => {
        if (item.dataset.type === 'image') {
            selectItem(item);
            imageFit.value = 'cover';
            updateSelectedStyles();
            saveHistory();
            return;
        }

        if (item.dataset.type !== 'text') {
            return;
        }

        selectItem(item);
        content.contentEditable = 'true';
        content.focus();
        document.execCommand('selectAll', false, null);
    });

    content.addEventListener('blur', () => {
        if (item.dataset.type === 'text') {
            content.contentEditable = 'false';
            syncInspector();
            saveHistory();
        }
    });

    content.addEventListener('input', () => {
        if (item.dataset.type === 'text' && selectedItem === item) {
            textContent.value = content.innerText;
        }
    });

    interact(item)
        .draggable({
            ignoreFrom: '[contenteditable="true"], .rotate-handle, .resize-handle',
            listeners: {
                move(event) {
                    if (isLocked(item)) {
                        return;
                    }

                    const targets = selectedItems.has(item) && selectedItems.size > 1
                        ? getEditableSelection()
                        : [item];
                    const dx = event.dx / currentCanvasScale;
                    const dy = event.dy / currentCanvasScale;

                    targets.forEach((target) => {
                        const left = snap(parseFloat(target.style.left || 0) + dx);
                        const top = snap(parseFloat(target.style.top || 0) + dy);
                        target.style.left = `${clampItemLeft(target, left)}px`;
                        target.style.top = `${clampItemTop(target, top)}px`;
                    });
                    syncInspector();
                },
                end() {
                    saveHistory();
                }
            }
        })
        .resizable({
            edges: {
                left: '.resize-left',
                right: '.resize-right',
                bottom: '.resize-bottom',
                top: '.resize-top'
            },
            margin: 14,
            listeners: {
                move(event) {
                    if (isLocked(item)) {
                        return;
                    }

                    let width = clamp(event.rect.width / currentCanvasScale, 30, getMaximumItemWidth(item));
                    const minHeight = getMinimumItemHeight(item);
                    let height = clamp(event.rect.height / currentCanvasScale, minHeight, getMaximumItemHeight(item));
                    let left = snap(parseFloat(item.style.left || 0) + (event.deltaRect.left / currentCanvasScale));
                    let top = snap(parseFloat(item.style.top || 0) + (event.deltaRect.top / currentCanvasScale));

                    left = clampItemLeft(item, left, width);
                    top = clampItemTop(item, top, height);

                    item.style.width = `${snap(width)}px`;
                    item.style.height = `${height <= 10 ? Math.round(height) : snap(height)}px`;
                    item.style.left = `${left}px`;
                    item.style.top = `${top}px`;
                    syncInspector();
                },
                end() {
                    saveHistory();
                }
            },
            modifiers: [
                interact.modifiers.restrictSize({
                    min: { width: 30, height: 2 }
                })
            ]
        });

    let rotating = false;

    rotateHandle.addEventListener('mousedown', (event) => {
        if (isLocked(item)) {
            return;
        }

        event.stopPropagation();
        selectItem(item);
        rotating = true;
    });

    document.addEventListener('mousemove', (event) => {
        if (!rotating || selectedItem !== item) {
            return;
        }

        const rect = item.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const angle = Math.atan2(event.clientY - cy, event.clientX - cx) * (180 / Math.PI) + 90;
        setRotation(item, Math.round(angle));
        syncInspector();
    });

    document.addEventListener('mouseup', () => {
        if (rotating) {
            rotating = false;
            saveHistory();
        }
    });
}

function setRotation(item, angle) {
    item.dataset.rotation = angle;
    item.style.transform = `rotate(${angle}deg)`;
}

function openContextMenu(x, y) {
    const menuWidth = 220;
    const menuHeight = 340;
    const maxX = window.innerWidth - menuWidth - 16;
    const maxY = window.innerHeight - menuHeight - 16;
    contextMenu.style.left = `${Math.max(12, Math.min(x, maxX))}px`;
    contextMenu.style.top = `${Math.max(12, Math.min(y, maxY))}px`;
    contextMenu.classList.add('open');
}

function closeContextMenu() {
    contextMenu.classList.remove('open');
}

function refreshSelectionState() {
    selectedItems = new Set(getSelectedItems());
    document.querySelectorAll('.design-item.selected').forEach((node) => {
        if (!selectedItems.has(node)) {
            node.classList.remove('selected');
        }
    });
    selectedItems.forEach((node) => node.classList.add('selected'));

    selectedItem = selectedItems.has(selectedItem) ? selectedItem : (getSelectedItems()[0] || null);

    if (selectedItem) {
        setInspectorHidden(false);
        selectionLabel.textContent = selectedItems.size > 1
            ? `${selectedItems.size} elements selected`
            : `${getSelectionName(selectedItem)}${isLocked(selectedItem) ? ' (Locked)' : ''}`;
        inspectorEmpty.classList.add('hidden');
        inspectorFields.classList.remove('hidden');
        syncInspector();
    } else {
        setInspectorHidden(true);
        selectionLabel.textContent = 'Nothing selected';
        inspectorEmpty.classList.remove('hidden');
        inspectorFields.classList.add('hidden');
    }
}

function selectItem(item, additive = false) {
    if (!item) {
        selectedItems.clear();
        selectedItem = null;
        refreshSelectionState();
        return;
    }

    if (additive) {
        selectedItems.add(item);
        selectedItem = item;
    } else {
        selectedItems.clear();
        selectedItems.add(item);
        selectedItem = item;
    }

    refreshSelectionState();
}

function selectAllItems() {
    selectedItems = new Set(canvas.querySelectorAll('.design-item'));
    selectedItem = getSelectedItems()[0] || null;
    refreshSelectionState();
}

function clearSelection() {
    selectedItems.clear();
    selectedItem = null;
    closeContextMenu();
    refreshSelectionState();
}

function getItemStyles(item) {
    const content = item.querySelector('.item-content');
    const img = content.querySelector('img');
    return {
        fontFamily: content.style.fontFamily || '',
        fontSize: content.style.fontSize || '',
        fontWeight: content.style.fontWeight || '',
        color: content.style.color || '',
        textAlign: content.style.textAlign || '',
        backgroundColor: content.style.backgroundColor || 'transparent',
        borderRadius: item.dataset.type === 'image' ? item.style.borderRadius || '0px' : content.style.borderRadius || '0px',
        borderWidth: item.dataset.type === 'image'
            ? (item.style.borderWidth || '0px')
            : (content.style.borderWidth || '0px'),
        borderColor: item.dataset.type === 'image'
            ? (item.style.borderColor || 'transparent')
            : (content.style.borderColor || 'transparent'),
        objectFit: item.dataset.type === 'image' ? (img?.style.objectFit || 'contain') : '',
        objectPosition: item.dataset.type === 'image' ? (img?.style.objectPosition || '50% 50%') : ''
    };
}

function serializeLayout() {
    return {
        board: {
            backgroundColor: canvas.style.backgroundColor || '#fffdf8',
            backgroundImage: canvas.dataset.bgImage || ''
        },
        elements: [...canvas.querySelectorAll('.design-item')].map((item) => {
            const content = item.querySelector('.item-content');
            const payload = {
                id: item.dataset.id,
                type: item.dataset.type,
                x: parseFloat(item.style.left || 0),
                y: parseFloat(item.style.top || 0),
                width: parseFloat(item.style.width || 0),
                height: parseFloat(item.style.height || 0),
                rotation: Number(item.dataset.rotation || 0),
                zIndex: Number(item.style.zIndex || 1),
                opacity: parseFloat(item.style.opacity || 1),
                lockedFill: item.dataset.lockedFill === '1',
                locked: item.dataset.locked === '1',
                styles: getItemStyles(item)
            };

            if (item.dataset.type === 'image') {
                payload.src = content.querySelector('img')?.src || '';
            } else {
                payload.html = content.innerHTML;
            }

            return payload;
        })
    };
}

function restoreLayout(layout, pushHistory = false) {
    canvas.innerHTML = '';
    highestZ = 10;

    const normalized = Array.isArray(layout)
        ? { board: { backgroundColor: '#fffdf8', backgroundImage: '' }, elements: layout }
        : layout;

    canvas.style.backgroundColor = normalized.board?.backgroundColor || '#fffdf8';
    canvasColor.value = toHexColor(normalized.board?.backgroundColor || '#fffdf8');
    canvas.dataset.bgImage = normalized.board?.backgroundImage || '';
    canvas.style.backgroundImage = canvas.dataset.bgImage ? `url(${canvas.dataset.bgImage})` : 'none';

    (normalized.elements || []).forEach((item) => {
        const prepared = item.type ? item : normalizeLegacyElement(item);
        createItem(prepared, false);
    });

    clearSelection();
    applyCanvasScale();

    if (pushHistory) {
        saveHistory();
    }
}

function saveHistory(clearRedo = true) {
    const snapshot = JSON.stringify(serializeLayout());
    if (historyStack[historyStack.length - 1] === snapshot) {
        return;
    }
    historyStack.push(snapshot);
    if (historyStack.length > 60) {
        historyStack.shift();
    }
    if (clearRedo) {
        redoStack = [];
    }
}

function undo() {
    if (historyStack.length <= 1) {
        return;
    }
    const current = historyStack.pop();
    redoStack.push(current);
    restoreLayout(JSON.parse(historyStack[historyStack.length - 1]), false);
}

function redo() {
    if (!redoStack.length) {
        return;
    }
    const snapshot = redoStack.pop();
    historyStack.push(snapshot);
    restoreLayout(JSON.parse(snapshot), false);
}

function duplicateSelected() {
    if (!selectedItem) {
        return;
    }

    const payload = serializeLayout().elements.find((item) => item.id === selectedItem.dataset.id);
    if (!payload) {
        return;
    }

    payload.id = uid();
    payload.x = clamp(payload.x + 25, 0, getCanvasWidth() - payload.width);
    payload.y = clamp(payload.y + 25, 0, getCanvasHeight() - payload.height);
    payload.zIndex = ++highestZ;
    const clone = createItem(payload, true);
    closeContextMenu();
    selectItem(clone);
}

function deleteSelected() {
    const targets = getSelectedItems();
    if (!targets.length) {
        return;
    }
    targets.forEach((item) => item.remove());
    clearSelection();
    saveHistory();
}

function syncInspector() {
    if (!selectedItem) {
        return;
    }

    const styles = getItemStyles(selectedItem);
    const content = selectedItem.querySelector('.item-content');
    const type = selectedItem.dataset.type;

    textFields.classList.toggle('hidden', type !== 'text');
    shapeFields.classList.toggle('hidden', type === 'text');
    imageFields.classList.toggle('hidden', type !== 'image');

    if (type === 'text') {
        textContent.value = content.innerText.replace(/\u00a0/g, ' ');
        fontFamily.value = styles.fontFamily || "'Poppins', sans-serif";
        fontSize.value = parseInt(styles.fontSize, 10) || 18;
        fontWeight.value = styles.fontWeight || '400';
        textColor.value = toHexColor(styles.color || '#001a47');
        alignButtons.forEach((btn) => btn.classList.toggle('active', btn.dataset.align === (styles.textAlign || 'center')));
    }

    if (type !== 'text') {
        fillColor.value = toHexColor(styles.backgroundColor || '#d4af37');
        borderColor.value = toHexColor(styles.borderColor || '#d4af37');
        borderWidth.value = parseInt(styles.borderWidth, 10) || 0;
        cornerRadius.value = parseInt(styles.borderRadius, 10) || 0;
        fillColor.disabled = type === 'image' || selectedItem.dataset.lockedFill === '1';
    }

    if (type === 'image') {
        borderColor.value = toHexColor(styles.borderColor || '#001a47');
        borderWidth.value = parseInt(styles.borderWidth, 10) || 0;
        cornerRadius.value = parseInt(styles.borderRadius, 10) || 14;
        const cropPosition = parseObjectPosition(styles.objectPosition);
        imageFit.value = styles.objectFit || 'contain';
        imageCropX.value = cropPosition.x;
        imageCropY.value = cropPosition.y;
    }

    if (type === 'text') {
        fillColor.disabled = true;
    }

    opacityRange.value = parseFloat(selectedItem.style.opacity || 1);
    opacityValue.textContent = Number(opacityRange.value).toFixed(2);
    rotationRange.value = parseInt(selectedItem.dataset.rotation || 0, 10);
    rotationValue.textContent = `${rotationRange.value} deg`;
    posX.value = parseInt(selectedItem.style.left || 0, 10);
    posY.value = parseInt(selectedItem.style.top || 0, 10);
    boxWidth.value = parseInt(selectedItem.style.width || 0, 10);
    boxHeight.value = parseInt(selectedItem.style.height || 0, 10);
}

function toHexColor(value) {
    const input = document.createElement('canvas').getContext('2d');
    input.fillStyle = value || '#000000';
    return input.fillStyle;
}

function parseObjectPosition(value) {
    const parts = String(value || '50% 50%').match(/(\d+(?:\.\d+)?)%/g) || ['50%', '50%'];
    return {
        x: parseFloat(parts[0]) || 50,
        y: parseFloat(parts[1] || parts[0]) || 50
    };
}

function updateSelectedText() {
    if (!selectedItem || selectedItem.dataset.type !== 'text') {
        return;
    }

    selectedItem.querySelector('.item-content').innerHTML = textContent.value.replace(/\n/g, '<br>');
}

function updateSelectedStyles() {
    if (!selectedItem) {
        return;
    }

    const content = selectedItem.querySelector('.item-content');
    const type = selectedItem.dataset.type;

    if (type === 'text') {
        content.style.fontFamily = fontFamily.value;
        content.style.fontSize = `${fontSize.value}px`;
        content.style.fontWeight = fontWeight.value;
        content.style.color = textColor.value;
    }

    if (type === 'shape') {
        content.style.backgroundColor = selectedItem.dataset.lockedFill === '1' ? 'transparent' : fillColor.value;
        content.style.borderColor = borderColor.value;
        content.style.borderWidth = `${borderWidth.value}px`;
        content.style.borderRadius = `${cornerRadius.value}px`;
    }

    if (type === 'image') {
        const img = content.querySelector('img');
        selectedItem.style.border = `${borderWidth.value}px solid ${borderColor.value}`;
        selectedItem.style.borderRadius = `${cornerRadius.value}px`;
        if (img) {
            img.style.objectFit = imageFit.value;
            img.style.objectPosition = `${imageCropX.value}% ${imageCropY.value}%`;
        }
    }

    selectedItem.style.opacity = opacityRange.value;
    if (!isLocked(selectedItem)) {
        setRotation(selectedItem, rotationRange.value);
        const width = clamp(Number(boxWidth.value || 50), 30, getMaximumItemWidth(selectedItem));
        const height = clamp(Number(boxHeight.value || getMinimumItemHeight(selectedItem)), getMinimumItemHeight(selectedItem), getMaximumItemHeight(selectedItem));
        selectedItem.style.width = `${width}px`;
        selectedItem.style.height = `${height}px`;
        selectedItem.style.left = `${clampItemLeft(selectedItem, Number(posX.value || 0), width)}px`;
        selectedItem.style.top = `${clampItemTop(selectedItem, Number(posY.value || 0), height)}px`;
    }
    opacityValue.textContent = Number(opacityRange.value).toFixed(2);
    rotationValue.textContent = `${rotationRange.value} deg`;
}

function bringToFront() {
    const targets = getSelectedItems();
    if (!targets.length) return;
    targets.forEach((item) => {
        item.style.zIndex = ++highestZ;
    });
    closeContextMenu();
    saveHistory();
}

function bringForward() {
    const targets = getSelectedItems();
    if (!targets.length) return;
    targets.forEach((item) => {
        item.style.zIndex = Number(item.style.zIndex || 1) + 1;
        highestZ = Math.max(highestZ, Number(item.style.zIndex));
    });
    closeContextMenu();
    saveHistory();
}

function sendBackward() {
    const targets = getSelectedItems();
    if (!targets.length) return;
    targets.forEach((item) => {
        item.style.zIndex = Math.max(1, Number(item.style.zIndex || 1) - 1);
    });
    closeContextMenu();
    saveHistory();
}

function sendToBack() {
    const targets = getSelectedItems();
    if (!targets.length) return;
    targets.forEach((item) => {
        item.style.zIndex = 1;
    });
    closeContextMenu();
    saveHistory();
}

function setSelectedLocked(locked) {
    const targets = getSelectedItems();
    if (!targets.length) {
        return;
    }

    targets.forEach((item) => {
        item.dataset.locked = locked ? '1' : '0';
        item.classList.toggle('locked', locked);
    });
    closeContextMenu();
    refreshSelectionState();
    saveHistory();
}

function addPreset(name) {
    if (name === 'image') {
        pendingImageMode = 'element';
        imageInput.click();
        return;
    }

    const item = createItem(deepClone(presets[name]), true);
    selectItem(item);
}

function saveTemplate() {
    const payload = JSON.stringify(serializeLayout());
    document.body.classList.add('exporting');

    html2canvas(canvas, { backgroundColor: null }).then((result) => {
        const params = new URLSearchParams();
        params.set('save_layout', '1');
        params.set('layout', payload);
        params.set('image', result.toDataURL('image/png'));
        params.set('template_name', templateNameInput.value.trim());

        return fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        });
    }).then((response) => response.json())
      .then(() => window.location.reload())
      .finally(() => document.body.classList.remove('exporting'));
}

function getExportFilename(extension) {
    const baseName = templateNameInput.value.trim()
        .replace(/[^a-zA-Z0-9_-]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'certificate-design';

    return `${baseName}.${extension}`;
}

function renderCanvasForExport() {
    document.body.classList.add('exporting');

    return html2canvas(canvas, {
        backgroundColor: null,
        scale: 2
    }).finally(() => document.body.classList.remove('exporting'));
}

function downloadPNG() {
    renderCanvasForExport().then((result) => {
        const link = document.createElement('a');
        link.href = result.toDataURL('image/png');
        link.download = getExportFilename('png');
        link.click();
    });
}

function downloadPDF() {
    if (!window.jspdf?.jsPDF) {
        alert('PDF export is still loading. Please try again in a moment.');
        return;
    }

    renderCanvasForExport().then((result) => {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
            orientation: 'landscape',
            unit: 'px',
            format: [BASE_CANVAS_WIDTH, BASE_CANVAS_HEIGHT]
        });

        pdf.addImage(
            result.toDataURL('image/png'),
            'PNG',
            0,
            0,
            BASE_CANVAS_WIDTH,
            BASE_CANVAS_HEIGHT
        );
        pdf.save(getExportFilename('pdf'));
    });
}

function setDownloadMenuOpen(open) {
    downloadMenu.hidden = !open;
    downloadBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function toggleDownloadMenu() {
    setDownloadMenuOpen(downloadMenu.hidden);
}

function loadTemplate(name) {
    fetch(`?load=${encodeURIComponent(name)}`)
        .then((response) => response.json())
        .then((layout) => restoreLayout(layout, true));
}

document.querySelectorAll('[data-add]').forEach((button) => {
    button.addEventListener('click', () => addPreset(button.dataset.add));
});

document.querySelectorAll('[data-template]').forEach((button) => {
    button.addEventListener('click', () => loadTemplate(button.dataset.template));
});

document.getElementById('undoBtn').addEventListener('click', undo);
document.getElementById('redoBtn').addEventListener('click', redo);
document.getElementById('duplicateBtn').addEventListener('click', duplicateSelected);
document.getElementById('deleteBtn').addEventListener('click', deleteSelected);
document.getElementById('lockBtn').addEventListener('click', () => setSelectedLocked(true));
document.getElementById('unlockBtn').addEventListener('click', () => setSelectedLocked(false));
document.getElementById('saveBtn').addEventListener('click', saveTemplate);
downloadBtn.addEventListener('click', toggleDownloadMenu);
downloadMenu.addEventListener('click', (event) => {
    const format = event.target.closest('[data-download-format]')?.dataset.downloadFormat;
    if (!format) {
        return;
    }

    setDownloadMenuOpen(false);

    if (format === 'pdf') {
        downloadPDF();
    }

    if (format === 'png') {
        downloadPNG();
    }
});
document.getElementById('zoomInBtn').addEventListener('click', zoomIn);
document.getElementById('zoomOutBtn').addEventListener('click', zoomOut);
document.getElementById('zoomFitBtn').addEventListener('click', zoomToFit);
hideLeftPanelBtn.addEventListener('click', () => setLeftPanelHidden(true));
showLeftPanelBtn.addEventListener('click', () => setLeftPanelHidden(false));
document.getElementById('bringFrontBtn').addEventListener('click', bringToFront);
document.getElementById('bringForwardBtn').addEventListener('click', bringForward);
document.getElementById('sendBackwardBtn').addEventListener('click', sendBackward);
contextMenu.addEventListener('click', (event) => {
    const action = event.target.closest('[data-menu-action]')?.dataset.menuAction;
    if (!action) {
        return;
    }

    if (action === 'bring-front') bringToFront();
    if (action === 'bring-forward') bringForward();
    if (action === 'send-backward') sendBackward();
    if (action === 'send-back') sendToBack();
    if (action === 'lock') setSelectedLocked(true);
    if (action === 'unlock') setSelectedLocked(false);
    if (action === 'duplicate') duplicateSelected();
    if (action === 'delete') deleteSelected();
});
document.getElementById('replaceImageBtn').addEventListener('click', () => {
    if (selectedItem?.dataset.type !== 'image') {
        return;
    }
    pendingImageMode = 'replace';
    imageInput.click();
});

canvas.addEventListener('mousedown', (event) => {
    closeContextMenu();
    if (event.target === canvas) {
        clearSelection();
    }
});

boardStage.addEventListener('wheel', (event) => {
    if (!event.ctrlKey) {
        return;
    }

    event.preventDefault();

    if (event.deltaY < 0) {
        zoomIn();
    } else {
        zoomOut();
    }
}, { passive: false });

canvas.addEventListener('contextmenu', (event) => {
    if (event.target === canvas) {
        event.preventDefault();
        clearSelection();
    }
});

document.addEventListener('mousedown', (event) => {
    if (!event.target.closest('#contextMenu') && !event.target.closest('.design-item')) {
        closeContextMenu();
    }

    if (!event.target.closest('.download-menu-wrap')) {
        setDownloadMenuOpen(false);
    }
});

document.addEventListener('scroll', closeContextMenu, true);
window.addEventListener('resize', closeContextMenu);
window.addEventListener('resize', applyCanvasScale);

canvasColor.addEventListener('input', () => {
    canvas.style.backgroundColor = canvasColor.value;
    saveHistory();
});

document.getElementById('backgroundImageBtn').addEventListener('click', () => {
    pendingImageMode = 'background';
    backgroundInput.click();
});

document.getElementById('clearBackgroundBtn').addEventListener('click', () => {
    canvas.dataset.bgImage = '';
    canvas.style.backgroundImage = 'none';
    saveHistory();
});

imageInput.addEventListener('change', (event) => {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    readFileAsDataURL(file, (dataUrl) => {
        if (pendingImageMode === 'replace' && selectedItem?.dataset.type === 'image') {
            selectedItem.querySelector('img').src = dataUrl;
            saveHistory();
        } else {
            const payload = deepClone(presets.image);
            payload.src = dataUrl;
            const item = createItem(payload, true);
            selectItem(item);
        }
    });

    event.target.value = '';
});

backgroundInput.addEventListener('change', (event) => {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    readFileAsDataURL(file, (dataUrl) => {
        canvas.dataset.bgImage = dataUrl;
        canvas.style.backgroundImage = `url(${dataUrl})`;
        saveHistory();
    });

    event.target.value = '';
});

textContent.addEventListener('input', updateSelectedText);
textContent.addEventListener('change', saveHistory);
fontFamily.addEventListener('change', () => { updateSelectedStyles(); saveHistory(); });
fontSize.addEventListener('input', updateSelectedStyles);
fontSize.addEventListener('change', saveHistory);
fontWeight.addEventListener('change', () => { updateSelectedStyles(); saveHistory(); });
textColor.addEventListener('input', updateSelectedStyles);
textColor.addEventListener('change', saveHistory);
fillColor.addEventListener('input', updateSelectedStyles);
fillColor.addEventListener('change', saveHistory);
borderColor.addEventListener('input', updateSelectedStyles);
borderColor.addEventListener('change', saveHistory);
borderWidth.addEventListener('input', updateSelectedStyles);
borderWidth.addEventListener('change', saveHistory);
cornerRadius.addEventListener('input', updateSelectedStyles);
cornerRadius.addEventListener('change', saveHistory);
imageFit.addEventListener('change', () => { updateSelectedStyles(); saveHistory(); });
imageCropX.addEventListener('input', updateSelectedStyles);
imageCropX.addEventListener('change', saveHistory);
imageCropY.addEventListener('input', updateSelectedStyles);
imageCropY.addEventListener('change', saveHistory);
opacityRange.addEventListener('input', updateSelectedStyles);
opacityRange.addEventListener('change', saveHistory);
rotationRange.addEventListener('input', updateSelectedStyles);
rotationRange.addEventListener('change', saveHistory);
posX.addEventListener('input', updateSelectedStyles);
posX.addEventListener('change', saveHistory);
posY.addEventListener('input', updateSelectedStyles);
posY.addEventListener('change', saveHistory);
boxWidth.addEventListener('input', updateSelectedStyles);
boxWidth.addEventListener('change', saveHistory);
boxHeight.addEventListener('input', updateSelectedStyles);
boxHeight.addEventListener('change', saveHistory);

alignButtons.forEach((button) => {
    button.addEventListener('click', () => {
        if (!selectedItem || selectedItem.dataset.type !== 'text') {
            return;
        }
        selectedItem.querySelector('.item-content').style.textAlign = button.dataset.align;
        syncInspector();
        saveHistory();
    });
});

document.addEventListener('keydown', (event) => {
    const activeTag = document.activeElement?.tagName;
    const editingField = ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeTag) || document.activeElement?.isContentEditable;

    if (event.key === 'Escape' && !downloadMenu.hidden) {
        setDownloadMenuOpen(false);
        return;
    }

    if (event.ctrlKey && event.key.toLowerCase() === 's') {
        event.preventDefault();
        saveTemplate();
        return;
    }

    if (event.ctrlKey && event.key.toLowerCase() === 'a' && !editingField) {
        event.preventDefault();
        selectAllItems();
        return;
    }

    if (event.ctrlKey && (event.key === '+' || event.key === '=')) {
        event.preventDefault();
        zoomIn();
        return;
    }

    if (event.ctrlKey && event.key === '-') {
        event.preventDefault();
        zoomOut();
        return;
    }

    if (event.ctrlKey && event.key === '0') {
        event.preventDefault();
        zoomToFit();
        return;
    }

    if (event.ctrlKey && event.key.toLowerCase() === 'z') {
        event.preventDefault();
        undo();
        return;
    }

    if (event.ctrlKey && event.key.toLowerCase() === 'y') {
        event.preventDefault();
        redo();
        return;
    }

    if (event.ctrlKey && event.shiftKey && event.key.toLowerCase() === 'z') {
        event.preventDefault();
        redo();
        return;
    }

    if (event.ctrlKey && event.key.toLowerCase() === 'd' && selectedItem) {
        event.preventDefault();
        duplicateSelected();
        return;
    }

    if (editingField || !selectedItem) {
        return;
    }

    if (event.key === 'Delete') {
        deleteSelected();
        return;
    }

    if (event.key === 'Escape') {
        closeContextMenu();
        return;
    }

    const moveTargets = getEditableSelection();
    if (!moveTargets.length) {
        return;
    }

    const step = event.shiftKey ? 10 : 1;
    let moved = false;
    let dx = 0;
    let dy = 0;

    if (event.key === 'ArrowLeft') {
        dx = -step;
        moved = true;
    }
    if (event.key === 'ArrowRight') {
        dx = step;
        moved = true;
    }
    if (event.key === 'ArrowUp') {
        dy = -step;
        moved = true;
    }
    if (event.key === 'ArrowDown') {
        dy = step;
        moved = true;
    }

    if (moved) {
        event.preventDefault();
        moveTargets.forEach((item) => {
            const left = parseInt(item.style.left || 0, 10) + dx;
            const top = parseInt(item.style.top || 0, 10) + dy;
            item.style.left = `${clampItemLeft(item, left)}px`;
            item.style.top = `${clampItemTop(item, top)}px`;
        });
        syncInspector();
        saveHistory();
    }
});

restoreLayout({
    board: {
        backgroundColor: '#ffffff',
        backgroundImage: ''
    },
    elements: [
        deepClone(presets.border),
        deepClone(presets.innerBorder),
        deepClone(presets.logoDti),
        deepClone(presets.logoNegosyo),
        deepClone(presets.title),
        deepClone(presets.subtitle),
        deepClone(presets.awardText),
        deepClone(presets.recipient),
        deepClone(presets.body),
        deepClone(presets.signature),
        deepClone(presets.signatoryName),
        deepClone(presets.signatoryPosition),
        {
            ...deepClone(presets.line),
            x: 270,
            y: 390,
            width: 584,
            height: 1,
            zIndex: 16,
            styles: {
                ...deepClone(presets.line).styles,
                backgroundColor: '#000000',
                borderColor: '#000000'
            }
        }
    ]
}, false);

saveHistory();
setLeftPanelHidden(localStorage.getItem(LEFT_PANEL_STORAGE_KEY) === '1');
setInspectorHidden(true);
applyCanvasScale();
window.addEventListener('load', applyCanvasScale);
if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(applyCanvasScale);
}
</script>
</body>
</html>
