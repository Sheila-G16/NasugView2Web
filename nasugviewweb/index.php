
<?php
session_start();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/account_security.php";

nasugviewweb_ensure_password_security_columns($conn);

$login_error = '';
$email_value = '';
$password_value = '';
$remember = false;

// Check if cookies exist for "Remember Me"
if(isset($_COOKIE['remember_email'])){
    $email_value = $_COOKIE['remember_email'];
    $remember = true;
}

// LOGIN ONLY
if (isset($_POST['login'])) {

    $login_input = trim($_POST['email']);
    $password_input = trim($_POST['password']);
    $remember_me = isset($_POST['remember']);

    if (empty($login_input) || empty($password_input)) {

        $login_error = "Please provide both email/username and password.";

    } else {

        $stmt = $conn->prepare("
            SELECT id, username, password, must_change_password
            FROM negosyo_center_users
            WHERE email=? OR username=?
        ");

        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();

        $res = $stmt->get_result();

        if ($res->num_rows === 1) {

            $user = $res->fetch_assoc();

            if ($password_input === $user['password'] || password_verify($password_input, $user['password'])) {

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['must_change_password'] = (int) ($user['must_change_password'] ?? 0);

                // REMEMBER ME
                if($remember_me){

                    setcookie(
                        'remember_email',
                        $login_input,
                        time()+30*24*60*60,
                        "/"
                    );

                } else {

                    setcookie('remember_email', '', time()-3600, "/");

                }

                setcookie('remember_password', '', time()-3600, "/");

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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>NasugView</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>

<style>

@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    width:100%;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:#001b6b;
    overflow:hidden;
}

/* MAIN CONTAINER */

.container{
    width:1120px;
    height:650px;
    background:#fff;
    border-radius:18px;
    overflow:hidden;
    display:flex;
    position:relative;
    box-shadow:
        0 25px 60px rgba(0,0,0,0.28),
        0 10px 30px rgba(0,0,0,0.16);
}

/* LEFT SIDE */

.toggle-container{
    width:40%;
    height:100%;
    position:relative;
    overflow:hidden;
    background:linear-gradient(180deg,#5b8be0 0%, #001f75 100%);
}

/* BIG SHAPES */

.toggle-container::before{
    content:'';
    position:absolute;
    width:620px;
    height:620px;
    background:rgba(255,255,255,0.08);
    top:-260px;
    left:-120px;
    transform:rotate(45deg);
    border-radius:80px;
}

.toggle-container::after{
    content:'';
    position:absolute;
    width:540px;
    height:540px;
    background:rgba(255,255,255,0.05);
    bottom:-260px;
    left:-150px;
    transform:rotate(45deg);
    border-radius:80px;
}

/* LEFT CONTENT */

.toggle{
    width:100%;
    height:100%;
    position:relative;
    z-index:2;
    display:flex;
    align-items:flex-end;
    padding:0 45px 72px;
}

.toggle-panel{
    width:100%;
    color:#fff;
    position:relative;
}

/* YELLOW TEXT */

.mini-title{
    color:#ffd54f;
    font-size:15px;
    font-weight:700;
    margin-bottom:16px;
}

/* WELCOME */

.toggle-panel h1{
    font-size:58px;
    font-weight:500;
    line-height:1;
    margin-bottom:14px;
    font-family:Georgia, serif;
}

/* PARAGRAPH */

.toggle-panel p{
    font-size:18px;
    line-height:1.65;
    max-width:300px;
    color:rgba(255,255,255,0.92);
}

/* RIGHT SIDE */

.form-container{
    width:60%;
    height:100%;
    background:#f7f7f7;
    display:flex;
    justify-content:center;
    align-items:center;
    position:relative;
}

/* FORM */

form{
    width:100%;
    max-width:540px;
    display:flex;
    flex-direction:column;
    align-items:center;
}

/* LOGIN ICON */

.login-icon{
    width:92px;
    height:92px;
    border-radius:50%;
    background:linear-gradient(180deg,#5b8be0,#002186);
    display:flex;
    justify-content:center;
    align-items:center;
    margin-bottom:20px;
    box-shadow:0 10px 22px rgba(0,0,0,0.10);
}

.login-icon i{
    color:#fff;
    font-size:40px;
}

/* LOGIN TITLE */

.login-title{
    font-size:28px;
    font-weight:800;
    color:#001b6b;
    margin-bottom:55px;
}

/* INPUTS */

.input-group{
    width:100%;
    max-width:540px;
    position:relative;
    margin-bottom:38px;
}

.input-group input{
    width:100%;
    border:none;
    border-bottom:2px solid #dddddd;
    background:transparent;
    padding:0 50px 16px 50px;
    font-size:17px;
    outline:none;
    color:#444;
}

.input-group input::placeholder{
    color:#8b8b8b;
}

.input-group i{
    position:absolute;
    top:0;
    font-size:20px;
    color:#6c757d;
}

.input-group .fa-envelope,
.input-group .fa-lock{
    left:10px;
}

#togglePassword{
    right:10px;
    cursor:pointer;
    color:#001b6b;
}

/* OPTIONS */

.options-row{
    width:100%;
    max-width:540px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:-18px;
    margin-bottom:48px;
}

.options-row label{
    font-size:14px;
    display:flex;
    align-items:center;
    gap:7px;
}

.options-row input[type="checkbox"]{
    accent-color:#9f4dff;
}

.options-row a{
    text-decoration:none;
    color:#001b6b;
    font-size:14px;
    font-weight:700;
}

/* LOGIN BUTTON */

button{
    width:175px;
    height:48px;
    border:none;
    border-radius:40px;
    background:#002d8d;
    color:#fff;
    font-size:18px;
    font-weight:700;
    cursor:pointer;
}

button:hover{
    background:#001f6b;
}

/* ERROR */

.error-message{
    color:red;
    margin-bottom:20px;
}

/* MOBILE */

@media(max-width:991px){

    body{
        padding:20px;
    }

    .container{
        width:100%;
        height:auto;
        flex-direction:column;
    }

    .toggle-container{
        width:100%;
        min-height:320px;
    }

    .form-container{
        width:100%;
        padding:70px 30px 50px;
    }
}

/* Screenshot-inspired login UI */
body{
    background:#05265f;
    padding:24px;
}

.container{
    width:min(1225px,96vw);
    height:675px;
    border-radius:14px;
    box-shadow:0 24px 60px rgba(0,0,0,.25);
}

.toggle-container{
    width:39%;
    background:linear-gradient(145deg,#5f8fd5 0%,#123c86 42%,#041f57 100%);
}

.toggle-container::before{
    width:420px;
    height:420px;
    top:-145px;
    left:130px;
    border-radius:34px;
    background:rgba(255,255,255,.13);
}

.toggle-container::after{
    width:360px;
    height:360px;
    bottom:95px;
    left:-120px;
    border-radius:34px;
    background:rgba(255,255,255,.1);
}

.toggle{
    padding:0 50px 82px;
}

.toggle-panel h1{
    font-size:40px;
}

.toggle-panel p{
    font-size:20px;
    line-height:1.45;
    max-width:360px;
    color:rgba(255,255,255,.84);
}

.form-container{
    width:61%;
    background:#fff;
}

form{
    max-width:594px;
}

.login-icon{
    width:96px;
    height:96px;
    background:linear-gradient(160deg,#5c8fe4,#12356d);
    box-shadow:0 18px 30px rgba(8,35,90,.22);
    margin-bottom:18px;
}

.login-icon i{
    font-size:44px;
}

.login-title{
    font-size:30px;
    color:#001b4f;
    margin-bottom:56px;
}

.input-group{
    max-width:594px;
    margin-bottom:44px;
}

.input-group input{
    border-bottom:1px solid #c9d3e1;
    padding:0 52px 16px;
    font-size:20px;
    color:#172554;
}

.input-group input::placeholder{
    color:#6f7d9c;
}

.input-group i{
    color:#6f7d8f;
}

#togglePassword{
    color:#001b4f;
}

.options-row{
    justify-content:flex-start;
    max-width:594px;
    margin-top:-28px;
    margin-bottom:44px;
}

.options-row label{
    display:none;
}

.options-row a{
    color:#001b4f;
    font-weight:800;
}

button{
    width:188px;
    height:54px;
    background:linear-gradient(90deg,#082967,#214f9f);
    font-size:21px;
    font-weight:800;
}

@media(max-width:991px){
    body{
        overflow:auto;
    }

    .container{
        height:auto;
        max-width:520px;
    }

    .toggle-container{
        width:100%;
        min-height:300px;
    }

    .form-container{
        width:100%;
        padding:56px 28px;
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
    width:100%;
    min-height:540px;
}

.toggle{
    min-height:540px;
    padding:48px 40px;
    align-items:flex-end;
}

.toggle-panel{
    max-width:300px;
}

.mini-title{
    font-size:13px;
    margin-bottom:12px;
}

.toggle-panel h1{
    font-size:32px;
    margin-bottom:8px;
}

.toggle-panel p{
    max-width:300px;
    font-size:14px;
    line-height:1.65;
}

.form-container{
    width:100%;
    min-height:540px;
    height:auto;
    padding:40px 62px;
}

form{
    max-width:500px;
    align-items:stretch;
}

.login-icon{
    width:76px;
    height:76px;
    margin:0 auto 14px;
}

.login-icon i{
    font-size:36px;
}

.login-title{
    text-align:center;
    font-size:24px;
    margin-bottom:34px;
}

.input-group{
    max-width:none;
    margin-bottom:20px;
}

.input-group input{
    min-height:48px;
    padding:11px 44px;
    font-size:15px;
}

.input-group i{
    top:12px;
    font-size:16px;
}

.input-group .fa-envelope,
.input-group .fa-lock{
    left:15px;
}

#togglePassword{
    right:15px;
}

.options-row{
    max-width:none;
    margin-top:-6px;
    margin-bottom:28px;
}

button{
    align-self:center;
    min-width:150px;
    width:auto;
    height:42px;
    padding:10px 30px;
    font-size:16px;
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
        min-height:190px;
    }

    .toggle{
        min-height:190px;
        padding:28px 24px;
    }

    .form-container{
        min-height:auto;
        padding:42px 16px 36px;
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

<div class="container">

    <!-- LEFT SIDE -->

    <div class="toggle-container">

        <div class="toggle">

            <div class="toggle-panel">

                <div class="mini-title">
                    DISCOVER LOCAL BUSINESSES
                </div>

                <h1>Welcome</h1>

                <p>
                    Sign in to manage events, connect with
                    local entrepreneurs, and support businesses
                    in Nasugbu.
                </p>

            </div>

        </div>

    </div>

    <!-- RIGHT SIDE -->

    <div class="form-container">

        <form method="POST">

            <?php if($login_error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>

            <!-- ICON -->

            <div class="login-icon">
                <i class="fa-regular fa-user"></i>
            </div>

            <!-- TITLE -->

            <div class="login-title">
                LOGIN
            </div>

            <!-- EMAIL -->

            <div class="input-group">

                <i class="fa-solid fa-envelope"></i>

                <input
                    type="text"
                    name="email"
                    placeholder="Email or Username"
                    value="<?php echo htmlspecialchars($email_value); ?>"
                    required
                >

            </div>

            <!-- PASSWORD -->

            <div class="input-group">

                <i class="fa-solid fa-lock"></i>

                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Password"
                    value="<?php echo htmlspecialchars($password_value); ?>"
                    required
                >

                <i class="fa-solid fa-eye-slash" id="togglePassword"></i>

            </div>

            <!-- OPTIONS -->

            <div class="options-row">

                <label>
                    <input
                        type="checkbox"
                        name="remember"
                        <?php echo $remember ? 'checked' : ''; ?>
                    >
                    Remember Me
                </label>

                <a href="#">
                    Forgot password?
                </a>

            </div>

            <!-- BUTTON -->

            <button type="submit" name="login">
                Login
            </button>

        </form>

    </div>

</div>

<script>

// PASSWORD TOGGLE

const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');

togglePassword.addEventListener('click', () => {

    if(password.type === 'password'){

        password.type = 'text';

        togglePassword.classList.remove('fa-eye-slash');
        togglePassword.classList.add('fa-eye');

    } else {

        password.type = 'password';

        togglePassword.classList.remove('fa-eye');
        togglePassword.classList.add('fa-eye-slash');

    }

});

</script>

</body>
</html>
