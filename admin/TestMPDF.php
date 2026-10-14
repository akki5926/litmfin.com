<?php
require_once('auth_guard.php');


ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

if (class_exists('\Mpdf\Mpdf')) {
    echo "mPDF loaded successfully<br>";
} else {
    die("mPDF NOT loaded");
}

$mpdf = new \Mpdf\Mpdf();

$mpdf->WriteHTML('<h1>Hello mPDF on GoDaddy!</h1>');

$mpdf->Output();