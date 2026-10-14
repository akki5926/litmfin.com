<?php
require_once('auth_guard.php');

/**
 * bootstrap.php - Centralized configuration loader
 */

if (!defined('CONFIG_PATH')) {
    // This is the relative path you requested
    $mapPath = __DIR__ . '/../../../mfd-private/domain-map.php';

    if (!file_exists($mapPath)) {
        throw new Exception('Domain map file missing at: ' . $mapPath);
    }

    require_once $mapPath;

    if (!defined('CONFIG_PATH')) {
        throw new Exception('CONFIG_PATH not defined in domain-map.php');
    }

    if (!file_exists(CONFIG_PATH)) {
        throw new Exception('System config file not found at: ' . CONFIG_PATH);
    }
}