<?php
include_once '../includes/session.php';
include_once '../includes/functions.php';

if (empty($_SESSION['register_email']) || empty($_SESSION['verification_code'])) {
    redirect('/NextPickStore/auth/register.php');
}

$errors = $_SESSION['verify_errors'] ?? [];
unset($_SESSION['verify_errors']);

$email = $_SESSION['register_email'];
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verify Email - NextPick</title>
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

            .desc {
                font-size: 13px;
                color: #666;
                margin-bottom: 18px;
                line-height: 1.6;
            }

            .code-row {
                display: flex;
                gap: 8px;
                margin: 14px 0 18px;
            }

            .code-row input {
                width: 44px;
                height: 44px;
                text-align: center;
                border: 1px solid #3b5cff;
                border-radius: 6px;
                font-size: 18px;
                outline: none;
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

            .note {
                margin-bottom: 14px;
                font-size: 12px;
                color: #444;
                background: #f5f7ff;
                border: 1px dashed #9fb2ff;
                padding: 10px;
                border-radius: 6px;
            }

            .links {
                font-size: 12px;
                margin-bottom: 18px;
            }

            .links a {
                color: #2155f5;
                text-decoration: none;
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
                    <div class="circle">2</div>
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

                <?php if (!empty($errors['general'])): ?>
                    <div class="top-error-box"><?php echo $errors['general']; ?></div>
                <?php endif; ?>

                <div class="desc">
                    Check your email inbox. Enter the 6-digit verification code sent to
                    <strong><?php echo htmlspecialchars($email); ?></strong>.
                    The code expires after 60 minutes.
                </div>

                <form action="process_verify_email.php" method="POST">
                    <div class="code-row">
                        <input type="text" name="digit1" maxlength="1" inputmode="numeric" required>
                        <input type="text" name="digit2" maxlength="1" inputmode="numeric" required>
                        <input type="text" name="digit3" maxlength="1" inputmode="numeric" required>
                        <input type="text" name="digit4" maxlength="1" inputmode="numeric" required>
                        <input type="text" name="digit5" maxlength="1" inputmode="numeric" required>
                        <input type="text" name="digit6" maxlength="1" inputmode="numeric" required>
                    </div>

                    <div class="links">
                        <a href="register.php">Change email address</a>
                    </div>

                    <button type="submit" class="btn">Continue</button>
                </form>
            </div>
        </div>
        <script>
            const codeInputs = document.querySelectorAll('.code-row input');

            codeInputs.forEach((input, index) => {
                input.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '');

                    if (this.value.length === 1 && index < codeInputs.length - 1) {
                        codeInputs[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && this.value === '' && index > 0) {
                        codeInputs[index - 1].focus();
                    }
                });

                input.addEventListener('paste', function (e) {
                    e.preventDefault();

                    const pastedData = (e.clipboardData || window.clipboardData)
                            .getData('text')
                            .replace(/\D/g, '')
                            .slice(0, 6);

                    pastedData.split('').forEach((char, i) => {
                        if (codeInputs[i]) {
                            codeInputs[i].value = char;
                        }
                    });

                    const nextIndex = Math.min(pastedData.length, codeInputs.length - 1);
                    codeInputs[nextIndex].focus();
                });
            });
        </script>
    </body>

</html>
