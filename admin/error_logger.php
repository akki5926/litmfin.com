<?php
define('LOG_FILE', __DIR__ . '/debugg/aplication_error_log.txt');

function log_error($message) {
    static $last_message = null;

    // Prevent logging the exact same message consecutively (during this specific page load)
    if ($message === $last_message) {
        return;
    }
    $last_message = $message;

    $logDir = dirname(LOG_FILE);
    if (!file_exists($logDir)) {
        if (!mkdir($logDir, 0775, true)) {
            error_log("Custom Log Failed: Cannot create directory $logDir. Check folder permissions.");
            return; // Exit early since we can't write to the file anyway
        }
    }

    $logMessage = "[" . date("Y-m-d H:i:s") . "] " . $message . "\n";
    
    if (file_put_contents(LOG_FILE, $logMessage, FILE_APPEND) === false) {
        error_log("Custom Log Failed: " . $message);
    }
}

?>
