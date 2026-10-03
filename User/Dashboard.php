<?php
declare(strict_types=1);
require_once __DIR__ . '/../session.php';
requireUser();
header('Location: ' . appUrl('User/MyProfile.php'));
exit;
