<?php
declare(strict_types=1); require_once __DIR__.'/session.php';
$scope=$_GET['scope']??'all';
if($scope==='admin'){logoutAll('AdminSignIn.php');}
if($scope==='user'){logoutAll('index.php');}
logoutAll('index.php');
