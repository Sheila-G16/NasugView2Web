<?php
session_start();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/account_security.php";

nasugviewweb_ensure_password_security_columns($conn);

// Check if admin is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';
$admin = [
    'admin_id' => $admin_id,
    'username' => '',
    'fname' => '',
    'lname' => '',
    'fullname' => 'Admin',
    'email' => '',
    'contact' => '',
    'negosyocenter' => '',
    'designation' => '',
    'profile_img' => '',
    'password' => '',
    'municipality' => '',
    'province' => '',
    'address' => '',
    'contact_number' => '',
    'center_email' => '',
    'officer_in_charge' => '',
    'opening_hours' => ''
];

// Fetch admin info
$stmt = $conn->prepare("
    SELECT
        id, username, email, password, fname, lname, designation, negosyocenter, contact, profile_img,
        branch_name, municipality, province, address, contact_number, center_email, officer_in_charge, opening_hours
    FROM negosyo_center_users
    WHERE id=?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $admin = [
        'admin_id' => $row['id'],
        'username' => $row['username'],
        'fname' => $row['fname'],
        'lname' => $row['lname'],
        'fullname' => trim($row['fname'] . ' ' . $row['lname']),
        'email' => $row['email'],
        'password' => $row['password'],
        'contact' => $row['contact'],
        'negosyocenter' => $row['negosyocenter'],
        'designation' => $row['designation'],
        'profile_img' => $row['profile_img'],
        'municipality' => $row['municipality'],
        'province' => $row['province'],
        'address' => $row['address'],
        'contact_number' => $row['contact_number'],
        'center_email' => $row['center_email'],
        'officer_in_charge' => $row['officer_in_charge'],
        'opening_hours' => $row['opening_hours']
    ];
}
$stmt->close();

// Fix for sidebar: define $admin_fullname
$admin_fullname = $admin['fullname'] ?? 'Admin';

