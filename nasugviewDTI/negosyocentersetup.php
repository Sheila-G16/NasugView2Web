<?php
session_start();

require_once __DIR__ . "/db.php";

// ==============================
// Fetch admin info
// ==============================
if(isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT username, fname, lname, designation FROM dti_user WHERE dti_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()){
        $admin_fullname = trim($row['fname'] . ' ' . $row['lname']);
        $designation    = $row['designation'];
    } else {
        $admin_fullname = "User";
        $designation    = "Unknown";
    }
} else {
    $admin_fullname = "User";
    $designation    = "Unknown";
}

// ==============================
// Fetch Negosyo Centers
// ==============================
$sql = "SELECT * FROM negosyo_centers ORDER BY branch_name ASC";
$centers = $conn->query($sql);

// ==============================
// Statistics
// ==============================
$totalCenters = $conn->query("SELECT COUNT(*) as total FROM negosyo_centers")->fetch_assoc()['total'] ?? 0;
$batangasCenters = $conn->query("SELECT COUNT(*) as total FROM negosyo_centers WHERE province='Batangas'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Negosyo Center Setup - NasugView</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<style>
body{
    font-family:'Poppins', sans-serif;
    background:#f0f4ff;
}

.main-content{
    margin-left:250px;
    padding:2rem;
    min-height:100vh;
}

.card{
    border-radius:10px;
    padding:2rem;
    background:#fff;
    border-left:6px solid #001a47;
    box-shadow:0 8px 25px rgba(0,0,0,0.08);
}

.card h3{
    color:#001a47;
    font-weight:700;
    margin-bottom:1.5rem;
}

.table th{
    background: linear-gradient(135deg,#001a47,#00308a);
    color:white;
    border:none;
    padding:1rem;
    font-weight:600;
    font-size:0.9rem;
}

.table td{
    padding:1rem;
    vertical-align:middle;
    border-bottom:1px solid #f1f3f4;
}

.btn-submit{
    background-color:#001a47;
    color:#fff;
    border-radius:10px;
    padding:10px 24px;
    font-weight:600;
    border:none;
}

.btn-submit:hover{
    background-color:#00308a;
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
}

.action-buttons{
    display:flex;
    gap:0.5rem;
    align-items:center;
}

.pagination button {
    background-color: #fff;
    border: 1px solid #00308a;
    color: #00308a;
    border-radius: 6px;
    padding: 5px 10px;
    margin: 0 2px;
    cursor: pointer;
    font-size: 0.875rem;
}

.pagination button.active {
    background-color: #00308a;
    color: #fff;
    font-weight: 600;
}

#results-info {
    color:#6c757d;
    font-weight:400;
    font-size:0.875rem;
}

@media (max-width:992px){
    .main-content{
        margin-left:0;
        padding:5rem 1rem 2rem;
    }
}

@media (max-width:768px){
    .card{
        padding:1.25rem;
        border-radius:10px;
    }

    .row.mb-4{
        row-gap:1rem;
    }

    .card > .d-flex{
        flex-direction:column;
        gap:.75rem;
    }

    #searchInput,
    .btn-submit{
        width:100%;
        margin-left:0 !important;
    }

    #centerTable thead{
        display:none;
    }

    #centerTable,
    #centerTable tbody,
    #centerTable tr,
    #centerTable td{
        display:block;
        width:100%;
    }

    #centerTable tr{
        margin-bottom:1rem;
        border:1px solid #e5e7eb;
        border-radius:8px;
        overflow:hidden;
        background:#fff;
    }

    #centerTable td{
        display:flex;
        justify-content:space-between;
        gap:1rem;
        padding:.8rem 1rem;
        text-align:right;
        border-bottom:1px solid #f1f3f4;
        overflow-wrap:anywhere;
    }

    #centerTable td:last-child{
        border-bottom:0;
    }

    #centerTable td::before{
        content:"";
        color:#001a47;
        font-weight:700;
        text-align:left;
        flex:0 0 42%;
    }

    #centerTable td:nth-child(1)::before{ content:"Branch Name"; }
    #centerTable td:nth-child(2)::before{ content:"Municipality"; }
    #centerTable td:nth-child(3)::before{ content:"Province"; }
    #centerTable td:nth-child(4)::before{ content:"Address"; }
    #centerTable td:nth-child(5)::before{ content:"Contact"; }
    #centerTable td:nth-child(6)::before{ content:"Email"; }
    #centerTable td:nth-child(7)::before{ content:"Officer"; }
    #centerTable td:nth-child(8)::before{ content:"Actions"; }

    .action-buttons{
        justify-content:flex-end;
    }

    #pagination-container{
        flex-direction:column;
        align-items:stretch !important;
    }
}

@media (max-width:576px){
    .main-content{
        padding-left:.75rem;
        padding-right:.75rem;
    }
}
</style>
</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

<!-- Statistics -->
<div class="row mb-4">

