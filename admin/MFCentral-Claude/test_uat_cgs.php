<?php
// test_uat_cgs.php - Test with MFCentral UAT test data

require 'MFCentralAPI.php';
require 'CGSService.php';
require 'pdfgenerator.php';

$api = new MFCentralAPI("config.json");
$api->generateToken();

$cgs = new CGSService($api);
$pdfGen = new PDFGenerator();

// ============================================
// UAT TEST DATA FROM MFCENTRAL SUPPORT
// ============================================

$testCases = [
    [
        'pan' => 'EEAPK9544D',
        'mobile' => '8015933245',
        'fromDate' => '01-Apr-2025',
        'toDate' => '31-Mar-2026'
    ],
    [
        'pan' => 'BKHPR6018P',
        'mobile' => '9786191066',
        'fromDate' => '01-Apr-2025',
        'toDate' => '31-Mar-2026'
    ]
];

foreach ($testCases as $index => $testData) {
    echo "\n" . str_repeat("=", 80);
    echo "\n🔵 TEST CASE " . ($index + 1) . "\n";
    echo str_repeat("=", 80) . "\n";
    echo "PAN: " . $testData['pan'] . "\n";
    echo "Mobile: " . $testData['mobile'] . "\n";
    echo "From: " . $testData['fromDate'] . "\n";
    echo "To: " . $testData['toDate'] . "\n";
    echo str_repeat("=", 80) . "\n";

    // Step 1: Request Statement
    echo "\n📤 Step 1: Submitting statement request...\n";
    $res = $cgs->requestStatement(
        $testData['pan'], 
        $testData['mobile'], 
        $testData['fromDate'], 
        $testData['toDate']
    );

    if (isset($res['errors'])) {
        echo "\n❌ Error in request:\n";
        print_r($res['errors']);
        echo "\n" . str_repeat("-", 80) . "\n";
        continue;
    }

    if (!isset($res['reqId']) || !isset($res['otpRef'])) {
        echo "\n❌ Missing reqId or otpRef in response\n";
        print_r($res);
        echo "\n" . str_repeat("-", 80) . "\n";
        continue;
    }

    $reqId = $res['reqId'];
    $otpRef = $res['otpRef'];
    echo "\n✅ Request submitted successfully!\n";
    echo "   reqId: $reqId\n";
    echo "   otpRef: $otpRef\n";

    // Calculate OTP for UAT: '00' + last 4 digits of PAN
    // PAN format is AAAAA9999A (5 letters, 4 digits, 1 check letter).
    // The UAT OTP uses the 4-digit numeric block, not the last 4 characters.
    $last4Pan = substr($testData['pan'], 5, 4);
    $otp = '00' . $last4Pan;
    echo "\n🔑 UAT OTP: $otp (calculated from PAN: " . $testData['pan'] . ")\n";

    // Step 2: Submit OTP
    echo "\n📤 Step 2: Submitting OTP...\n";
    $otpResult = $cgs->submitOTP($reqId, $otpRef, $otp);

    if (isset($otpResult['errors'])) {
        echo "\n❌ OTP validation failed:\n";
        print_r($otpResult['errors']);
        echo "\n" . str_repeat("-", 80) . "\n";
        continue;
    }

    echo "\n✅ OTP validated successfully!\n";

    // Step 3: Fetch CGS Document
    echo "\n📤 Step 3: Fetching CGS document...\n";
    echo "⏳ This may take a few seconds as it generates the statement...\n";

    $data = $cgs->fetchCGS($reqId);

    if (isset($data['errors'])) {
        echo "\n❌ Error fetching CGS:\n";
        print_r($data['errors']);
        echo "\n" . str_repeat("-", 80) . "\n";
        continue;
    }

    echo "\n✅ CGS Document fetched successfully!\n";

    // (We intentionally ignore the top-level 'Code' and 'Error message' fields 
    // that MFCentral returns when email is omitted, as they are not fatal errors)

    // Display summary
    if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
        $summary = $data['data'][0]['summaryDetails'] ?? [];
        $schemes = $data['data'][0]['schemeDetails'] ?? [];
        $transactions = $data['data'][0]['transactionDetails'] ?? [];
        
        echo "\n📊 CGS Summary:\n";
        echo str_repeat("-", 60) . "\n";
        
        if (!empty($summary)) {
            foreach ($summary as $item) {
                if (isset($item['summaryOfCapitalGains'])) {
                    echo "Summary of Capital Gains: " . $item['summaryOfCapitalGains'] . "\n";
                    echo "Total: " . ($item['total'] ?? 'N/A') . "\n";
                }
            }
        }
        
        if (!empty($schemes)) {
            echo "\n📈 Schemes (" . count($schemes) . "):\n";
            foreach ($schemes as $scheme) {
                echo "  - " . ($scheme['schemeName(Redeem/Switchout)'] ?? 'N/A') . "\n";
                echo "    LTCG: " . ($scheme['longTermWithoutIndexation'] ?? '0') . "\n";
            }
        }

        if (!empty($transactions)) {
            echo "\n📄 Transactions (" . count($transactions) . "):\n";
            echo str_repeat("-", 60) . "\n";
            foreach ($transactions as $i => $txn) {
                echo ($i + 1) . ". " . ($txn['schemeName'] ?? 'N/A') . "\n";
                echo "   Folio: " . ($txn['folio'] ?? 'N/A') . "  AMC: " . ($txn['amcName'] ?? $txn['amc'] ?? 'N/A') . "\n";
                echo "   Buy: " . ($txn['desc'] ?? 'N/A') . " on " . ($txn['date'] ?? 'N/A')
                     . " | Units: " . ($txn['units'] ?? 'N/A')
                     . " | Amount: " . ($txn['amount'] ?? 'N/A')
                     . " | Price: " . ($txn['price'] ?? 'N/A') . "\n";
                echo "   Sell: " . ($txn['desc_1'] ?? 'N/A') . " on " . ($txn['date_1'] ?? 'N/A')
                     . " | Units Redeemed: " . ($txn['redUnits'] ?? 'N/A') . "\n";
                echo "   Short Term: " . ($txn['shortTerm'] ?? '0')
                     . " | Long Term (w/o Index): " . ($txn['longTermWithoutIndex'] ?? '0')
                     . " | Long Term (w/ Index): " . ($txn['longTermWithIndex'] ?? '0') . "\n";
                echo "\n";
            }
        }
    }

    // Save response to file
    $invName = $data['data'][0]['staticDetails'][0]['invName'] ?? 'Investor';
    $safeInvName = preg_replace('/[^a-zA-Z0-9]/', '_', trim($invName));
    $baseFilename = $safeInvName . '-' . $testData['pan'];
    $jsonFile = $baseFilename . '_' . date('Ymd_His') . '.json';
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    echo "\n💾 Response saved to: $jsonFile\n";
    
    // Generate PDF
    $pdfFile = $baseFilename . '.pdf';
    echo "📄 Generating PDF...\n";
    try {
        $pdfGen->generate($data, $pdfFile);
        echo "✅ PDF saved to: $pdfFile\n";
    } catch (\Mpdf\MpdfException $e) {
        echo "❌ Error generating PDF: " . $e->getMessage() . "\n";
        echo "💡 TIP: Make sure the file '$pdfFile' is not open in another program (like Adobe Reader or your web browser) and try again!\n";
    }
    
    echo "\n" . str_repeat("-", 80) . "\n";
}

echo "\n✅ All test cases completed!\n";
?>