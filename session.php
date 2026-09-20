<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function userLoggedIn(): bool { return isset($_SESSION['user_id']); }
function adminLoggedIn(): bool { return isset($_SESSION['admin_id']); }
function requireUser(): void { if (!userLoggedIn()) { header('Location: SignIn.php'); exit; } }
function requireAdmin(): void { if (!adminLoggedIn()) { header('Location: AdminSignIn.php'); exit; } }
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function csrfToken(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verifyCsrf(?string $token): bool { return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$token); }
function logoutAll(string $destination='index.php'): never {
    $_SESSION=[];
    if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),' ',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
    session_destroy();
    header('Location: '.$destination); exit;
}