function nasugviewweb_password_rule_errors(string $password): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = "at least 8 characters";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "one uppercase letter";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "one lowercase letter";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "one number";
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "one special character";
    }

    return $errors;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? 'profile';

    if ($form_action === 'password') {
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $stored_password = (string) ($admin['password'] ?? '');
        $current_password_matches = $current_password !== ''
            && ($current_password === $stored_password || password_verify($current_password, $stored_password));

        if (!$current_password || !$new_password || !$confirm_password) {
            $error = "Please complete all password fields.";
        } elseif (!$current_password_matches) {
            $error = "Current password is incorrect.";
        } elseif ($password_rule_errors = nasugviewweb_password_rule_errors($new_password)) {
            $error = "New password must contain " . implode(", ", $password_rule_errors) . ".";
        } elseif ($new_password !== $confirm_password) {
            $error = "New password and confirmation do not match.";
        } elseif ($new_password === $current_password) {
            $error = "New password must be different from your current password.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE negosyo_center_users SET password=?, must_change_password=0, password_changed_at=NOW() WHERE id=?");
            $stmt->bind_param("si", $hashed_password, $admin_id);

            if ($stmt->execute()) {
                $success = "Password updated successfully!";
                $admin['password'] = $hashed_password;
                $_SESSION['must_change_password'] = 0;
            } else {
                $error = "Failed to update password.";
            }

            $stmt->close();
        }
    } elseif ($form_action === 'center_details') {
        $municipality = trim($_POST['municipality'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $center_email = trim($_POST['center_email'] ?? '');
        $officer_in_charge = trim((string) ($admin['fullname'] ?? ''));
        $opening_hours = trim($_POST['opening_hours'] ?? '');

        if ($center_email !== '' && !filter_var($center_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid center email address.";
        } else {
            $stmt = $conn->prepare("
                UPDATE negosyo_center_users
                SET branch_name=?, municipality=?, province=?, address=?, contact_number=?, center_email=?, officer_in_charge=?, opening_hours=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "ssssssssi",
                $admin['negosyocenter'],
                $municipality,
                $province,
                $address,
                $contact_number,
                $center_email,
                $officer_in_charge,
                $opening_hours,
                $admin_id
            );

            if ($stmt->execute()) {
                $success = "Negosyo Center details updated successfully!";
                $admin['municipality'] = $municipality;
                $admin['province'] = $province;
                $admin['address'] = $address;
                $admin['contact_number'] = $contact_number;
                $admin['center_email'] = $center_email;
                $admin['officer_in_charge'] = $officer_in_charge;
                $admin['opening_hours'] = $opening_hours;
            } else {
                $error = "Failed to update Negosyo Center details.";
            }

            $stmt->close();
        }
    } else {
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');

        if (!$fname || !$lname || !$email) {
            $error = "Full name and email are required.";
        } else {
            // Handle profile image upload
            if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['profile_img']['tmp_name'];
                $fileName = $_FILES['profile_img']['name'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                $allowedExtensions = ['jpg','jpeg','png','gif'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = "profile_" . $admin_id . "." . $fileExtension;
                    $uploadDir = './uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $destPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $stmt = $conn->prepare("UPDATE negosyo_center_users SET profile_img=? WHERE id=?");
                        $stmt->bind_param("si", $newFileName, $admin_id);
                        $stmt->execute();
                        $stmt->close();

                        $admin['profile_img'] = $newFileName;
                    } else {
                        $error = "There was an error uploading the image.";
                    }
                } else {
                    $error = "Allowed file types: " . implode(", ", $allowedExtensions);
                }
            }

            if (!$error) {
                // Update other profile info
                $stmt = $conn->prepare("UPDATE negosyo_center_users SET fname=?, lname=?, email=?, contact=? WHERE id=?");
                $stmt->bind_param("ssssi", $fname, $lname, $email, $contact, $admin_id);
                if ($stmt->execute()) {
                    $success = "Profile updated successfully!";
                    $admin['fname'] = $fname;
                    $admin['lname'] = $lname;
                    $admin['fullname'] = $fname . ' ' . $lname;
                    $admin['email'] = $email;
                    $admin['contact'] = $contact;

                    // Update $admin_fullname for sidebar
                    $admin_fullname = $admin['fullname'];
                } else {
                    $error = "Failed to update profile.";
                }
                $stmt->close();
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Settings - NasugView</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --primary-color: #001a47;
    --secondary-color: #f8f9fa;
    --gradient-start: #001a47;
    --gradient-end: #00308a;
    --sidebar-width: 250px;
}
body {margin:0; padding:0; font-family:'Poppins',sans-serif; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); min-height:100vh; overflow-x:hidden;}
.sidebar {background: linear-gradient(180deg,var(--gradient-start),var(--gradient-end)); color:white; height:100vh; padding:0; box-shadow:4px 0 20px rgba(0,0,0,0.1); position:fixed; top:0; left:0; width:var(--sidebar-width); z-index:1000; overflow-y:auto;}
.main-content {margin-left:var(--sidebar-width); min-height:100vh; background-color: var(--secondary-color);}
.content-wrapper {padding:2rem; max-width:1200px; margin:0 auto;}
.page-title h1 {font-weight:700;color:var(--primary-color); margin-bottom:1rem;}
.page-title p {color:#6c757d; margin-bottom:0;}
.settings-container {display:grid; grid-template-columns:320px 1fr; gap:2rem;}
.profile-sidebar {background:white; border-radius:10px; padding:2.5rem; box-shadow:0 5px 25px rgba(0,0,0,0.08); text-align:center; position:sticky; top:2rem;}
.profile-picture {width:150px;height:150px;border-radius:50%;margin:0 auto 1.5rem; overflow:hidden; border:5px solid #e8f0fe; box-shadow:0 8px 25px rgba(0,0,0,0.15); display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:3rem;}
.profile-picture img {width:100%; height:100%; object-fit:cover;}
.profile-info h4 {margin-bottom:0.5rem;color:var(--primary-color); font-weight:700;}
.profile-info p {color:#6c757d; margin-bottom:1rem; word-break: break-word; overflow-wrap: break-word; white-space: normal;}
.admin-id {background:#f8f9ff; border:1px solid #e8f0fe; border-radius:10px; padding:0.75rem; font-size:0.9rem; color:var(--primary-color); font-weight:600;}
.settings-form {background:white; border-radius:10px; padding:2.5rem; box-shadow:0 5px 25px rgba(0,0,0,0.08);}
.section-title {font-weight:700;color:var(--primary-color); margin-bottom:1.5rem; font-size:1.3rem; display:flex;align-items:center; gap:10px; padding-bottom:1rem; border-bottom:2px solid #f1f3f4;}
.form-control {border-radius:8px; border:2px solid #e8f0fe; padding:0.75rem 1rem; background:#fafbfc;}
.form-control:focus {border-color:var(--primary-color); background:white; box-shadow:0 0 0 0.2rem rgba(0,26,71,0.15);}
.form-actions {display:flex; gap:1rem; margin-top:2rem;}
.btn-save {background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); border:none; border-radius:8px; padding:1rem 2rem; color:white; font-weight:600;}
.btn-save:hover {transform:translateY(-2px); box-shadow:0 8px 25px rgba(0,26,71,0.3);}
.btn-reset {background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); border:none; color:white; border-radius:8px; padding:1rem 2rem; font-weight:600; box-shadow:0 8px 25px rgba(0,26,71,0.22);}
.btn-reset:hover {background:linear-gradient(135deg,var(--gradient-end),var(--primary-color)); color:white; transform:translateY(-2px); box-shadow:0 10px 28px rgba(0,26,71,0.28);}
.alert {border-radius: 8px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; opacity: 1; transition: opacity 1s ease-out, max-height 1s ease-out; max-height: 200px; overflow: hidden;}
.alert.hide {opacity: 0; max-height: 0;}
.alert-success {background:#e8f5e8; color:#2e7d32; border-left:4px solid #28a745;}
.alert-danger {background:#ffeaea; color:#c62828; border-left:4px solid #dc3545;}
.account-forms {display:flex; flex-direction:column; gap:1.5rem;}
.password-help {color:#6c757d; font-size:.9rem; margin-top:-.35rem;}
.password-rules {background:#f8f9ff; border:1px solid #e8f0fe; border-radius:8px; padding:.85rem 1rem; margin-top:.75rem; color:#495057; font-size:.9rem;}
.password-rules ul {margin:.35rem 0 0; padding-left:1.1rem;}
.password-rules li {margin:.15rem 0;}
.form-textarea {min-height:110px; resize:vertical;}
.readonly-field {background:#f1f5f9; color:#64748b;}

@media (max-width:992px) {
    .main-content {
        margin-left:0;
    }

    .content-wrapper {
        padding:5rem 1rem 2rem;
    }

    .settings-container {
        grid-template-columns:1fr;
        gap:1rem;
    }

    .profile-sidebar {
        position:static;
    }
}

@media (max-width:576px) {
    .content-wrapper {
        padding-left:.75rem;
        padding-right:.75rem;
    }

    .page-title h1 {
        font-size:1.55rem;
    }

    .profile-sidebar,
    .settings-form {
        padding:1.25rem;
        border-radius:10px;
    }

    .profile-picture {
        width:112px;
        height:112px;
        font-size:2.25rem;
    }

    .form-actions {
        flex-direction:column;
    }

    .btn-save,
    .btn-reset {
        width:100%;
        text-align:center;
    }
}
</style>
</head>
<body>

<!-- Include Sidebar -->
<?php include 'sidebar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="page-title">
                        <h1>Admin Settings</h1>
                        <p>Manage your profile information and account settings</p>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="settings-container">
                    <!-- Profile Sidebar -->
                    <div class="profile-sidebar">
                        <div class="profile-picture">
                            <?php if (!empty($admin['profile_img']) && file_exists('./uploads/' . $admin['profile_img'])): ?>
                                <img id="profilePreview" src="<?php echo htmlspecialchars('./uploads/' . $admin['profile_img']); ?>" alt="Profile Image">
                            <?php else: ?>
                                <div id="profileInitial" style="display:flex; align-items:center; justify-content:center; width:100%; height:100%; background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); color:white; border-radius:50%; font-size:3rem;">
                                    <?php echo htmlspecialchars(strtoupper(substr($admin['fname'],0,1)) ?: 'A'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-info">
                            <h4><?php echo htmlspecialchars($admin['fullname']); ?></h4>
                            <p><?php echo htmlspecialchars($admin['email']); ?></p>
                        </div>
                        <a href="logout.php" class="btn-save">Logout</a>
                    </div>

                    <div class="account-forms">
                        <!-- Settings Form -->
                        <div class="settings-form">
                            <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="form_action" value="profile">
                            <div class="section-title"><i class="fas fa-user"></i> Profile Information</div>
                            <div class="mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="fname" value="<?php echo htmlspecialchars($admin['fname']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="lname" value="<?php echo htmlspecialchars($admin['lname']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact</label>
                                <input type="text" class="form-control" name="contact" value="<?php echo htmlspecialchars($admin['contact']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Profile Image</label>
                                <input type="file" class="form-control" name="profile_img" accept="image/*" onchange="previewImage(event)">
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save Changes</button>
                                <button type="reset" class="btn-reset"><i class="fas fa-undo me-2"></i>Reset</button>
                            </div>
                            </form>
                        </div>

                        <div class="settings-form">
                            <form method="POST">
                                <input type="hidden" name="form_action" value="center_details">
                                <div class="section-title"><i class="fas fa-store"></i> Negosyo Center Details</div>
                                <div class="mb-3">
                                    <label class="form-label">Negosyo Center</label>
                                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($admin['negosyocenter']); ?>" readonly>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Municipality / City</label>
                                        <input type="text" class="form-control" name="municipality" value="<?php echo htmlspecialchars($admin['municipality']); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Province</label>
                                        <input type="text" class="form-control" name="province" value="<?php echo htmlspecialchars($admin['province']); ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control form-textarea" name="address"><?php echo htmlspecialchars($admin['address']); ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Center Contact Number</label>
                                        <input type="text" class="form-control" name="contact_number" value="<?php echo htmlspecialchars($admin['contact_number']); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Center Email</label>
                                        <input type="email" class="form-control" name="center_email" value="<?php echo htmlspecialchars($admin['center_email']); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Officer in Charge</label>
                                        <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($admin['fullname']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Opening Hours</label>
                                        <input type="text" class="form-control" name="opening_hours" value="<?php echo htmlspecialchars($admin['opening_hours']); ?>" placeholder="e.g. Mon-Fri, 8:00 AM - 5:00 PM">
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i>Save Center Details</button>
                                    <button type="reset" class="btn-reset"><i class="fas fa-undo me-2"></i>Reset</button>
                                </div>
                            </form>
                        </div>

                        <div class="settings-form" id="change-password">
                            <form method="POST">
                                <input type="hidden" name="form_action" value="password">
                                <div class="section-title"><i class="fas fa-lock"></i> Change Password</div>
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" name="current_password" autocomplete="current-password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password" minlength="8" autocomplete="new-password" required>
                                    <div class="password-rules">
                                        <strong>Password must contain:</strong>
                                        <ul>
                                            <li>At least 8 characters</li>
                                            <li>One uppercase letter</li>
                                            <li>One lowercase letter</li>
                                            <li>One number</li>
                                            <li>One special character</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" minlength="8" autocomplete="new-password" required>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn-save"><i class="fas fa-key me-2"></i>Update Password</button>
                                    <button type="reset" class="btn-reset"><i class="fas fa-undo me-2"></i>Reset</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(event) {
    const input = event.target;
    const reader = new FileReader();
    reader.onload = function(){
        let img = document.getElementById('profilePreview');
        if (!img) {
            img = document.createElement('img');
            img.id = 'profilePreview';
            img.alt = 'Profile Image';
            document.querySelector('.profile-picture').appendChild(img);
        }
        img.src = reader.result;
        img.style.display = 'block';
        const initial = document.getElementById('profileInitial');
        if (initial) initial.style.display = 'none';
    };
    if(input.files && input.files[0]){
        reader.readAsDataURL(input.files[0]);
    }
}

// Auto-hide alerts after 3 seconds
window.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('hide');
        }, 3000);
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
