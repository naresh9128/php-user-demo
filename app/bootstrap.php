<?php
declare(strict_types=1);
ini_set('display_errors', '0');
session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax', 'secure'=>getenv('COOKIE_SECURE') === '1']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        try {
            $pdo = new PDO('mysql:host='.(getenv('DB_HOST') ?: 'db').';dbname='.(getenv('DB_NAME') ?: 'user_demo').';charset=utf8mb4', getenv('DB_USER') ?: 'demo_user', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        } catch (PDOException $e) { error_log($e->getMessage()); http_response_code(503); exit('Database unavailable. Please try again later.'); }
    }
    return $pdo;
}
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function go(string $page): never { header('Location: /?page='.$page); exit; }
function user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $q=db()->prepare('SELECT id,name,email,phone,role,created_at FROM users WHERE id=?');
    $q->execute([$_SESSION['uid']]); return $q->fetch() ?: null;
}
function csrf(): string {
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';
}
function check_csrf(): void {
    if (!isset($_POST['csrf'],$_SESSION['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) { http_response_code(403); exit('Invalid form token. Reload the page and try again.'); }
}
function profile_data(): array {
    $name=trim((string)($_POST['name'] ?? ''));
    $email=strtolower(trim((string)($_POST['email'] ?? '')));
    $phone=trim((string)($_POST['phone'] ?? ''));
    if ($name==='' || strlen($name)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($phone)>20 || !preg_match('/^[0-9+() .-]*$/',$phone)) throw new RuntimeException('Enter a name (up to 100 bytes), valid email, and valid phone (up to 20 characters).');
    return [$name,$email,$phone];
}
