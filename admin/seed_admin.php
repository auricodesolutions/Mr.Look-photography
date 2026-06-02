<?php
// seed_admin.php
require __DIR__ . '/config.php';

$email     = 'admin@mrlook.com';  // change
$username  = 'mrlookadmin';       // change
$plainPass = 'ChangeMe!123';      // change immediately
$phone     = '+94 77 000 0000';   // optional

$hash = password_hash($plainPass, PASSWORD_DEFAULT);

$sql = "INSERT INTO admin_users (email, username, password_hash, contact_number)
        VALUES (:email, :username, :hash, :phone)";
$stmt = $pdo->prepare($sql);

try {
  $stmt->execute([
    ':email'    => $email,
    ':username' => $username,
    ':hash'     => $hash,
    ':phone'    => $phone
  ]);
  echo "✅ Admin created: $email / $username";
} catch (PDOException $e) {
  if ($e->getCode() === '23000') {
    echo "⚠️ Email or username already exists.";
  } else {
    echo "❌ Error: " . $e->getMessage();
  }
}
