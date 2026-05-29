<?php
session_start();
require 'db.php';

// FETCH USERS
$users = $conn->query("
SELECT *
FROM negosyo_center_users
ORDER BY id DESC
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management - NasugView</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<style>
body{font-family:Poppins;background:#f0f4ff;}
.main-content{margin-left:250px;padding:2rem;}
.card{border-left:6px solid #001a47;border-radius:10px;padding:2rem;background:#fff;box-shadow:0 8px 25px rgba(0,0,0,0.08);}
.table th{background:linear-gradient(135deg,#001a47,#00308a);color:white;}
.btn-submit{background:#001a47;color:#fff;border-radius:10px;padding:8px 20px;border:none;}
.btn-submit:hover{background:#00308a;}
.btn-action{width:36px;height:36px;border-radius:10px;background:#001a47;color:#fff;border:none;display:flex;align-items:center;justify-content:center;}
.action-buttons{display:flex;gap:5px;}

@media (max-width:992px){
    .main-content{margin-left:0;padding:5rem 1rem 2rem;}
}

@media (max-width:768px){
    .card{padding:1.25rem;border-radius:10px;}
    .card > .d-flex{flex-direction:column;gap:.75rem;}
    #searchInput,.btn-submit{width:100%;margin-left:0 !important;}

    #userTable thead{display:none;}
    #userTable,#userTable tbody,#userTable tr,#userTable td{display:block;width:100%;}
    #userTable tr{margin-bottom:1rem;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;background:#fff;}
    #userTable td{display:flex;justify-content:space-between;gap:1rem;padding:.8rem 1rem;text-align:right;border-bottom:1px solid #f1f3f4;overflow-wrap:anywhere;}
    #userTable td:last-child{border-bottom:0;}
    #userTable td::before{content:"";color:#001a47;font-weight:700;text-align:left;flex:0 0 42%;}
    #userTable td:nth-child(1)::before{content:"Name";}
    #userTable td:nth-child(2)::before{content:"Email";}
    #userTable td:nth-child(3)::before{content:"Designation";}
    #userTable td:nth-child(4)::before{content:"Center";}
    #userTable td:nth-child(5)::before{content:"Contact";}
    #userTable td:nth-child(6)::before{content:"Actions";}
    .action-buttons{justify-content:flex-end;}
}

@media (max-width:576px){
    .main-content{padding-left:.75rem;padding-right:.75rem;}
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
<div class="card">

<div class="d-flex justify-content-between mb-3">
<input type="text" id="searchInput" class="form-control" placeholder="Search user...">
<button class="btn btn-submit ms-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
<i class="fas fa-plus"></i> Issue Account
</button>
</div>

<table class="table table-hover" id="userTable">
<thead>
<tr>
<th>Name</th><th>Email</th><th>Designation</th><th>Center</th><th>Contact</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php while($u=$users->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($u['fname']." ".$u['lname']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td><?= htmlspecialchars($u['designation']) ?></td>
<td><?= htmlspecialchars($u['negosyocenter']) ?></td>
<td><?= htmlspecialchars($u['contact']) ?></td>
<td>
<div class="action-buttons">
<button class="btn-action editBtn"
data-id="<?= $u['id'] ?>"
data-fname="<?= htmlspecialchars($u['fname']) ?>"
data-lname="<?= htmlspecialchars($u['lname']) ?>"
data-username="<?= htmlspecialchars($u['username']) ?>"
data-email="<?= htmlspecialchars($u['email']) ?>"
data-designation="<?= htmlspecialchars($u['designation']) ?>"
data-contact="<?= htmlspecialchars($u['contact']) ?>"
data-negosyocenter="<?= htmlspecialchars($u['negosyocenter']) ?>"
title="Edit User">
<i class="fas fa-edit"></i>
</button>

<button class="btn-action deleteUser" data-id="<?= $u['id'] ?>" title="Delete User">
<i class="fas fa-trash"></i>
</button>
</div>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</div>
</div>

<!-- ISSUE ACCOUNT MODAL -->
<div class="modal fade" id="addUserModal">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header"><h5>Issue Negosyo Center Account</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form id="addUserForm">
<div class="modal-body">

<h6 class="text-primary fw-bold mb-2">Negosyo Center Details</h6>
<div class="row">
<div class="col-md-6">
<select name="province" id="provinceSelect" class="form-control mb-2" required>
<option value="">Select Province</option>
<option value="Abra">Abra</option>
<option value="Agusan del Norte">Agusan del Norte</option>
<option value="Agusan del Sur">Agusan del Sur</option>
<option value="Aklan">Aklan</option>
<option value="Albay">Albay</option>
<option value="Antique">Antique</option>
<option value="Apayao">Apayao</option>
<option value="Aurora">Aurora</option>
<option value="Basilan">Basilan</option>
<option value="Bataan">Bataan</option>
<option value="Batanes">Batanes</option>
<option value="Batangas">Batangas</option>
<option value="Benguet">Benguet</option>
<option value="Biliran">Biliran</option>
<option value="Bohol">Bohol</option>
<option value="Bukidnon">Bukidnon</option>
<option value="Bulacan">Bulacan</option>
<option value="Cagayan">Cagayan</option>
<option value="Camarines Norte">Camarines Norte</option>
<option value="Camarines Sur">Camarines Sur</option>
<option value="Camiguin">Camiguin</option>
<option value="Capiz">Capiz</option>
<option value="Catanduanes">Catanduanes</option>
<option value="Cavite">Cavite</option>
<option value="Cebu">Cebu</option>
<option value="Cotabato">Cotabato</option>
<option value="Davao de Oro">Davao de Oro</option>
<option value="Davao del Norte">Davao del Norte</option>
<option value="Davao del Sur">Davao del Sur</option>
<option value="Davao Occidental">Davao Occidental</option>
<option value="Davao Oriental">Davao Oriental</option>
<option value="Dinagat Islands">Dinagat Islands</option>
<option value="Eastern Samar">Eastern Samar</option>
<option value="Guimaras">Guimaras</option>
<option value="Ifugao">Ifugao</option>
<option value="Ilocos Norte">Ilocos Norte</option>
<option value="Ilocos Sur">Ilocos Sur</option>
<option value="Iloilo">Iloilo</option>
<option value="Isabela">Isabela</option>
<option value="Kalinga">Kalinga</option>
<option value="La Union">La Union</option>
<option value="Laguna">Laguna</option>
<option value="Lanao del Norte">Lanao del Norte</option>
<option value="Lanao del Sur">Lanao del Sur</option>
<option value="Leyte">Leyte</option>
<option value="Maguindanao del Norte">Maguindanao del Norte</option>
<option value="Maguindanao del Sur">Maguindanao del Sur</option>
<option value="Marinduque">Marinduque</option>
<option value="Masbate">Masbate</option>
<option value="Misamis Occidental">Misamis Occidental</option>
<option value="Misamis Oriental">Misamis Oriental</option>
<option value="Mountain Province">Mountain Province</option>
<option value="Negros Occidental">Negros Occidental</option>
<option value="Negros Oriental">Negros Oriental</option>
<option value="Northern Samar">Northern Samar</option>
<option value="Nueva Ecija">Nueva Ecija</option>
<option value="Nueva Vizcaya">Nueva Vizcaya</option>
<option value="Occidental Mindoro">Occidental Mindoro</option>
<option value="Oriental Mindoro">Oriental Mindoro</option>
<option value="Palawan">Palawan</option>
<option value="Pampanga">Pampanga</option>
<option value="Pangasinan">Pangasinan</option>
<option value="Quezon">Quezon</option>
<option value="Quirino">Quirino</option>
<option value="Rizal">Rizal</option>
<option value="Romblon">Romblon</option>
<option value="Samar">Samar</option>
<option value="Sarangani">Sarangani</option>
<option value="Siquijor">Siquijor</option>
<option value="Sorsogon">Sorsogon</option>
<option value="South Cotabato">South Cotabato</option>
<option value="Southern Leyte">Southern Leyte</option>
<option value="Sultan Kudarat">Sultan Kudarat</option>
<option value="Sulu">Sulu</option>
<option value="Surigao del Norte">Surigao del Norte</option>
<option value="Surigao del Sur">Surigao del Sur</option>
<option value="Tarlac">Tarlac</option>
<option value="Tawi-Tawi">Tawi-Tawi</option>
<option value="Zambales">Zambales</option>
<option value="Zamboanga del Norte">Zamboanga del Norte</option>
<option value="Zamboanga del Sur">Zamboanga del Sur</option>
<option value="Zamboanga Sibugay">Zamboanga Sibugay</option>
</select>
</div>
<div class="col-md-6">
<input name="municipality" id="municipalitySelect" list="municipalityOptions" class="form-control mb-2" placeholder="Municipality / City" required disabled>
<datalist id="municipalityOptions"></datalist>
</div>
<div class="col-12">
<input name="negosyocenter" id="negosyoCenterName" class="form-control mb-3" placeholder="Negosyo Center Name" readonly required>
</div>
</div>

<h6 class="text-primary fw-bold mb-2">Login Account Details</h6>
<div class="row">
<div class="col-md-6">
<input name="fname" class="form-control mb-2" placeholder="First Name" required>
</div>
<div class="col-md-6">
<input name="lname" class="form-control mb-2" placeholder="Last Name" required>
</div>
<div class="col-md-6">
<input name="username" class="form-control mb-2" placeholder="Username" required>
</div>
<div class="col-md-6">
<input name="account_email" type="email" class="form-control mb-2" placeholder="Login Email" required>
</div>
<div class="col-md-6">
<select name="designation" class="form-control mb-2" required>
<option value="">Select Designation</option>
<option value="Admin">Admin</option>
<option value="Staff">Staff</option>
</select>
</div>
<div class="col-md-6">
<input name="contact" class="form-control mb-2" placeholder="Contact">
</div>
</div>
</div>

<div class="modal-footer">
<button type="submit" class="btn btn-submit">Issue Account</button>
</div>
</form>

</div>
</div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal fade" id="editUserModal">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header"><h5>Edit User</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form id="editUserForm">
<input type="hidden" name="id" id="edit_id">
<div class="modal-body">
<input id="edit_fname" name="fname" class="form-control mb-2" placeholder="First Name">
<input id="edit_lname" name="lname" class="form-control mb-2" placeholder="Last Name">
<input id="edit_username" name="username" class="form-control mb-2" placeholder="Username">
<input id="edit_email" name="email" type="email" class="form-control mb-2" placeholder="Email">
<input id="edit_negosyocenter" name="negosyocenter" class="form-control mb-2" placeholder="Negosyo Center Name">
<select id="edit_designation" name="designation" class="form-control mb-2">
<option value="Admin">Admin</option>
<option value="Staff">Staff</option>
</select>
<input id="edit_contact" name="contact" class="form-control mb-2" placeholder="Contact">
</div>

<div class="modal-footer">
<button class="btn btn-submit">Update</button>
</div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// SEARCH
document.getElementById('searchInput').addEventListener('keyup',function(){
let v=this.value.toLowerCase();
document.querySelectorAll('#userTable tbody tr').forEach(r=>{
r.style.display=r.textContent.toLowerCase().includes(v)?'':'none';
});
});

// NEGOSYO CENTER LOCATION SELECTS
const provinceMunicipalities = {
Batangas: [
'Agoncillo',
'Alitagtag',
'Balayan',
'Balete',
'Batangas City',
'Bauan',
'Calaca City',
'Calatagan',
'Cuenca',
'Ibaan',
'Laurel',
'Lemery',
'Lian',
'Lipa City',
'Lobo',
'Mabini',
'Malvar',
'Mataasnakahoy',
'Nasugbu',
'Padre Garcia',
'Rosario',
'San Jose',
'San Juan',
'San Luis',
'San Nicolas',
'San Pascual',
'Santa Teresita',
'Santo Tomas City',
'Taal',
'Talisay',
'Tanauan City',
'Taysan',
'Tingloy',
'Tuy'
]
};

const provinceSelect = document.getElementById('provinceSelect');
const municipalitySelect = document.getElementById('municipalitySelect');
const municipalityOptions = document.getElementById('municipalityOptions');
const negosyoCenterName = document.getElementById('negosyoCenterName');

function updateMunicipalities() {
const province = provinceSelect.value;
const municipalities = provinceMunicipalities[province] || [];
municipalityOptions.innerHTML = '';
municipalities.forEach(municipality => {
const option = document.createElement('option');
option.value = municipality;
municipalityOptions.appendChild(option);
});
municipalitySelect.disabled = province === '';
municipalitySelect.value = '';
municipalitySelect.placeholder = municipalities.length ? 'Select or type Municipality / City' : 'Type Municipality / City';
negosyoCenterName.value = '';
}

function updateNegosyoCenterName() {
negosyoCenterName.value = municipalitySelect.value ? `Negosyo Center - ${municipalitySelect.value}` : '';
}

provinceSelect.addEventListener('change', updateMunicipalities);
municipalitySelect.addEventListener('input', updateNegosyoCenterName);

// ADD USER
document.getElementById('addUserForm').addEventListener('submit',function(e){
e.preventDefault();
const submitBtn = this.querySelector('button[type="submit"]');
submitBtn.disabled = true;
submitBtn.textContent = 'Issuing...';
fetch('create_user_process.php',{method:'POST',body:new FormData(this)})
.then(async res=>{
const text = await res.text();
try {
return JSON.parse(text);
} catch (error) {
throw new Error(text || 'The server returned an invalid response.');
}
})
.then(data=>{
if(data.success){
const credentials = `
<div class="text-start">
<p class="mb-2">${data.email_sent ? 'Credentials were sent to the user email.' : 'Email sending is disabled. Give these credentials to the Negosyo Center:'}</p>
<div><strong>Center:</strong> ${data.center || ''}</div>
<div><strong>Municipality / City:</strong> ${data.municipality || ''}</div>
<div><strong>Province:</strong> ${data.province || ''}</div>
<div><strong>Username:</strong> ${data.username}</div>
<div><strong>Email:</strong> ${data.email}</div>
<div><strong>Temporary Password:</strong> ${data.temp}</div>
</div>`;
Swal.fire({
title:'Account Issued',
html:credentials,
icon:'success',
confirmButtonText:'Done'
}).then(()=>location.reload());
}else{
Swal.fire('Error',data.error || 'Unable to create user.','error');
}
})
.catch(error=>{
Swal.fire('Error', error.message || 'Unable to create user.', 'error');
})
.finally(()=>{
submitBtn.disabled = false;
submitBtn.textContent = 'Issue Account';
});
});

// EDIT USER OPEN
document.querySelectorAll('.editBtn').forEach(btn=>{
btn.onclick=function(){
edit_id.value=this.dataset.id;
edit_fname.value=this.dataset.fname;
edit_lname.value=this.dataset.lname;
edit_username.value=this.dataset.username;
edit_email.value=this.dataset.email;
edit_negosyocenter.value=this.dataset.negosyocenter;
edit_designation.value=this.dataset.designation;
edit_contact.value=this.dataset.contact;
new bootstrap.Modal(editUserModal).show();
};
});

// UPDATE USER
document.getElementById('editUserForm').addEventListener('submit',function(e){
e.preventDefault();
fetch('update_user.php',{method:'POST',body:new FormData(this)})
.then(res=>res.json())
.then(data=>{
if(data.success){
location.reload();
}else{
Swal.fire('Error', data.error || 'Unable to update user.', 'error');
}
});
});

// DELETE USER
document.querySelectorAll('.deleteUser').forEach(btn=>{
btn.onclick=function(){
Swal.fire({title:'Delete user?',showCancelButton:true}).then(r=>{
if(r.isConfirmed){
fetch('delete_user.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:this.dataset.id})})
.then(res=>res.json())
.then(data=>{
if(data.success){
location.reload();
}else{
Swal.fire('Error', data.error || 'Unable to delete user.', 'error');
}
});
}
});
};
});
</script>

</body>
</html>
