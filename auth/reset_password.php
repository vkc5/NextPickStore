<?php
include_once '../includes/session.php';
include_once '../includes/functions.php';

if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_verified'])) {
    redirect('/NextPickStore/auth/forgot_password.php');
}

$errors = $_SESSION['reset_password_errors'] ?? [];
unset($_SESSION['reset_password_errors']);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Password - NextPick</title>
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
                border-radius: 6px;
                padding: 40px 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .card {
                width: 100%;
                max-width: 360px;
                background: #fff;
                border: 1px solid #d8d8d8;
                border-radius: 8px;
                padding: 28px 24px;
            }
            .logo {
                text-align: center;
                margin-bottom: 24px;
            }
            .logo img {
                max-width: 140px;
                height: auto;
            }
            h2 {
                font-size: 26px;
                margin-bottom: 10px;
            }
            .desc {
                font-size: 13px;
                color: #666;
                margin-bottom: 18px;
                line-height: 1.5;
            }
            .field-group {
                margin-bottom: 14px;
            }
            input {
                width: 100%;
                height: 44px;
                border: 1px solid #3b5cff;
                border-radius: 6px;
                padding: 0 14px;
                outline: none;
                font-size: 14px;
            }
            input.error-input {
                border-color: #ff4d4f;
                background: #fffafa;
            }
            .field-error {
                color: #ff4d4f;
                font-size: 12px;
                margin-top: 6px;
            }
            .btn {
                width: 100%;
                height: 44px;
                border: none;
                border-radius: 6px;
                background: #2155f5;
                color: #fff;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                margin-top: 6px;
            }
        </style>
    </head>
    <body>
        <div class="page-wrapper">
            <div class="card">
                <div class="logo">
                    <img src="../assets/images/Logos/nextpickstore-logo.png" alt="NextPickStore Logo">
                </div>

                <h2>Reset Password</h2>
                <div class="desc">Enter your new password below.</div>

                <form id="resetPasswordForm" action="process_reset_password.php" method="POST" novalidate>
                    <div class="field-group">
                        <input type="password" name="password" id="password" placeholder="New password"
                               class="<?php echo!empty($errors['password']) ? 'error-input' : ''; ?>">
                               <?php if (!empty($errors['password'])): ?>
                            <div class="field-error"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password"
                               class="<?php echo!empty($errors['confirm_password']) ? 'error-input' : ''; ?>">
                               <?php if (!empty($errors['confirm_password'])): ?>
                            <div class="field-error"><?php echo $errors['confirm_password']; ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn">Update Password</button>
                </form>
            </div>
        </div>

        <script>
            const form = document.getElementById('resetPasswordForm');

            form.addEventListener('submit', function (e) {
                document.querySelectorAll('.js-error').forEach(el => el.remove());

                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');

                [password, confirmPassword].forEach(el => el.classList.remove('error-input'));

                let hasError = false;

                if (password.value.trim() === '') {
                    showError(password, 'Password is required.');
                    hasError = true;
                } else if (password.value.length < 8) {
                    showError(password, 'Password must be at least 8 characters.');
                    hasError = true;
                }

                if (confirmPassword.value.trim() === '') {
                    showError(confirmPassword, 'Please confirm your password.');
                    hasError = true;
                } else if (password.value !== confirmPassword.value) {
                    showError(confirmPassword, 'Passwords do not match.');
                    hasError = true;
                }

                if (hasError)
                    e.preventDefault();
            });

            function showError(input, message) {
                input.classList.add('error-input');
                const error = document.createElement('div');
                error.className = 'field-error js-error';
                error.textContent = message;
                input.parentElement.appendChild(error);
            }
        </script>
    </body>
</html>