<div class="col-md-4">
<div class="card text-center">
<h4>Total Negosyo Centers</h4>
<h2><?php echo $totalCenters; ?></h2>
</div>
</div>

<div class="col-md-4">
<div class="card text-center">
<h4>Batangas Centers</h4>
<h2><?php echo $batangasCenters; ?></h2>
</div>
</div>

</div>

<!-- Table -->
<div class="card">

<div class="d-flex justify-content-between mb-3">
<input type="text" id="searchInput" class="form-control" placeholder="Search branch...">
<a href="create_center.php" class="btn btn-submit ms-2">
<i class="fas fa-plus"></i> Add Negosyo Center
</a>
</div>

<table class="table table-hover" id="centerTable">
<thead>
<tr>
<th>Branch Name</th>
<th>Municipality</th>
<th>Province</th>
<th>Address</th>
<th>Contact</th>
<th>Email</th>
<th>Officer In Charge</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php while($center = $centers->fetch_assoc()): ?>
<tr>
<td><?php echo htmlspecialchars($center['branch_name']); ?></td>
<td><?php echo htmlspecialchars($center['municipality']); ?></td>
<td><?php echo htmlspecialchars($center['province']); ?></td>
<td><?php echo htmlspecialchars($center['address']); ?></td>
<td><?php echo htmlspecialchars($center['contact_number']); ?></td>
<td><?php echo htmlspecialchars($center['email']); ?></td>
<td><?php echo htmlspecialchars($center['officer_in_charge']); ?></td>
<td>
<div class="action-buttons">
<button class="btn-action viewBtn"
data-branch="<?php echo htmlspecialchars($center['branch_name']); ?>"
data-municipality="<?php echo htmlspecialchars($center['municipality']); ?>"
data-province="<?php echo htmlspecialchars($center['province']); ?>"
data-address="<?php echo htmlspecialchars($center['address']); ?>"
data-contact="<?php echo htmlspecialchars($center['contact_number']); ?>"
data-email="<?php echo htmlspecialchars($center['email']); ?>"
data-officer="<?php echo htmlspecialchars($center['officer_in_charge']); ?>"
title="View Details">
<i class="fas fa-eye"></i>
</button>

<div class="dropdown">
<button class="btn-action" data-bs-toggle="dropdown">
<i class="fas fa-ellipsis-h"></i>
</button>
<ul class="dropdown-menu dropdown-menu-end">
<li><a class="dropdown-item" href="edit_center.php?id=<?php echo $center['id']; ?>">Edit</a></li>
<li><a class="dropdown-item text-danger deleteCenter" href="#" data-center-id="<?php echo $center['id']; ?>">Delete</a></li>
</ul>
</div>
</div>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

<!-- Pagination & results info -->
<div id="pagination-container" style="display:flex; justify-content:flex-end; align-items:center; margin-top:15px; gap:10px;">
    <div id="results-info"></div>
    <div id="pagination"></div>
</div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('searchInput').addEventListener('keyup',function(){
    let filter=this.value.toLowerCase();
    let rows=document.querySelectorAll('#centerTable tbody tr');
    rows.forEach(row=>{
        row.style.display=row.textContent.toLowerCase().includes(filter)?'':'none';
    });
});

document.querySelectorAll('.deleteCenter').forEach(btn=>{
    btn.addEventListener('click',function(e){
        e.preventDefault();
        let id=this.dataset.centerId;
        Swal.fire({
            title:'Delete Center?',
            text:'This action is permanent',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Delete'
        }).then(result=>{
            if(result.isConfirmed){
                fetch('delete_center.php',{
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({id:id})
                })
                .then(res=>res.json())
                .then(data=>{
                    if(data.success){
                        Swal.fire('Deleted!','Center removed','success').then(()=>location.reload());
                    }else{
                        Swal.fire('Error',data.error,'error');
                    }
                });
            }
        });
    });
});

const rowsPerPage = 5;
const table = document.getElementById('centerTable');
const tbody = table.querySelector('tbody');
let rows = Array.from(tbody.querySelectorAll('tr'));
let currentPage = 1;
const pagination = document.getElementById('pagination');
const resultsInfo = document.getElementById('results-info');

function renderTablePage(page){
    const totalRows = rows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    const start = (page-1)*rowsPerPage;
    const end = start + rowsPerPage;
    rows.forEach((row,index)=>{
        row.style.display = (index >= start && index < end)?'':'none';
    });

    pagination.innerHTML='';
    for(let i=1;i<=totalPages;i++){
        const btn = document.createElement('button');
        btn.textContent=i;
        btn.className = i===page?'active':'';
        btn.addEventListener('click',()=>{ currentPage=i; renderTablePage(i); });
        pagination.appendChild(btn);
    }

    resultsInfo.textContent = `Showing ${Math.min(start+1,totalRows)} to ${Math.min(end,totalRows)} of ${totalRows} results`;
}

renderTablePage(currentPage);
</script>

</body>
</html>
