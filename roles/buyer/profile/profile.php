<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';
include_once '../../../includes/functions.php';

requireRole(['Buyer']);

$conn = getConnection();

$buyerId = $_SESSION['user_id'] ?? 0;
$buyerName = $_SESSION['full_name'] ?? 'Buyer';

$errors = $_SESSION['profile_errors'] ?? [];
$success = $_SESSION['profile_success'] ?? '';
$old = $_SESSION['profile_old'] ?? [];

unset($_SESSION['profile_errors'], $_SESSION['profile_success'], $_SESSION['profile_old']);

$profileData = null;

$sql = "SELECT user_id, full_name, email, phone_number, address
        FROM nps_users
        WHERE user_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $buyerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profileData = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$profileData) {
    die("Buyer profile not found.");
}

$fullName = $old['full_name'] ?? $profileData['full_name'];
$email = $old['email'] ?? $profileData['email'];
$phoneNumber = $old['phone_number'] ?? $profileData['phone_number'];
$address = $old['address'] ?? $profileData['address'];
$userId = $profileData['user_id'];
?>
<div class="profile-container">

    <div class="profile-header">
        <div class="profile-image">
            <h1><?php echo strtoupper(substr($buyerName, 0,2));?></h1>
        </div>

        <div class="profile-info">
            <h2><?php echo $buyerName;?></h2>
            <p>Manage your personal information</p>
        </div>
    </div>

    <form class="profile-form">

        <div class="input-group">
            <label>Full Name</label>
            <input type="text" Value="<?php echo $fullName;?>">
        </div>

        <div class="input-group">
            <label>Email Address</label>
            <input type="email"  value="<?php echo $email?>">
        </div>

        <div class="input-group">
            <label>Phone Number</label>
            <input type="text" value="<?php echo $phoneNumber;?>">
        </div>

        <div class="input-group">
            <label>Password</label>
            <input type="password" placeholder="Enter your password">
        </div>

        <div class="input-group full-width">
            <label>Address</label>
            <textarea placeholder="Enter your address" value="<?php echo $address?>"></textarea>
        </div>

        <button type="submit" class="save-btn">
            Save Changes
        </button>

    </form>

</div>
<style>
.profile-container{
    width: 90%;
    max-width: 950px;
    margin: 40px auto;
    background: white;
    border-radius: 18px;
    padding: 35px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}

.profile-header{
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 35px;
    padding-bottom: 25px;
    border-bottom: 1px solid #eee;
}

.profile-image {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #1A4DE1;
    background-color: #1A4DE1;
    text-align: center;
    color:white;
}

.profile-info h2{
    font-size: 28px;
    color: #222;
    margin-bottom: 6px;
}

.profile-info p{
    color: #777;
    font-size: 15px;
}

.profile-form{
    display: grid;
    grid-template-columns: repeat(2,1fr);
    gap: 24px;
}

.input-group{
    display: flex;
    flex-direction: column;
}

.input-group label{
    margin-bottom: 8px;
    font-size: 15px;
    font-weight: 600;
    color: #333;
}

.input-group input,
.input-group textarea{
    width: 100%;
    padding: 14px;
    border-radius: 10px;
    border: 1px solid #ddd;
    background: #f9f9f9;
    font-size: 15px;
    transition: 0.3s;
}

.input-group input:focus,
.input-group textarea:focus{
    border-color: #1A4DE1;
    background: white;
    outline: none;
}

.input-group textarea{
    resize: none;
    height: 120px;
}

.full-width{
    grid-column: span 2;
}

.save-btn{
    width: 220px;
    height: 50px;
    border: none;
    border-radius: 12px;
    background: #1A4DE1;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.save-btn:hover{
    background: #153bb8;
}

@media(max-width:768px){

    .profile-form{
        grid-template-columns: 1fr;
    }

    .full-width{
        grid-column: span 1;
    }

    .profile-header{
        flex-direction: column;
        text-align: center;
    }

    .save-btn{
        width: 100%;
    }
}
</style>