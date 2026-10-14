<?php

require 'MFCentralAPI.php';
require 'CGSService.php';
require 'pdfgenerator.php';

// Check if we're in UAT or Production mode
$isUAT = true; // Set to false for production

$api = new MFCentralAPI("config.json");

if ($isUAT) {
    echo "🔵 RUNNING IN UAT MODE\n";
    echo "⚠️  OTP will be calculated as '00' + last 4 digits of PAN\n";
} else {
    echo "🟢 RUNNING IN PRODUCTION MODE\n";
    echo "⚠️  OTP will be sent to user's mobile\n";
}

$api->generateToken();

$cgs = new CGSService($api);

// Get user input
echo "\n📝 Enter PAN: ";
$pan = trim(fgets(STDIN));

echo "📝 Enter Mobile (with country code, e.g., +919341059619): ";
$mobile = trim(fgets(STDIN));

echo "📝 Enter From Date (e.g., 01-Apr-2024): ";
$from = trim(fgets(STDIN));

echo "📝 Enter To Date (e.g., 31-Mar-2025): ";
$to = trim(fgets(STDIN));

// Step 1: Request Statement
echo "\n📤 Submitting statement request...\n";
$res = $cgs->requestStatement($pan, $mobile, $from, $to);

if (isset($res['errors'])) {
    echo "\n❌ Error:\n";
    print_r($res['errors']);
    die();
}

if (!isset($res['reqId']) || !isset($res['otpRef'])) {
    echo "\n❌ Missing reqId or otpRef\n";
    print_r($res);
    die();
}

$reqId = $res['reqId'];
$otpRef = $res['otpRef'];
echo "\n✅ Request submitted. reqId: $reqId, otpRef: $otpRef\n";

// Get OTP
if ($isUAT) {
    // UAT: Calculate OTP
    // PAN format is AAAAA9999A (5 letters, 4 digits, 1 check letter).
    // The UAT OTP uses the 4-digit numeric block, not the last 4 characters.
    $last4Pan = substr($pan, 5, 4);
    $otp = '00' . $last4Pan;
    echo "\n🔑 UAT OTP: $otp (calculated from PAN)\n";
} else {
    // Production: Ask user for OTP
    echo "\n📱 Enter OTP received on mobile: ";
    $otp = trim(fgets(STDIN));
}

// Step 2: Submit OTP
echo "\n📤 Submitting OTP...\n";
$otpResult = $cgs->submitOTP($reqId, $otpRef, $otp);

if (isset($otpResult['errors'])) {
    echo "\n❌ OTP validation failed:\n";
    print_r($otpResult['errors']);
    die();
}

echo "\n✅ OTP validated!\n";

// Step 3: Fetch CGS Document
echo "\n📤 Fetching CGS document...\n";
$data = $cgs->fetchCGS($reqId);

if (isset($data['errors'])) {
    echo "\n❌ Error fetching CGS:\n";
    print_r($data['errors']);
    die();
}

echo "\n✅ CGS Document received!\n";

// Save response to file
$invName = $data['data'][0]['staticDetails'][0]['invName'] ?? 'Investor';
$safeInvName = preg_replace('/[^a-zA-Z0-9]/', '_', trim($invName));
$baseFilename = $safeInvName . '-' . $pan;
$jsonFile = $baseFilename . '_' . date('Ymd_His') . '.json';
file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
echo "\n💾 Response saved to: $jsonFile\n";

// Generate PDF
$pdfFile = $baseFilename . '.pdf';
echo "📄 Generating PDF...\n";
$pdf = new PDFGenerator();
try {
    $pdf->generate($data, $pdfFile);
    echo "✅ PDF saved to: $pdfFile\n";
} catch (\Mpdf\MpdfException $e) {
    echo "❌ Error generating PDF: " . $e->getMessage() . "\n";
    echo "💡 TIP: Make sure the file '$pdfFile' is not open in another program (like Adobe Reader or your web browser) and try again!\n";
}
?>