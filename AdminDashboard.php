<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================================================
   ADMIN AUTHENTICATION
   ========================================================= */

if (!isset($_SESSION['admin_id'])) {
    header('Location: AdminSignIn.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$adminEmail = (string) ($_SESSION['admin_email'] ?? '');

/* =========================================================
   LOGOUT
   ========================================================= */

if (isset($_GET['action']) && $_GET['action'] === 'logout') {

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: AdminSignIn.php');
    exit;
}

/* =========================================================
   HELPER FUNCTIONS
   ========================================================= */

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function getInitials(string $email): string
{
    $email = trim($email);

    if ($email === '') {
        return 'AD';
    }

    $name = explode('@', $email)[0] ?? '';

    $name = preg_replace('/[^a-zA-Z0-9]+/', ' ', $name);

    if (!$name) {
        return 'AD';
    }

    $parts = preg_split('/\s+/', trim($name));

    if (count($parts) >= 2) {
        return strtoupper(
            substr($parts[0], 0, 1) .
            substr($parts[1], 0, 1)
        );
    }

    return strtoupper(substr($parts[0], 0, 2));
}

function formatDate(string $date): string
{
    $time = strtotime($date);

    if (!$time) {
        return $date;
    }

    return date('M d, Y · h:i A', $time);
}

/* =========================================================
   VERIFY ADMIN
   ========================================================= */

$stmt = $conn->prepare(
    "SELECT id, email, created_at
     FROM admins
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    die('Unable to verify administrator account.');
}

$stmt->bind_param('i', $adminId);
$stmt->execute();

$result = $stmt->get_result();
$currentAdmin = $result ? $result->fetch_assoc() : null;

$stmt->close();

if (!$currentAdmin) {

    $_SESSION = [];

    session_destroy();

    header('Location: AdminSignIn.php');
    exit;
}

$adminEmail = (string) $currentAdmin['email'];
$adminInitials = getInitials($adminEmail);

/* =========================================================
   DASHBOARD STATISTICS
   ========================================================= */

$totalUsers = 0;
$totalAdmins = 0;
$usersToday = 0;
$adminsToday = 0;

/* Total users */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users"
);

if ($result) {
    $row = $result->fetch_assoc();
    $totalUsers = (int) ($row['total'] ?? 0);
}

/* Total administrators */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM admins"
);

if ($result) {
    $row = $result->fetch_assoc();
    $totalAdmins = (int) ($row['total'] ?? 0);
}

/* Users registered today */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE DATE(created_at) = CURDATE()"
);

if ($result) {
    $row = $result->fetch_assoc();
    $usersToday = (int) ($row['total'] ?? 0);
}

/* Admins registered today */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM admins
     WHERE DATE(created_at) = CURDATE()"
);

if ($result) {
    $row = $result->fetch_assoc();
    $adminsToday = (int) ($row['total'] ?? 0);
}

/* =========================================================
   RECENT USERS
   ========================================================= */

$recentUsers = [];

$result = $conn->query(
    "SELECT id, email, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 8"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $recentUsers[] = $row;
    }
}

/* =========================================================
   RECENT ADMINS
   ========================================================= */

$recentAdmins = [];

$result = $conn->query(
    "SELECT id, email, created_at
     FROM admins
     ORDER BY created_at DESC
     LIMIT 6"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $recentAdmins[] = $row;
    }
}

/* =========================================================
   USER SEARCH AJAX
   ========================================================= */

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'search_users'
) {

    header('Content-Type: application/json; charset=utf-8');

    $query = trim((string) ($_GET['q'] ?? ''));

    if ($query === '') {

        echo json_encode([
            'status' => 'success',
            'users' => []
        ]);

        exit;
    }

    $search = '%' . $query . '%';

    $stmt = $conn->prepare(
        "SELECT id, email, created_at
         FROM users
         WHERE email LIKE ?
         ORDER BY created_at DESC
         LIMIT 10"
    );

    if (!$stmt) {

        echo json_encode([
            'status' => 'error',
            'message' => 'Search unavailable.'
        ]);

        exit;
    }

    $stmt->bind_param('s', $search);
    $stmt->execute();

    $result = $stmt->get_result();

    $users = [];

    if ($result) {

        while ($row = $result->fetch_assoc()) {

            $users[] = [
                'id' => (int) $row['id'],
                'email' => (string) $row['email'],
                'created_at' => (string) $row['created_at']
            ];
        }
    }

    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'users' => $users
    ]);

    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#07100d"
    >

    <title>
        Admin Dashboard | DunkHome Kicks
    </title>

    <link
        rel="icon"
        type="image/jpeg"
        href="image/logo.jpeg"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

<style>

/* =========================================================
   VARIABLES
   ========================================================= */

