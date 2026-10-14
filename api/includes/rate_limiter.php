<?php

class RateLimiter {
    private $db;
    private $limit;
    private $windowSeconds;
    private static $dbAvailable = null; // null = untested, true = available, false = unavailable

    /**
     * @param MfdDb $db Database wrapper instance
     * @param int $limit Maximum requests allowed per window
     * @param int $windowSeconds Time window in seconds
     */
    public function __construct(MfdDb $db, int $limit = 100, int $windowSeconds = 60) {
        $this->db = $db;
        $this->limit = $limit;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Check if the current request exceeds the rate limit
     *
     * @param string $ipAddress The IP address of the client
     * @param string $endpoint The endpoint being accessed
     * @return bool True if allowed, False if limit exceeded
     */
    public function checkRateLimit(string $ipAddress, string $endpoint): bool {
        // If database is confirmed unavailable, use file-based fallback
        if (self::$dbAvailable === false) {
            return $this->checkRateLimitFile($ipAddress, $endpoint);
        }

        // Try to ensure the table exists on first check
        if (self::$dbAvailable === null) {
            $tableCreated = $this->db->createRateLimitTable();
            if (!$tableCreated) {
                self::$dbAvailable = false;
                return $this->checkRateLimitFile($ipAddress, $endpoint);
            }
            self::$dbAvailable = true;
        }

        // Calculate the start of the current time window
        $now = time();
        $windowStart = $now - ($now % $this->windowSeconds);

        // Prune old records probabilistically to keep the table small (10% chance)
        if (rand(1, 10) === 1) {
            $cutoff = $now - ($this->windowSeconds * 2);
            $this->db->pruneRateLimitRecords($cutoff);
        }

        try {
            // Call the database wrapper to increment and check the rate limit
            return $this->db->incrementAndCheckRateLimit($ipAddress, $endpoint, $windowStart, $this->limit);
        } catch (Exception $e) {
            // On DB error, fall back to file-based limiting to avoid bringing down the API
            error_log("RateLimiter DB Error, falling back to file: " . $e->getMessage());
            self::$dbAvailable = false;
            return $this->checkRateLimitFile($ipAddress, $endpoint);
        }
    }

    /**
     * File-based rate limiting fallback
     */
    private function checkRateLimitFile(string $ipAddress, string $endpoint): bool {
        try {
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'litmfin_rate_limits';
            if (!is_dir($tempDir)) {
                if (!@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
                    return true; // Fail open if directory cannot be created
                }
            }

            $now = time();
            $windowStart = $now - ($now % $this->windowSeconds);
            
            $key = md5($ipAddress . '_' . $endpoint);
            $filePath = $tempDir . DIRECTORY_SEPARATOR . 'limit_' . $key . '.json';

            $hits = 1;
            if (file_exists($filePath)) {
                $content = @file_get_contents($filePath);
                if ($content !== false) {
                    $data = json_decode($content, true);
                    if (is_array($data) && isset($data['window_start']) && isset($data['hits'])) {
                        if ($data['window_start'] === $windowStart) {
                            $hits = $data['hits'] + 1;
                        }
                    }
                }
            }

            $newData = [
                'window_start' => $windowStart,
                'hits' => $hits
            ];
            @file_put_contents($filePath, json_encode($newData));

            // Probabilistic pruning for file-based limits (1% chance)
            if (rand(1, 100) === 1) {
                $this->pruneOldFiles($tempDir, $now);
            }

            if ($hits > $this->limit) {
                return false; // Limit exceeded
            }

            return true; // Allowed
        } catch (Exception $e) {
            error_log("RateLimiter File Error: " . $e->getMessage());
            return true; // Fail open
        }
    }

    private function pruneOldFiles(string $dir, int $now) {
        try {
            $cutoff = $now - ($this->windowSeconds * 2);
            $files = glob($dir . DIRECTORY_SEPARATOR . 'limit_*.json');
            if ($files) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        $mtime = filemtime($file);
                        if ($mtime && $mtime < $cutoff) {
                            @unlink($file);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("RateLimiter File Pruning Error: " . $e->getMessage());
        }
    }
}


