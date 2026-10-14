<?php
require_once('auth_guard.php');

$memoryLimit = ini_get('memory_limit');
echo "Current memory_limit: " . $memoryLimit;
?>
