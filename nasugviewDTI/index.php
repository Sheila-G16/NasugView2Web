<?php
session_start();
require_once "db.php";

/* ================= VARIABLES ================= */
$login_error = '';
$register_error = '';

$email_value = '';
$password_value = '';
$remember = false;

/* REMEMBER ME */
if(isset($_COOKIE['remember_email'])){
    $email_value = $_COOKIE['remember_email'];
    $remember = true;
}

/* ================= LOGIN ================= */
if(isset($_POST['login'])){
    $login_input = trim($_POST['email']);
    $password_input = trim($_POST['password']);
    $remember_me = isset($_POST['remember']);

    if(empty($login_input) || empty($password_input)){
        $login_error = "Please provide email and password.";
    } else {
        // Case-insensitive login
        $stmt = $conn->prepare("SELECT dti_id AS id, username, password, email FROM dti_user WHERE LOWER(email)=LOWER(?) OR LOWER(username)=LOWER(?)");
        $login_lower = strtolower($login_input);
        $stmt->bind_param("ss", $login_lower, $login_lower);
        $stmt->execute();
        $res = $stmt->get_result();

        if($res->num_rows === 1){
            $user = $res->fetch_assoc();

            // Verify password (support old plain-text passwords temporarily)
            if($password_input === $user['password'] || password_verify($password_input, $user['password'])){
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                // Only store email in cookie for Remember Me
                if($remember_me){
                    setcookie("remember_email", $login_input, time()+60*60*24*30, "/");
                } else {
                    setcookie("remember_email", "", time()-3600, "/");
                }

                header("Location: dashboard.php");
                exit();
            } else {
                $login_error = "Invalid email/username or password.";
            }
        } else {
            $login_error = "Invalid email/username or password.";
        }
        $stmt->close();
    }
}

