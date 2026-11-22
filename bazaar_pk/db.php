<?php
// db.php — Database connection
// This file is included at the top of every other PHP file.
// It gives all pages access to the $conn variable to talk to MySQL.

$host = 'localhost';
$db   = 'bazaar_pk';
$user = 'root';
$pass = '';


// $host     = "sql211.infinityfree.com";   // XAMPP always runs MySQL on localhost
// $dbname   = "if0_41913609_bazaar_pk";  // The database name we just created
// $username = "if0_41913609";        // Default XAMPP MySQL username
// $password = "abcd1234bazaar";            // Default XAMPP MySQL password is empty



try {
    // PDO is the modern, safe way to connect PHP to MySQL
    // It protects against SQL injection attacks automatically
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    // This line makes PDO throw errors instead of failing silently
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // This makes fetched rows return as arrays with named keys
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // If connection fails, stop everything and show the error
    die("Database connection failed: " . $e->getMessage());
}
?>