:root {

    --bg:
        #07100d;

    --bg2:
        #0b1713;

    --panel:
        rgba(14, 29, 24, .84);

    --panel-soft:
        rgba(255,255,255,.035);

    --white:
        #f8faf8;

    --text:
        #dbe5df;

    --muted:
        #91a199;

    --muted2:
        #62726a;

    --line:
        rgba(255,255,255,.085);

    --line2:
        rgba(255,255,255,.14);

    --green:
        #79e6aa;

    --green2:
        #45c980;

    --red:
        #ff7c7c;

    --orange:
        #ff9c65;

    --blue:
        #82b8ff;

    --shadow:
        0 25px 70px rgba(0,0,0,.35);

    --radius:
        20px;
}

/* =========================================================
   RESET
   ========================================================= */

* {
    box-sizing:
        border-box;
}

html {
    scroll-behavior:
        smooth;
}

body {

    margin:
        0;

    min-height:
        100vh;

    overflow-x:
        hidden;

    color:
        var(--white);

    font-family:
        "DM Sans",
        sans-serif;

    background:

        radial-gradient(
            circle at 8% 0%,
            rgba(121,230,170,.10),
            transparent 25%
        ),

        radial-gradient(
            circle at 92% 90%,
            rgba(255,156,101,.06),
            transparent 24%
        ),

        linear-gradient(
            135deg,
            #06100d,
            #0a1713,
            #06100d
        );
}

body::before {

    content:
        "";

    position:
        fixed;

    inset:
        0;

    pointer-events:
        none;

    opacity:
        .12;

    background-image:

        linear-gradient(
            rgba(255,255,255,.025) 1px,
            transparent 1px
        ),

        linear-gradient(
            90deg,
            rgba(255,255,255,.025) 1px,
            transparent 1px
        );

    background-size:
        45px 45px;

    mask-image:
        linear-gradient(
            to bottom,
            black,
            transparent 90%
        );
}

button,
input {
    font:
        inherit;
}

a {
    color:
        inherit;

    text-decoration:
        none;
}

/* =========================================================
   APPLICATION
   ========================================================= */

.app {

    min-height:
        100vh;

    display:
        grid;

    grid-template-columns:
        255px minmax(0, 1fr);
}

/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar {

    position:
        sticky;

    top:
        0;

    height:
        100vh;

    z-index:
        50;

    display:
        flex;

    flex-direction:
        column;

    padding:
        20px 15px;

    border-right:
        1px solid var(--line);

    background:
        rgba(6,14,11,.97);

    backdrop-filter:
        blur(20px);
}

.brand {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    padding:
        4px 9px 26px;
}

.logo {

    width:
        42px;

    height:
        42px;

    object-fit:
        cover;

    border-radius:
        13px;

    border:
        1px solid var(--line2);

    box-shadow:
        0 12px 25px rgba(0,0,0,.3);
}

.brand-name {

    font-size:
        12px;

    font-weight:
        800;

    letter-spacing:
        .13em;

    text-transform:
        uppercase;
}

.brand-name span {
    color:
        var(--green);
}

.nav-title {

    padding:
        0 10px 10px;

    color:
        #52625a;

    font-size:
        8px;

    font-weight:
        800;

    letter-spacing:
        .18em;

    text-transform:
        uppercase;
}

.nav {

    display:
        grid;

    gap:
        5px;
}

.nav-link {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;

    padding:
        11px 12px;

    border:
        1px solid transparent;

    border-radius:
        13px;

    color:
        #819089;

    font-size:
        10px;

    font-weight:
        700;

    transition:
        .22s ease;
}

.nav-link i {

    width:
        18px;

    text-align:
        center;

    font-size:
        10px;
}

.nav-link:hover {

    color:
        #e0eae4;

    background:
        rgba(255,255,255,.035);

    border-color:
        var(--line);

    transform:
        translateX(2px);
}

.nav-link.active {

    color:
        #06170f;

    background:
        linear-gradient(
            135deg,
            var(--green),
            #55d892
        );

    box-shadow:
        0 12px 28px rgba(121,230,170,.12);
}

.sidebar-bottom {

    margin-top:
        auto;
}

.admin-mini {

    display:
        flex;

    align-items:
        center;

    gap:
        9px;

    padding:
        10px;

    border:
        1px solid var(--line);

    border-radius:
        15px;

    background:
        rgba(255,255,255,.025);
}

.avatar {

    width:
        35px;

    height:
        35px;

    flex:
        0 0 35px;

    display:
        grid;

    place-items:
        center;

    border-radius:
        11px;

    color:
        #06170f;

    background:
        linear-gradient(
            135deg,
            var(--green),
            var(--green2)
        );

    font-size:
        10px;

    font-weight:
        900;
}

.admin-info {
    min-width:
        0;
}

.admin-info strong {

    display:
        block;

    overflow:
        hidden;

    color:
        #dce7e1;

    font-size:
        9px;

    white-space:
        nowrap;

    text-overflow:
        ellipsis;
}