/* ================= REGISTER ================= */
if(isset($_POST['register'])){
    $username   = trim($_POST['username']);
    $email      = trim($_POST['email']);
    $password   = trim($_POST['password']);
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $gender     = trim($_POST['gender']);
    $birthday   = trim($_POST['birthday']);
    $address    = trim($_POST['address']);

    if(empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($gender) || empty($birthday) || empty($address)){
        $register_error = "All fields required.";
    } else {
        $check = $conn->prepare("SELECT dti_id FROM dti_user WHERE username=? OR email=?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0){
            $register_error = "Username or email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $birthDate = new DateTime($birthday);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;

            $insert = $conn->prepare("INSERT INTO dti_user (username,password,email,fname,lname,gender,birthday,age,address) VALUES (?,?,?,?,?,?,?,?,?)");
            $insert->bind_param("sssssssis",$username,$hashed_password,$email,$first_name,$last_name,$gender,$birthday,$age,$address);

            if($insert->execute()){
                $_SESSION['user_id'] = $insert->insert_id;
                $_SESSION['username'] = $username;

                header("Location: dashboard.php");
                exit();
            } else {
                $register_error = "Registration failed.";
            }
            $insert->close();
        }
        $check->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NasugView DTI</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<style>
/* ======= FONTS ======= */
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap');

@font-face { font-family:'ITC Benguiat'; src:url('fonts/ITCBenguiat-Regular.woff2') format('woff2'); }
@font-face { font-family:'ITC Benguiat Bold Condensed'; src:url('fonts/ITCBenguiat-BoldCondensed.woff2') format('woff2'); }

/* ======= VARIABLES ======= */
:root{--primary-color:#000f2e; --secondary-color:#f9fff9; --star-color:#FFD700;}

/* ======= BASE ======= */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{background:linear-gradient(to right,#FFFFF0,#f0e6d2);display:flex;justify-content:center;align-items:center;height:100vh;}
.container{width:720px;max-width:95%;min-height:520px;background:#fff;border-radius:10px;overflow:hidden;position:relative;box-shadow:0 20px 50px rgba(0,15,46,0.7),0 10px 30px rgba(0,15,46,0.5);}

/* ======= FORM AREAS ======= */
.form-container{position:absolute;top:0;height:100%;width:50%;display:flex;flex-direction:column;background:var(--secondary-color);transition:all .6s ease-in-out;}
.sign-in{left:0;z-index:2;}
.container.active .sign-in{transform:translateX(100%);}
.sign-up{left:0;opacity:0;z-index:1;}
.container.active .sign-up{transform:translateX(100%);opacity:1;z-index:5;}

/* ======= SCROLLABLE FORM ======= */
.form-scroll{flex:1;overflow-y:auto;padding:20px 40px;display:flex;flex-direction:column;gap:10px;}
.sign-up .form-scroll{height: calc(100% - 120px);overflow-y: auto;}
.form-scroll img, input, select, .password-wrapper {width:100%;max-width:320px;}
.form-actions{padding:10px 40px 20px;display:flex;flex-direction:column;align-items:center;gap:10px;background:var(--secondary-color);}

form img{width:250px;margin-bottom:15px;filter: drop-shadow(0 6px 6px rgba(0,26,71,0.5));transition: filter 0.3s ease;}
form img:hover{filter: drop-shadow(0 12px 12px rgba(0,26,71,0.7));}

input,select{background:#e8f5e9;border:none;padding:12px;font-size:14px;border-radius:8px;width:100%;max-width:320px;outline:none;}

.password-wrapper{position:relative;width:100%;max-width:320px;}
.password-wrapper input{padding-right:40px;}
.password-wrapper i{position:absolute;top:50%;right:12px;transform:translateY(-50%);cursor:pointer;color:#666;}

.options-row{display:flex;justify-content:space-between;width:100%;max-width:320px;font-size:11px;margin-top:5px;}

button{background:var(--primary-color);color:#fff;font-size:14px;padding:12px 50px;border:none;border-radius:6px;font-weight:600;cursor:pointer;width:100%;max-width:320px;}
button:hover{background:#001a47;}

.switch-text{font-size:14px;text-align:center;color:#0d6efd;cursor:pointer;}

.toggle-container{position:absolute;top:0;left:50%;width:50%;height:100%;overflow:hidden;border-radius:150px 0 0 100px;transition:all .6s ease-in-out;}
.container.active .toggle-container{transform:translateX(-100%);border-radius:0 150px 100px 0;}

.toggle{background:linear-gradient(135deg,#001a47,#2555b5);height:100%;display:flex;align-items:center;justify-content:center;}
.toggle-panel{font-family:'ITC Benguiat',serif;color:#fff;text-align:center;}
.phrase1{font-family:'ITC Benguiat Bold Condensed',serif;font-size:1.7rem;display:flex;gap:0.4rem;justify-content:center;}
.star{color:var(--star-color); font-size:0.6rem;}
.phrase2{font-size:1rem;margin-top:4px;opacity:0.9;}

@media (max-width:760px){
    body{
        height:auto;
        min-height:100vh;
        padding:1rem;
        align-items:flex-start;
    }

    .container{
        width:100%;
        max-width:430px;
        min-height:0;
        border-radius:10px;
    }

    .toggle-container{
        display:none;
    }

    .form-container{
        position:relative;
        width:100%;
        height:auto;
        min-height:0;
        transform:none !important;
        transition:none;
    }

    .sign-in{
        display:flex;
    }

    .sign-up{
        display:none;
        opacity:1;
        z-index:2;
    }

    .container.active .sign-in{
        display:none;
    }

    .container.active .sign-up{
        display:flex;
    }

    .form-scroll{
        overflow:visible;
        padding:20px;
    }

    .sign-up .form-scroll{
        height:auto;
        overflow:visible;
    }

    .form-actions{
        padding:10px 20px 20px;
    }

    form img{
        width:min(220px, 80%);
        align-self:center;
    }
}

@media (max-width:420px){
    .options-row{
        flex-direction:column;
        align-items:flex-start;
        gap:8px;
    }
}

/* Screenshot-inspired split login UI */
body{
    background:#06275f;
    padding:24px;
    overflow:hidden;
}

.container{
    width:min(1225px,96vw);
    height:675px;
    max-width:none;
    min-height:0;
    border-radius:14px;
    box-shadow:0 24px 60px rgba(0,0,0,.25);
}

.form-container{
    left:39%;
    width:61%;
    background:#fff;
    justify-content:center;
    align-items:center;
}

.sign-in,
.sign-up{
    left:39%;
}

.container.active .sign-in{
    transform:none;
    opacity:0;
    pointer-events:none;
}

.sign-up{
    transform:none;
}

.container.active .sign-up{
    transform:none;
}

.toggle-container{
    left:0;
    width:39%;
    border-radius:0;
}

.container.active .toggle-container{
    transform:none;
    border-radius:0;
}

.toggle{
    position:relative;
    background:linear-gradient(145deg,#5f8fd5 0%,#123c86 42%,#041f57 100%);
    align-items:flex-end;
    justify-content:flex-start;
    padding:0 50px 82px;
    overflow:hidden;
}

.toggle::before,
.toggle::after{
    content:"";
    position:absolute;
    border-radius:34px;
    background:rgba(255,255,255,.13);
    transform:rotate(45deg);
}

.toggle::before{
    width:420px;
    height:420px;
    top:-145px;
    left:130px;
}

.toggle::after{
    width:360px;
    height:360px;
    bottom:95px;
    left:-120px;
    background:rgba(255,255,255,.1);
}

.toggle-panel{
    position:relative;
    z-index:2;
    text-align:left;
    max-width:350px;
}

.toggle-panel::before{
    content:"Login";
    position:absolute;
    top:-205px;
    right:-290px;
    width:210px;
    height:68px;
    border-radius:999px;
    background:#fff;
    color:#001b4f;
    display:flex;
    align-items:center;
    justify-content:center;
    font-family:'Montserrat',sans-serif;
    font-size:21px;
    font-weight:800;
    box-shadow:0 18px 28px rgba(0,0,0,.12);
}

.container.active .toggle-panel::before{
    content:"Sign Up";
}

.toggle-panel::after{
    content:"Sign Up";
    position:absolute;
    top:-118px;
    right:-247px;
    color:#fff;
    font-family:'Montserrat',sans-serif;
    font-size:21px;
    font-weight:800;
    cursor:pointer;
}

.container.active .toggle-panel::after{
    content:"Login";
}

.panel-tabs{
    position:absolute;
    top:-205px;
    right:-290px;
    width:210px;
    z-index:4;
}

.panel-tab{
    width:210px;
    height:68px;
    border:0;
    border-radius:999px;
    background:transparent;
    color:#fff;
    font-size:21px;
    font-weight:800;
    cursor:pointer;
    margin:0 0 14px;
}

.panel-tab.active{
    background:#fff;
    color:#001b4f;
    box-shadow:0 18px 28px rgba(0,0,0,.12);
}

.toggle-panel::before,
.toggle-panel::after{
    display:none;
}

.toggle-panel:has(.panel-tabs)::before,
.toggle-panel:has(.panel-tabs)::after{
    display:none;
}

.phrase1{
    display:block;
    color:#ffd54f;
    font-family:'Montserrat',sans-serif;
    font-size:16px;
    font-weight:800;
    text-transform:uppercase;
}

.phrase1 .star,
.phrase1 .star + *{
    display:none;
}

.phrase2{
    display:block;
    margin-top:14px;
    max-width:345px;
    color:rgba(255,255,255,.84);
    font-size:0;
}

.phrase2::before{
    content:"Welcome";
    display:block;
    font-family:Georgia,serif;
    font-size:40px;
    line-height:1.05;
    color:#fff;
    margin-bottom:10px;
}

.phrase2::after{
    content:"Sign in or create an account to manage Negosyo Center access and activity.";
    display:block;
    font-family:'Montserrat',sans-serif;
    font-size:20px;
    line-height:1.45;
    color:rgba(255,255,255,.82);
}

form{
    width:100%;
    max-width:594px;
    display:flex;
    flex-direction:column;
    align-items:center;
}

form::before{
    content:"\f007";
    font-family:"Font Awesome 6 Free";
    font-weight:400;
    width:96px;
    height:96px;
    border-radius:50%;
    background:linear-gradient(160deg,#5c8fe4,#12356d);
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:44px;
    box-shadow:0 18px 30px rgba(8,35,90,.22);
    margin-bottom:18px;
}

form::after{
    display:none;
}

.sign-in .form-scroll::before,
.sign-up .form-scroll::before{
    display:block;
    width:100%;
    text-align:center;
    color:#001b4f;
    font-size:30px;
    font-weight:800;
    margin-bottom:44px;
}

.sign-in .form-scroll::before{
    content:"LOGIN";
}

.sign-up .form-scroll::before{
    content:"SIGN UP";
    margin-bottom:22px;
}

form img{
    display:none;
}

.form-scroll{
    width:100%;
    max-height:390px;
    overflow:auto;
    padding:0 6px 0 0;
    gap:0;
    align-items:center;
}

.sign-in .form-scroll{
    max-height:none;
}

.form-scroll input,
.form-scroll select,
.password-wrapper{
    max-width:none;
}

input,
select{
    height:44px;
    border:0;
    border-bottom:1px solid #c9d3e1;
    border-radius:0;
    background:transparent;
    padding:0 52px;
    font-size:20px;
    color:#172554;
}

input::placeholder{
    color:#6f7d9c;
}

.password-wrapper i{
    right:20px;
    color:#001b4f;
    font-size:20px;
}

.form-scroll > input,
.form-scroll > select,
.password-wrapper{
    margin-bottom:34px;
}

.sign-up form::before{
    content:"\f2bb";
}

.sign-up .form-scroll{
    max-height:270px;
    overflow-y:auto;
    padding-right:8px;
    margin-bottom:20px;
}

.sign-up .form-scroll::-webkit-scrollbar{
    width:6px;
}

.sign-up .form-scroll::-webkit-scrollbar-thumb{
    background:rgba(0,26,71,.24);
    border-radius:999px;
}

.signup-grid{
    width:100%;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:0 18px;
}

.signup-grid .full{
    grid-column:1 / -1;
}

.input-field{
    position:relative;
    width:100%;
}

.input-field i{
    position:absolute;
    left:15px;
    top:14px;
    color:#637489;
    font-size:16px;
    z-index:2;
}

.input-field input,
.input-field select{
    width:100%;
}

.input-field .password-wrapper{
    width:100%;
}

.input-field .password-wrapper input{
    padding-right:44px;
}

.input-field .password-wrapper i{
    left:auto;
    right:15px;
    color:#001a47;
}

.options-row{
    max-width:none;
    margin-top:-15px;
    margin-bottom:44px;
    justify-content:flex-start;
}

.options-row label{
    display:none;
}

.options-row a{
    color:#001b4f;
    font-size:14px;
    font-weight:800;
    text-decoration:none;
}

.form-actions{
    padding:0;
    background:#fff;
}

button{
    width:188px;
    height:54px;
    max-width:none;
    padding:0;
    border-radius:999px;
    background:linear-gradient(90deg,#082967,#214f9f);
    font-size:21px;
    font-weight:800;
}

.panel-tab{
    width:210px;
    height:68px;
    background:transparent;
    color:#fff;
    font-size:21px;
    margin:0 0 14px;
    box-shadow:none;
}

.panel-tab.active{
    background:#fff;
    color:#001b4f;
    box-shadow:0 18px 28px rgba(0,0,0,.12);
}

.switch-text{
    margin-top:18px;
    color:#001b4f;
    font-weight:700;
}

@media (max-width:900px){
    body{
        overflow:auto;
        align-items:flex-start;
    }

    .container{
        height:auto;
        width:100%;
        max-width:520px;
    }

    .toggle-container{
        display:block;
        position:relative;
        width:100%;
        height:300px;
    }

    .form-container,
    .sign-in,
    .sign-up{
        position:relative;
        left:0;
        width:100%;
        height:auto;
        min-height:620px;
    }

    .toggle-panel::before,
    .toggle-panel::after{
        display:none;
    }

    .form-container{
        display:none;
    }

    .sign-in{
        display:flex;
    }

    .container.active .sign-in{
        display:none;
    }

    .container.active .sign-up{
        display:flex;
    }
}

/* Final reference-style normalization */
body{
    min-height:100vh;
    background:
        radial-gradient(circle at 20% 12%, rgba(40, 96, 204, 0.32), transparent 28%),
        linear-gradient(145deg, #061231 0%, #082257 46%, #001a47 100%);
    padding:24px;
}

.container{
    width:980px;
    max-width:100%;
    min-height:540px;
    height:auto;
    display:grid;
    grid-template-columns:minmax(300px, .39fr) minmax(500px, .61fr);
    border-radius:12px;
    background:#fff;
    box-shadow:0 28px 60px rgba(0,0,0,.38),0 10px 24px rgba(0,0,0,.22);
}

.toggle-container{
    position:relative;
    inset:auto;
    width:100%;
    height:auto;
    min-height:540px;
    grid-column:1;
    grid-row:1;
}

.toggle{
    min-height:540px;
    padding:48px 40px;
    justify-content:flex-end;
}

.form-container{
    position:static;
    grid-column:2;
    grid-row:1;
    width:100%;
    min-height:540px;
    height:540px;
    padding:40px 62px;
    transform:none !important;
}

.sign-up{
    opacity:1;
}

.container:not(.active) .sign-up,
.container.active .sign-in{
    display:none;
}

.container.active .sign-up,
.container:not(.active) .sign-in{
    display:flex;
}

.panel-tabs{
    display:none;
}

.panel-tab{
    width:168px;
    min-height:54px;
    height:54px;
    font-size:16px;
    padding:10px 18px;
}

.panel-tab.active{
    transform:translateX(34px);
}

.toggle-panel{
    max-width:300px;
}

.phrase1{
    font-size:13px;
}

.phrase2::before{
    font-size:32px;
}

.phrase2::after{
    font-size:14px;
    line-height:1.65;
}

form{
    max-width:500px;
    align-items:stretch;
}

form::before{
    width:76px;
    height:76px;
    font-size:36px;
    margin:0 auto 14px;
}

.sign-in .form-scroll::before,
.sign-up .form-scroll::before{
    font-size:24px;
    margin-bottom:34px;
}

.form-scroll{
    max-height:462px;
    align-items:stretch;
    padding-right:6px;
}

.sign-in .form-scroll{
    overflow:visible;
}

input,
select{
    min-height:46px;
    height:46px;
    margin:0 0 18px;
    padding:11px 44px;
    font-size:15px;
}

.password-wrapper{
    margin-bottom:18px;
}

.options-row{
    margin-top:-6px;
    margin-bottom:28px;
}

.form-actions{
    align-items:center;
}

button{
    min-width:150px;
    width:auto;
    height:42px;
    padding:10px 30px;
    font-size:16px;
}

.mobile-auth-switch{
    display:none;
    gap:6px;
    width:100%;
    max-width:260px;
    margin:0 auto 22px;
    padding:6px;
    background:#eef3f8;
    border:1px solid #dce5ee;
    border-radius:14px;
}

.mobile-auth-switch button{
    width:50%;
    min-width:0;
    height:42px;
    padding:0;
    border-radius:10px;
    background:transparent;
    color:#5b6b81;
    box-shadow:none;
    font-size:14px;
}

.container:not(.active) .mobile-login-switch,
.container.active .mobile-register-switch{
    background:#fff;
    color:#001a47;
}

@media(max-width:900px){
    body{
        padding:12px;
        align-items:stretch;
        overflow:auto;
    }

    .container{
        min-height:100vh;
        display:grid;
        grid-template-columns:1fr;
        border-radius:20px;
    }

    .toggle-container{
        grid-column:1;
        min-height:190px;
        height:auto;
    }

    .toggle{
        min-height:190px;
        padding:28px 24px;
    }

    .form-container{
        grid-column:1;
        min-height:auto;
        height:auto;
        padding:22px 16px 0;
    }

    .panel-tabs{
        display:none;
    }

    .mobile-auth-switch{
        display:flex;
        grid-column:1;
        grid-row:2;
    }

    .form-container{
        grid-row:3;
    }

    .panel-tab,
    .panel-tab.active{
        width:100%;
        min-width:0;
        min-height:42px;
        height:42px;
        color:#5b6b81;
        background:transparent;
        border-radius:10px;
        box-shadow:none;
        transform:none;
    }

    .panel-tab.active{
        background:#fff;
        color:#001a47;
    }

    .signup-grid{
        grid-template-columns:1fr;
    }

    .sign-up .form-scroll{
        max-height:none;
        overflow:visible;
    }
}

@media(max-width:520px){
    body{
        padding:0;
    }

    .container{
        border-radius:0;
    }
}
</style>
</head>

<body>
<div class="container <?php echo $register_error ? 'active' : ''; ?>" id="container">
<div class="mobile-auth-switch">
<button type="button" class="mobile-login-switch" id="mobileLoginBtn">Login</button>
<button type="button" class="mobile-register-switch" id="mobileRegisterBtn">Sign Up</button>
</div>

<!-- LOGIN -->
<div class="form-container sign-in">
<?php if($login_error) echo "<p style='color:red;margin-bottom:10px;'>$login_error</p>"; ?>
<form method="POST">
<div class="form-scroll">
<img src="assets/nasugviewlogoblue.png" alt="logo">
<input type="text" name="email" placeholder="Email or Username" value="<?= htmlspecialchars($email_value) ?>" required>
<div class="password-wrapper">
<input type="password" name="password" id="loginPassword" placeholder="Password" required>
<i class="fa-solid fa-eye-slash" id="toggleLoginPassword"></i>
</div>
<div class="options-row">
<label><input type="checkbox" name="remember" <?= $remember ? 'checked' : '' ?>> Remember Me</label>
<a href="#">Forgot your password?</a>
</div>
</div>
<div class="form-actions">
<button type="submit" name="login">Sign In</button>
<p class="switch-text" id="registerBtn">Don't have an account? Sign up</p>
</div>
</form>
</div>

<!-- SIGNUP -->
<div class="form-container sign-up">
<?php if($register_error) echo "<p style='color:red;margin-bottom:10px;'>$register_error</p>"; ?>
<form method="POST">
<div class="form-scroll">
<img src="assets/nasugviewlogoblue.png" alt="logo">
<div class="signup-grid">
<div class="input-field full"><i class="fa-solid fa-user"></i><input type="text" name="username" placeholder="Username" required></div>
<div class="input-field full"><i class="fa-solid fa-envelope"></i><input type="email" name="email" placeholder="Email" required></div>
<div class="input-field full">
<i class="fa-solid fa-lock"></i>
<div class="password-wrapper">
<input type="password" name="password" id="registerPassword" placeholder="Password" required>
<i class="fa-solid fa-eye-slash" id="toggleRegisterPassword"></i>
</div>
</div>
<div class="input-field"><i class="fa-solid fa-id-card"></i><input type="text" name="first_name" placeholder="First Name" required></div>
<div class="input-field"><i class="fa-solid fa-id-card"></i><input type="text" name="last_name" placeholder="Last Name" required></div>
<div class="input-field"><i class="fa-solid fa-venus-mars"></i><select name="gender" required>
<option value="">Sex</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
<option value="Other">Prefer not to say</option>
</select></div>
<div class="input-field"><i class="fa-solid fa-calendar"></i><input type="date" name="birthday" required></div>
<div class="input-field full"><i class="fa-solid fa-location-dot"></i><input type="text" name="address" placeholder="Address" required></div>
</div>
</div>
<div class="form-actions">
<button type="submit" name="register">Sign Up</button>
<p class="switch-text" id="loginBtn">Already have an account? Sign in</p>
</div>
</form>
</div>

<!-- PANEL -->
<div class="toggle-container">
<div class="toggle">
<div class="toggle-panel">
<div class="panel-tabs">
<button type="button" class="panel-tab active" id="panelLoginBtn">Login</button>
<button type="button" class="panel-tab" id="panelRegisterBtn">Sign Up</button>
</div>
<span class="phrase1">
Discover<span class="star">★</span>
Connect<span class="star">★</span>
Support
</span>
<span class="phrase2">Thrive with NasugView</span>
</div>
</div>
</div>
</div>

<script>
const container = document.getElementById("container");
const panelLoginBtn = document.getElementById("panelLoginBtn");
const panelRegisterBtn = document.getElementById("panelRegisterBtn");
const mobileLoginBtn = document.getElementById("mobileLoginBtn");
const mobileRegisterBtn = document.getElementById("mobileRegisterBtn");

if (container.classList.contains("active")) {
    panelLoginBtn.classList.remove("active");
    panelRegisterBtn.classList.add("active");
}

function showLogin(){
    container.classList.remove("active");
    panelLoginBtn.classList.add("active");
    panelRegisterBtn.classList.remove("active");
}

function showRegister(){
    container.classList.add("active");
    panelLoginBtn.classList.remove("active");
    panelRegisterBtn.classList.add("active");
}

document.getElementById("registerBtn").onclick = showRegister;
document.getElementById("loginBtn").onclick = showLogin;
panelLoginBtn.onclick = showLogin;
panelRegisterBtn.onclick = showRegister;
mobileLoginBtn.onclick = showLogin;
mobileRegisterBtn.onclick = showRegister;

/* LOGIN PASSWORD TOGGLE */
const loginPass = document.getElementById("loginPassword");
const toggleLogin = document.getElementById("toggleLoginPassword");
toggleLogin.onclick = ()=>{
    if(loginPass.type==="password"){
        loginPass.type="text";
        toggleLogin.classList.replace("fa-eye-slash","fa-eye");
    }else{
        loginPass.type="password";
        toggleLogin.classList.replace("fa-eye","fa-eye-slash");
    }
};

/* REGISTER PASSWORD TOGGLE */
const regPass = document.getElementById("registerPassword");
const toggleReg = document.getElementById("toggleRegisterPassword");
toggleReg.onclick = ()=>{
    if(regPass.type==="password"){
        regPass.type="text";
        toggleReg.classList.replace("fa-eye-slash","fa-eye");
    }else{
        regPass.type="password";
        toggleReg.classList.replace("fa-eye","fa-eye-slash");
    }
};
</script>
</body>
</html>
