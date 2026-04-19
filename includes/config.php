<?php
function getConnection() {

    $host = $_SERVER['HTTP_HOST'];

    // Local XAMPP
    if ($host === 'localhost' || $host === '127.0.0.1') {
        $db_host = "localhost";
        $db_user = "root";
        $db_pass = "";
        $db_name = "nextpickstore"; // your local DB name
    }
    // Lab / server
    else {
        $db_host = "localhost";
        $db_user = "uYOURID";
        $db_pass = "uYOURID";
        $db_name = "dbYOURID";
    }

    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

    if (!$conn) {
        die("Database Connection Failed: " . mysqli_connect_error());
    }

    return $conn;
}
?>