.admin-info span {

    display:
        block;

    margin-top:
        3px;

    color:
        #53645c;

    font-size:
        7px;
}

.logout {
    margin-top:
        8px;
}

.logout:hover {
    color:
        #ffaaaa;

    border-color:
        rgba(255,124,124,.15);

    background:
        rgba(255,124,124,.05);
}

/* =========================================================
   MAIN
   ========================================================= */

.main {
    min-width:
        0;
}

.topbar {

    position:
        sticky;

    top:
        0;

    z-index:
        40;

    height:
        76px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

    padding:
        0 30px;

    border-bottom:
        1px solid var(--line);

    background:
        rgba(7,16,13,.78);

    backdrop-filter:
        blur(18px);
}

.page-label small {

    display:
        block;

    margin-bottom:
        4px;

    color:
        var(--green);

    font-size:
        8px;

    font-weight:
        800;

    letter-spacing:
        .18em;

    text-transform:
        uppercase;
}

.page-label strong {

    font-size:
        16px;

    font-weight:
        800;
}

.actions {

    display:
        flex;

    gap:
        8px;
}

.icon-btn {

    width:
        38px;

    height:
        38px;

    display:
        grid;

    place-items:
        center;

    border:
        1px solid var(--line);

    border-radius:
        12px;

    color:
        #81918a;

    background:
        rgba(255,255,255,.025);

    cursor:
        pointer;

    transition:
        .22s ease;
}

.icon-btn:hover {

    color:
        var(--green);

    border-color:
        rgba(121,230,170,.25);

    background:
        rgba(121,230,170,.05);

    transform:
        translateY(-1px);
}

.mobile-menu {
    display:
        none;
}

/* =========================================================
   CONTENT
   ========================================================= */

.content {

    width:
        min(1450px, 100%);

    margin:
        auto;

    padding:
        28px 30px 40px;
}

/* =========================================================
   HERO
   ========================================================= */

.hero {

    position:
        relative;

    overflow:
        hidden;

    padding:
        28px;

    border:
        1px solid var(--line);

    border-radius:
        var(--radius);

    background:

        radial-gradient(
            circle at 100% 0%,
            rgba(121,230,170,.11),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            rgba(255,255,255,.045),
            rgba(255,255,255,.015)
        );

    box-shadow:
        0 20px 55px rgba(0,0,0,.16);

    animation:
        fadeUp .55s ease both;
}

.hero-kicker {

    margin:
        0 0 7px;

    color:
        var(--green);

    font-size:
        8px;

    font-weight:
        800;

    letter-spacing:
        .2em;

    text-transform:
        uppercase;
}

.hero h1 {

    margin:
        0;

    max-width:
        750px;

    font-family:
        "Playfair Display",
        serif;

    font-size:
        clamp(30px, 4vw, 44px);

    line-height:
        1;

    letter-spacing:
        -.035em;
}

.hero h1 span {
    color:
        var(--green);
}

.hero p {

    max-width:
        700px;

    margin:
        12px 0 0;

    color:
        var(--muted);

    font-size:
        11px;

    line-height:
        1.65;
}

/* =========================================================
   STATISTICS
   ========================================================= */

.stats {

    display:
        grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:
        12px;

    margin-top:
        14px;
}

.stat {

    padding:
        18px;

    border:
        1px solid var(--line);

    border-radius:
        17px;

    background:
        rgba(255,255,255,.025);

    transition:
        .25s ease;

    animation:
        fadeUp .55s ease both;
}

.stat:nth-child(2) {
    animation-delay:
        .05s;
}

.stat:nth-child(3) {
    animation-delay:
        .10s;
}

.stat:nth-child(4) {
    animation-delay:
        .15s;
}

.stat:hover {

    transform:
        translateY(-3px);

    border-color:
        rgba(121,230,170,.2);

    background:
        rgba(121,230,170,.035);
}

.stat-head {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;
}

.stat-label {

    color:
        #6e7e76;

    font-size:
        8px;

    font-weight:
        800;

    letter-spacing:
        .06em;

    text-transform:
        uppercase;
}

.stat-icon {

    width:
        31px;

    height:
        31px;

    display:
        grid;

    place-items:
        center;

    border-radius:
        10px;

    color:
        var(--green);

    background:
        rgba(121,230,170,.075);

    font-size:
        10px;
}

.stat-number {

    margin-top:
        13px;

    font-size:
        27px;

    font-weight:
        800;

    letter-spacing:
        -.04em;
}

.stat-foot {

    margin-top:
        4px;

    color:
        #53635b;

    font-size:
        8px;
}

/* =========================================================
   DASHBOARD GRID
   ========================================================= */

.dashboard-grid {

    display:
        grid;

    grid-template-columns:
        minmax(0, 1.55fr)
        minmax(285px, .9fr);

    gap:
        14px;

    margin-top:
        14px;
}

