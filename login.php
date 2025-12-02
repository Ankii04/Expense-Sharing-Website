<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        error_log("Login attempt - Email: " . $username);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = "User not found with email: " . $username;
            error_log($error);
        } else {
            error_log("User found with ID: " . $user['id']);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['email'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['avatar'] = $user['avatar'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password";
                error_log($error);
            }
        }
    } catch (PDOException $e) {
        $error = "An error occurred. Please try again.";
        error_log("Login error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        .particles-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            pointer-events: none;
            transform-style: preserve-3d;
            animation: float 20s infinite linear;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
        }

        @keyframes float {
            0% {
                transform: translateY(110vh) translateX(-50px) translateZ(0) rotate(0deg) scale(1);
                opacity: 0;
            }
            10% {
                opacity: 0.8;
                transform: translateY(90vh) translateX(0) translateZ(20px) rotate(90deg) scale(1.2);
            }
            50% {
                transform: translateY(50vh) translateX(100px) translateZ(50px) rotate(180deg) scale(0.8);
            }
            90% {
                opacity: 0.8;
                transform: translateY(10vh) translateX(50px) translateZ(20px) rotate(270deg) scale(1.2);
            }
            100% {
                transform: translateY(-10vh) translateX(0) translateZ(0) rotate(360deg) scale(1);
                opacity: 0;
            }
        }

        .login-container {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.95);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #666;
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
            position: relative;
        }

        .password-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            padding: 0;
            font-size: 1.1rem;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .form-control {
            height: 50px;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            width: 100%;
            height: 50px;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background-color: #ddd;
        }

        .divider::before { left: 0; }
        .divider::after { right: 0; }

        .social-signin {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 1.5rem 0;
        }

        .g_id_signin {
            display: flex;
            justify-content: center;
        }

        .signup-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .g_id_signin > div {
            margin: 0 auto !important;
        }
        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .signup-link a:hover {
            text-decoration: underline;
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
        }
        .forgot-password a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="particles-container">
        <?php for($i = 0; $i < 50; $i++): ?>
            <div class="particle" style="
                left: <?php echo rand(-20, 120); ?>vw;
                width: <?php echo rand(4, 12); ?>px;
                height: <?php echo rand(4, 12); ?>px;
                animation-delay: <?php echo $i * 0.3; ?>s;
                animation-duration: <?php echo rand(15, 25); ?>s;
                transform: translateZ(<?php echo rand(0, 150); ?>px);
                filter: blur(<?php echo rand(0, 1); ?>px);
            "></div>
        <?php endfor; ?>
    </div>

    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Sign in to continue to Expense Maker</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="text" class="form-control" id="username" name="username" placeholder="Email" required>
            </div>
            <div class="form-group">
                <div class="password-group">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <div class="forgot-password">
                    <a href="forgot-password.php">Forgot Password?</a>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>

        <div class="divider">
            <span>or</span>
        </div>

        <div class="social-signin">
            <div id="g_id_onload"
                 data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                 data-context="signin"
                 data-ux_mode="popup"
                 data-callback="handleGoogleSignIn"
                 data-auto_prompt="false">
            </div>

            <div class="g_id_signin"
                 data-type="standard"
                 data-shape="rectangular"
                 data-theme="outline"
                 data-text="signin_with"
                 data-size="large"
                 data-width="300"
                 data-logo_alignment="center">
            </div>
        </div>

        <div class="signup-link">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.classList.remove('fa-eye');
                toggleButton.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleButton.classList.remove('fa-eye-slash');
                toggleButton.classList.add('fa-eye');
            }
        }

        function handleGoogleSignIn(response) {
            // Show loading state
            document.body.style.cursor = 'wait';
            
            fetch('google-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    credential: response.credential
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    throw new Error(data.message || 'Authentication failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'Error signing in with Google');
                document.body.style.cursor = 'default';
            });
        }

        // Pre-connect to dashboard to make redirection faster
        let preconnectLink = document.createElement('link');
        preconnectLink.rel = 'preconnect';
        preconnectLink.href = 'dashboard.php';
        document.head.appendChild(preconnectLink);
    </script>
</body>
</html>