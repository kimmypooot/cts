<?php
// Optimized database connection
$servername = 'localhost';
$username = 'u390694310_cscro8';
$password = 'civilService@ro08';
$dbname = 'u390694310_cscro8';

try {
    $conn = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            PDO::ATTR_PERSISTENT => false, // Avoid persistent connections on shared hosting
            PDO::ATTR_TIMEOUT => 10 // Connection timeout
        ]
    );
} catch (PDOException $e) {
    // Log the error instead of displaying it
    error_log("Database connection failed: " . $e->getMessage());
    
    // Return generic error to user
    if (php_sapi_name() !== 'cli') {
        header('HTTP/1.1 503 Service Unavailable');
        header('Retry-After: 300');
        echo json_encode(['error' => 'Service temporarily unavailable']);
        exit();
    } else {
        die("Database connection failed\n");
    }
}
?>