.panel {

    min-width:
        0;

    overflow:
        hidden;

    border:
        1px solid var(--line);

    border-radius:
        var(--radius);

    background:
        rgba(255,255,255,.025);
}

.panel-head {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

    padding:
        17px 18px;

    border-bottom:
        1px solid var(--line);
}

.panel-head h2 {

    margin:
        0;

    font-size:
        12px;

    font-weight:
        800;
}

.panel-head p {

    margin:
        4px 0 0;

    color:
        #596961;

    font-size:
        8px;
}

.panel-link {

    padding:
        7px 10px;

    border:
        1px solid var(--line);

    border-radius:
        9px;

    color:
        #8ba198;

    background:
        rgba(255,255,255,.025);

    font-size:
        8px;

    font-weight:
        700;

    transition:
        .2s ease;
}

.panel-link:hover {

    color:
        var(--green);

    border-color:
        rgba(121,230,170,.2);
}

/* =========================================================
   SEARCH
   ========================================================= */

.search-box {

    padding:
        13px 14px 0;
}

.search {

    position:
        relative;
}

.search i {

    position:
        absolute;

    left:
        12px;

    top:
        50%;

    transform:
        translateY(-50%);

    color:
        #55655e;

    font-size:
        10px;
}

.search input {

    width:
        100%;

    height:
        39px;

    padding:
        0 12px 0 34px;

    outline:
        none;

    border:
        1px solid var(--line);

    border-radius:
        11px;

    color:
        white;

    background:
        rgba(255,255,255,.025);

    font-size:
        10px;

    transition:
        .2s ease;
}

.search input::placeholder {
    color:
        #52625a;
}

.search input:focus {

    border-color:
        rgba(121,230,170,.4);

    box-shadow:
        0 0 0 4px rgba(121,230,170,.045);
}

.search-results {

    display:
        none;

    margin-top:
        6px;

    overflow:
        hidden;

    border:
        1px solid var(--line);

    border-radius:
        11px;

    background:
        #10221d;

    box-shadow:
        0 18px 35px rgba(0,0,0,.25);
}

.search-results.show {
    display:
        block;
}

.search-result {

    display:
        flex;

    align-items:
        center;

    gap:
        9px;

    padding:
        9px 10px;

    border-bottom:
        1px solid rgba(255,255,255,.05);

    cursor:
        pointer;
}

.search-result:hover {
    background:
        rgba(121,230,170,.045);
}

.result-avatar {

    width:
        28px;

    height:
        28px;

    display:
        grid;

    place-items:
        center;

    flex:
        0 0 28px;

    border-radius:
        9px;

    color:
        #071610;

    background:
        linear-gradient(
            135deg,
            #c1f7d7,
            #60db99
        );

    font-size:
        8px;

    font-weight:
        900;
}

.result-text {
    min-width:
        0;
}

.result-text strong {

    display:
        block;

    overflow:
        hidden;

    color:
        #dbe6e0;

    font-size:
        9px;

    white-space:
        nowrap;

    text-overflow:
        ellipsis;
}

.result-text span {

    display:
        block;

    margin-top:
        2px;

    color:
        #5e7068;

    font-size:
        7px;
}

/* =========================================================
   TABLE
   ========================================================= */

.table-wrap {
    overflow-x:
        auto;
}

table {

    width:
        100%;

    border-collapse:
        collapse;
}

th,
td {

    padding:
        12px 18px;

    text-align:
        left;

    border-bottom:
        1px solid rgba(255,255,255,.05);

    white-space:
        nowrap;
}

th {

    color:
        #576860;

    font-size:
        8px;

    font-weight:
        800;

    letter-spacing:
        .08em;

    text-transform:
        uppercase;
}

td {

    color:
        #c6d2cc;

    font-size:
        9px;
}

tr:last-child td {
    border-bottom:
        0;
}

tr:hover td {
    background:
        rgba(121,230,170,.018);
}

.user {

    display:
        flex;

    align-items:
        center;

    gap:
        9px;

    min-width:
        230px;
}

.user-avatar {

    width:
        30px;

    height:
        30px;

    display:
        grid;

    place-items:
        center;

    flex:
        0 0 30px;

    border-radius:
        10px;

    color:
        #071610;

    background:
        linear-gradient(
            135deg,
            #aef3c9,
            #4fcf8b
        );

    font-size:
        8px;

    font-weight:
        900;
}

.user-email {

    overflow:
        hidden;

    text-overflow:
        ellipsis;

    font-size:
        9px;
}

.id {

    display:
        inline-flex;

    padding:
        4px 7px;

    border:
        1px solid var(--line);

    border-radius:
        999px;

    color:
        #80918a;

    background:
        rgba(255,255,255,.02);

    font-size:
        7px;
}

