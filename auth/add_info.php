<?php
include_once '../includes/session.php';
include_once '../includes/functions.php';

if (empty($_SESSION['register_email']) || empty($_SESSION['email_verified'])) {
    redirect('/NextPickStore/auth/register.php');
}

$errors = $_SESSION['add_info_errors'] ?? [];
$old = $_SESSION['add_info_old'] ?? [];

unset($_SESSION['add_info_errors'], $_SESSION['add_info_old']);

$email = $_SESSION['register_email'];
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Complete Registration - NextPick</title>
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
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .steps {
                display: flex;
                align-items: center;
                gap: 18px;
                margin-bottom: 40px;
                font-size: 13px;
                color: #999;
            }

            .step {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }

            .circle {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                background: #2155f5;
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .circle.inactive {
                background: #c9c9c9;
            }

            .line {
                width: 80px;
                height: 1px;
                background: #c9c9c9;
            }

            .card {
                width: 100%;
                max-width: 420px;
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
            }

            .field-group {
                margin-bottom: 14px;
            }

            input, select {
                width: 100%;
                height: 44px;
                border: 1px solid #3b5cff;
                border-radius: 6px;
                padding: 0 14px;
                outline: none;
                font-size: 14px;
            }

            input.error-input, select.error-input {
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

            .readonly-email {
                background: #f3f4f6;
                color: #555;
            }
        </style>
    </head>
    <body>
        <div class="page-wrapper">
            <div class="steps">
                <div class="step">
                    <div class="circle inactive">1</div>
                    <span>Sign up</span>
                </div>
                <div class="line"></div>
                <div class="step">
                    <div class="circle inactive">2</div>
                    <span>Verify email</span>
                </div>
                <div class="line"></div>
                <div class="step">
                    <div class="circle">3</div>
                    <span>Add info</span>
                </div>
            </div>

            <div class="card">
                <div class="logo">
                    <img src="../assets/images/Logos/nextpickstore-logo.png" alt="NextPickStore Logo">
                </div>

                <h2>Complete your account</h2>
                <div class="desc">Finish setting up your account information.</div>

                <form id="addInfoForm" action="process_add_info.php" method="POST" novalidate>
                    <div class="field-group">
                        <input type="text" value="<?php echo htmlspecialchars($email); ?>" class="readonly-email" readonly>
                    </div>

                    <div class="field-group">
                        <input type="text" name="full_name" id="full_name" placeholder="Full name"
                               value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>"
                               class="<?php echo!empty($errors['full_name']) ? 'error-input' : ''; ?>">
                               <?php if (!empty($errors['full_name'])): ?>
                            <div class="field-error"><?php echo $errors['full_name']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <select name="role_name" id="role_name" class="<?php echo!empty($errors['role_name']) ? 'error-input' : ''; ?>">
                            <option value="">Select account type</option>
                            <option value="Buyer" <?php echo (($old['role_name'] ?? '') === 'Buyer') ? 'selected' : ''; ?>>Buyer</option>
                            <option value="Seller" <?php echo (($old['role_name'] ?? '') === 'Seller') ? 'selected' : ''; ?>>Seller</option>
                        </select>
                        <?php if (!empty($errors['role_name'])): ?>
                            <div class="field-error"><?php echo $errors['role_name']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <input type="text" name="phone_number" id="phone_number" placeholder="Phone number"
                               value="<?php echo htmlspecialchars($old['phone_number'] ?? ''); ?>"
                               class="<?php echo!empty($errors['phone_number']) ? 'error-input' : ''; ?>">
                               <?php if (!empty($errors['phone_number'])): ?>
                            <div class="field-error"><?php echo $errors['phone_number']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <input type="text" name="address" id="address" placeholder="Address"
                               value="<?php echo htmlspecialchars($old['address'] ?? ''); ?>"
                               class="<?php echo!empty($errors['address']) ? 'error-input' : ''; ?>">
                               <?php if (!empty($errors['address'])): ?>
                            <div class="field-error"><?php echo $errors['address']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <input type="password" name="password" id="password" placeholder="Password"
                               class="<?php echo!empty($errors['password']) ? 'error-input' : ''; ?>">
                               <?php if (!empty($errors['password'])): ?>
                            <div class="field-error"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password"
                               class="<?php echo!empty($errors['confirm_password']) ? 'error-input' : ''; ?>">
                               <?php if (!empty($errors['confirm_password'])): ?>
                            <div class="field-error"><?php echo $errors['confirm_password']; ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn">Create Account</button>
                </form>
            </div>
        </div>

        <script>
            const form = document.getElementById('addInfoForm');

            form.addEventListener('submit', function (e) {
                document.querySelectorAll('.js-error').forEach(el => el.remove());

                const fullName = document.getElementById('full_name');
                const roleName = document.getElementById('role_name');
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');

                [fullName, roleName, password, confirmPassword].forEach(el => el.classList.remove('error-input'));

                let hasError = false;

                if (fullName.value.trim() === '') {
                    showError(fullName, 'Full name is required.');
                    hasError = true;
                }

                if (roleName.value.trim() === '') {
                    showError(roleName, 'Please select an account type.');
                    hasError = true;
                }

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

                if (hasError) {
                    e.preventDefault();
                }
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