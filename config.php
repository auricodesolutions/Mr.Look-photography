<?php
// config.php
$dsn = 'mysql:host=localhost;dbname=mrlook;charset=utf8mb4';
$db_user = 'mrlook';          // <-- change if needed
$db_pass = 'saKLV0rUNaIUL6HSzZSU';              // <-- change if needed

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
  exit('DB connection failed: ' . $e->getMessage());
}
