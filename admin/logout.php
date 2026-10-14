<?php
require_once('auth_guard.php');

if(!isset($_SESSION)) { session_start(); } 
$_SESSION = array();
$_SESSION["loggedin"]= false;
session_destroy();
header("location: login.php");
exit;
?>