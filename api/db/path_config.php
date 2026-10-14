<?php
// Returns the absolute path to the domain map configuration file
$host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($host, 'litm') !== false) {
    $mapPath = '/home/csmkodk4wdt8/mfd-private/domain-map.php';
} elseif (strpos($host, 'autowealth') !== false) {
    $mapPath = '/home/rrj6671syt4c/mfd-private/domain-map.php';
} else {
    $mapPath = __DIR__ . '/../../../mfd-private/domain-map.php';
}

return $mapPath;