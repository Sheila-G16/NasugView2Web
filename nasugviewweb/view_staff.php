<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$loggedInUserId = (int) $_SESSION['user_id'];
$centerStmt = $conn->prepare("SELECT negosyocenter FROM negosyo_center_users WHERE id=? LIMIT 1");
$centerStmt->bind_param("i", $loggedInUserId);
$centerStmt->execute();
$currentCenter = trim((string) ($centerStmt->get_result()->fetch_assoc()['negosyocenter'] ?? ''));
$centerStmt->close();

$staffStmt = $conn->prepare("SELECT * FROM negosyo_center_users WHERE designation='Staff' AND negosyocenter=? ORDER BY id DESC");
$staffStmt->bind_param("s", $currentCenter);
$staffStmt->execute();
$staff = $staffStmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Staff List</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="../bootstrap5/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

<style>
body{
    font-family:'Poppins',sans-serif;
    background:#f0f4ff;
}

/* SAME CARD STYLE AS EVENTS */
.card{
    border-radius:10px;
    padding:2rem;
    background:#fff;
    border-left:6px solid #001a47;
    box-shadow:0 8px 25px rgba(0,0,0,0.08);
}

/* Table styles */
.table-responsive{
    border:1px solid rgba(0,26,71,.08);
    border-radius:10px;
    overflow:hidden;
    background:#fff;
}

.table{
    border-collapse:collapse;
}

.table th,
.table td{
    border:1px solid rgba(15,23,42,.08);
    padding:.62rem .75rem;
    font-size:.88rem;
    vertical-align:top;
}

.table th{
    background:linear-gradient(135deg,#123c73,#1d5ea8);
    color:white;
    font-weight:600;
    line-height:1.25;
    white-space:normal;
}

.table tbody tr:nth-child(even) td{
    background:#f8fafc;
}

/* SAME ACTION BUTTONS */
.action-buttons{
    display:flex;
    gap:0.5rem;
}

.btn-action{
    width:36px;
    height:36px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:none;
    background: linear-gradient(135deg,#001a47,#00308a);
    color:white;
    transition:.3s;
}

.btn-action:hover{
    transform:translateY(-2px);
    box-shadow:0 4px 12px rgba(0,0,0,.2);
}

#searchInput{
    margin-bottom:1rem;
}

@media (max-width:768px){
    .container{
        max-width:100%;
        margin-top:1rem !important;
        padding-left:.75rem;
        padding-right:.75rem;
    }

    .card{
        padding:1.25rem;
        border-radius:10px;
    }

    .card > .d-flex{
        flex-direction:column;
        gap:.75rem;
    }

    #searchInput{
        width:100% !important;
        margin-bottom:0;
    }

    .table-responsive{
        border:0;
        border-radius:0;
        overflow:visible;
        background:transparent;
    }

    #staffTable thead{
        display:none;
    }

    #staffTable,
    #staffTable tbody,
    #staffTable tr,
    #staffTable td{
        display:block;
        width:100%;
    }

    #staffTable tr{
        margin-bottom:1rem;
        border:1px solid #e5e7eb;
        border-radius:8px;
        overflow:hidden;
        background:#fff;
    }

    #staffTable td{
        display:flex;
        justify-content:space-between;
        gap:1rem;
        padding:.8rem 1rem;
        text-align:right;
        border-bottom:1px solid #f1f3f4;
        overflow-wrap:anywhere;
    }

    #staffTable td:last-child{
        border-bottom:0;
    }

    #staffTable td::before{
        content:"";
        color:#001a47;
        font-weight:700;
        text-align:left;
        flex:0 0 42%;
    }

    #staffTable td:nth-child(1)::before{ content:"ID"; }
    #staffTable td:nth-child(2)::before{ content:"Name"; }
    #staffTable td:nth-child(3)::before{ content:"Username"; }
    #staffTable td:nth-child(4)::before{ content:"Actions"; }

    .action-buttons{
        justify-content:flex-end;
    }
}
</style>
</head>

<body>

<div class="container mt-4">

<!-- CARD wrapper (IMPORTANT) -->
<div class="card">

    <div class="d-flex justify-content-between mb-3">
        <h4 class="mb-0">Staff Directory</h4>
        <input type="text" id="searchInput" class="form-control w-25" placeholder="Search...">
    </div>

    <div class="table-responsive">
    <table class="table table-hover mb-0" id="staffTable">

        <thead>
        <tr>
            <th width="80">ID</th>
            <th>Name</th>
            <th>Username</th>
            <th width="140">Actions</th>
        </tr>
        </thead>

        <tbody>
        <?php while($s=$staff->fetch_assoc()): ?>
        <tr>
            <td><?= $s['id'] ?></td>
            <td><?= $s['fname']." ".$s['lname'] ?></td>
            <td><?= $s['username'] ?></td>
            <td>
                <div class="action-buttons">
                    <a href="view_staff.php?id=<?= $s['id'] ?>" class="btn-action">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="edit_staff.php?id=<?= $s['id'] ?>" class="btn-action">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="delete_staff.php?id=<?= $s['id'] ?>" class="btn-action">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>

    </table>
    </div>

</div>
</div>


<script>
const rows = document.querySelectorAll('#staffTable tbody tr');
document.getElementById('searchInput').addEventListener('input', function(){
    const val=this.value.toLowerCase();
    rows.forEach(r=>{
        r.style.display=r.textContent.toLowerCase().includes(val)?'':'none';
    });
});
</script>

</body>
</html>
