<?php
require_once __DIR__.'/config.php';

$token = $_GET['token'] ?? '';
if (!$token) { http_response_code(400); exit('Missing token.'); }

$pdo = db();
$stmt = $pdo->prepare("SELECT id, verification_expires, email_verified FROM users WHERE verification_token = :t");
$stmt->execute([':t'=>$token]);
$user = $stmt->fetch();

if (!$user) { http_response_code(400); exit('Invalid token.'); }
if ($user['email_verified']) { header('Location: dashboard.php'); exit; }
if (strtotime($user['verification_expires']) < time()) { http_response_code(400); exit('Verification link expired.'); }

$pdo->prepare("UPDATE users SET email_verified=1, verification_token=NULL, verification_expires=NULL WHERE id=:id")->execute([':id'=>$user['id']]);

$_SESSION['user_id'] = $user['id']; // auto-login after verify
header('Location: dashboard.php');
exit;