.date {
    color:
        #66766e;
}

/* =========================================================
   ADMIN LIST
   ========================================================= */

.admin-list {
    padding:
        4px 0;
}

.admin-row {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    padding:
        12px 16px;

    border-bottom:
        1px solid rgba(255,255,255,.05);
}

.admin-row:last-child {
    border-bottom:
        0;
}

.admin-avatar {

    width:
        32px;

    height:
        32px;

    display:
        grid;

    place-items:
        center;

    flex:
        0 0 32px;

    border-radius:
        10px;

    color:
        #071610;

    background:
        linear-gradient(
            135deg,
            #ffbd94,
            #ff8960
        );

    font-size:
        8px;

    font-weight:
        900;
}

.admin-text {
    min-width:
        0;

    flex:
        1;
}

.admin-text strong {

    display:
        block;

    overflow:
        hidden;

    color:
        #d3ded8;

    font-size:
        9px;

    white-space:
        nowrap;

    text-overflow:
        ellipsis;
}

.admin-text span {

    display:
        block;

    margin-top:
        3px;

    color:
        #596a62;

    font-size:
        7px;
}

.you {

    padding:
        4px 6px;

    border-radius:
        999px;

    color:
        #7fe4a9;

    background:
        rgba(121,230,170,.08);

    font-size:
        7px;

    font-weight:
        800;
}

/* =========================================================
   QUICK ACTIONS
   ========================================================= */

.quick {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        8px;

    padding:
        13px 15px 15px;
}

.quick a {

    display:
        flex;

    align-items:
        center;

    gap:
        8px;

    padding:
        11px 10px;

    border:
        1px solid var(--line);

    border-radius:
        12px;

    color:
        #84948d;

    background:
        rgba(255,255,255,.022);

    font-size:
        8px;

    font-weight:
        800;

    transition:
        .22s ease;
}

.quick a i {
    color:
        var(--green);
}

.quick a:hover {

    color:
        #dce7e2;

    border-color:
        rgba(121,230,170,.2);

    background:
        rgba(121,230,170,.04);

    transform:
        translateY(-1px);
}

/* =========================================================
   ANIMATIONS
   ========================================================= */

@keyframes fadeUp {

    from {
        opacity:
            0;

        transform:
            translateY(14px);
    }

    to {
        opacity:
            1;

        transform:
            translateY(0);
    }
}

@keyframes spin {

    to {
        transform:
            rotate(360deg);
    }
}

.spinning i {
    animation:
        spin .55s linear infinite;
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1100px) {

    .app {
        grid-template-columns:
            220px minmax(0, 1fr);
    }

    .content {
        padding-left:
            20px;

        padding-right:
            20px;
    }

    .topbar {
        padding-left:
            20px;

        padding-right:
            20px;
    }

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .dashboard-grid {
        grid-template-columns:
            1fr;
    }
}

@media (max-width: 760px) {

    .app {
        grid-template-columns:
            1fr;
    }

    .sidebar {

        position:
            fixed;

        left:
            0;

        top:
            0;

        width:
            275px;

        transform:
            translateX(-105%);

        transition:
            transform .28s ease;

        box-shadow:
            25px 0 60px rgba(0,0,0,.35);
    }

    body.sidebar-open .sidebar {
        transform:
            translateX(0);
    }

    .mobile-overlay {

        position:
            fixed;

        inset:
            0;

        z-index:
            45;

        display:
            none;

        background:
            rgba(0,0,0,.5);

        backdrop-filter:
            blur(3px);
    }

    body.sidebar-open .mobile-overlay {
        display:
            block;
    }

    .mobile-menu {
        display:
            grid;
    }

    .topbar {
        padding:
            0 14px;
    }

    .page-label {
        display:
            none;
    }

    .content {
        padding:
            17px 13px 25px;
    }

    .hero {
        padding:
            21px;
    }

    .hero h1 {
        font-size:
            33px;
    }

    .stats {
        gap:
            9px;

        margin-top:
            10px;
    }

    .stat {
        padding:
            14px;
    }

    .stat-number {
        font-size:
            23px;
    }

    .dashboard-grid {
        margin-top:
            10px;

        gap:
            10px;
    }
}

@media (max-width: 430px) {

    .stats {
        grid-template-columns:
            1fr 1fr;
    }

    .stat-label {
        font-size:
            7px;
    }

    .stat-number {
        font-size:
            21px;
    }

    th,
    td {
        padding:
            11px 13px;
    }

    .panel-head {
        padding:
            15px;
    }
}

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation-duration:
            .01ms !important;

        transition-duration:
            .01ms !important;

        scroll-behavior:
            auto !important;
    }
}

</style>
    <link rel="stylesheet" href="assets/dunkhome-ui.css">
</head>

<body>

