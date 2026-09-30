<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$email=strtolower(trim((string)fgets(STDIN)));
if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { fwrite(STDERR,"Enter a valid registered email.\n"); exit(1); }
$pdo=new PDO('mysql:host='.getenv('DB_HOST').';dbname='.getenv('DB_NAME').';charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$q=$pdo->prepare('SELECT id FROM users WHERE email=?'); $q->execute([$email]); $id=$q->fetchColumn();
if (!$id) { fwrite(STDERR,"Register this account first.\n"); exit(1); }
$q=$pdo->prepare("UPDATE users SET role='admin' WHERE id=?"); $q->execute([$id]);
echo "Administrator access enabled.\n";
