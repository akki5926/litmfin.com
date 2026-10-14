<?php
require_once('auth_guard.php');

// ajax_change_password.php - Pure API endpoint with no HTML
// This file should be placed in the same directory as change_password.php

// No session start here - we'll check later
if(!isset($_SESSION)) { session_start(); }

// Set header to JSON
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Load database
require_once('database.php');

$db = new MYSQLDB();
$response = ['success' => false, 'message' => 'Invalid request'];

if(isset($_POST['action'])) {
    switch($_POST['action']) {
        case 'update_password':
            $userId = $_SESSION["userid"] ?? 0;
            $oldPassword = $_POST['oldPassword'] ?? '';
            $newPassword = $_POST['newPassword'] ?? '';
            $confirmPassword = $_POST['confirmPassword'] ?? '';
            
            if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
                $response['message'] = "All fields are required.";
            } elseif ($newPassword !== $confirmPassword) {
                $response['message'] = "New password and confirm password do not match.";
            } elseif (strlen($newPassword) < 6) {
                $response['message'] = "Password must be at least 6 characters long.";
            } elseif ($oldPassword === $newPassword) {
                $response['message'] = "New password must be different from old password.";
            } else {
                if ($db->verifyOldPassword($userId, $oldPassword)) {
                    if ($db->updateAdminPassword($userId, $newPassword)) {
                        $response['success'] = true;
                        $response['message'] = "Password updated successfully!";
                    } else {
                        $response['message'] = "Something went wrong. Please try again.";
                    }
                } else {
                    $response['message'] = "Old password is incorrect.";
                }
            }
            break;
            
        case 'send_otp':
            $random = random_strings(10);
            $_SESSION['reset_otp'] = $random;
            $_SESSION['otp_time'] = time();
            $db->updateRandomnumber($random);
            $response['success'] = true;
            $response['message'] = 'OTP sent successfully. For testing: ' . $random;
            break;
            
        case 'verify_otp':
            $otp = $_POST['otp'] ?? '';
            $newPassword = $_POST['newPassword'] ?? '';
            $confirmPassword = $_POST['confirmPassword'] ?? '';
            
            if (empty($otp)) {
                $response['message'] = "Please enter OTP.";
            } elseif (empty($newPassword) || empty($confirmPassword)) {
                $response['message'] = "Please enter new password.";
            } elseif ($newPassword !== $confirmPassword) {
                $response['message'] = "Passwords do not match.";
            } elseif (strlen($newPassword) < 6) {
                $response['message'] = "Password must be at least 6 characters.";
            } else {
                $storedOtp = $db->getadminOtp();
                $sessionOtp = $_SESSION['reset_otp'] ?? '';
                $otpTime = $_SESSION['otp_time'] ?? 0;
                $currentTime = time();
                
                if (($storedOtp === $otp || $sessionOtp === $otp) && ($currentTime - $otpTime) < 600) {
                    if ($db->updatepassword($newPassword)) {
                        $_SESSION['reset_otp'] = null;
                        $_SESSION['otp_time'] = null;
                        $response['success'] = true;
                        $response['message'] = "Password reset successfully! Please login with new password.";
                    } else {
                        $response['message'] = "Failed to update password. Please try again.";
                    }
                } else {
                    $response['message'] = "Invalid or expired OTP.";
                }
            }
            break;
    }
}

function random_strings($length_of_string) 
{ 
    $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'; 
    return substr(str_shuffle($str_result), 0, $length_of_string); 
}

echo json_encode($response);
exit;
?>