<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="app">

    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <aside class="sidebar" id="sidebar">

        <a href="AdminDashboard.php" class="brand">

            <img
                src="image/logo.jpeg"
                alt="DunkHome Kicks"
                class="logo"
            >

            <div class="brand-name">
                dunkhome_<span>kicks</span>
            </div>

        </a>

        <div class="nav-title">
            Administration
        </div>

        <nav class="nav">

            <a
                href="AdminDashboard.php"
                class="nav-link active"
            >
                <i class="fa-solid fa-grid-2"></i>
                Dashboard
            </a>

            <a
                href="#users"
                class="nav-link"
            >
                <i class="fa-solid fa-users"></i>
                Users
            </a>

            <a
                href="#admins"
                class="nav-link"
            >
                <i class="fa-solid fa-user-shield"></i>
                Administrators
            </a>

            <a
                href="index.php"
                class="nav-link"
            >
                <i class="fa-solid fa-globe"></i>
                View website
            </a>

            <a
                href="SignUp.php"
                class="nav-link"
            >
                <i class="fa-solid fa-user-plus"></i>
                User signup
            </a>

            <a
                href="Products.php"
                class="nav-link"
            >
                <i class="fa-solid fa-shoe-prints"></i>
                Products
            </a>

            <a
                href="AddProduct.php"
                class="nav-link"
            >
                <i class="fa-solid fa-plus"></i>
                Add product
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="admin-mini">

                <div class="avatar">
                    <?= e($adminInitials) ?>
                </div>

                <div class="admin-info">

                    <strong>
                        <?= e($adminEmail) ?>
                    </strong>

                    <span>
                        Administrator
                    </span>

                </div>

            </div>

            <a
                href="AdminDashboard.php?action=logout"
                class="nav-link logout"
            >
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                Sign out
            </a>

        </div>

    </aside>

    <!-- =====================================================
         MAIN
         ===================================================== -->

    <div class="main">

        <header class="topbar">

            <button
                type="button"
                class="icon-btn mobile-menu"
                id="mobileMenu"
                aria-label="Open menu"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="page-label">

                <small>
                    Administrator portal
                </small>

                <strong>
                    Dashboard overview
                </strong>

            </div>

            <div class="actions">

                <button
                    type="button"
                    class="icon-btn"
                    id="refreshButton"
                    title="Refresh"
                    aria-label="Refresh"
                >
                    <i class="fa-solid fa-rotate-right"></i>
                </button>

                <a
                    href="AdminDashboard.php?action=logout"
                    class="icon-btn"
                    title="Sign out"
                    aria-label="Sign out"
                >
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>

            </div>

        </header>

        <main class="content">

            <!-- =================================================
                 HERO
                 ================================================= -->

            <section class="hero">

                <p class="hero-kicker">
                    Welcome back
                </p>

                <h1>
                    Admin <span>Dashboard.</span>
                </h1>

                <p>
                    Manage your DunkHome Kicks account ecosystem from
                    one secure and elegant administration portal.
                    Monitor registered users and administrator accounts
                    in real time.
                </p>

            </section>

            <!-- =================================================
                 STATISTICS
                 ================================================= -->

            <section class="stats">

                <article class="stat">

                    <div class="stat-head">

                        <span class="stat-label">
                            Total users
                        </span>

                        <span class="stat-icon">
                            <i class="fa-solid fa-users"></i>
                        </span>

                    </div>

                    <div
                        class="stat-number"
                        data-counter="<?= $totalUsers ?>"
                    >
                        0
                    </div>

                    <div class="stat-foot">
                        Registered accounts
                    </div>

                </article>

                <article class="stat">

                    <div class="stat-head">

                        <span class="stat-label">
                            New today
                        </span>

                        <span class="stat-icon">
                            <i class="fa-solid fa-user-plus"></i>
                        </span>

                    </div>

                    <div
                        class="stat-number"
                        data-counter="<?= $usersToday ?>"
                    >
                        0
                    </div>

                    <div class="stat-foot">
                        Users registered today
                    </div>

                </article>

                <article class="stat">

                    <div class="stat-head">

                        <span class="stat-label">
                            Administrators
                        </span>

                        <span class="stat-icon">
                            <i class="fa-solid fa-user-shield"></i>
                        </span>

                    </div>

                    <div
                        class="stat-number"
                        data-counter="<?= $totalAdmins ?>"
                    >
                        0
                    </div>

                    <div class="stat-foot">
                        Admin accounts
                    </div>

                </article>

                <article class="stat">

                    <div class="stat-head">

                        <span class="stat-label">
                            Admins today
                        </span>

                        <span class="stat-icon">
                            <i class="fa-solid fa-crown"></i>
                        </span>

                    </div>

                    <div
                        class="stat-number"
                        data-counter="<?= $adminsToday ?>"
                    >
                        0
                    </div>

                    <div class="stat-foot">
                        Created today
                    </div>

                </article>

            </section>

            <!-- =================================================
                 MAIN DASHBOARD GRID
                 ================================================= -->

            <section class="dashboard-grid">

                <!-- USERS -->

                <article
                    class="panel"
                    id="users"
                >

                    <div class="panel-head">

                        <div>

                            <h2>
                                Recent users
                            </h2>

                            <p>
                                Latest accounts registered
                            </p>

                        </div>

                    </div>

                    <div class="search-box">

                        <div class="search">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            <input
                                type="search"
                                id="userSearch"
                                placeholder="Search users by email..."
                                autocomplete="off"
                            >

                        </div>

                        <div
                            class="search-results"
                            id="searchResults"
                        ></div>

                    </div>

                    <div class="table-wrap">

                        <table>

                            <thead>

                                <tr>
                                    <th>User</th>
                                    <th>ID</th>
                                    <th>Registered</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php if (!$recentUsers): ?>

                                <tr>

                                    <td
                                        colspan="3"
                                        style="
                                            text-align:center;
                                            color:#607068;
                                            padding:28px;
                                        "
                                    >
                                        No users registered yet.
                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($recentUsers as $user): ?>

                                    <tr>

                                        <td>

                                            <div class="user">

                                                <div class="user-avatar">
                                                    <?= e(
                                                        getInitials(
                                                            (string) $user['email']
                                                        )
                                                    ) ?>
                                                </div>

                                                <div class="user-email">

                                                    <?= e(
                                                        (string) $user['email']
                                                    ) ?>

                                                </div>

                                            </div>

                                        </td>

                                        <td>

                                            <span class="id">
                                                #<?= (int) $user['id'] ?>
                                            </span>

                                        </td>

                                        <td class="date">

                                            <?= e(
                                                formatDate(
                                                    (string) $user['created_at']
                                                )
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </article>

                <!-- ADMINISTRATORS -->

                <article
                    class="panel"
                    id="admins"
                >

                    <div class="panel-head">

                        <div>

                            <h2>
                                Administrators
                            </h2>

                            <p>
                                Accounts with admin access
                            </p>

                        </div>

                        <a
                            href="AdminSignUp.php"
                            class="panel-link"
                        >
                            Add admin
                        </a>

                    </div>

                    <div class="admin-list">

                    <?php if (!$recentAdmins): ?>

                        <div
                            style="
                                padding:28px 16px;
                                color:#607068;
                                text-align:center;
                                font-size:9px;
                            "
                        >
                            No administrator accounts found.
                        </div>

                    <?php else: ?>

                        <?php foreach ($recentAdmins as $recentAdmin): ?>

                            <div class="admin-row">

                                <div class="admin-avatar">

                                    <?= e(
                                        getInitials(
                                            (string) $recentAdmin['email']
                                        )
                                    ) ?>

                                </div>

                                <div class="admin-text">

                                    <strong>
                                        <?= e(
                                            (string) $recentAdmin['email']
                                        ) ?>
                                    </strong>

                                    <span>
                                        Created
                                        <?= e(
                                            formatDate(
                                                (string) $recentAdmin['created_at']
                                            )
                                        ) ?>
                                    </span>

                                </div>

                                <?php if (
                                    (int) $recentAdmin['id'] === $adminId
                                ): ?>

                                    <span class="you">
                                        YOU
                                    </span>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </div>

                    <!-- QUICK ACTIONS -->

                    <div class="panel-head">

                        <div>

                            <h2>
                                Quick actions
                            </h2>

                            <p>
                                Frequently used controls
                            </p>

                        </div>

                    </div>

                    <div class="quick">

                        <a href="AdminSignUp.php">

                            <i class="fa-solid fa-user-shield"></i>

                            Add administrator

                        </a>

                        <a href="index.php">

                            <i class="fa-solid fa-globe"></i>

                            Open website

                        </a>

                        <a href="SignUp.php">

                            <i class="fa-solid fa-user-plus"></i>

                            User signup

                        </a>

                        <a href="AdminDashboard.php?action=logout">

                            <i class="fa-solid fa-lock"></i>

                            Secure sign out

                        </a>

                    </div>

                </article>

            </section>

        </main>

    </div>

</div>

<script>

/* =========================================================
   MOBILE SIDEBAR
   ========================================================= */

const mobileMenu =
    document.getElementById('mobileMenu');

const mobileOverlay =
    document.getElementById('mobileOverlay');

const sidebar =
    document.getElementById('sidebar');

function closeSidebar() {

    document.body.classList.remove(
        'sidebar-open'
    );
}

mobileMenu?.addEventListener(
    'click',
    () => {

        document.body.classList.toggle(
            'sidebar-open'
        );

    }
);

mobileOverlay?.addEventListener(
    'click',
    closeSidebar
);

sidebar?.querySelectorAll('a').forEach(
    link => {

        link.addEventListener(
            'click',
            closeSidebar
        );

    }
);

/* =========================================================
   REFRESH
   ========================================================= */

const refreshButton =
    document.getElementById('refreshButton');

refreshButton?.addEventListener(
    'click',
    () => {

        refreshButton.classList.add(
            'spinning'
        );

        setTimeout(
            () => {
                window.location.reload();
            },
            250
        );

    }
);

/* =========================================================
   ANIMATED COUNTERS
   ========================================================= */

document
    .querySelectorAll('[data-counter]')
    .forEach(counter => {

        const target =
            Number(counter.dataset.counter || 0);

        if (target === 0) {

            counter.textContent = '0';

            return;
        }

        const duration = 750;

        const start =
            performance.now();

        function animate(now) {

            const progress =
                Math.min(
                    (now - start) / duration,
                    1
                );

            const eased =
                1 -
                Math.pow(
                    1 - progress,
                    3
                );

            counter.textContent =
                Math.floor(
                    target * eased
                );

            if (progress < 1) {

                requestAnimationFrame(
                    animate
                );

            } else {

                counter.textContent =
                    target;
            }
        }

        requestAnimationFrame(
            animate
        );

    });

/* =========================================================
   USER SEARCH
   ========================================================= */

const searchInput =
    document.getElementById('userSearch');

const searchResults =
    document.getElementById('searchResults');

let searchTimer = null;

let searchController = null;

function escapeHtml(value) {

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function hideSearchResults() {

    searchResults.classList.remove(
        'show'
    );

    searchResults.innerHTML = '';
}

function getInitials(email) {

    const name =
        String(email)
            .split('@')[0]
            .replace(
                /[^a-zA-Z0-9]+/g,
                ' '
            )
            .trim();

    if (!name) {
        return 'US';
    }

    const parts =
        name.split(/\s+/);

    if (parts.length >= 2) {

        return (
            parts[0][0] +
            parts[1][0]
        ).toUpperCase();
    }

    return name
        .substring(0, 2)
        .toUpperCase();
}

async function searchUsers(query) {

    if (!query) {

        hideSearchResults();

        return;
    }

    if (searchController) {

        searchController.abort();
    }

    searchController =
        new AbortController();

    try {

        const response =
            await fetch(
                `AdminDashboard.php?action=search_users&q=${encodeURIComponent(query)}`,
                {
                    signal:
                        searchController.signal,

                    headers: {
                        'X-Requested-With':
                            'XMLHttpRequest'
                    }
                }
            );

        if (!response.ok) {
            throw new Error(
                'Request failed'
            );
        }

        const data =
            await response.json();

        if (
            data.status !== 'success' ||
            !Array.isArray(data.users)
        ) {

            hideSearchResults();

            return;
        }

        if (!data.users.length) {

            searchResults.innerHTML = `
                <div
                    class="search-result"
                    style="cursor:default;"
                >
                    <div class="result-text">
                        <strong>
                            No matching users
                        </strong>

                        <span>
                            Try another email.
                        </span>
                    </div>
                </div>
            `;

            searchResults.classList.add(
                'show'
            );

            return;
        }

        searchResults.innerHTML =
            data.users.map(user => `

                <div
                    class="search-result"
                    data-email="${escapeHtml(user.email)}"
                >

                    <div class="result-avatar">
                        ${escapeHtml(
                            getInitials(user.email)
                        )}
                    </div>

                    <div class="result-text">

                        <strong>
                            ${escapeHtml(user.email)}
                        </strong>

                        <span>
                            User #${Number(user.id)}
                        </span>

                    </div>

                </div>

            `).join('');

        searchResults.classList.add(
            'show'
        );

        searchResults
            .querySelectorAll(
                '.search-result[data-email]'
            )
            .forEach(item => {

                item.addEventListener(
                    'click',
                    () => {

                        searchInput.value =
                            item.dataset.email || '';

                        hideSearchResults();

                        searchInput.focus();
                    }
                );

            });

    } catch (error) {

        if (
            error.name !==
            'AbortError'
        ) {

            hideSearchResults();
        }
    }
}

searchInput?.addEventListener(
    'input',
    () => {

        clearTimeout(
            searchTimer
        );

        const query =
            searchInput.value.trim();

        searchTimer =
            setTimeout(
                () => {
                    searchUsers(query);
                },
                180
            );
    }
);

searchInput?.addEventListener(
    'keydown',
    event => {

        if (
            event.key ===
            'Escape'
        ) {

            hideSearchResults();

            searchInput.blur();
        }
    }
);

document.addEventListener(
    'click',
    event => {

        if (
            searchResults &&
            searchInput &&
            !searchResults.contains(
                event.target
            ) &&
            event.target !==
                searchInput
        ) {

            hideSearchResults();
        }
    }
);

</script>

    <script src="assets/dunkhome-ui.js" defer></script>
</body>
</html>