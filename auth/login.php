<?php
include_once '../includes/session.php';
include_once '../includes/functions.php';

if (isLoggedIn()) {
    redirectByRole(getUserRole());
}

unset($_SESSION['login_errors'], $_SESSION['login_old']);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - NextPick</title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
            }

            body {
                background: #d9d9d9;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 30px;
            }

            .page-wrapper {
                width: 100%;
                max-width: 1200px;
                min-height: 720px;
                background: #f8f8f8;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                padding: 40px 20px;
            }

            .login-card {
                width: 100%;
                max-width: 340px;
                background: #ffffff;
                border: 1px solid #d8d8d8;
                border-radius: 8px;
                padding: 28px 24px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            }

            .logo {
                text-align: center;
                margin-bottom: 28px;
            }

            .logo img {
                max-width: 140px;
                height: auto;
                display: inline-block;
            }

            h2 {
                font-size: 28px;
                font-weight: 700;
                color: #1b1b1b;
                margin-bottom: 24px;
                line-height: 1.3;
            }

            .field-group {
                margin-bottom: 14px;
            }

            .input-wrapper {
                position: relative;
            }

            .input-wrapper input {
                width: 100%;
                height: 44px;
                border: 1px solid #3b5cff;
                border-radius: 6px;
                padding: 0 14px 0 40px;
                outline: none;
                font-size: 14px;
                background: #fff;
            }

            .input-wrapper input.error-input {
                border-color: #ff4d4f;
                background: #fffafa;
            }

            .input-icon {
                position: absolute;
                top: 50%;
                left: 12px;
                transform: translateY(-50%);
                color: #7a7a7a;
                font-size: 14px;
            }

            .toggle-password {
                position: absolute;
                top: 50%;
                right: 12px;
                transform: translateY(-50%);
                border: none;
                background: transparent;
                cursor: pointer;
                color: #7a7a7a;
                font-size: 14px;
            }

            .field-error {
                color: #ff4d4f;
                font-size: 12px;
                margin-top: 6px;
                padding-left: 2px;
            }

            .forgot-password {
                display: inline-block;
                margin: 4px 0 16px;
                font-size: 12px;
                color: #5c78ff;
                text-decoration: none;
            }

            .forgot-password:hover {
                text-decoration: underline;
            }

            .login-btn {
                width: 100%;
                height: 44px;
                border: none;
                border-radius: 6px;
                background: #2155f5;
                color: #fff;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: 0.2s;
            }

            .login-btn:hover {
                background: #1848d9;
            }

            .divider {
                display: flex;
                align-items: center;
                gap: 12px;
                margin: 18px 0;
                color: #8d8d8d;
                font-size: 12px;
                justify-content: center;
            }

            .divider::before,
            .divider::after {
                content: "";
                flex: 1;
                height: 1px;
                background: #d8d8d8;
            }

            .register-link {
                display: block;
                text-align: center;
                font-size: 13px;
                color: #2155f5;
                text-decoration: none;
                font-weight: 500;
            }

            .register-link:hover {
                text-decoration: underline;
            }

            .top-error-box {
                background: #fff1f0;
                border: 1px solid #ffb3b3;
                color: #d93025;
                padding: 10px 12px;
                border-radius: 6px;
                margin-bottom: 16px;
                font-size: 13px;
            }

            @media (max-width: 480px) {
                .page-wrapper {
                    padding: 20px 12px;
                }

                .login-card {
                    padding: 24px 18px;
                }

                h2 {
                    font-size: 24px;
                }
            }
        </style>
    </head>
    <body>
        <div class="page-wrapper">
            <div class="login-card">
                <div class="logo">
                    <img src="../assets/images/Logos/nextpickstore-logo.png" alt="NextPickStore Logo">
                </div>

                <h2>Log into Your Account</h2>

                <?php if (isset($_GET['timeout'])): ?>
                    <div class="top-error-box">Your session has expired. Please log in again.</div>
                <?php endif; ?>

                <?php if (!empty($errors['general'])): ?>
                    <div class="top-error-box"><?php echo $errors['general']; ?></div>
                <?php endif; ?>

                <form id="loginForm" action="process_login.php" method="POST" novalidate>
                    <div class="field-group">
                        <div class="input-wrapper">
                            <span class="input-icon">✉</span>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                placeholder="Email"
                                value="<?php echo isset($old['email']) ? htmlspecialchars($old['email']) : ''; ?>"
                                class="<?php echo!empty($errors['email']) ? 'error-input' : ''; ?>"
                                >
                        </div>
                        <?php if (!empty($errors['email'])): ?>
                            <div class="field-error"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                placeholder="Password"
                                class="<?php echo!empty($errors['password']) ? 'error-input' : ''; ?>"
                                >
                            <button type="button" class="toggle-password" onclick="togglePassword()">👁</button>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="field-error"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>

                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                    
                    <button type="submit" class="login-btn">Login</button>

                    <div class="divider">OR</div>

                    <a href="register.php" class="register-link">Create an account</a>
                </form>
            </div>
        </div>

        <script>
            const loginForm = document.getElementById('loginForm');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');

            function togglePassword() {
                passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
            }

            function isValidEmail(email) {
                const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return pattern.test(email);
            }

            loginForm.addEventListener('submit', function (e) {
                let hasError = false;

                document.querySelectorAll('.field-error.js-error').forEach(el => el.remove());
                emailInput.classList.remove('error-input');
                passwordInput.classList.remove('error-input');

                if (emailInput.value.trim() === '') {
                    showError(emailInput, 'Email is required.');
                    hasError = true;
                } else if (!isValidEmail(emailInput.value.trim())) {
                    showError(emailInput, 'Please enter a valid email address.');
                    hasError = true;
                }

                if (passwordInput.value.trim() === '') {
                    showError(passwordInput, 'Password is required.');
                    hasError = true;
                }

                if (hasError) {
                    e.preventDefault();
                }
            });

            function showError(input, message) {
                input.classList.add('error-input');

                const error = document.createElement('div');
                error.className = 'field-error js-error';
                error.textContent = message;

                input.closest('.field-group').appendChild(error);
            }
        </script>
    </body>
</html>