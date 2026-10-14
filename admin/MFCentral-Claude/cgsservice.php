<?php

class CGSService
{
    private $api;
    private $clientRefNo; // shared across the whole submit -> consent -> fetch flow

    public function __construct($api)
    {
        $this->api = $api;
    }

    public function requestStatement($pan, $mobile, $from, $to)
    {
        // Generate the clientRefNo ONCE for this session. Per spec section 3.5.1,
        // the same clientRefNo must be reused for investorconsent and
        // getStatementDocument calls tied to this reqId.
        $this->clientRefNo = 'REQ' . date('YmdHis') . rand(1000, 9999);

        // Build the payload
        $payload = [
            "clientRefNo" => $this->clientRefNo,
            "pan" => $pan,
            "mobile" => $mobile,
            "email" => "",
            "fromDate" => $from,
            "toDate" => $to
        ];

        return $this->api->callAPI("/api/client/V1/submitstatementrequest", json_encode($payload));
    }

    public function submitOTP($reqId, $otpRef, $otp)
    {
        // Reuse the SAME clientRefNo from requestStatement() — do NOT generate a new one.
        $payload = [
            "reqId" => $reqId,
            "otpRef" => $otpRef,
            "userSubjectReference" => "",
            "clientRefNo" => $this->clientRefNo,
            "enteredOtp" => $otp
        ];

        return $this->api->callAPI("/api/client/V1/investorconsent", json_encode($payload));
    }

    public function fetchCGS($reqId)
    {
        $attempts = 0;
        $maxAttempts = 120; // Max 120 attempts (~10 minutes at 5s each) — UAT statement
                             // generation can take well over a minute since it depends
                             // on all AMCs/RTAs responding behind the scenes.

        do {
            $attempts++;

            // Reuse the SAME clientRefNo again — per spec 3.5.1, use the reqId
            // and clientRefNo sent in the previous investorconsent request.
            $payload = [
                "reqId" => $reqId,
                "clientRefNo" => $this->clientRefNo
            ];

            $res = $this->api->callAPI("/api/client/V1/getStatementDocument", json_encode($payload));

            // Check if response contains errors about processing
            if (isset($res['errors']) && isset($res['errors'][0]['code']) && $res['errors'][0]['code'] == '422') {
                $message = $res['errors'][0]['message'] ?? '';
                if (strpos($message, 'process of generating') !== false ||
                    strpos($message, 'after sometime') !== false ||
                    strpos($message, 'still in process') !== false) {
                    if ($attempts === 1) {
                        echo "⏳ Generating statement on MFCentral servers (this may take a minute).";
                    }
                    echo ".";

                    if ($attempts >= $maxAttempts) {
                        echo "\n❌ Max attempts reached. Statement generation is taking too long.\n";
                        return $res;
                    }

                    sleep(5);
                    continue;
                }
            }

            // If we have data or different error, return
            if ($attempts > 0) {
                echo "\n"; // Print newline after dots finish
            }
            return $res;

        } while (true);
    }
}
?>
