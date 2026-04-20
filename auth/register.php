<?php
include_once '../includes/session.php';
include_once '../includes/functions.php';

if (isLoggedIn()) {
    redirectByRole(getUserRole());
}

$errors = $_SESSION['register_errors'] ?? [];
$old = $_SESSION['register_old'] ?? [];

unset($_SESSION['register_errors'], $_SESSION['register_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - NextPick</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }

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
            font-size: 14px;
        }

        .circle.inactive {
            background: #c9c9c9;
            color: #fff;
        }

        .line {
            width: 80px;
            height: 1px;
            background: #c9c9c9;
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
            font-size: 28px;
            margin-bottom: 12px;
            color: #1b1b1b;
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

        .btn-secondary {
            width: 100%;
            height: 44px;
            border: 1px solid #2155f5;
            border-radius: 6px;
            background: #fff;
            color: #2155f5;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 14px;
        }

        .small-text {
            margin-top: 18px;
            font-size: 12px;
            color: #666;
        }

        .top-error-box {
            background: #fff1f0;
            border: 1px solid #ffb3b3;
            color: #d93025;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 14px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="steps">
            <div class="step">
                <div class="circle">1</div>
                <span>Sign up</span>
            </div>
            <div class="line"></div>
            <div class="step">
                <div class="circle inactive">2</div>
                <span>Verify email</span>
            </div>
            <div class="line"></div>
            <div class="step">
                <div class="circle inactive">3</div>
                <span>Add info</span>
            </div>
        </div>

        <div class="card">
            <div class="logo">
                    <img src="../assets/images/Logos/nextpickstore-logo.png" alt="NextPickStore Logo">
            </div>

            <h2>Create an account</h2>
            <div class="desc">
                Enter your email address below. We will send you a 6-digit code to verify and secure your account.
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="top-error-box"><?php echo $errors['general']; ?></div>
            <?php endif; ?>

            <form id="registerEmailForm" action="process_register.php" method="POST" novalidate>
                <div class="field-group">
                    <input
                        type="email"
                        name="email"
                        id="email"
                        placeholder="Email"
                        value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>"
                        class="<?php echo !empty($errors['email']) ? 'error-input' : ''; ?>"
                    >
                    <?php if (!empty($errors['email'])): ?>
                        <div class="field-error"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn">Continue</button>
            </form>

            <div class="small-text">Have you already created an account?</div>
            <a href="login.php" class="btn-secondary">Login</a>
        </div>
    </div>

    <script>
        const form = document.getElementById('registerEmailForm');
        const emailInput = document.getElementById('email');

        function isValidEmail(email) {
            const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return pattern.test(email);
        }

        form.addEventListener('submit', function(e) {
            document.querySelectorAll('.js-error').forEach(el => el.remove());
            emailInput.classList.remove('error-input');

            let hasError = false;

            if (emailInput.value.trim() === '') {
                showError(emailInput, 'Email is required.');
                hasError = true;
            } else if (!isValidEmail(emailInput.value.trim())) {
                showError(emailInput, 'Please enter a valid email address.');
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