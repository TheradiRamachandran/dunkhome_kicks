<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function userLoggedIn(): bool { return isset($_SESSION['user_id']); }
function adminLoggedIn(): bool { return isset($_SESSION['admin_id']); }
function appBaseUrl(): string {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directory = rtrim(dirname($scriptName), '/');
    while (preg_match('~/(Admin|User)$~i', $directory)) {
        $directory = rtrim(dirname($directory), '/');
    }
    return ($directory === '' ? '/' : $directory . '/');
}
function appUrl(string $path = ''): string { return appBaseUrl() . ltrim($path, '/'); }
function requireUser(): void { if (!userLoggedIn()) { header('Location: ' . appUrl('User/SignIn.php')); exit; } }
function requireAdmin(): void { if (!adminLoggedIn()) { header('Location: ' . appUrl('Admin/AdminSignIn.php')); exit; } }
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function csrfToken(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verifyCsrf(?string $token): bool { return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$token); }
function logoutAll(string $destination='index.php'): never {
    $_SESSION=[];
    if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),' ',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
    session_destroy();
    header('Location: '.$destination); exit;
}
