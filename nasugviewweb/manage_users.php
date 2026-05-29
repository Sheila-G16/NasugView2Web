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

if(isset($_GET['delete'])){
    $id=intval($_GET['delete']);
    $deleteStmt = $conn->prepare("DELETE FROM negosyo_center_users WHERE id=? AND negosyocenter=?");
    $deleteStmt->bind_param("is", $id, $currentCenter);
    $deleteStmt->execute();
    header("Location: manage_users.php");
    exit();
}

$usersStmt = $conn->prepare("SELECT * FROM negosyo_center_users WHERE negosyocenter=? ORDER BY id DESC");
$usersStmt->bind_param("s", $currentCenter);
$usersStmt->execute();
$users = $usersStmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="../bootstrap5/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

<style>
body{
    font-family:'Poppins', sans-serif;
    background:#f0f4ff;
}

/* Card wrapper */
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

/* Action buttons like events */
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
    transition: all 0.3s ease;
}

.btn-action:hover{
    transform:translateY(-2px);
    box-shadow:0 4px 12px rgba(0,0,0,.2);
}

/* Search input */
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

    #usersTable thead{
        display:none;
    }

    #usersTable,
    #usersTable tbody,
    #usersTable tr,
    #usersTable td{
        display:block;
        width:100%;
    }

    #usersTable tr{
        margin-bottom:1rem;
        border:1px solid #e5e7eb;
        border-radius:8px;
        overflow:hidden;
        background:#fff;
    }

    #usersTable td{
        display:flex;
        justify-content:space-between;
        gap:1rem;
        padding:.8rem 1rem;
        text-align:right;
        border-bottom:1px solid #f1f3f4;
        overflow-wrap:anywhere;
    }

    #usersTable td:last-child{
        border-bottom:0;
    }

    #usersTable td::before{
        content:"";
        color:#001a47;
        font-weight:700;
        text-align:left;
        flex:0 0 42%;
    }

    #usersTable td:nth-child(1)::before{ content:"ID"; }
    #usersTable td:nth-child(2)::before{ content:"Name"; }
    #usersTable td:nth-child(3)::before{ content:"Username"; }
    #usersTable td:nth-child(4)::before{ content:"Role"; }
    #usersTable td:nth-child(5)::before{ content:"Actions"; }

    .action-buttons{
        justify-content:flex-end;
    }
}
</style>
</head>

<body>

<div class="container mt-4">

<div class="card">

    <div class="d-flex justify-content-between mb-3">
        <h4 class="mb-0"><i class="fas fa-users me-2"></i>Manage Users</h4>
        <input type="text" id="searchInput" class="form-control w-25" placeholder="Search users...">
    </div>

    <div class="table-responsive">
    <table class="table table-hover mb-0" id="usersTable">
        <thead>
        <tr>
            <th width="60">ID</th>
            <th>Name</th>
            <th>Username</th>
            <th width="120">Role</th>
            <th width="140">Actions</th>
        </tr>
        </thead>

        <tbody>
        <?php while($u=$users->fetch_assoc()): ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><?= $u['fname']." ".$u['lname'] ?></td>
            <td><?= $u['username'] ?></td>
            <td><span class="badge bg-primary"><?= $u['designation'] ?></span></td>
            <td>
                <div class="action-buttons">
                    <a href="view_user.php?id=<?= $u['id'] ?>" class="btn-action" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn-action" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?= $u['id'] ?>" 
                       onclick="return confirm('Delete this user?')" 
                       class="btn-action" title="Delete">
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
const rows = document.querySelectorAll('#usersTable tbody tr');
document.getElementById('searchInput').addEventListener('input', function(){
    const val=this.value.toLowerCase();
    rows.forEach(r=>{
        r.style.display=r.textContent.toLowerCase().includes(val)?'':'none';
    });
});
</script>

</body>
</html>
