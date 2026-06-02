<?php
$footerPage = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$isAdminFooter = strpos($footerPage, '/roles/admin/') !== false;
$footerBackground = $isAdminFooter ? '#f6f6f6' : '#fff';
?>
<style>
    .footer {
        border-top: 1px solid #ececec;
        background: <?php echo $footerBackground; ?>;
        margin-top: 24px;
        margin-bottom: 10px;
        border-radius: 14px;
        overflow: hidden;
    }

    .footer-top {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 24px;
        padding: 28px;
    }

    .footer h4 {
        font-size: 14px;
        margin-bottom: 12px;
        color: #222;
        font-weight: 700;
    }

    .footer p,
    .footer a,
    .footer li {
        font-size: 13px;
        color: #666;
        line-height: 1.8;
    }

    .footer ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer a {
        text-decoration: none;
    }

    .footer a:hover {
        color: #3158ff;
    }

    .footer-bottom {
        border-top: 1px solid #f0f0f0;
        padding: 16px 28px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 12px;
        color: #666;
    }

    .footer-links {
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
    }

    @media (max-width: 900px) {
        .footer-top {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .footer-top {
            grid-template-columns: 1fr;
        }

        .footer-top,
        .footer-bottom {
            padding-left: 16px;
            padding-right: 16px;
        }
    }
</style>

<footer class="footer">
    <div class="footer-top">
        <div>
            <h4>E-commerce support</h4>
            <p>NEXTPICK</p>
            <p>Manama, Bahrain</p>
            <p>Phone: +973 123 4567</p>
            <p>Email: support@nextpick.com</p>
        </div>
        <div>
            <h4>Working hours</h4>
            <p>Monday to Friday: 09:00 - 18:00</p>
            <p>Saturday: 10:00 - 16:00</p>
            <p>Sunday: Closed</p>
        </div>
        <div>
            <h4>About us</h4>
            <ul>
                <li><a href="#">Stores</a></li>
                <li><a href="#">Corporate website</a></li>
                <li><a href="#">Exclusive Offers</a></li>
                <li><a href="#">Career</a></li>
            </ul>
        </div>
        <div>
            <h4>Help &amp; Support</h4>
            <ul>
                <li><a href="#">Help center</a></li>
                <li><a href="#">Payments</a></li>
                <li><a href="#">Product returns</a></li>
                <li><a href="#">FAQ</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div>&copy; 2024 NEXTPICK. All Rights Reserved.</div>
        <div class="footer-links">
            <a href="#">Privacy policy</a>
            <a href="#">Cookie settings</a>
            <a href="#">Terms and conditions</a>
            <a href="#">Imprint</a>
        </div>
    </div>
</footer>
