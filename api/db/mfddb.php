<?php
/**
 * Database Class - All SQL queries go here
 */
require_once __DIR__ . "/../vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;
// ... Your existing code in mfddb.php ...

class MfdDb
{
    private $pdo;
    private $config;
    private $jwt_secret;
    private $token_expiry;
    private $site_url;

    private $smtp;
    private $company;
    private $cors;

    public function __construct()
    {
        /**
         * -------------------------------------------------
         * LOAD CONFIG PATH (PRIVATE, ONE-LINER FILE)
         * -------------------------------------------------
         */
        
        // Read the map path from the external configuration file
        $mapPath = require __DIR__ . '/path_config.php';

        if (!file_exists($mapPath)) {
            $pathStr = is_string($mapPath) ? $mapPath : gettype($mapPath);
            throw new Exception('Config path file missing. Attempted to load: ' . $pathStr);
        }

        require_once $mapPath;

        if (!defined('CONFIG_PATH')) {
            throw new Exception('CONFIG_PATH not defined');
        }

        $configPath = constant('CONFIG_PATH');

        if (!is_string($configPath) || $configPath === '') {
            throw new Exception('CONFIG_PATH is invalid');
        }

        if (!file_exists($configPath)) {
            throw new Exception('Config file not found: ' . $configPath);
        }

        /**
         * -------------------------------------------------
         * LOAD CONFIG
         * -------------------------------------------------
         */
        $this->config = parse_ini_file($configPath, true);

        if (!$this->config) {
            throw new Exception('Failed to parse config file');
        }

        /**
         * -------------------------------------------------
         * VALIDATE REQUIRED SECTIONS
         * -------------------------------------------------
         */
        foreach (['database', 'api', 'smtp', 'company', 'cors'] as $section) {
            if (!isset($this->config[$section])) {
                throw new Exception("Missing [$section] section in config");
            }
        }

        $this->cors    = $this->config['cors'];
        $this->smtp    = $this->config['smtp'];
        $this->company = $this->config['company'];

        /**
         * -------------------------------------------------
         * JWT CONFIG
         * -------------------------------------------------
         */
        $this->jwt_secret   = $this->config['api']['jwt_secret'] ?? '';
        $this->token_expiry = (int) ($this->config['api']['token_expiry'] ?? 0);
        $this->site_url     = $this->company['site_url'] ?? '';

        /**
         * -------------------------------------------------
         * DATABASE CONFIG
         * -------------------------------------------------
         */
        $dbConfig = $this->config['database'];

        foreach (['host', 'dbname', 'username', 'password', 'charset'] as $key) {
            if (!array_key_exists($key, $dbConfig)) {
                throw new Exception("Missing DB config key: {$key}");
            }
        }

        /**
         * -------------------------------------------------
         * CONNECT DATABASE
         * -------------------------------------------------
         */
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['dbname'],
                $dbConfig['charset']
            );

            $this->pdo = new PDO(
                $dsn,
                $dbConfig['username'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Optional getter
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }


	public function getCompanyConfig(): array
	{
		return $this->company;
	}

	public function getSmtpConfig(): array
	{
		return $this->smtp;
	}
	public function getCorsConfig(): array
	{
		return $this->cors;
	}
    // PDO accessor
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Create the rate limit table if it does not exist
     * 
     * @return bool True on success, False on failure
     */
    public function createRateLimitTable(): bool {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $driver = strtolower($driver);

            if (strpos($driver, 'sqlite') !== false) {
                $sql = "
                    CREATE TABLE IF NOT EXISTS tb_api_rate_limits (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        ip_address VARCHAR(45) NOT NULL,
                        endpoint VARCHAR(255) NOT NULL,
                        window_start INTEGER NOT NULL,
                        hits INTEGER NOT NULL DEFAULT 1,
                        UNIQUE (ip_address, endpoint, window_start)
                    );
                ";
            } elseif (strpos($driver, 'pgsql') !== false) {
                $sql = "
                    CREATE TABLE IF NOT EXISTS tb_api_rate_limits (
                        id SERIAL PRIMARY KEY,
                        ip_address VARCHAR(45) NOT NULL,
                        endpoint VARCHAR(255) NOT NULL,
                        window_start INTEGER NOT NULL,
                        hits INTEGER NOT NULL DEFAULT 1,
                        CONSTRAINT uq_limit UNIQUE (ip_address, endpoint, window_start)
                    );
                ";
            } else {
                // Default to MySQL
                $sql = "
                    CREATE TABLE IF NOT EXISTS tb_api_rate_limits (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        ip_address VARCHAR(45) NOT NULL,
                        endpoint VARCHAR(255) NOT NULL,
                        window_start INT NOT NULL,
                        hits INT NOT NULL DEFAULT 1,
                        UNIQUE KEY uq_limit (ip_address, endpoint, window_start)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ";
            }

            $this->pdo->exec($sql);
            return true;
        } catch (Exception $e) {
            error_log("MfdDb RateLimiter Table Creation Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prune old rate limit records
     * 
     * @param int $cutoff Cutoff timestamp
     * @return bool
     */
    public function pruneRateLimitRecords(int $cutoff): bool {
        try {
            $sql = "DELETE FROM tb_api_rate_limits WHERE window_start < :cutoff";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':cutoff' => $cutoff]);
            return true;
        } catch (Exception $e) {
            error_log("MfdDb RateLimiter Prune Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment hits and check if the limit is exceeded
     * 
     * @param string $ipAddress Client IP address
     * @param string $endpoint Endpoint accessed
     * @param int $windowStart Start of current time window
     * @param int $limit Maximum allowed requests in the window
     * @return bool True if allowed, False if limit exceeded
     * @throws Exception If database query fails
     */
    public function incrementAndCheckRateLimit(string $ipAddress, string $endpoint, int $windowStart, int $limit): bool {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isMysql = (strpos(strtolower($driver), 'mysql') !== false);

        if ($isMysql) {
            // MySQL-specific high-performance upsert
            $sql = "
                INSERT INTO tb_api_rate_limits (ip_address, endpoint, hits, window_start)
                VALUES (:ip, :endpoint, 1, :window)
                ON DUPLICATE KEY UPDATE hits = hits + 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':ip' => $ipAddress,
                ':endpoint' => $endpoint,
                ':window' => $windowStart
            ]);
        } else {
            // Database-agnostic standard SQL fallback for SQLite/PostgreSQL
            $selectSql = "
                SELECT id, hits FROM tb_api_rate_limits
                WHERE ip_address = :ip AND endpoint = :endpoint AND window_start = :window
            ";
            $selectStmt = $this->pdo->prepare($selectSql);
            $selectStmt->execute([
                ':ip' => $ipAddress,
                ':endpoint' => $endpoint,
                ':window' => $windowStart
            ]);
            $row = $selectStmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $updateSql = "UPDATE tb_api_rate_limits SET hits = hits + 1 WHERE id = :id";
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->execute([':id' => $row['id']]);
            } else {
                try {
                    $insertSql = "
                        INSERT INTO tb_api_rate_limits (ip_address, endpoint, hits, window_start)
                        VALUES (:ip, :endpoint, 1, :window)
                    ";
                    $insertStmt = $this->pdo->prepare($insertSql);
                    $insertStmt->execute([
                        ':ip' => $ipAddress,
                        ':endpoint' => $endpoint,
                        ':window' => $windowStart
                    ]);
                } catch (Exception $e) {
                    // Handle race condition/unique constraint violation
                    $updateSql = "
                        UPDATE tb_api_rate_limits SET hits = hits + 1
                        WHERE ip_address = :ip AND endpoint = :endpoint AND window_start = :window
                    ";
                    $updateStmt = $this->pdo->prepare($updateSql);
                    $updateStmt->execute([
                        ':ip' => $ipAddress,
                        ':endpoint' => $endpoint,
                        ':window' => $windowStart
                    ]);
                }
            }
        }

        // Check current hits
        $checkSql = "
            SELECT hits FROM tb_api_rate_limits
            WHERE ip_address = :ip AND endpoint = :endpoint AND window_start = :window
        ";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([
            ':ip' => $ipAddress,
            ':endpoint' => $endpoint,
            ':window' => $windowStart
        ]);

        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['hits'] > $limit) {
            return false; // Limit exceeded
        }

        return true; // Allowed
    }

    // JWT secret
    public function getSecretCode(): string
    {
        return $this->jwt_secret;
    }

    // JWT expiry (seconds)
    public function getTokenExpiry(): int
    {
        return $this->token_expiry;
    }

    // Site URL
    public function getSiteUrl(): string
    {
        return $this->site_url;
    }

    // Basic database operations
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollBack() {
        return $this->pdo->rollBack();
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    // User related SQL functions
    public function checkUserExists($email, $mobile, $pan) {
        $sql = "SELECT id FROM vw_client_profiles WHERE EMAIL = ? OR INDIAN_MOBILE_NO = ? OR PRIMARY_HOLDER_PAN = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email, $mobile, $pan]);
        return $stmt->fetch();
    }
    public function checkUserExistsWithEmail($email) {
        $sql = "CALL sp_user_get_by_identifier(?, 'email')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    public function createUser($userData) {
        if (isset($userData['country'])) {
            $userData['country'] = $this->getCountryNameByCodeOrName($userData['country']);
        }
        if (isset($userData[':country'])) {
            $userData[':country'] = $this->getCountryNameByCodeOrName($userData[':country']);
        }
        $sql = "INSERT INTO vw_client_profiles (
            CLIENT_CODE, PRIMARY_HOLDER_NAME, PRIMARY_HOLDER_PAN, 
            EMAIL, INDIAN_MOBILE_NO, ADDRESS_1, CITY, STATE, PINCODE, COUNTRY,
            PASSWORD_HASH, EMAIL_VERIFIED, CREATED_AT, UPDATED_AT
        ) VALUES (
            :client_code, :primary_holder_name, :primary_holder_pan,
            :email, :indian_mobile_no, :address_1, :city, :state, :pincode, :country,
            :password_hash, :email_verified, NOW(), NOW()
        )";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($userData);
    }
    
    public function getUserByIdentifier($identifier) {
        $type = '';
        // Check if identifier is email
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $type = 'email';
        } 
        // Check if identifier is mobile (10 digits)
        elseif (preg_match('/^[6-9]\d{9}$/', $identifier)) {
            $type = 'mobile';
        }
        // Check if identifier is PAN (10 chars)
        elseif (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper($identifier))) {
            $type = 'pan';
        } else {
            return null;
        }
        
        $sql = "CALL sp_user_get_by_identifier(?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$identifier, $type]);
        $result = $stmt->fetchAll();
        return $result ?: [];
    }

    public function getUserProfileDetails($userId) {
        $sql = "SELECT 
                    id,
                    CLIENT_CODE,
                    PRIMARY_HOLDER_NAME,
                    PRIMARY_HOLDER_PAN,
                    PRIMARY_HOLDER_DOB,
                    EMAIL,
                    INDIAN_MOBILE_NO
                FROM vw_client_profiles 
                WHERE id = ? OR CLIENT_CODE = ?";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return $stmt->fetch();
    }
    
    public function getUserById($userId) {
        $sql = "CALL sp_user_get_by_id(?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
	public function getUserUccById($userId) {
        $sql = "SELECT CLIENT_CODE FROM vw_client_profiles WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row && isset($row['CLIENT_CODE'])) ? $row['CLIENT_CODE'] : '';
    }

    public function getUserByEmail($email) {
        $sql = "CALL sp_user_get_by_identifier(?, 'email')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch();
    }   
    public function getUserByMobile($mobile) {
        $sql = "CALL sp_user_get_by_identifier(?, 'mobile')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mobile]);
        return $stmt->fetch();
    }
    
    public function getUserByPan($pan) {
        $sql = "CALL sp_user_get_by_identifier(?, 'pan')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pan]);
        return $stmt->fetch();
    }
    
    public function updatePassword($userId, $passwordHash) {
        $sql = "CALL sp_user_update_password(?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId, $passwordHash]);
    }
    
    public function updateLoginTimestamp($userId) {
        $sql = "CALL sp_user_update_last_login(?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId]);
    }
    
    public function createPasswordResetToken($email, $token) {
        $sql = "CALL sp_user_set_reset_token(?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$email, $token]);
    }

    public function createPasswordResetTokenByPan($pan, $token) {
        $pan = strtoupper(trim($pan));
        $sql = "UPDATE vw_client_profiles 
                SET RESET_TOKEN = ?, 
                    RESET_TOKEN_EXPIRY = DATE_ADD(NOW(), INTERVAL 1 HOUR), 
                    UPDATED_AT = NOW() 
                WHERE PRIMARY_HOLDER_PAN = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$token, $pan]);
    }

    public function validateResetTokenByPan($pan, $token) {
        $pan = strtoupper(trim($pan));
        $sql = "SELECT id, CLIENT_CODE, PRIMARY_HOLDER_NAME, PRIMARY_HOLDER_PAN, EMAIL, RESET_TOKEN_EXPIRY 
                FROM vw_client_profiles 
                WHERE PRIMARY_HOLDER_PAN = ? 
                  AND RESET_TOKEN = ? 
                  AND RESET_TOKEN_EXPIRY > NOW()";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$pan, $token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('PDO Error in validateResetTokenByPan: ' . $e->getMessage());
            return false;
        }
    }

    public function clearResetTokenById($userId) {
        $sql = "UPDATE vw_client_profiles 
                SET RESET_TOKEN = NULL, 
                    RESET_TOKEN_EXPIRY = NULL, 
                    UPDATED_AT = NOW() 
                WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId]);
    }

    
    public function updateEmailVerified($email) {
        $sql = "CALL sp_user_verify_email(?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$email]);
    }
    
    public function validateResetToken($email, $token)
	{
		////error_log('--- validateResetToken() called ---');
		////error_log('Email: ' . $email);
		////error_log('Token length: ' . strlen($token));

		$sql = "
			SELECT id, RESET_TOKEN_EXPIRY 
			FROM vw_client_profiles 
			WHERE EMAIL = ? 
			  AND RESET_TOKEN = ? 
			  AND RESET_TOKEN_EXPIRY > NOW()
		";

		////error_log('SQL: ' . preg_replace('/\s+/', ' ', trim($sql)));

		try {
			$stmt = $this->pdo->prepare($sql);

			/*//error_log('Executing SQL with parameters: ' . json_encode([
				'EMAIL' => $email,
				'RESET_TOKEN' => $token
			]));
			*/
			$stmt->execute([$email, $token]);

			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			////error_log('Row fetched: ' . json_encode($row));

			if (!$row) {
				//error_log('Token validation FAILED (no matching row)');
			} else {
				////error_log('Token validation SUCCESS');
				////error_log('Token expiry in DB: ' . $row['RESET_TOKEN_EXPIRY']);
			}

			return $row;

		} catch (PDOException $e) {
			//error_log('PDO Error in validateResetToken: ' . $e->getMessage());
			return false;
		}
	}

	public function getEmailVerificationToken($id){
		$sql = "SELECT * FROM vw_client_profiles WHERE id = ? ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
	}
    public function markTokenAsVerified($email, $token) {
		try {
			$sql = "UPDATE vw_client_profiles 
					SET EMAIL_VERIFIED = 1, 
						RESET_TOKEN = NULL,
						RESET_TOKEN_EXPIRY = NULL,
						UPDATED_AT = NOW()
					WHERE EMAIL = ? 
					AND RESET_TOKEN = ? 
					AND RESET_TOKEN_EXPIRY > NOW()";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$email, $token]);
			
			// Return true if any rows were affected
			return $stmt->rowCount() > 0;
			
		} catch (Exception $e) {
			//error_log("Error marking token as verified: " . $e->getMessage());
			return false;
		}
	}
    public function clearResetToken($email) {
        $sql = "CALL sp_user_clear_reset_token(?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$email]);
    }
    
	public function generateClientCode()
	{
		$year = (int) date('Y');

		try {
			$this->pdo->beginTransaction();

			// Lock row for current year
			$stmt = $this->pdo->prepare(
				"SELECT last_number 
				 FROM client_code_counter 
				 WHERE year = :year 
				 FOR UPDATE"
			);
			$stmt->execute(['year' => $year]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			// Safety Check: Get actual highest sequence number from tb_users to prevent out-of-sync counters
			$maxStmt = $this->pdo->prepare("SELECT MAX(CAST(SUBSTRING(CLIENT_CODE, 5) AS UNSIGNED)) FROM tb_users WHERE CLIENT_CODE LIKE :yrPattern");
			$maxStmt->execute(['yrPattern' => $year . '%']);
			$actualMax = (int) $maxStmt->fetchColumn();

			if ($row) {
				$nextNumber = max((int)$row['last_number'], $actualMax);
			} else {
				$nextNumber = $actualMax;
				$insert = $this->pdo->prepare(
					"INSERT INTO client_code_counter (year, last_number) 
					 VALUES (:year, :last_num)"
				);
				$insert->execute(['year' => $year, 'last_num' => $actualMax]);
			}

			// Loop until we find a completely unique code that doesn't exist in tb_users
			// Added fail-safe to prevent infinite loops (max 100 attempts)
			$isUnique = false;
			$clientCode = '';
			$attempts = 0;
			$maxAttempts = 100;
			
			$checkUserStmt = $this->pdo->prepare("SELECT 1 FROM tb_users WHERE CLIENT_CODE = :code");

			while (!$isUnique && $attempts < $maxAttempts) {
				$attempts++;
				$nextNumber++;
				// 10 digits: YYYY + 6-digit sequence
				$clientCode = $year . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
				
				$checkUserStmt->execute(['code' => $clientCode]);
				if (!$checkUserStmt->fetchColumn()) {
					$isUnique = true;
				}
			}

			if (!$isUnique) {
				throw new Exception("Unable to generate a unique client code after {$maxAttempts} attempts.");
			}

			// Update the counter to the latest used number
			$update = $this->pdo->prepare(
				"UPDATE client_code_counter 
				 SET last_number = :num 
				 WHERE year = :year"
			);
			$update->execute([
				'num'  => $nextNumber,
				'year' => $year
			]);

			$this->pdo->commit();

			return $clientCode;

		} catch (Exception $e) {
			$this->pdo->rollBack();
			throw $e;
		}
	}


    
    // Master Data SQL functions
    public function getTaxStatus() {
        $sql = "SELECT code, tax_status FROM mtb_tax_status ORDER BY CAST(code AS UNSIGNED)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTaxStatusByCode($code) {
        $sql = "SELECT code, tax_status FROM mtb_tax_status WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getOccupationCode() {
        $sql = "SELECT code, details FROM mtb_occupation_code ORDER BY CAST(code AS UNSIGNED)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getOccupationCodeByCode($code) {
        $sql = "SELECT code, details FROM mtb_occupation_code WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getClientHolding() {
        $sql = "SELECT code, details FROM mtb_client_holding ORDER BY code";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getClientHoldingByCode($code) {
        $sql = "SELECT code, details FROM mtb_client_holding WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getPanExemptCat() {
        $sql = "SELECT category, description FROM mtb_pan_exempt_cat ORDER BY CAST(category AS UNSIGNED)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPanExemptCatByCategory($category) {
        $sql = "SELECT category, description FROM mtb_pan_exempt_cat WHERE category = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$category]);
        return $stmt->fetch();
    }

    public function getBankAccType() {
        $sql = "SELECT code, details FROM mtb_bank_acc_type ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getBankAccTypeByCode($code) {
        $sql = "SELECT code, details FROM mtb_bank_acc_type WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getNomineeRelation() {
        $sql = "SELECT code, value, applicable_for FROM mtb_nominee_relation ORDER BY CAST(code AS UNSIGNED)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getNomineeRelationByCode($code) {
        $sql = "SELECT code, value, applicable_for FROM mtb_nominee_relation WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }
    
    public function getNomineeIdentity() {
        $sql = "SELECT `identity_type_code` as code, `identity_type_name`as value,description
 FROM mtb_nominee_identity ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getNomineeIdentityByCode($code) {
        $sql = "SELECT * FROM mtb_nominee_identity WHERE identity_type_code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }
    
    // Nominee Authentication methods
    public function getNomineeAuth() {
        $sql = "SELECT `id`, `code`, `description`, `created_at`, `updated_at` FROM `mtb_nominee_auth` ORDER BY `id`";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getNomineeAuthByCode($code) {
        $sql = "SELECT `id`, `code`, `description`, `created_at`, `updated_at` FROM `mtb_nominee_auth` WHERE `code` = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }
    
    public function getEmailMobSelfDecl() {
        $sql = "SELECT code, details FROM mtb_email_mob_self_decl ORDER BY code";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getEmailMobSelfDeclByCode($code) {
        $sql = "SELECT code, details FROM mtb_email_mob_self_decl WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }
    
    public function getCountries() {
        $sql = "SELECT country_code AS code, country_name AS details FROM mtb_country ORDER BY country_name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getCountryByCode($code) {
        $sql = "SELECT country_code AS code, country_name AS details FROM mtb_country WHERE country_code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getFamiliesByAnyIIN($iin) {
        $sql = "SELECT f.*,
                  h.PRIMARY_HOLDER_NAME AS head_PRIMARY_HOLDER_NAME,
                  m1.PRIMARY_HOLDER_NAME AS member1_PRIMARY_HOLDER_NAME,
                  m2.PRIMARY_HOLDER_NAME AS member2_PRIMARY_HOLDER_NAME,
                  m3.PRIMARY_HOLDER_NAME AS member3_PRIMARY_HOLDER_NAME
                FROM tb_family f
                LEFT JOIN vw_client_profiles h ON h.CLIENT_CODE = f.head_UCC
                LEFT JOIN vw_client_profiles m1 ON m1.CLIENT_CODE = f.member1_UCC
                LEFT JOIN vw_client_profiles m2 ON m2.CLIENT_CODE = f.member2_UCC
                LEFT JOIN vw_client_profiles m3 ON m3.CLIENT_CODE = f.member3_UCC
                WHERE f.head_UCC = ?
                   OR f.member1_UCC = ?
                   OR f.member2_UCC = ?
                   OR f.member3_UCC = ?";
        $stmt = $this->pdo->prepare($sql);
        //error_log("getFamiliesByAnyIIN SQL: " . $sql . " | Parameter: " . $iin);
        $stmt->execute([$iin, $iin, $iin, $iin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCountryNameByCodeOrName($codeOrName) {
        if (empty($codeOrName)) {
            return '';
        }
        $trimmed = trim($codeOrName);

        // Map known numeric/custom codes first
        $codeMap = [
            '101' => 'INDIA',
            '106' => 'INDIA',
            'IN'  => 'INDIA',
            'IND' => 'INDIA',
            'US'  => 'UNITED STATES',
            'USA' => 'UNITED STATES',
            'GB'  => 'UNITED KINGDOM',
            'UK'  => 'UNITED KINGDOM'
        ];
        if (isset($codeMap[strtoupper($trimmed)])) {
            return $codeMap[strtoupper($trimmed)];
        }

        // Query mtb_country by country_code, country_name, or id
        $sql = "SELECT country_name FROM mtb_country WHERE country_code = ? OR UPPER(country_name) = UPPER(?) OR id = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$trimmed, $trimmed, is_numeric($trimmed) ? (int)$trimmed : 0]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['country_name'])) {
            return $row['country_name'];
        }

        return $trimmed;
    }

    public function getStateCodeByCodeOrName($codeOrName) {
        if (empty($codeOrName)) {
            return '';
        }
        $trimmed = trim($codeOrName);
        $sql = "SELECT state_code FROM mtb_state WHERE state_code = ? OR UPPER(state_name) = UPPER(?) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$trimmed, $trimmed]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['state_code'] ?? $trimmed;
    }
    
    public function getStates() {
        $sql = "SELECT state_code AS code, state_name AS details FROM mtb_state ORDER BY sort_order, state_name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStateByCode($code) {
        $sql = "SELECT state_code AS code, state_name AS details FROM mtb_state WHERE state_code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getLocationByPincode($pincode) {
        // Validate pincode format (6 digits, Indian standard)
        if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
            return false;
        }

        $sql = "SELECT 
                    sl_no,
                    city,
                    state_code,
                    country_code,
                    pincode
                FROM mtb_city_pincode 
                WHERE pincode = ? 
                LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$pincode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && !empty($result['country_code'])) {
                $countryName = $this->getCountryNameByCodeOrName($result['country_code']);
                $result['country'] = $countryName;
                $result['country_code'] = $countryName;
                $result['COUNTRY'] = $countryName;
            }

            return $result ?: false;
        } catch (Exception $e) {
            //error_log("getLocationByPincode error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getBank() {
        $sql = "SELECT * FROM mtb_billdeskbank WHERE `BANK_CATEGORY` = 'Net Banking' ORDER BY `BD_BANK_NAME` ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
	
	// Add these methods to your existing MfdDb class (I'll place them after the getBank() method for logical grouping):

	/**
	 * Get Fund Category master data
	 */
	public function getFundCategory() {
		try {
			$sql = "SELECT `fund_cat_name` as details, `fund_search` as code FROM `tb_fund_category` WHERE 1 ORDER BY `id`";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
			return $stmt->fetchAll();
		} catch (Exception $e) {
			//error_log("Error in getFundCategory: " . $e->getMessage());
			return [];
		}
	}
	public function getFundCategoryByAmc_old($amc_code, $invest_type = "equity")
	{
		try {
			// 🔍 DEBUG: function entry
			/*//error_log(
				"getFundCategoryByAmc called | amc_code={$amc_code}, invest_type={$invest_type}"
			);*/

			/**
			 * STEP 1: Fetch valid categories for invest_type
			 */
			$catSql = "
				SELECT fund_cat_name, fund_search
				FROM tb_fund_category
				WHERE invest_type = :invest_type
			";

			////error_log("Category SQL: " . preg_replace('/\s+/', ' ', trim($catSql)));

			$catStmt = $this->pdo->prepare($catSql);
			$catStmt->execute([':invest_type' => $invest_type]);
			$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

			////error_log("Categories fetched: " . count($categories));

			if (empty($categories)) {
				//error_log("No categories found for invest_type={$invest_type}");
				return [];
			}

			/**
			 * STEP 2: Build dynamic column conditions
			 */
			$conditions = [];
			foreach ($categories as $cat) {
				// 🔒 whitelist: column name must be snake_case
				if (!preg_match('/^[a-z_]+$/', $cat['fund_search'])) {
					//error_log("Invalid fund_search skipped: " . $cat['fund_search']);
					continue;
				}

				$conditions[] = "mm.`{$cat['fund_search']}` IS NOT NULL";
			}

			////error_log("Dynamic conditions count: " . count($conditions));

			if (empty($conditions)) {
				//error_log("No valid dynamic conditions after validation");
				return [];
			}

			$dynamicWhere = implode(' OR ', $conditions);

			/**
			 * STEP 3: Final SQL
			 */
			$sql = "
				SELECT DISTINCT
					fc.fund_cat_name AS details,
					fc.fund_search AS code
				FROM tb_fund_category fc
				INNER JOIN tb_mf_master mm
					ON ($dynamicWhere)
				WHERE
					mm.amc_code = :amc_code
					AND fc.invest_type = :invest_type
				ORDER BY fc.id
			";

			////error_log("Final SQL: " . preg_replace('/\s+/', ' ', trim($sql)));

			/**
			 * STEP 4: Execute
			 */
			$stmt = $this->pdo->prepare($sql);

			/*//error_log("Executing with params: " . json_encode([
				'amc_code'    => $amc_code,
				'invest_type' => $invest_type
			]));*/

			$stmt->execute([
				':amc_code'    => $amc_code,
				':invest_type' => $invest_type
			]);

			$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

			////error_log("Final rows fetched: " . count($result));

			// 🔍 DEBUG: sample rows
			if (!empty($result)) {
				//error_log("Sample result: " . json_encode(array_slice($result, 0, 3)));
			}

			return $result;

		} catch (Exception $e) {
			// 🔥 DEBUG: exception
			//error_log("ERROR in getFundCategoryByAmc: " . $e->getMessage());
			//error_log($e->getTraceAsString());
			return [];
		}
	}
public function getFundCategoryByAmc($amc_code, $invest_type = "equity")
{
    try {
        // Build the WHERE condition based on invest_type
        $categoryCondition = "";
        switch (strtolower($invest_type)) {
            case "gold":
                // Gold funds are stored as 'Commodity' in fund_cat
                $categoryCondition = "AND `fund_cat` = 'Commodity'";
                break;
            case "fof":
                // FOF can have multiple categories
                $categoryCondition = "AND `fund_cat` IN ('Commodity', 'Debt', 'hybrid', 'Other Equity', 'FOF')";
                break;
            case "arbitrage":
                $categoryCondition = "AND `scheme_name` LIKE '%ARBITRAGE%'";
                break;
            case "tax-saving":
                // Tax saving/ELSS funds - only Other Equity category
                $categoryCondition = "AND `fund_cat` = 'Other Equity'";
                break;
            case "etf":
                // ETF funds - check both fund_cat and cat_tag columns
                $categoryCondition = "AND (`fund_cat` = 'ETF' OR `cat_tag` LIKE '%etf%')";
                break;
            case "sif":
                $categoryCondition = "AND `fund_cat` = 'SIF'";
                break;
            case "hybrid":
                $categoryCondition = "AND `fund_cat` = 'hybrid'";
                break;
            case "debt":
                $categoryCondition = "AND `fund_cat` = 'Debt'";
                break;
            case "equity":
            default:
                $categoryCondition = "AND `fund_cat` NOT IN ('Debt', 'hybrid', 'Commodity', 'Arbitrage', 'Tax Saving', 'ELSS', 'Equity - Tax Saving', 'ETF', 'FOF', 'SIF')";
                break;
        }
        
        /**
         * STEP 1: Get distinct fund categories from mtb_scheme_master
         */
        $sql = "
            SELECT DISTINCT 
                `fund_cat` AS details,
                `fund_cat` AS code
            FROM `mtb_scheme_master`
            WHERE 
                `amc_code` = :amc_code
                AND `fund_cat` IS NOT NULL 
                AND `fund_cat` != ''
                {$categoryCondition}
            ORDER BY `fund_cat` ASC
        ";

        // Log the SQL for debugging
        //error_log("Final SQL: " . preg_replace('/\s+/', ' ', trim($sql)) . " | invest_type={$invest_type}");

        /**
         * STEP 2: Execute query
         */
        $stmt = $this->pdo->prepare($sql);
        
        //error_log("Executing with amc_code: " . $amc_code . ", invest_type: " . $invest_type);
        
        $stmt->execute([
            ':amc_code' => $amc_code
        ]);
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        //error_log("getFundCategoryByAmc executed | amc_code={$amc_code}, invest_type={$invest_type}, rows=" . count($result));
        
        // Sample rows for debugging
        if (!empty($result)) {
            //error_log("Sample result: " . json_encode(array_slice($result, 0, 3)));
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("ERROR in getFundCategoryByAmc: " . $e->getMessage());
        error_log($e->getTraceAsString());
        return [];
    }
}

	/**
	 * Get Mutual Fund Master data
	 */
	public function getMfMaster() {
		try {
			$sql = "SELECT `sl_no`, `registrar_id`, `amc_code`, `sub_fund_code`, `sub_fund_name`, `product_code`, `product_long_name`, `open_date`, `product_category`, `created_date`, `reinvest_tag`, `sip_allowed`, `asset_class`, `sebi_asset_class`, `sip_dates`, `swp_dates`, `stp_dates`, `systematic_frequencies`, `scheme_type`, `elss`, `equity`, `hybrid`, `debt`, `close_ended`, `elss_scheme`, `face_value`, `swp_allowed`, `stp_allowed`, `plan_type`, `scheme_option`, `purchase_allowed`, `redemption_allowed`, `switch_allowed`, `isin`, `last_modified_date`, `active_flag`, `insurance_enabled`, `rtacode`, `nfo_enabled`, `nfo_close_date`, `nfo_sip_effective_date`, `etf`, `arbitrage`, `small_cap`, `mid_cap`, `multi_cap`, `large_cap`, `focussed`, `sector_funds`, `other_equities`, `other_debt`, `bond`, `credit_risk`, `stp`, `gilt`, `cash_liquid`, `fof`, `gold`, `amfi_code` FROM `tb_mf_master` WHERE 1 ORDER BY `sl_no`";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
			return $stmt->fetchAll();
		} catch (Exception $e) {
			//error_log("Error in getMfMaster: " . $e->getMessage());
			return [];
		}
	}
	public function getMfSchemes($amc_code, $category_code, $asset_class, $settlementType = 'NRM',$include_idcw = false)
	{
		try {
			//error_log("1. getMfSchemes called | amc_code={$amc_code}, category_code={$category_code}, asset_class={$asset_class}, include_idcw=" . ($include_idcw ? 'true' : 'false') . ", settlementType={$settlementType}");
			
			// ✅ Build SQL - ensuring scheme_code is selected correctly
			$sql = "SELECT `scheme_code` AS product_code, `scheme_name` AS product_long_name
					FROM `mtb_scheme_master`
					WHERE `amc_code` = :amc_code 
						AND `fund_cat` = :category_code
						AND `scheme_code` IS NOT NULL
						AND `scheme_name` IS NOT NULL";
			
			// Add ELSS filter for tax-saving funds
			if (strtolower($asset_class) === 'tax-saving' || strtolower($asset_class) === 'tax_saving') {
				$sql .= " AND `scheme_name` LIKE '%ELSS%'";
			}
			
			// Add IDCW filter based on flag
			if (!$include_idcw) {
				$sql .= " AND `scheme_name` NOT LIKE '%IDCW%'";
			}
			
			// Add settlement type filter
			switch ($settlementType) {
				case 'L0':
					$sql .= " AND `scheme_code` LIKE '%-L0'";
					break;
				case 'L1':
					$sql .= " AND `scheme_code` LIKE '%-L1'";
					break;
				case 'NFO':
					$sql .= " AND `scheme_code` LIKE '%-NFO'";
					break;
				case 'NRM':
				default:
					$sql .= " AND `scheme_code` NOT LIKE '%-L0' 
							 AND `scheme_code` NOT LIKE '%-L1' 
							 AND `scheme_code` NOT LIKE '%-NFO'";
					break;
			}
			
			$sql .= " ORDER BY `scheme_name` ASC";
			
			$stmt = $this->pdo->prepare($sql);
			
			$params = [
				':amc_code' => $amc_code, 
				':category_code' => $category_code
			];
			
			// Log SQL with parameters
			//error_log("Executing SQL: " . $this->debugSqlQuery($sql, $params));
			
			$stmt->execute($params);

			$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			//error_log("2. SQL executed successfully | rows=" . count($result));
			
			return $result;

		} catch (Exception $e) {
			error_log("Error in getMfSchemes: " . $e->getMessage());
			return [];
		}
	}

// Helper function to debug SQL with parameters
private function debugSqlQuery($sql, $params) {
    foreach ($params as $key => $value) {
        $sql = str_replace($key, "'" . addslashes($value) . "'", $sql);
    }
    return preg_replace('/\s+/', ' ', trim($sql));
}

	/**
	 * Get Mutual Fund by product code or ISIN (optional)
	 */
	public function getMfMasterByCode($code) {
		try {
			$sql = "SELECT * FROM `tb_mf_master` WHERE `product_code` = ? OR `isin` = ? LIMIT 1";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$code, $code]);
			return $stmt->fetch();
		} catch (Exception $e) {
			//error_log("Error in getMfMasterByCode: " . $e->getMessage());
			return false;
		}
		
}
	public function getMFAmcMaster()
	{
		// Base query
		// $sql = "SELECT DISTINCT `amc_code` as code, `amc_code` as details FROM `mtb_scheme_master` WHERE  `amc_code` NOT LIKE '%SIF' ORDER BY `amc_code` ASC;";
		$sql = "SELECT DISTINCT `AMC_CODE` as code, `LONG_NAME` as details FROM `mtb_amc` WHERE  `AMC_CODE` NOT LIKE '%SIF' ORDER BY `AMC_CODE` ASC;";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll();
	}
	public function getSIFAmcMaster()
	{
		$sql = "SELECT DISTINCT `amc_code` as code FROM `mtb_scheme_master` WHERE `amc_code` LIKE '%SIF%' ORDER BY `amc_code` ASC";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$amcLabels = [
			'360ONEMF_SIF' => '360 ONE SIF',
			'BANDHANMF_SIF' => 'Bandhan SIF',
			'BIRLAMF_SIF' => 'Aditya Birla Sun Life SIF',
			'EDELWEISSMMF_SIF' => 'Edelweiss SIF',
			'FRANKLINMF_SIF' => 'Franklin Templeton SIF',
			'HSBCMF_SIF' => 'HSBC SIF',
			'ICICIMF_SIF' => 'ICICI Prudential SIF',
			'ITIMF_SIF' => 'ITI SIF',
			'KOTAKMF_SIF' => 'Kotak SIF',
			'MIRAEMF_SIF' => 'Mirae Asset SIF',
			'QSIF' => 'Quant SIF',
			'SBIMF_SIF' => 'SBI SIF',
			'TATAMF_SIF' => 'Tata SIF',
			'UNIONMF_SIF' => 'Union SIF',
			'WEALTHMF_SIF' => 'Wealth SIF'
		];

		return array_map(function($row) use ($amcLabels) {
			$code = $row['code'];
			$label = $amcLabels[$code] ?? $code;
			return [
				'code' => $code,
				'details' => $label,
				'long_name' => $label
			];
		}, $rows);
	}
	public function getAmcMasterByCode(string $amc_code): ?array
	{
			$sql = "SELECT `AMC_ID`, `AMC_CODE`, `LONG_NAME`, `LAST_MODIFIED_DATE`, `BANNED_COUNTRY` 
					FROM `mtb_amc` 
					WHERE `AMC_CODE` = ?";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$amc_code]);
			
			
			
			if ($row = $stmt->fetch()) {
				return [
					'amc_id' => $row['AMC_ID'],
					'amc_code' => $row['AMC_CODE'],
					'long_name' => $row['LONG_NAME'],
					'last_modified_date' => $row['LAST_MODIFIED_DATE'],
					'banned_country' => $row['BANNED_COUNTRY']
				];
			}
			
			return null;
		}

	/**
	 * Save parsed scheme rows from NSE master into tb_scheme_master table
	 *
	 * @param array $headers Numeric array of header names (as parsed from NSE)
	 * @param array $rows Numeric-indexed arrays of cell values (same order as headers)
	 * @param array $opts Optional settings:
	 *                    - dry_run: bool (if true, do not write to DB, just return stats)
	 * @return array ['inserted' => int, 'updated' => int, 'skipped' => int, 'errors' => array]
	 */
	public function saveSchemesFromNse(array $headers, array $rows, array $opts = []): array
	{
		$dryRun = !empty($opts['dry_run']);
		$stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

		// Build header -> index map (normalized uppercase)
		$hmap = [];
		foreach ($headers as $i => $h) {
			$hn = strtoupper(trim($h));
			$hmap[$hn] = $i;
		}

		// Helper to get cell by header name variants
		$getCell = function(array $row, array $variants) use ($hmap) {
			foreach ($variants as $v) {
				$k = strtoupper(trim($v));
				if (isset($hmap[$k]) && isset($row[$hmap[$k]])) return trim($row[$hmap[$k]]);
			}
			return '';
		};

		try {
			if (!$dryRun) $this->pdo->beginTransaction();

			// Discover actual columns in mtb_scheme_master so we can persist all present fields
			$colsStmt = $this->pdo->query("SHOW COLUMNS FROM mtb_scheme_master");
			$tableCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
			$colsLower = array_map('strtolower', $tableCols);

			// Build mapping from normalized header -> actual column name when possible
			$headerToCol = [];
			foreach ($hmap as $hn => $idx) {
				// candidate column names derived from header
				$base = strtolower($hn);
				$candidates = [
					preg_replace('/[^a-z0-9]+/', '_', $base),
					str_replace(' ', '_', $base),
					preg_replace('/[^a-z0-9]/', '', $base)
				];
				foreach ($candidates as $cand) {
					if ($cand === '') continue;
					$pos = array_search($cand, $colsLower, true);
					if ($pos !== false) { $headerToCol[$hn] = $tableCols[$pos]; break; }
				}
			}

			// Ensure scheme_code column exists (required unique key)
			if (!in_array('scheme_code', $colsLower, true)) {
				throw new Exception('mtb_scheme_master table missing required column: scheme_code');
			}

			// Build list of columns we will persist (ensure scheme_code first)
			$persistCols = ['scheme_code'];
			foreach ($headerToCol as $hn => $col) {
				if ($col !== 'scheme_code' && !in_array($col, $persistCols, true)) $persistCols[] = $col;
			}
			// Ensure we store categories, primary_category and raw if available
			foreach (['fund_cat','categories','primary_category','raw'] as $c) {
				if (in_array($c, $colsLower, true)) {
					$real = $tableCols[array_search($c, $colsLower, true)];
					if (!in_array($real, $persistCols, true)) $persistCols[] = $real;
				}
			}

			// Prepare dynamic INSERT and UPDATE statements based on discovered columns
			$placeholders = array_map(function($c){ return ':' . $c; }, $persistCols);
			$insertSql = 'INSERT INTO mtb_scheme_master (' . implode(', ', $persistCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
			$insertStmt = $this->pdo->prepare($insertSql);

			$updateCols = array_filter($persistCols, function($c){ return $c !== 'scheme_code'; });
			$updatePairs = array_map(function($c){ return $c . ' = :' . $c; }, $updateCols);
			$updateSql = 'UPDATE mtb_scheme_master SET ' . implode(', ', $updatePairs) . ' WHERE scheme_code = :scheme_code';
			$updateStmt = $this->pdo->prepare($updateSql);

			$selectStmt = $this->pdo->prepare("SELECT scheme_code FROM mtb_scheme_master WHERE scheme_code = ? LIMIT 1");

			foreach ($rows as $r) {
				// Required unique id
				$schemeCode = $getCell($r, ['SCHEME CODE', 'SCHEMECODE']);
				if ($schemeCode === '') { $stats['skipped']++; continue; }

				// Basic derived fields
				$fundCat = $getCell($r, ['FUND CATEGORY']);
				$startDateRaw = $getCell($r, ['SCHEME START DATE']);
				$startDate = null;
				if ($startDateRaw !== '') {
					$dt = DateTime::createFromFormat('d-m-Y', $startDateRaw);
					if ($dt !== false) $startDate = $dt->format('Y-m-d');
				}

				$categories = [];
				if ($fundCat !== '') $categories[] = $fundCat;
				$categoriesJson = json_encode(array_values(array_unique($categories)), JSON_UNESCAPED_UNICODE);
				$primaryCategory = $categories[0] ?? '';

				$raw = json_encode($r, JSON_UNESCAPED_UNICODE);

				// Build params dynamically according to $persistCols
				$params = [];
				foreach ($persistCols as $col) {
					switch ($col) {
						case 'scheme_code': $params[':' . $col] = $schemeCode; break;
						case 'scheme_start_date': $params[':' . $col] = $startDate; break;
						case 'fund_cat': $params[':' . $col] = $fundCat; break;
						case 'categories': $params[':' . $col] = $categoriesJson; break;
						case 'primary_category': $params[':' . $col] = $primaryCategory; break;
						case 'raw': $params[':' . $col] = $raw; break;
						default:
							// find header that maps to this column
							$val = '';
							foreach ($headerToCol as $hn => $realCol) {
								if ($realCol === $col) {
									$idx = $hmap[$hn];
									$val = isset($r[$idx]) ? trim($r[$idx]) : '';
									break;
								}
							}
							$params[':' . $col] = $val;
						break;
					}
				}

				// Check existence
				$selectStmt->execute([$schemeCode]);
				$exists = (bool) $selectStmt->fetch();

				if ($dryRun) { if ($exists) $stats['updated']++; else $stats['inserted']++; continue; }

				if ($exists) {
					$ok = $updateStmt->execute($params);
					if ($ok) $stats['updated']++; else $stats['errors'][] = "Failed to update: $schemeCode";
				} else {
					$ok = $insertStmt->execute($params);
					if ($ok) $stats['inserted']++; else $stats['errors'][] = "Failed to insert: $schemeCode";
				}
			}

			if (!$dryRun) $this->pdo->commit();
		} catch (Exception $e) {
			if (!$dryRun) $this->pdo->rollBack();
			$stats['errors'][] = $e->getMessage();
		}

		return $stats;
	}

    // FATCA Related Methods
    public function getFatcaTaxStatus() {
        $sql = "SELECT sr_no, tax_code as code, tax_name as details, category FROM mtb_fatca_tax_status ORDER BY sr_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFatcaTaxStatusByCode($code) {
        $sql = "SELECT sr_no, tax_code as code, tax_name as details, category FROM mtb_fatca_tax_status WHERE tax_code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getFatcaWealthSc() {
        $sql = "SELECT sr_no, code, details FROM mtb_fatca_wealth_sc ORDER BY sr_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFatcaWealthScByCode($code) {
        $sql = "SELECT sr_no, code, details FROM mtb_fatca_wealth_sc WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getFatcaOccupation() {
        $sql = "SELECT sr_no, code, occupation as details,type FROM mtb_fatca_occupation ORDER BY sr_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFatcaOccupationByCode($code) {
        $sql = "SELECT sr_no, code, occupation as details FROM mtb_fatca_occupation WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getFatcaIncomeRange() {
        $sql = "SELECT sr_no, code, income_range as details FROM mtb_fatca_income_range ORDER BY sr_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFatcaIncomeRangeByCode($code) {
        $sql = "SELECT sr_no, code, income_range as details FROM mtb_fatca_income_range WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getFatcaUboCode() {
        $sql = "SELECT sr_no, ubo_code as code, ubo_details as details FROM mtb_fatca_ubo_code ORDER BY sr_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFatcaUboCodeByCode($code) {
        $sql = "SELECT sr_no, ubo_code as code, ubo_details as details FROM mtb_fatca_ubo_code WHERE ubo_code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getFatcaGiinExempt() {
        $sql = "SELECT sr_no, code, giin_exempt_detail as details FROM mtb_fatca_giin_exempt ORDER BY sr_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFatcaGiinExemptByCode($code) {
        $sql = "SELECT sr_no, code, giin_exempt_detail as details FROM mtb_fatca_giin_exempt WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getFatcaExemptCode() {
        $sql = "SELECT sr_no, code, exemption_reason as details FROM mtb_fatca_exempt_code ORDER BY sr_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFatcaExemptCodeByCode($code) {
        $sql = "SELECT sr_no, code, exemption_reason as details FROM mtb_fatca_exempt_code WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getFatcaActNfeSubCat() {
        $sql = "SELECT sr_no, code, active_nfe_sub_cat_detail as details FROM mtb_fatca_act_nfe_sub_cat ORDER BY sr_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
	
/**
 * Get all recommended funds
 */
public function getAllRecommendedFunds() {
    try {
        $sql = "SELECT 
                    r.`recmd_id`, 
                    r.`category_id`, 
                    r.`amc`, 
                    r.`invest_type`, 
                    r.`fund_cat`, 
                    r.`product_id`, 
                    r.`allocation`, 
                    r.`recmd_rational`,
                    a.`LONG_NAME` as amc_name,
                    sm.`scheme_name` as scheme_name,
                    sm.`scheme_code` as scheme_code,
                    sm.`scheme_type` as scheme_type,
                    sm.`isin`,
                    rp.`risk_name`
                FROM `tb_recommanded_funds` r
                LEFT JOIN `mtb_amc` a ON r.`amc` = a.`AMC_CODE`
                LEFT JOIN `mtb_scheme_master` sm ON r.`product_id` = sm.`scheme_code`
                LEFT JOIN `mtb_risk_profile` rp ON r.`category_id` = rp.`risk_code`
                GROUP BY r.`recmd_id`
                ORDER BY r.`category_id`, r.`allocation` DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getAllRecommendedFunds: " . $e->getMessage());
        return [];
    }
}
/**
 * Get recommended funds by category ID
 */
public function getRecommendedFundsByCategory($category_id) {
    try {
        // Debug: Log the input parameter
        error_log("getRecommendedFundsByCategory called with category_id: " . $category_id);
        
        $sql = "SELECT 
                    r.`recmd_id`, 
                    r.`category_id`, 
                    r.`amc`, 
                    r.`invest_type`, 
                    r.`fund_cat`, 
                    r.`product_id`, 
                    r.`allocation`, 
                    r.`recmd_rational`,
                    a.`LONG_NAME` as amc_name,
                    -- Use mtb_scheme_master instead of tb_mf_master
                    s.`scheme_name` as fund_name,
                    s.`isin`,
                    s.`fund_cat` as product_category,
                    s.`scheme_type` as asset_class,
                    s.`scheme_type`,
                    -- Map scheme_type to equity/debt/hybrid flags
                    CASE WHEN s.`scheme_type` = 'Equity' THEN 'Y' ELSE 'N' END as equity,
                    CASE WHEN s.`scheme_type` = 'Debt' THEN 'Y' ELSE 'N' END as debt,
                    CASE WHEN s.`scheme_type` = 'Hybrid' THEN 'Y' ELSE 'N' END as hybrid,
                    -- Additional flags based on scheme_name patterns
                    CASE WHEN s.`scheme_name` LIKE '%Small Cap%' THEN 'Y' ELSE 'N' END as small_cap,
                    CASE WHEN s.`scheme_name` LIKE '%Mid Cap%' THEN 'Y' ELSE 'N' END as mid_cap,
                    CASE WHEN s.`scheme_name` LIKE '%Multi Cap%' THEN 'Y' ELSE 'N' END as multi_cap,
                    CASE WHEN s.`scheme_name` LIKE '%Large Cap%' THEN 'Y' ELSE 'N' END as large_cap,
                    CASE WHEN s.`scheme_name` LIKE '%Focussed%' THEN 'Y' ELSE 'N' END as focussed,
                    CASE WHEN s.`scheme_type` = 'Equity' AND s.`fund_cat` NOT IN ('Large Cap', 'Mid Cap', 'Small Cap', 'Multi Cap') THEN 'Y' ELSE 'N' END as other_equities,
                    CASE WHEN s.`scheme_type` = 'Debt' AND s.`fund_cat` NOT IN ('Gilt', 'Liquid', 'Credit Risk') THEN 'Y' ELSE 'N' END as other_debt,
                    CASE WHEN s.`fund_cat` = 'Bond' OR s.`scheme_name` LIKE '%Bond%' THEN 'Y' ELSE 'N' END as bond,
                    CASE WHEN s.`scheme_name` LIKE '%Credit Risk%' THEN 'Y' ELSE 'N' END as credit_risk,
                    CASE WHEN s.`fund_cat` = 'Gilt' OR s.`scheme_name` LIKE '%Gilt%' THEN 'Y' ELSE 'N' END as gilt,
                    CASE WHEN s.`fund_cat` = 'Liquid' OR s.`scheme_name` LIKE '%Liquid%' THEN 'Y' ELSE 'N' END as cash_liquid,
                    CASE WHEN s.`scheme_name` LIKE '%FOF%' OR s.`fund_cat` = 'FOF' THEN 'Y' ELSE 'N' END as fof,
                    CASE WHEN s.`scheme_name` LIKE '%Gold%' OR s.`fund_cat` = 'Gold' THEN 'Y' ELSE 'N' END as gold
                FROM `tb_recommanded_funds` r
                LEFT JOIN `mtb_amc` a ON r.`amc` = a.`AMC_CODE`
                LEFT JOIN `mtb_scheme_master` s ON r.`product_id` = s.`scheme_code`
                WHERE r.`category_id` = ?
                ORDER BY r.`allocation` DESC, r.`recmd_id`";
        
        // Debug: Log the SQL query
        error_log("SQL Query: " . $sql);
        error_log("Query Parameters: [" . $category_id . "]");
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$category_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log number of results
        error_log("Number of results found: " . count($results));
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Error in getRecommendedFundsByCategory: " . $e->getMessage());
        error_log("SQL Error Info: " . print_r($this->pdo->errorInfo(), true));
        error_log("Stack Trace: " . $e->getTraceAsString());
        return [];
    }
}

	/**
	 * Get risk profile details by risk_code
	 */
	public function getRiskProfileDetails($risk_code) {
		try {
			//error_log("getRiskProfileDetails called with risk_code: " . $risk_code);
			
			$sql = "SELECT 
						`id`, 
						`risk_code`, 
						`risk_name`, 
						`risk_description`, 
						`capital_erosion_percentage`, 
						`details`, 
						`equity_allocation`, 
						`debt_allocation`, 
						`gold_allocation`, 
						`cash_allocation`, 
						`portfolio_description`, 
						`color_code`, 
						`border_color`, 
						`text_color`
					FROM `mtb_risk_profile`
					WHERE `risk_code` = ?";
			
			//error_log("SQL Query: " . $sql);
			//error_log("Query Parameters: [" . $risk_code . "]");
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$risk_code]);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			
			//error_log("Result found: " . ($result ? "YES" : "NO"));
			
			return $result;
			
		} catch (Exception $e) {
			//error_log("Error in getRiskProfileDetails: " . $e->getMessage());
			//error_log("SQL Error Info: " . print_r($this->pdo->errorInfo(), true));
			return false;
		}

	}

	/**
	 * Get all fund categories with risk profile info
	 */
	public function getRecommendedFundCategories() {
		try {
			$sql = "SELECT DISTINCT 
						r.`category_id`,
						rp.`risk_name`,
						rp.`risk_description`,
						COUNT(r.`recmd_id`) as fund_count,
						SUM(r.`allocation`) as total_allocation
					FROM `tb_recommanded_funds` r
					LEFT JOIN `mtb_risk_profile` rp ON r.`category_id` = rp.`risk_code`
					GROUP BY r.`category_id`, rp.`risk_name`, rp.`risk_description`
					ORDER BY r.`category_id`";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		} catch (Exception $e) {
			//error_log("Error in getRecommendedFundCategories: " . $e->getMessage());
			return [];
		}
	}

    public function getFatcaActNfeSubCatByCode($code) {
        $sql = "SELECT sr_no, code, active_nfe_sub_cat_detail as details FROM mtb_fatca_act_nfe_sub_cat WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function getFatcaIdentityType() {
        $sql = "SELECT sr_no, code, identification_type as details FROM mtb_fatca_identity_type ORDER BY sr_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFatcaIdentityTypeByCode($code) {
        $sql = "SELECT sr_no, code, identification_type as details FROM mtb_fatca_identity_type WHERE code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }
	
	public function getFatcaAccType() {
		$sql = "SELECT sr_no, code, account_type as details FROM mtb_fatca_acc_type ORDER BY sr_no";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function getFatcaAccTypeByCode($code) {
		$sql = "SELECT sr_no, code, account_type as details FROM mtb_fatca_acc_type WHERE code = ?";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$code]);
		return $stmt->fetch();
	}

	public function getFatcaAddType() {
		$sql = "SELECT sr_no, code, address_type as details FROM mtb_fatca_add_type ORDER BY sr_no";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function getFatcaAddTypeByCode($code) {
		$sql = "SELECT sr_no, code, address_type as details FROM mtb_fatca_add_type WHERE code = ?";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$code]);
		return $stmt->fetch();
	}

	public function getFatcaIdentityDocType() {
		$sql = "SELECT sr_no, code, document_type as details FROM mtb_fatca_identity_doc_type ORDER BY sr_no";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function getFatcaIdentityDocTypeByCode($code) {
		$sql = "SELECT sr_no, code, document_type as details FROM mtb_fatca_identity_doc_type WHERE code = ?";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$code]);
		return $stmt->fetch();
	}

	public function getFatcaCorpServSec() {
		$sql = "SELECT sr_no, code, service_sector as details FROM mtb_fatca_corp_serv_sec ORDER BY sr_no";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function getFatcaCorpServSecByCode($code) {
		$sql = "SELECT sr_no, code, service_sector as details FROM mtb_fatca_corp_serv_sec WHERE code = ?";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$code]);
		return $stmt->fetch();
	}

    // Bank Account SQL functions
	public function updateBankAccount($userId, $accountData)
	{
		$sql = "UPDATE vw_client_profiles SET
			BANK_HOLDER_NAME_1 = :accountHolderName,
			ACCOUNT_TYPE_1 = :account_type,
			BD_BANK_CODE_1 = :bd_bank_code,
			ACCOUNT_NO_1 = :account_no,
			IFSC_CODE_1 = :ifsc_code,
			BANK_BRANCH_1 = :bank_branch,
			UPDATED_AT = NOW()
		WHERE id = :user_id";
		
		$params = [
			':accountHolderName' => $accountData['accountHolderName'] ?? '',
			':account_type' => $accountData['accountType'] ?? '',
			':bd_bank_code' => $accountData['bdBankCode'] ?? '',
			':account_no' => $accountData['accountNo'] ?? '',
			':ifsc_code' => $accountData['ifscCode'] ?? '',
			':bank_branch' => $accountData['bankBranch'] ?? '',
			':user_id' => $userId
		];
		
		$stmt = $this->pdo->prepare($sql);
		$success = $stmt->execute($params);

		// ✅ Update status if any row was affected
		if ($success && $stmt->rowCount() > 0) {
			$this->updateUserStatus($userId, 'bank_details_submitted', 'Y');
		}

		return $success;
	}


    public function getBankAccountByUserId($userId) {
        $sql = "SELECT 
            BANK_HOLDER_NAME_1 as accountHolderName,
            ACCOUNT_TYPE_1 as accountType,
            BD_BANK_CODE_1 as bdBankCode,
            ACCOUNT_NO_1 as accountNo,
            IFSC_CODE_1 as ifscCode,
            BANK_BRANCH_1 as bankBranch
        FROM vw_client_profiles 
        WHERE id = ? AND (ACCOUNT_NO_1 IS NOT NULL AND ACCOUNT_NO_1 != '')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public function getBankByBdBankCode($bdBankCode) {
        $sql = "SELECT * FROM mtb_billdeskbank WHERE BD_BANK_CODE = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$bdBankCode]);
        return $stmt->fetch();
    }

    // Personal Details SQL functions
    public function getPersonalDetails($userId) {
        $sql = "SELECT 
            id,
            CLIENT_CODE,
            PRIMARY_HOLDER_NAME,
            TAX_STATUS,
            GENDER,
            PRIMARY_HOLDER_DOB,
            OCCUPATION_CODE,
            HOLDING_NATURE,
            SECOND_HOLDER_NAME,
            SECOND_HOLDER_DOB,
            GUARDIAN_FIRST_NAME,
            GUARDIAN_DOB,
            PRIMARY_HOLDER_PAN,
            SECOND_HOLDER_PAN,
            GUARDIAN_PAN,
            THIRD_HOLDER_NAME,
            THIRD_HOLDER_DOB,
            THIRD_HOLDER_EMAIL,
            THIRD_HOLDER_MOBILE,
            THIRD_HOLDER_PAN,
            ADDRESS_1 as address,
            CITY,
            STATE,
            PINCODE,
            COUNTRY,
            EMAIL,
            FOREIGN_ADDRESS_1 as foreignAddress,
            FOREIGN_ADDRESS_PINCODE as foreignAddressPincode,
            FOREIGN_ADDRESS_STATE as foreignAddressState,
            FOREIGN_ADDRESS_COUNTRY as foreignAddressCountry,
            INDIAN_MOBILE_NO,
            SECOND_HOLDER_EMAIL,
            SECOND_HOLDER_MOBILE,
            GUARDIAN_RELATIONSHIP as guardianRelationship,
            CREATED_AT,
            UPDATED_AT
        FROM vw_client_profiles 
        WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
	public function updateUserStatus($usId, $fieldName, $status)
	{
		// Allowed fields whitelist (VERY IMPORTANT for security)
		$allowedFields = [
			'kyc_complient',
			'kyc_status_remark',
			'bank_details_submitted',
			'personal_details_submitted',
			'iin_physical_uploded',
			'cc_uploded',
			'kra_form_uploded',
			'ach_form_uploded',
			'fatca_done',
			'risk_profile_done',
			'activation_status',
			'deactivation_message',
			'pan_card',
			'driving_license',
			'passport',
			'adhar_card',
			'nominee_details_submitted'
		];

		if (!in_array($fieldName, $allowedFields)) {
			throw new InvalidArgumentException('Invalid status field');
		}

		try {
			$this->pdo->beginTransaction();

			// Check if record exists
			$checkStmt = $this->pdo->prepare(
				"SELECT status_id 
				 FROM mtb_user_status 
				 WHERE us_id = :us_id 
				 FOR UPDATE"
			);
			$checkStmt->execute(['us_id' => $usId]);
			$exists = $checkStmt->fetch(PDO::FETCH_ASSOC);

			if ($exists) {
				// Update existing record
				$sql = "UPDATE mtb_user_status 
						SET {$fieldName} = :status 
						WHERE us_id = :us_id";
			} else {
				// Insert new record
				$sql = "INSERT INTO mtb_user_status (us_id, {$fieldName}) 
						VALUES (:us_id, :status)";
			}

			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([
				'us_id'  => $usId,
				'status' => $status
			]);

			$this->pdo->commit();
			return true;

		} catch (Exception $e) {
			$this->pdo->rollBack();
			throw $e;
		}
	}
	public function getUserStatus($userId)
	{
		$sql = "SELECT *
				FROM mtb_user_status
				WHERE us_id = :user_id
				LIMIT 1";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([':user_id' => $userId]);

		$result = $stmt->fetch(PDO::FETCH_ASSOC);

		return $result ?: null;
	}
    // Save/Update personal details
    public function savePersonalDetails($userId, $data) {
        $sql = "UPDATE vw_client_profiles SET
            PRIMARY_HOLDER_NAME = :primaryHolderName,
            TAX_STATUS = :taxStatus,
            GENDER = :gender,
            PRIMARY_HOLDER_DOB = :primaryHolderDOB,
            OCCUPATION_CODE = :occupationCode,
            HOLDING_NATURE = :holdingNature,
            SECOND_HOLDER_NAME = :secondHolderName,
            SECOND_HOLDER_DOB = :secondHolderDOB,
            THIRD_HOLDER_NAME = :thirdHolderName,
            THIRD_HOLDER_DOB = :thirdHolderDOB,
            THIRD_HOLDER_EMAIL = :thirdHolderEmail,
            THIRD_HOLDER_MOBILE = :thirdHolderMobile,
            GUARDIAN_FIRST_NAME = :guardianFirstName,
            GUARDIAN_DOB = :guardianDOB,
            GUARDIAN_RELATIONSHIP = :guardianRelationship,
            
            SECOND_HOLDER_PAN = :secondHolderPAN,
            THIRD_HOLDER_PAN = :thirdHolderPAN,
            GUARDIAN_PAN = :guardianPAN,
            ADDRESS_1 = :address,
            CITY = :city,
            STATE = :state,
            PINCODE = :pincode,
            COUNTRY = :country,
            EMAIL = :email,
            FOREIGN_ADDRESS_1 = :foreignAddress,
            FOREIGN_ADDRESS_PINCODE = :foreignAddressPincode,
            FOREIGN_ADDRESS_STATE = :foreignAddressState,
            FOREIGN_ADDRESS_COUNTRY = :foreignAddressCountry,
            INDIAN_MOBILE_NO = :indianMobileNo,
            SECOND_HOLDER_EMAIL = :secondHolderEmail,
            SECOND_HOLDER_MOBILE = :secondHolderMobile,
            UPDATED_AT = NOW()
        WHERE id = :userId";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Prepare parameters with defaults for all fields
        $params = [
            ':userId' => $userId,
            ':primaryHolderName' => $data['primaryHolderName'] ?? '',
            ':taxStatus' => $data['taxStatus'] ?? '',
            ':gender' => $data['gender'] ?? '',
            ':primaryHolderDOB' => !empty($data['primaryHolderDOB']) ? $data['primaryHolderDOB'] : null,
            ':occupationCode' => $data['occupationCode'] ?? '',
            ':holdingNature' => $data['holdingNature'] ?? '',
            ':secondHolderName' => $data['secondHolderName'] ?? '',
            ':secondHolderDOB' => !empty($data['secondHolderDOB']) ? $data['secondHolderDOB'] : null,
            ':thirdHolderName' => $data['thirdHolderName'] ?? '',
            ':thirdHolderDOB' => !empty($data['thirdHolderDOB']) ? $data['thirdHolderDOB'] : null,
            ':thirdHolderEmail' => $data['thirdHolderEmail'] ?? '',
            ':thirdHolderMobile' => $data['thirdHolderMobile'] ?? '',
            ':guardianFirstName' => $data['guardianFirstName'] ?? '',
            ':guardianDOB' => !empty($data['guardianDOB']) ? $data['guardianDOB'] : null,
            ':guardianRelationship' => $data['guardianRelationship'] ?? '',

            ':secondHolderPAN' => $data['secondHolderPAN'] ?? '',
            ':thirdHolderPAN' => $data['thirdHolderPAN'] ?? '',
            ':guardianPAN' => $data['guardianPAN'] ?? '',
            ':address' => $data['address'] ?? '',
            ':city' => $data['city'] ?? '',
            ':state' => $this->getStateCodeByCodeOrName($data['state'] ?? ''),
            ':pincode' => $data['pincode'] ?? '',
            ':country' => $this->getCountryNameByCodeOrName($data['country'] ?? ''),
            ':email' => $data['email'] ?? '',
            ':foreignAddress' => $data['foreignAddress'] ?? '',
            ':foreignAddressPincode' => $data['foreignAddressPincode'] ?? '',
            ':foreignAddressState' => $data['foreignAddressState'] ?? '',
            ':foreignAddressCountry' => $this->getCountryNameByCodeOrName($data['foreignAddressCountry'] ?? ''),
            ':indianMobileNo' => $data['indianMobileNo'] ?? '',
            ':secondHolderEmail' => $data['secondHolderEmail'] ?? '',
            ':secondHolderMobile' => $data['secondHolderMobile'] ?? '',
        ];
        
        $result = $stmt->execute($params);

		if ($result && $stmt->rowCount() > 0) {
			// Mark personal details as submitted
			$this->updateUserStatus($userId, 'personal_details_submitted', 'Y');
		}

		return $result;
    }
    
    // Check if personal details exist for user
    public function hasPersonalDetails($userId) {
        $sql = "SELECT id FROM vw_client_profiles WHERE id = ? AND PRIMARY_HOLDER_NAME IS NOT NULL AND PRIMARY_HOLDER_NAME != ''";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch() !== false;
    }
    
    // Nominee Details SQL functions
    public function getNomineeDetails($userId) {
        $sql = "SELECT 
            NOMINATION,
            AUTHENTICATION,
            NOMINEE_1_NAME,
            NOMINEE_1_RELATIONSHIP,
            NOMINEE_1_APPLICABLE_PERCENT,
            NOMINEE_1_MINOR_FLAG,
            NOMINEE_1_DOB,
            NOMINEE_1_GUARDIAN,
            NOMINEE_1_GUARDIAN_PAN,
            NOMINEE_1_IDENTITY_TYPE,
            NOMINEE_1_IDENTITY_NO,
            NOMINEE_1_EMAIL,
            NOMINEE_1_MOBILE,
            NOMINEE_1_ADDRESS1,
            NOMINEE_1_CITY,
            NOMINEE_1_PINCODE,
            NOMINEE_1_COUNTRY,
            NOMINEE_1_ADDRESS_SAME_AS_HOLDER,
            NOMINEE_2_NAME,
            NOMINEE_2_RELATIONSHIP,
            NOMINEE_2_APPLICABLE_PERCENT,
            NOMINEE_2_MINOR_FLAG,
            NOMINEE_2_DOB,
            NOMINEE_2_GUARDIAN,
            NOMINEE_2_GUARDIAN_PAN,
            NOMINEE_2_IDENTITY_TYPE,
            NOMINEE_2_IDENTITY_NO,
            NOMINEE_2_EMAIL,
            NOMINEE_2_MOBILE,
            NOMINEE_2_ADDRESS1,
            NOMINEE_2_CITY,
            NOMINEE_2_PINCODE,
            NOMINEE_2_COUNTRY,
            NOMINEE_2_ADDRESS_SAME_AS_HOLDER,
            NOMINEE_3_NAME,
            NOMINEE_3_RELATIONSHIP,
            NOMINEE_3_APPLICABLE_PERCENT,
            NOMINEE_3_MINOR_FLAG,
            NOMINEE_3_DOB,
            NOMINEE_3_GUARDIAN,
            NOMINEE_3_GUARDIAN_PAN,
            NOMINEE_3_IDENTITY_TYPE,
            NOMINEE_3_IDENTITY_NO,
            NOMINEE_3_EMAIL,
            NOMINEE_3_MOBILE,
            NOMINEE_3_ADDRESS1,
            NOMINEE_3_CITY,
            NOMINEE_3_PINCODE,
            NOMINEE_3_COUNTRY,
            NOMINEE_3_ADDRESS_SAME_AS_HOLDER,
            NOMINEE_NAMES_STATUS,
            Filler1
        FROM vw_client_profiles 
        WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        // Return data if record exists AND either:
        // 1. User has opted out of nomination (NOMINATION = 'N'), OR
        // 2. At least one nominee name is filled (NOMINATION = 'Y' with data)
        if ($result && (
            $result['NOMINATION'] === 'N' ||
            $result['NOMINEE_1_NAME'] ||
            $result['NOMINEE_2_NAME'] ||
            $result['NOMINEE_3_NAME']
        )) {
            if (!empty($result['NOMINEE_1_COUNTRY'])) {
                $result['NOMINEE_1_COUNTRY'] = $this->getCountryNameByCodeOrName($result['NOMINEE_1_COUNTRY']);
            }
            if (!empty($result['NOMINEE_2_COUNTRY'])) {
                $result['NOMINEE_2_COUNTRY'] = $this->getCountryNameByCodeOrName($result['NOMINEE_2_COUNTRY']);
            }
            if (!empty($result['NOMINEE_3_COUNTRY'])) {
                $result['NOMINEE_3_COUNTRY'] = $this->getCountryNameByCodeOrName($result['NOMINEE_3_COUNTRY']);
            }
            return $result;
        }
        
        return false;
    }
	private function hasAnyNomineeData(array $data): bool
	{
		for ($i = 1; $i <= 3; $i++) {
			$nameKey = "nominee{$i}Name";
			$percentKey = "nominee{$i}ApplicablePercentage";

			if (
				!empty($data[$nameKey]) &&
				isset($data[$percentKey]) &&
				(int)$data[$percentKey] > 0
			) {
				return true;
			}
		}
		return false;
	}

    // Save/Update nominee details
   // Save/Update nominee details
public function saveNomineeDetails($userId, $data) {
    try {
        
        // First, check if user exists
        $checkSql = "SELECT id FROM tb_users WHERE id = ?";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([$userId]);
        
        if (!$checkStmt->fetch()) {
            $checkSql = "SELECT id FROM vw_client_profiles WHERE id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$userId]);
            if (!$checkStmt->fetch()) {
                throw new Exception("User not found");
            }
        }
        
        // UPDATE tb_users base table directly
        $sql = "UPDATE tb_users SET
            NOMINATION = :nomination,
            AUTHENTICATION = :authentication,
            NOMINEE_1_NAME = :nominee1_name,
            NOMINEE_1_RELATIONSHIP = :nominee1_relationship,
            NOMINEE_1_APPLICABLE_PERCENT = :nominee1_applicable_percent,
            NOMINEE_1_MINOR_FLAG = :nominee1_minor_flag,
            NOMINEE_1_DOB = :nominee1_dob,
            NOMINEE_1_GUARDIAN = :nominee1_guardian,
            NOMINEE_1_GUARDIAN_PAN = :nominee1_guardian_pan,
            NOMINEE_1_IDENTITY_TYPE = :nominee1_identity_type,
            NOMINEE_1_IDENTITY_NO = :nominee1_identity_no,
            NOMINEE_1_EMAIL = :nominee1_email,
            NOMINEE_1_MOBILE = :nominee1_mobile,
            NOMINEE_1_ADDRESS1 = :nominee1_address,
            NOMINEE_1_CITY = :nominee1_city,
            NOMINEE_1_PINCODE = :nominee1_pincode,
            NOMINEE_1_COUNTRY = :nominee1_country,
            NOMINEE_1_ADDRESS_SAME_AS_HOLDER = :nominee1_address_same_as_holder,
            NOMINEE_2_NAME = :nominee2_name,
            NOMINEE_2_RELATIONSHIP = :nominee2_relationship,
            NOMINEE_2_APPLICABLE_PERCENT = :nominee2_applicable_percent,
            NOMINEE_2_MINOR_FLAG = :nominee2_minor_flag,
            NOMINEE_2_DOB = :nominee2_dob,
            NOMINEE_2_GUARDIAN = :nominee2_guardian,
            NOMINEE_2_GUARDIAN_PAN = :nominee2_guardian_pan,
            NOMINEE_2_IDENTITY_TYPE = :nominee2_identity_type,
            NOMINEE_2_IDENTITY_NO = :nominee2_identity_no,
            NOMINEE_2_EMAIL = :nominee2_email,
            NOMINEE_2_MOBILE = :nominee2_mobile,
            NOMINEE_2_ADDRESS1 = :nominee2_address,
            NOMINEE_2_CITY = :nominee2_city,
            NOMINEE_2_PINCODE = :nominee2_pincode,
            NOMINEE_2_COUNTRY = :nominee2_country,
            NOMINEE_2_ADDRESS_SAME_AS_HOLDER = :nominee2_address_same_as_holder,
            NOMINEE_3_NAME = :nominee3_name,
            NOMINEE_3_RELATIONSHIP = :nominee3_relationship,
            NOMINEE_3_APPLICABLE_PERCENT = :nominee3_applicable_percent,
            NOMINEE_3_MINOR_FLAG = :nominee3_minor_flag,
            NOMINEE_3_DOB = :nominee3_dob,
            NOMINEE_3_GUARDIAN = :nominee3_guardian,
            NOMINEE_3_GUARDIAN_PAN = :nominee3_guardian_pan,
            NOMINEE_3_IDENTITY_TYPE = :nominee3_identity_type,
            NOMINEE_3_IDENTITY_NO = :nominee3_identity_no,
            NOMINEE_3_EMAIL = :nominee3_email,
            NOMINEE_3_MOBILE = :nominee3_mobile,
            NOMINEE_3_ADDRESS1 = :nominee3_address,
            NOMINEE_3_CITY = :nominee3_city,
            NOMINEE_3_PINCODE = :nominee3_pincode,
            NOMINEE_3_COUNTRY = :nominee3_country,
            NOMINEE_3_ADDRESS_SAME_AS_HOLDER = :nominee3_address_same_as_holder,
            NOMINEE_NAMES_STATUS = :nominee_names_status,
            Filler1 = :filler1,
            UPDATED_AT = NOW()
        WHERE id = :user_id";
        
        // Fetch holder's primary address to fill nominee address if Address Same As First Holder is 'Y'
        $holderAddress = '';
        $holderCity = '';
        $holderPincode = '';
        $holderCountry = '';

        $isSame1 = in_array(strtoupper(trim($data['nominee1AddressSameAsHolder'] ?? $data['NOMINEE_1_ADDRESS_SAME_AS_HOLDER'] ?? '')), ['Y', 'YES', '1', 'TRUE']);
        $isSame2 = in_array(strtoupper(trim($data['nominee2AddressSameAsHolder'] ?? $data['NOMINEE_2_ADDRESS_SAME_AS_HOLDER'] ?? '')), ['Y', 'YES', '1', 'TRUE']);
        $isSame3 = in_array(strtoupper(trim($data['nominee3AddressSameAsHolder'] ?? $data['NOMINEE_3_ADDRESS_SAME_AS_HOLDER'] ?? '')), ['Y', 'YES', '1', 'TRUE']);

        if ($isSame1 || $isSame2 || $isSame3) {
            $holderSql = "SELECT * FROM tb_users WHERE id = ?";
            $holderStmt = $this->pdo->prepare($holderSql);
            $holderStmt->execute([$userId]);
            $holderData = $holderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$holderData) {
                $holderSql = "SELECT * FROM vw_client_profiles WHERE id = ?";
                $holderStmt = $this->pdo->prepare($holderSql);
                $holderStmt->execute([$userId]);
                $holderData = $holderStmt->fetch(PDO::FETCH_ASSOC);
            }
            $holderStmt = $this->pdo->prepare($holderSql);
            $holderStmt->execute([$userId]);
            $holderData = $holderStmt->fetch(PDO::FETCH_ASSOC);
            if ($holderData) {
                $getVal = function(array $arr, array $keys) {
                    foreach ($keys as $k) {
                        foreach ($arr as $key => $val) {
                            if (strcasecmp($key, $k) === 0 && $val !== null && trim((string)$val) !== '') {
                                return trim((string)$val);
                            }
                        }
                    }
                    return '';
                };

                $holderAddress = $getVal($holderData, ['ADDRESS_1', 'ADDRESS', 'ADDRESS1']);
                $holderCity = $getVal($holderData, ['CITY']);
                $holderPincode = $getVal($holderData, ['PINCODE']);
                $holderCountry = $this->getCountryNameByCodeOrName($getVal($holderData, ['COUNTRY']));
            }
        }

        $nominee1Address = $isSame1 ? $holderAddress : ($data['nominee1Address'] ?? '');
        $nominee1City = $isSame1 ? $holderCity : ($data['nominee1City'] ?? '');
        $nominee1Pincode = $isSame1 ? $holderPincode : ($data['nominee1Pincode'] ?? '');
        $nominee1CountryInput = $isSame1 ? $holderCountry : ($data['nominee1Country'] ?? '');

        $nominee2Address = $isSame2 ? $holderAddress : ($data['nominee2Address'] ?? '');
        $nominee2City = $isSame2 ? $holderCity : ($data['nominee2City'] ?? '');
        $nominee2Pincode = $isSame2 ? $holderPincode : ($data['nominee2Pincode'] ?? '');
        $nominee2CountryInput = $isSame2 ? $holderCountry : ($data['nominee2Country'] ?? '');

        $nominee3Address = $isSame3 ? $holderAddress : ($data['nominee3Address'] ?? '');
        $nominee3City = $isSame3 ? $holderCity : ($data['nominee3City'] ?? '');
        $nominee3Pincode = $isSame3 ? $holderPincode : ($data['nominee3Pincode'] ?? '');
        $nominee3CountryInput = $isSame3 ? $holderCountry : ($data['nominee3Country'] ?? '');

        $isNominationOptedOut = (strtoupper(trim($data['nomination'] ?? '')) === 'N');

        // Prepare parameters with defaults
        $params = [
            ':user_id' => $userId,
            ':nomination' => $data['nomination'] ?? '',
            ':authentication' => $isNominationOptedOut ? '' : ($data['authentication'] ?? ''),
            ':nominee1_name' => $isNominationOptedOut ? '' : ($data['nominee1Name'] ?? ''),
            ':nominee1_relationship' => $isNominationOptedOut ? '' : ($data['nominee1Relationship'] ?? ''),
            ':nominee1_applicable_percent' => $isNominationOptedOut ? '0' : ($data['nominee1ApplicablePercentage'] ?? '0'),
            ':nominee1_minor_flag' => $isNominationOptedOut ? 'N' : ($data['nominee1MinorFlag'] ?? 'N'),
            ':nominee1_dob' => ($isNominationOptedOut || empty($data['nominee1DOB'])) ? null : $data['nominee1DOB'],
            ':nominee1_guardian' => $isNominationOptedOut ? '' : ($data['nominee1Guardian'] ?? ''),
            ':nominee1_guardian_pan' => ($isNominationOptedOut || empty($data['nominee1GuardianPAN'])) ? '' : strtoupper($data['nominee1GuardianPAN']),
            ':nominee1_identity_type' => $isNominationOptedOut ? '' : ($data['nominee1IdentityType'] ?? ''),
            ':nominee1_identity_no' => $isNominationOptedOut ? '' : ($data['nominee1IdentityNo'] ?? ''),
            ':nominee1_email' => $isNominationOptedOut ? '' : ($data['nominee1Email'] ?? ''),
            ':nominee1_mobile' => $isNominationOptedOut ? '' : ($data['nominee1Mobile'] ?? ''),
            ':nominee1_address' => $isNominationOptedOut ? '' : $nominee1Address,
            ':nominee1_city' => $isNominationOptedOut ? '' : $nominee1City,
            ':nominee1_pincode' => $isNominationOptedOut ? '' : $nominee1Pincode,
            ':nominee1_country' => $isNominationOptedOut ? '' : $this->getCountryNameByCodeOrName($nominee1CountryInput),
            ':nominee1_address_same_as_holder' => $isNominationOptedOut ? 'N' : ($isSame1 ? 'Y' : ($data['nominee1AddressSameAsHolder'] ?? 'N')),
            ':nominee2_name' => $isNominationOptedOut ? '' : ($data['nominee2Name'] ?? ''),
            ':nominee2_relationship' => $isNominationOptedOut ? '' : ($data['nominee2Relationship'] ?? ''),
            ':nominee2_applicable_percent' => $isNominationOptedOut ? '0' : ($data['nominee2ApplicablePercentage'] ?? '0'),
            ':nominee2_minor_flag' => $isNominationOptedOut ? 'N' : ($data['nominee2MinorFlag'] ?? 'N'),
            ':nominee2_dob' => ($isNominationOptedOut || empty($data['nominee2DOB'])) ? null : $data['nominee2DOB'],
            ':nominee2_guardian' => $isNominationOptedOut ? '' : ($data['nominee2Guardian'] ?? ''),
            ':nominee2_guardian_pan' => ($isNominationOptedOut || empty($data['nominee2GuardianPAN'])) ? '' : strtoupper($data['nominee2GuardianPAN']),
            ':nominee2_identity_type' => $isNominationOptedOut ? '' : ($data['nominee2IdentityType'] ?? ''),
            ':nominee2_identity_no' => $isNominationOptedOut ? '' : ($data['nominee2IdentityNo'] ?? ''),
            ':nominee2_email' => $isNominationOptedOut ? '' : ($data['nominee2Email'] ?? ''),
            ':nominee2_mobile' => $isNominationOptedOut ? '' : ($data['nominee2Mobile'] ?? ''),
            ':nominee2_address' => $isNominationOptedOut ? '' : $nominee2Address,
            ':nominee2_city' => $isNominationOptedOut ? '' : $nominee2City,
            ':nominee2_pincode' => $isNominationOptedOut ? '' : $nominee2Pincode,
            ':nominee2_country' => $isNominationOptedOut ? '' : $this->getCountryNameByCodeOrName($nominee2CountryInput),
            ':nominee2_address_same_as_holder' => $isNominationOptedOut ? 'N' : ($isSame2 ? 'Y' : ($data['nominee2AddressSameAsHolder'] ?? 'N')),
            ':nominee3_name' => $isNominationOptedOut ? '' : ($data['nominee3Name'] ?? ''),
            ':nominee3_relationship' => $isNominationOptedOut ? '' : ($data['nominee3Relationship'] ?? ''),
            ':nominee3_applicable_percent' => $isNominationOptedOut ? '0' : ($data['nominee3ApplicablePercentage'] ?? '0'),
            ':nominee3_minor_flag' => $isNominationOptedOut ? 'N' : ($data['nominee3MinorFlag'] ?? 'N'),
            ':nominee3_dob' => ($isNominationOptedOut || empty($data['nominee3DOB'])) ? null : $data['nominee3DOB'],
            ':nominee3_guardian' => $isNominationOptedOut ? '' : ($data['nominee3Guardian'] ?? ''),
            ':nominee3_guardian_pan' => ($isNominationOptedOut || empty($data['nominee3GuardianPAN'])) ? '' : strtoupper($data['nominee3GuardianPAN']),
            ':nominee3_identity_type' => $isNominationOptedOut ? '' : ($data['nominee3IdentityType'] ?? ''),
            ':nominee3_identity_no' => $isNominationOptedOut ? '' : ($data['nominee3IdentityNo'] ?? ''),
            ':nominee3_email' => $isNominationOptedOut ? '' : ($data['nominee3Email'] ?? ''),
            ':nominee3_mobile' => $isNominationOptedOut ? '' : ($data['nominee3Mobile'] ?? ''),
            ':nominee3_address' => $isNominationOptedOut ? '' : $nominee3Address,
            ':nominee3_city' => $isNominationOptedOut ? '' : $nominee3City,
            ':nominee3_pincode' => $isNominationOptedOut ? '' : $nominee3Pincode,
            ':nominee3_country' => $isNominationOptedOut ? '' : $this->getCountryNameByCodeOrName($nominee3CountryInput),
            ':nominee3_address_same_as_holder' => $isNominationOptedOut ? 'N' : ($isSame3 ? 'Y' : ($data['nominee3AddressSameAsHolder'] ?? 'N')),
            ':nominee_names_status' => $isNominationOptedOut ? '' : ($data['nomineeNamesRegistrationStatus'] ?? ''),
            ':filler1' => $isNominationOptedOut ? '' : ($data['filler1'] ?? '')
        ];
        
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute($params);
        
        if (!$success) {
            throw new Exception("Failed to update nominee details");
        }
        
        // FIXED: Check if user has any nominee data OR has opted out of nomination
        $hasNominee = $this->hasAnyNomineeData($data);
        $isNominationOptedOut = ($data['nomination'] ?? '') === 'N';
        
        if ($hasNominee || $isNominationOptedOut) {
            $this->updateUserStatus(
                $userId,
                'nominee_details_submitted',
                'Y'
            );
            error_log("Nominee status set to Y for user $userId: hasNominee=$hasNominee, optedOut=$isNominationOptedOut");
        }
        
        return true;
        
    } catch (Exception $e) {
        throw $e;
    }
}

    // Check if user has nominee details
    public function hasNomineeDetails($userId) {
        $sql = "SELECT id FROM vw_client_profiles WHERE id = ? AND (NOMINEE_1_NAME IS NOT NULL AND NOMINEE_1_NAME != '')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch() !== false;
    }
    
    public function getNseInvestApiCredential()
    {
        $sql = "CALL sp_nse_get_active_api_credentials()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }
	public function saveVerificationToken($email, $token, $expiryTime)
	{
		try {
			// SQL
			$sql = "UPDATE vw_client_profiles 
					SET RESET_TOKEN = ?, 
						RESET_TOKEN_EXPIRY = ?,
						UPDATED_AT = NOW()
					WHERE ID = ?";

			$stmt = $this->pdo->prepare($sql);

			$stmt->execute([$token, $expiryTime, $email]);

			// ---- DEBUG RESULT ----
			$rowCount = $stmt->rowCount();

			// Important insight:
			// rowCount() === 0 means:
			// 1) EMAIL not found
			// 2) Same token already stored
			// 3) Same expiry already stored

			return $rowCount > 0;

		} catch (PDOException $e) {
			//error_log('PDO Error in saveVerificationToken: ' . $e->getMessage());
			//error_log('SQLSTATE: ' . ($e->errorInfo[0] ?? 'N/A'));
			return false;

		} catch (Exception $e) {
			//error_log('General Error in saveVerificationToken: ' . $e->getMessage());
			return false;
		}
	}
	public function isEmailAlreadyVerified($email)
	{
		try {
			$sql = "
				SELECT EMAIL_VERIFIED 
				FROM vw_client_profiles 
				WHERE EMAIL = ?
				LIMIT 1
			";

			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$email]);

			$result = $stmt->fetch(PDO::FETCH_ASSOC);

			// If user exists and email is verified
			return $result && (int)$result['EMAIL_VERIFIED'] === 1;

		} catch (Exception $e) {
			//error_log("Error checking email verification status: " . $e->getMessage());
			return false;
		}
	}
    
	public function saveFatcaDetails($userId, $fatcaData)
	{
		//error_log("=== FATCA SAVE - fatcaData=".print_r($fatcaData,true));
		
		// Initialize success as false
		$success = false;
		$inTransaction = false;
		
		try {
			// Start transaction only if not already in one
			$inTransaction = $this->pdo->inTransaction();
			if (!$inTransaction) {
				$this->pdo->beginTransaction();
			}

			//error_log("=== FATCA SAVE - Checking Financial Fields ===");
			$financialFields = ['srce_wealt', 'inc_slab', 'pep_flag', 'occ_code', 'occ_type'];
			foreach ($financialFields as $field) {
				//error_log("$field: " . ($fatcaData[$field] ?? 'NOT SET'));
			}

			// SQL WITHOUT any PHP comments inside the string
			$sql = "
				INSERT INTO fatca_details (
					user_id, pan_rp, pekrn, inv_name, dob, fr_name, sp_name,
					tax_status_us, data_src, addr_type, po_bir_inc, co_bir_inc,
					tax_res1, tpin1, id1_type, tax_res2, tpin2, id2_type,
					tax_res3, tpin3, id3_type, tax_res4, tpin4, id4_type,
					srce_wealt, corp_servs, inc_slab, net_worth, nw_date,
					pep_flag, occ_code, occ_type, exemp_code, ffi_drnfe,
					giin_no, spr_entity, giin_na, giin_exemc, nffe_catg,
					act_nfe_sc, nature_bus, rel_listed, exch_name, ubo_appl,
					ubo_count, ubo_name, ubo_pan, ubo_nation, ubo_add1,
					ubo_add2, ubo_add3, ubo_city, ubo_pin, ubo_state,
					ubo_cntry, ubo_add_type, ubo_ctr, ubo_tin, ubo_id_ty,
					ubo_cob, ubo_dob, ubo_gender, ubo_fr_nam, ubo_occ,
					ubo_occ_ty, ubo_tel, ubo_mobile, ubo_code, ubo_hol_pc,
					sdf_flag, ubo_df, aadhaar_rp, new_cha, log_name,
					ubo_exch, ubo_isin, ubo_rel_li, ubo_categ, ubo_pep_fl,
					ubo_email, ubo_smo_de, npo_form, npo_dcl, npo_rgno,
					filler1, filler2, created_at, updated_at
				) VALUES (
					:user_id, :pan_rp, :pekrn, :inv_name, :dob, :fr_name, :sp_name,
					:tax_status_us, :data_src, :addr_type, :po_bir_inc, :co_bir_inc,
					:tax_res1, :tpin1, :id1_type, :tax_res2, :tpin2, :id2_type,
					:tax_res3, :tpin3, :id3_type, :tax_res4, :tpin4, :id4_type,
					:srce_wealt, :corp_servs, :inc_slab, :net_worth, :nw_date,
					:pep_flag, :occ_code, :occ_type, :exemp_code, :ffi_drnfe,
					:giin_no, :spr_entity, :giin_na, :giin_exemc, :nffe_catg,
					:act_nfe_sc, :nature_bus, :rel_listed, :exch_name, :ubo_appl,
					:ubo_count, :ubo_name, :ubo_pan, :ubo_nation, :ubo_add1,
					:ubo_add2, :ubo_add3, :ubo_city, :ubo_pin, :ubo_state,
					:ubo_cntry, :ubo_add_type, :ubo_ctr, :ubo_tin, :ubo_id_ty,
					:ubo_cob, :ubo_dob, :ubo_gender, :ubo_fr_nam, :ubo_occ,
					:ubo_occ_ty, :ubo_tel, :ubo_mobile, :ubo_code, :ubo_hol_pc,
					:sdf_flag, :ubo_df, :aadhaar_rp, :new_cha, :log_name,
					:ubo_exch, :ubo_isin, :ubo_rel_li, :ubo_categ, :ubo_pep_fl,
					:ubo_email, :ubo_smo_de, :npo_form, :npo_dcl, :npo_rgno,
					:filler1, :filler2, NOW(), NOW()
				)
				ON DUPLICATE KEY UPDATE
					pekrn = VALUES(pekrn),
					inv_name = VALUES(inv_name),
					dob = VALUES(dob),
					fr_name = VALUES(fr_name),
					sp_name = VALUES(sp_name),
					tax_status_us = VALUES(tax_status_us),
					data_src = VALUES(data_src),
					addr_type = VALUES(addr_type),
					po_bir_inc = VALUES(po_bir_inc),
					co_bir_inc = VALUES(co_bir_inc),
					tax_res1 = VALUES(tax_res1),
					tpin1 = VALUES(tpin1),
					id1_type = VALUES(id1_type),
					tax_res2 = VALUES(tax_res2),
					tpin2 = VALUES(tpin2),
					id2_type = VALUES(id2_type),
					tax_res3 = VALUES(tax_res3),
					tpin3 = VALUES(tpin3),
					id3_type = VALUES(id3_type),
					tax_res4 = VALUES(tax_res4),
					tpin4 = VALUES(tpin4),
					id4_type = VALUES(id4_type),
					srce_wealt = VALUES(srce_wealt),
					corp_servs = VALUES(corp_servs),
					inc_slab = VALUES(inc_slab),
					net_worth = VALUES(net_worth),
					nw_date = VALUES(nw_date),
					pep_flag = VALUES(pep_flag),
					occ_code = VALUES(occ_code),
					occ_type = VALUES(occ_type),
					exemp_code = VALUES(exemp_code),
					ffi_drnfe = VALUES(ffi_drnfe),
					giin_no = VALUES(giin_no),
					spr_entity = VALUES(spr_entity),
					giin_na = VALUES(giin_na),
					giin_exemc = VALUES(giin_exemc),
					nffe_catg = VALUES(nffe_catg),
					act_nfe_sc = VALUES(act_nfe_sc),
					nature_bus = VALUES(nature_bus),
					rel_listed = VALUES(rel_listed),
					exch_name = VALUES(exch_name),
					ubo_appl = VALUES(ubo_appl),
					ubo_count = VALUES(ubo_count),
					ubo_name = VALUES(ubo_name),
					ubo_pan = VALUES(ubo_pan),
					ubo_nation = VALUES(ubo_nation),
					ubo_add1 = VALUES(ubo_add1),
					ubo_add2 = VALUES(ubo_add2),
					ubo_add3 = VALUES(ubo_add3),
					ubo_city = VALUES(ubo_city),
					ubo_pin = VALUES(ubo_pin),
					ubo_state = VALUES(ubo_state),
					ubo_cntry = VALUES(ubo_cntry),
					ubo_add_type = VALUES(ubo_add_type),
					ubo_ctr = VALUES(ubo_ctr),
					ubo_tin = VALUES(ubo_tin),
					ubo_id_ty = VALUES(ubo_id_ty),
					ubo_cob = VALUES(ubo_cob),
					ubo_dob = VALUES(ubo_dob),
					ubo_gender = VALUES(ubo_gender),
					ubo_fr_nam = VALUES(ubo_fr_nam),
					ubo_occ = VALUES(ubo_occ),
					ubo_occ_ty = VALUES(ubo_occ_ty),
					ubo_tel = VALUES(ubo_tel),
					ubo_mobile = VALUES(ubo_mobile),
					ubo_code = VALUES(ubo_code),
					ubo_hol_pc = VALUES(ubo_hol_pc),
					sdf_flag = VALUES(sdf_flag),
					ubo_df = VALUES(ubo_df),
					aadhaar_rp = VALUES(aadhaar_rp),
					new_cha = VALUES(new_cha),
					log_name = VALUES(log_name),
					ubo_exch = VALUES(ubo_exch),
					ubo_isin = VALUES(ubo_isin),
					ubo_rel_li = VALUES(ubo_rel_li),
					ubo_categ = VALUES(ubo_categ),
					ubo_pep_fl = VALUES(ubo_pep_fl),
					ubo_email = VALUES(ubo_email),
					ubo_smo_de = VALUES(ubo_smo_de),
					npo_form = VALUES(npo_form),
					npo_dcl = VALUES(npo_dcl),
					npo_rgno = VALUES(npo_rgno),
					filler1 = VALUES(filler1),
					filler2 = VALUES(filler2),
					updated_at = NOW()
			";

			$stmt = $this->pdo->prepare($sql);
			
			// Create a complete parameters array with defaults for all required fields
			$allFields = [
				'user_id', 'pan_rp', 'pekrn', 'inv_name', 'dob', 'fr_name', 'sp_name',
				'tax_status_us', 'data_src', 'addr_type', 'po_bir_inc', 'co_bir_inc',
				'tax_res1', 'tpin1', 'id1_type', 'tax_res2', 'tpin2', 'id2_type',
				'tax_res3', 'tpin3', 'id3_type', 'tax_res4', 'tpin4', 'id4_type',
				'srce_wealt', 'corp_servs', 'inc_slab', 'net_worth', 'nw_date',
				'pep_flag', 'occ_code', 'occ_type', 'exemp_code', 'ffi_drnfe',
				'giin_no', 'spr_entity', 'giin_na', 'giin_exemc', 'nffe_catg',
				'act_nfe_sc', 'nature_bus', 'rel_listed', 'exch_name', 'ubo_appl',
				'ubo_count', 'ubo_name', 'ubo_pan', 'ubo_nation', 'ubo_add1',
				'ubo_add2', 'ubo_add3', 'ubo_city', 'ubo_pin', 'ubo_state',
				'ubo_cntry', 'ubo_add_type', 'ubo_ctr', 'ubo_tin', 'ubo_id_ty',
				'ubo_cob', 'ubo_dob', 'ubo_gender', 'ubo_fr_nam', 'ubo_occ',
				'ubo_occ_ty', 'ubo_tel', 'ubo_mobile', 'ubo_code', 'ubo_hol_pc',
				'sdf_flag', 'ubo_df', 'aadhaar_rp', 'new_cha', 'log_name',
				'ubo_exch', 'ubo_isin', 'ubo_rel_li', 'ubo_categ', 'ubo_pep_fl',
				'ubo_email', 'ubo_smo_de', 'npo_form', 'npo_dcl', 'npo_rgno',
				'filler1', 'filler2'
			];
			
			// Build the parameters array
			$params = [];
			foreach ($allFields as $field) {
				if ($field === 'user_id') {
					$params[':user_id'] = $userId;
				} elseif ($field === 'dob' || $field === 'nw_date' || $field === 'ubo_dob') {
					// Handle date fields separately
					$value = $fatcaData[$field] ?? null;
					$params[':' . $field] = empty($value) || $value === '-0001-11-30' ? null : $value;
				} elseif ($field === 'net_worth' || $field === 'ubo_hol_pc' || $field === 'ubo_count') {
					// Handle numeric fields
					$value = $fatcaData[$field] ?? 0;
					$params[':' . $field] = is_numeric($value) ? floatval($value) : 0;
				} elseif ($field === 'occ_type' || $field === 'ubo_occ_ty') {
					// Ensure occupation type is truncated to 1 character (e.g. 'Business' -> 'B')
					$value = $fatcaData[$field] ?? '';
					$params[':' . $field] = !empty($value) ? substr(trim($value), 0, 1) : '';
				} else {
					// Handle all other fields
					$params[':' . $field] = $fatcaData[$field] ?? '';
				}
			}
			
			//error_log("Prepared parameters count: " . count($params));
			
			// Execute the statement
			$result = $stmt->execute($params);
			
			if (!$result) {
				$errorInfo = $stmt->errorInfo();
				//error_log("SQL Error: " . print_r($errorInfo, true));
				//error_log("=== FATCA UPSERT FAILED - SQL Error ===");
				
				if (!$inTransaction) {
					$this->pdo->rollBack();
				}
				return false; // Explicitly return false
			}

			if (!$inTransaction) {
				$this->pdo->commit();
			}
			
			$success = true;
			//error_log("=== FATCA UPSERT SUCCESS ===");

		} catch (Exception $e) {
			//error_log("FATCA UPSERT EXCEPTION: " . $e->getMessage());
			//error_log("Stack trace: " . $e->getTraceAsString());
			
			// Only rollback if we started the transaction
			if (!$inTransaction && $this->pdo->inTransaction()) {
				$this->pdo->rollBack();
			}
			
			// Return false, don't re-throw
			return false;
		}
		
		return $success;
	}
	
    public function getFatcaDetailsByPan($pan) {
        $sql = "SELECT * FROM fatca_details WHERE pan_rp = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pan]);
        return $stmt->fetch();
    }

    public function getFatcaDetailsByUserId($userId) {
		////error_log("in getFatcaDetailsByUserId: User ID= " . $userId);
        // Get user's PAN first
        $userSql = "SELECT PRIMARY_HOLDER_PAN FROM vw_client_profiles WHERE id = ?";
        $userStmt = $this->pdo->prepare($userSql);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        if (!$user || empty($user['PRIMARY_HOLDER_PAN'])) {
            return null;
        }
        
        return $this->getFatcaDetailsByPan($user['PRIMARY_HOLDER_PAN']);
    }

    public function clearFatcaDetails($pan) {
        $sql = "DELETE FROM fatca_details WHERE pan_rp = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$pan]);
    }

	/**
	 * Get Risk Profile master data
	 */
	public function getRiskProfile() {
		try {
			$sql = "SELECT risk_code as code, risk_name as details FROM mtb_risk_profile ORDER BY risk_code";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
			return $stmt->fetchAll();
		} catch (Exception $e) {
			//error_log("Error in getRiskProfile: " . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get Risk Profile by code
	 */
	public function getRiskProfileByCode($risk_code) {
		try {
			$sql = "SELECT risk_code as code, risk_name as details FROM mtb_risk_profile WHERE risk_code = ?";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$risk_code]);
			return $stmt->fetch();
		} catch (Exception $e) {
			//error_log("Error in getRiskProfileByCode: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Save Risk Profile for a user
	 */
	public function saveUserRiskProfile($userId, $riskProfileId)
	{
		try {
			// Convert frontend IDs to database codes if needed
			$riskCodeMap = [
				'high' => '1',
				'medium' => '2',
				'low' => '3',
				'conservative' => '4'
			];

			// Use mapping or original value
			$dbRiskCode = $riskCodeMap[$riskProfileId] ?? $riskProfileId;

			////error_log("Saving risk profile - User ID: $userId, Frontend ID: $riskProfileId, DB Code: $dbRiskCode");

			$sql = "UPDATE vw_client_profiles SET 
					RISK_CATEGORY_ID = :riskCode,
					UPDATED_AT = NOW()
					WHERE id = :userId";

			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([
				':riskCode' => $dbRiskCode,
				':userId' => $userId
			]);

			$rowCount = $stmt->rowCount();
			////error_log("Rows affected: $rowCount");

			// ✅ If any row updated, mark risk profile as done
			if ($rowCount > 0) {
				$this->updateUserStatus($userId, 'risk_profile_done', 'Y');
			}

			return $rowCount > 0;

		} catch (Exception $e) {
			//error_log("Error saving risk profile: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get user's Risk Profile
	 */
	public function getUserRiskProfile($userId) {
		try {
			// First try to join with mtb_risk_profile table
			$sql = "SELECT 
						u.RISK_CATEGORY_ID,
						r.risk_code,
						r.risk_name 
					FROM vw_client_profiles u 
					LEFT JOIN mtb_risk_profile r ON u.RISK_CATEGORY_ID = r.risk_code 
					WHERE u.id = ?";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$userId]);
			$result = $stmt->fetch();
			
			////error_log("Database result for user $userId: " . print_r($result, true));
			
			// If no join result or no risk category ID
			if (!$result || empty($result['RISK_CATEGORY_ID'])) {
				//error_log("No risk profile found for user $userId, checking directly...");
				
				// Try to get just the RISK_CATEGORY_ID FROM vw_client_profiles table
				$sql = "SELECT RISK_CATEGORY_ID FROM vw_client_profiles WHERE id = ?";
				$stmt = $this->pdo->prepare($sql);
				$stmt->execute([$userId]);
				$result = $stmt->fetch();
				
				if ($result && !empty($result['RISK_CATEGORY_ID'])) {
					// Try to get risk name from mtb_risk_profile
					$code = $result['RISK_CATEGORY_ID'];
					$sql = "SELECT risk_code, risk_name FROM mtb_risk_profile WHERE risk_code = ?";
					$stmt = $this->pdo->prepare($sql);
					$stmt->execute([$code]);
					$riskDetails = $stmt->fetch();
					
					if ($riskDetails) {
						$result['risk_code'] = $riskDetails['risk_code'];
						$result['risk_name'] = $riskDetails['risk_name'];
					}
				}
			}
			
			////error_log("Final result for user $userId: " . print_r($result, true));
			
			return $result ?: null;
			
		} catch (Exception $e) {
			//error_log("Error getting user risk profile: " . $e->getMessage());
			return false;
		}
	}
	/**
	 * Map risk code to frontend ID
	 */
	public function mapRiskCodeToId($riskCode) {
		$map = [
			'1' => 'high',
			'2' => 'medium', 
			'3' => 'low',
			'4' => 'conservative'
		];
		return $map[$riskCode] ?? $riskCode;
	}
	
	public function getUserAllData(int $userId): array
	{
		$stmt = $this->pdo->prepare("SELECT * FROM vw_client_profiles WHERE id = :id LIMIT 1");
		$stmt->execute([':id' => $userId]);
		$u = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$u) {
			throw new Exception('User not found');
		}

		/* ---------- Helpers ---------- */

		$splitName = function ($fullName) {
			$parts = preg_split('/\s+/', trim($fullName));
			return [
				'first'  => $parts[0] ?? '',
				'middle' => $parts[1] ?? '',
				'last'   => isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : ''
			];
		};

		$formatDate = function ($date) {
			if (empty($date) || $date === '0000-00-00') {
				return '';
			}
			return date('d/m/Y', strtotime($date));
		};

		$panExempt = function ($pan) {
			return 'N';
		};

		$primaryName  = $splitName($u['PRIMARY_HOLDER_NAME'] ?? '');
		$secondName   = $splitName($u['SECOND_HOLDER_NAME'] ?? '');
		$thirdName    = $splitName($u['THIRD_HOLDER_NAME'] ?? '');
		$guardianName = $splitName($u['GUARDIAN_FIRST_NAME'] ?? '');

		$kycCompliant = ($u['KYC_COMPLAINT'] ?? '') == '1' ? 'K' : '';

		/* ---------- BASE PAYLOAD (Nominee 1 INCLUDED HERE) ---------- */

		$reg = [
			'client_code' => $u['CLIENT_CODE'] ?? '',
					'primary_holder_first_name' => $primaryName['first'],
					'primary_holder_middle_name' => $primaryName['middle'],
					'primary_holder_last_name' => $primaryName['last'],
					'tax_status' => $u['TAX_STATUS'] ?? '',
					'gender' => $u['GENDER'] ?? '',
					'primary_holder_dob_incorporation' => $formatDate($u['PRIMARY_HOLDER_DOB'] ?? ''),
					'occupation_code' => $u['OCCUPATION_CODE'] ?? '',
					'holding_nature' => $u['HOLDING_NATURE'] ?? '',
					'second_holder_first_name' => $secondName['first'],
					'second_holder_middle_name' => $secondName['middle'],
					'second_holder_last_name' => $secondName['last'],
					'third_holder_first_name' => $thirdName['first'],
					'third_holder_middle_name' => $thirdName['middle'],
					'third_holder_last_name' => $thirdName['last'],
					'second_holder_dob' => $formatDate($u['SECOND_HOLDER_DOB'] ?? ''),
					'third_holder_dob' => $formatDate($u['THIRD_HOLDER_DOB'] ?? ''),
					'guardian_first_name' => $guardianName['first'],
					'guardian_middle_name' => $guardianName['middle'],
					'guardian_last_name' => $guardianName['last'],
					'guardian_dob' => $formatDate($u['GUARDIAN_DOB'] ?? ''),
					'primary_holder_pan_exempt' => $panExempt($u['PRIMARY_HOLDER_PAN'] ?? ''),
					'second_holder_pan_exempt' => $panExempt($u['SECOND_HOLDER_PAN'] ?? ''),
					'third_holder_pan_exempt' => $panExempt($u['THIRD_HOLDER_PAN'] ?? ''),
					'guardian_pan_exempt' => $panExempt($u['GUARDIAN_PAN'] ?? ''),
					'primary_holder_pan' => $u['PRIMARY_HOLDER_PAN'] ?? '',
					'second_holder_pan' => $u['SECOND_HOLDER_PAN'] ?? '',
					'third_holder_pan' => $u['THIRD_HOLDER_PAN'] ?? '',
					'guardian_pan' => $u['GUARDIAN_PAN'] ?? '',
					'primary_holder_exempt_category' => '',
					'second_holder_exempt_category' => '',
					'third_holder_exempt_category' => '',
					'guardian_exempt_category' => '',
					'client_type' => 'P', 
					'pms' => 'N', 
					'default_dp' => '', 
					'cdsl_dpid' => '',
					'cdslcltid' => '',
					'cmbp_id' => '', 
					'nsdldpid' => '',
					'nsdlcltid' => '',
					'account_type_1' => $u['ACCOUNT_TYPE_1'] ?? 'SB',
					'account_no_1' => $u['ACCOUNT_NO_1'] ?? '',
					'micr_no_1' => '',
					'ifsc_code_1' => $u['IFSC_CODE_1'] ?? '',
					'default_bank_flag_1' => 'Y',
					'account_type_2' => '',
					'account_no_2' => '',
					'micr_no_2' => '',
					'ifsc_code_2' => '',
					'default_bank_flag_2' => '',
					'account_type_3' => '',
					'account_no_3' => '',
					'micr_no_3' => '',
					'ifsc_code_3' => '',
					'default_bank_flag_3' => '',
					'account_type_4' => '',
					'account_no_4' => '',
					'micr_no_4' => '',
					'ifsc_code_4' => '',
					'default_bank_flag_4' => '',
					'account_type_5' => '',
					'account_no_5' => '',
					'micr_no_5' => '',
					'ifsc_code_5' => '',
					'default_bank_flag_5' => '',
					'cheque_name' => $u['BANK_HOLDER_NAME_1'] ?? '',
					'div_pay_mode' => '02',
					'address_1' => $u['ADDRESS_1'] ?? '',
					'address_2' => $u['ADDRESS_2'] ?? '',
					'address_3' => '',
					'city' => $u['CITY'] ?? '',
					'state' => $u['STATE'] ?? '',
					'pincode' => $u['PINCODE'] ?? '',
					'country' => $u['COUNTRY'] ?? '',
					'resi_phone' => '',
					'resi_fax' => '',
					'office_phone' => '',
					'office_fax' => '',
					'email' => $u['EMAIL'] ?? '',
					'communication_mode' => 'E',
					'foreign_address_1' => $u['FOREIGN_ADDRESS_1'] ?? '',
					'foreign_address_2' => '',
					'foreign_address_3' => '',
					'foreign_address_city' => '',
					'foreign_address_pincode' => $u['FOREIGN_ADDRESS_PINCODE'] ?? '',
					'foreign_address_state' => $u['FOREIGN_ADDRESS_STATE'] ?? '',
					'foreign_address_country' => $u['FOREIGN_ADDRESS_COUNTRY'] ?? '',
					'foreign_address_resi_phone' => '',
					'foreign_address_fax' => '',
					'foreign_address_off_phone' => '',
					'foreign_address_off_fax' => '',
					'indian_mobile_no' => $u['INDIAN_MOBILE_NO'] ?? '',
					'primary_holder_kyc_type' => $kycCompliant,
					'primary_holder_ckyc_number' => '', 
					'second_holder_kyc_type' => '',
					'second_holder_ckyc_number' => '',
					'third_holder_kyc_type' => '',
					'third_holder_ckyc_number' => '',
					'guardian_kyc_type' => '',
					'guardian_ckyc_number' => '',
					'primary_holder_kra_exempt_ref_no' => '',
					'second_holder_kra_exempt_ref_no' => '',
					'third_holder_kra_exempt_ref_no' => '',
					'guardian_exempt_ref_no' => '',
					'aadhaar_updated' => 'Y', 
					'mapin_id' => '', 
					'paperless_flag' => 'Z',
					'lei_no' => '',
					'lei_validity' => '',
					'mobile_declaration_flag' => 'SE',
					'email_declaration_flag' => 'SE',
					'second_holder_email' => $u['SECOND_HOLDER_EMAIL'] ?? '',
					'second_holder_email_declaration' => '',
					'second_holder_mobile' => $u['SECOND_HOLDER_MOBILE'] ?? '',
					'second_holder_mobile_declaration' => '',
					'third_holder_email' => $u['THIRD_HOLDER_EMAIL'] ?? '',
					'third_holder_email_declaration' => '',
					'third_holder_mobile' => $u['THIRD_HOLDER_MOBILE'] ?? '',
					'third_holder_mobile_declaration' => '',
					'guardian_relation' => $u['GUARDIAN_RELATIONSHIP'] ?? '',
					'nomination_opt' => $u['NOMINATION'] ?? 'N',
					'nomination_authentication' => $u['AUTHENTICATION'] ?? 'O',
					'nominee_soa' => 'Y',
					'nominee_opt_out_ref_no' => '',
					'reg_id' => '',
					'reg_status' => '',
					'reg_remark' => ''
		];

		/* ---------- NOMINEE 1 (ONLY IF NOMINATION IS NOT 'N') ---------- */
		if (($u['NOMINATION'] ?? 'N') !== 'N') {
			$reg = array_merge($reg, [
				'nominee_1_name' => $u['NOMINEE_1_NAME'] ?? '',
				'nominee_1_relationship' => $u['NOMINEE_1_RELATIONSHIP'] ?? '',
				'nominee_1_applicable' => $u['NOMINEE_1_APPLICABLE_PERCENT'] ?? '',
				'nominee_1_minor_flag' => $u['NOMINEE_1_MINOR_FLAG'] ?? '',
				'nominee_1_dob' => $formatDate($u['NOMINEE_1_DOB'] ?? ''),
				'nominee_1_guardian' => $u['NOMINEE_1_GUARDIAN'] ?? '',
				'nominee_1_guardian_pan' => $u['NOMINEE_1_GUARDIAN_PAN'] ?? '',
				'nominee_1_identity_type' => $u['NOMINEE_1_IDENTITY_TYPE'] ?? '',
				'nominee_1_identity_number' => $u['NOMINEE_1_IDENTITY_NO'] ?? '',
				'nominee_1_email' => $u['NOMINEE_1_EMAIL'] ?? '',
				'nominee_1_mobile' => $u['NOMINEE_1_MOBILE'] ?? '',
				'nominee_1_address1' => $u['NOMINEE_1_ADDRESS1'] ?? '',
				'nominee_1_city' => $u['NOMINEE_1_CITY'] ?? '',
				'nominee_1_pin' => $u['NOMINEE_1_PINCODE'] ?? '',
				'nominee_1_country' => $u['NOMINEE_1_COUNTRY'] ?? '',
			]);
		}

		/* ---------- NOMINEE 2 (ONLY IF NAME EXISTS) ---------- */
		if (!empty(trim($u['NOMINEE_2_NAME'] ?? ''))) {
			$reg = array_merge($reg, [
				'nominee_2_name' => $u['NOMINEE_2_NAME'],
				'nominee_2_relationship' => $u['NOMINEE_2_RELATIONSHIP'] ?? '',
				'nominee_2_applicable' => $u['NOMINEE_2_APPLICABLE_PERCENT'] ?? '',
				'nominee_2_minor_flag' => $u['NOMINEE_2_MINOR_FLAG'] ?? '',
				'nominee_2_dob' => $formatDate($u['NOMINEE_2_DOB'] ?? ''),
				'nominee_2_guardian' => $u['NOMINEE_2_GUARDIAN'] ?? '',
				'nominee_2_guardian_pan' => $u['NOMINEE_2_GUARDIAN_PAN'] ?? '',
				'nominee_2_identity_type' => $u['NOMINEE_2_IDENTITY_TYPE'] ?? '',
				'nominee_2_identity_number' => $u['NOMINEE_2_IDENTITY_NO'] ?? '',
				'nominee_2_email' => $u['NOMINEE_2_EMAIL'] ?? '',
				'nominee_2_mobile' => $u['NOMINEE_2_MOBILE'] ?? '',
				'nominee_2_address1' => $u['NOMINEE_2_ADDRESS1'] ?? '',
				'nominee_2_city' => $u['NOMINEE_2_CITY'] ?? '',
				'nominee_2_pin' => $u['NOMINEE_2_PINCODE'] ?? '',
				'nominee_2_country' => $u['NOMINEE_2_COUNTRY'] ?? '',
			]);
		}

		/* ---------- NOMINEE 3 (ONLY IF NAME EXISTS) ---------- */
		if (!empty(trim($u['NOMINEE_3_NAME'] ?? ''))) {
			$reg = array_merge($reg, [
				'nominee_3_name' => $u['NOMINEE_3_NAME'],
				'nominee_3_relationship' => $u['NOMINEE_3_RELATIONSHIP'] ?? '',
				'nominee_3_applicable' => $u['NOMINEE_3_APPLICABLE_PERCENT'] ?? '',
				'nominee_3_minor_flag' => $u['NOMINEE_3_MINOR_FLAG'] ?? '',
				'nominee_3_dob' => $formatDate($u['NOMINEE_3_DOB'] ?? ''),
				'nominee_3_guardian' => $u['NOMINEE_3_GUARDIAN'] ?? '',
				'nominee_3_guardian_pan' => $u['NOMINEE_3_GUARDIAN_PAN'] ?? '',
				'nominee_3_identity_type' => $u['NOMINEE_3_IDENTITY_TYPE'] ?? '',
				'nominee_3_identity_number' => $u['NOMINEE_3_IDENTITY_NO'] ?? '',
				'nominee_3_email' => $u['NOMINEE_3_EMAIL'] ?? '',
				'nominee_3_mobile' => $u['NOMINEE_3_MOBILE'] ?? '',
				'nominee_3_address1' => $u['NOMINEE_3_ADDRESS1'] ?? '',
				'nominee_3_city' => $u['NOMINEE_3_CITY'] ?? '',
				'nominee_3_pin' => $u['NOMINEE_3_PINCODE'] ?? '',
				'nominee_3_country' => $u['NOMINEE_3_COUNTRY'] ?? '',
			]);
		}

		return [
			'reg_details' => [$reg]
		];
	}
	
	
	public function getEuinNumber(): ?string
	{
		$sql = "CALL sp_nse_get_active_api_credentials()";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();

		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ? $row['euin_number'] : null;
	}

	public function getArnNumber(): ?string
	{
		$sql = "CALL sp_nse_get_active_api_credentials()";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();

		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ? $row['arn_number'] : null;
	}


	public function getInvestBuyData(int $userId): array
	{
		/*
			client_code
			kyc_flag
			euin_number
			euin_declaration = Y
			min_redemption_flag = Y/N
			dpc_flag = Y
		*/

		$sql = "
			SELECT 
				u.CLIENT_CODE,
				m.kyc_complient
			FROM vw_client_profiles u
			INNER JOIN mtb_user_status m 
				ON m.us_id = u.id
			WHERE u.id = :userId
			LIMIT 1
		";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([':userId' => $userId]);

		return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
	}
	public function extractUserIdFromToken($token) {
		if (strpos($token, 'Bearer ') === 0) {
			$token = substr($token, 7);
		}
		
		$parts = explode('.', $token);
		if (count($parts) === 3) {
			$secret = $this->getSecretCode();
			if (!$secret) return null;
			
			$headerB64 = $parts[0];
			$payloadB64 = $parts[1];
			$signatureB64 = $parts[2];
			
			$signatureCheck = hash_hmac('sha256', $headerB64 . "." . $payloadB64, $secret, true);
			$expectedSignature = rtrim(strtr(base64_encode($signatureCheck), '+/', '-_'), '=');
			
			if (hash_equals($expectedSignature, $signatureB64)) {
				$payload = json_decode(base64_decode(str_replace(['_','-'], ['/','+'], $payloadB64)), true);
				if (isset($payload['exp']) && time() > $payload['exp']) return null;
				return $payload['user_id'] ?? null;
			}
		}
		return null;
	}

/**
 * Convert single-digit numeric value to 2-digit code (01–09)
 * Leave 2+ digit values unchanged
 */
	
	public function updateFatcaDeclaration($userId, $status) {
    try {
        //error_log("📝 updateFatcaDeclaration called - User ID: $userId, Status: $status");
        
        $sql = "UPDATE vw_client_profiles SET FATCA_DECLARATION = ?, UPDATED_AT = NOW() WHERE id = ?";
        $stmt = $this->getPdo()->prepare($sql);
        
        $result = $stmt->execute([$status, $userId]);
        
        if ($result) {
            $rowCount = $stmt->rowCount();
            //error_log("✅ FATCA_DECLARATION updated successfully. Rows affected: $rowCount");
            return true;
        } else {
            //error_log("❌ Failed to execute FATCA_DECLARATION update query");
            return false;
        }
        
    } catch (Exception $e) {
        //error_log("❌ Error updating FATCA_DECLARATION: " . $e->getMessage());
        //error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

	public function getUserFatcaDataPayload(int $userId): array
	{
		////error_log("inside getUserFatcaDataPayload user id: " . $userId);
		$stmt = $this->pdo->prepare("SELECT * FROM fatca_details WHERE user_id = :id LIMIT 1");
		$stmt->execute([':id' => $userId]);
		$fatcaData = $stmt->fetch(PDO::FETCH_ASSOC);
		////error_log("inside getUserFatcaDataPayload fatcaData: " . print_r($fatcaData, true));
		if (!$fatcaData) {
			throw new Exception('User not found');
		}

		/* ---------- Helpers ---------- */

		$splitName = function ($fullName) {
			$parts = preg_split('/\s+/', trim($fullName));
			return [
				'first'  => $parts[0] ?? '',
				'middle' => $parts[1] ?? '',
				'last'   => isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : ''
			];
		};

		$formatDate = function ($date) {
			if (empty($date) || $date === '0000-00-00') {
				return '';
			}
			return date('d/m/Y', strtotime($date));
		};
		
		$twoDigitCode = function ($value) {
			if ($value === null || $value === '') {
				return '';
			}

			if (is_numeric($value) && (int)$value < 10) {
				return '0' . (int)$value;
			}

			return (string)$value;
		};

		

		$reg = [
			 'user_id' => $fatcaData['user_id'] ?? 0,
            'pan_rp' => $fatcaData['pan_rp'] ?? '',
            'pekrn' => $fatcaData['pekrn'] ?? '',
            'inv_name' => $fatcaData['inv_name'] ?? '',
            'dob' => $formatDate($fatcaData['dob'] ?? ''),
            'fr_name' => $fatcaData['fr_name'] ?? '',
            'sp_name' => $fatcaData['sp_name'] ?? '',
            'tax_status' => $twoDigitCode($fatcaData['tax_status_us'] ?? ''),
            'data_src' => $fatcaData['data_src'] ?? '',
            'addr_type' => $fatcaData['addr_type'] ?? '',
            'po_bir_inc' => $fatcaData['po_bir_inc'] ?? '',
            'co_bir_inc' => $fatcaData['co_bir_inc'] ?? '',
            'tax_res1' => $fatcaData['tax_res1'] ?? '',
            'tpin1' => $fatcaData['tpin1'] ?? '',
            'id1_type' => $fatcaData['id1_type'] ?? '',
            'tax_res2' => $fatcaData['tax_res2'] ?? '',
            'tpin2' => $fatcaData['tpin2'] ?? '',
            'id2_type' => $fatcaData['id2_type'] ?? '',
            'tax_res3' => $fatcaData['tax_res3'] ?? '',
            'tpin3' => $fatcaData['tpin3'] ?? '',
            'id3_type' => $fatcaData['id3_type'] ?? '',
            'tax_res4' => $fatcaData['tax_res4'] ?? '',
            'tpin4' => $fatcaData['tpin4'] ?? '',
            'id4_type' => $fatcaData['id4_type'] ?? '',
            'srce_wealt' => $twoDigitCode($fatcaData['srce_wealt'] ?? ''),
            'corp_servs' => $fatcaData['corp_servs'] ?? '',
            'inc_slab' => $fatcaData['inc_slab'] ?? '',
            'net_worth' => $fatcaData['net_worth'] ?? 0,
            'nw_date' => $formatDate($fatcaData['dob'] ?? ''),
            'pep_flag' => $fatcaData['pep_flag'] ?? 'N',
            'occ_code' => $twoDigitCode($fatcaData['occ_code'] ?? ''),
            'occ_type' => $fatcaData['occ_type'] ?? '',
            'exemp_code' => $fatcaData['exemp_code'] ?? '',
            'ffi_drnfe' => $fatcaData['ffi_drnfe'] ?? '',
            'giin_no' => $fatcaData['giin_no'] ?? '',
            'spr_entity' => $fatcaData['spr_entity'] ?? '',
            'giin_na' => $fatcaData['giin_na'] ?? 'N',
            'giin_exemc' => $fatcaData['giin_exemc'] ?? '',
            'nffe_catg' => $fatcaData['nffe_catg'] ?? '',
            'act_nfe_sc' => $fatcaData['act_nfe_sc'] ?? '',
            'nature_bus' => $fatcaData['nature_bus'] ?? '',
            'rel_listed' => $fatcaData['rel_listed'] ?? 'N',
            'exch_name' => $fatcaData['exch_name'] ?? '',
            'ubo_appl' => $fatcaData['ubo_appl'] ?? 'N',
            'ubo_count' => $fatcaData['ubo_count'] ?? 0,
            'ubo_name' => $fatcaData['ubo_name'] ?? '',
            'ubo_pan' => $fatcaData['ubo_pan'] ?? '',
            'ubo_nation' => $fatcaData['ubo_nation'] ?? '',
            'ubo_add1' => $fatcaData['ubo_add1'] ?? '',
            'ubo_add2' => $fatcaData['ubo_add2'] ?? '',
            'ubo_add3' => $fatcaData['ubo_add3'] ?? '',
            'ubo_city' => $fatcaData['ubo_city'] ?? '',
            'ubo_pin' => $fatcaData['ubo_pin'] ?? '',
            'ubo_state' => $fatcaData['ubo_state'] ?? '',
            'ubo_cntry' => $fatcaData['ubo_cntry'] ?? '',
            'ubo_add_type' => $fatcaData['ubo_add_type'] ?? '',
            'ubo_ctr' => $fatcaData['ubo_ctr'] ?? '',
            'ubo_tin' => $fatcaData['ubo_tin'] ?? '',
            'ubo_id_ty' => $fatcaData['ubo_id_ty'] ?? '',
            'ubo_cob' => $fatcaData['ubo_cob'] ?? '',
            'ubo_dob' => $formatDate($fatcaData['dob'] ?? ''),
            'ubo_gender' => $fatcaData['ubo_gender'] ?? '',
            'ubo_fr_nam' => $fatcaData['ubo_fr_nam'] ?? '',
            'ubo_occ' => $fatcaData['ubo_occ'] ?? '',
            'ubo_occ_ty' => $fatcaData['ubo_occ_ty'] ?? '',
            'ubo_tel' => $fatcaData['ubo_tel'] ?? '',
            'ubo_mobile' => $fatcaData['ubo_mobile'] ?? '',
            'ubo_code' => $fatcaData['ubo_code'] ?? '',
            'ubo_hol_pc' => $fatcaData['ubo_hol_pc'] ?? 0,
            'sdf_flag' => $fatcaData['sdf_flag'] ?? 'N',
            'ubo_df' => $fatcaData['ubo_df'] ?? 'N',
            'aadhaar_rp' => $fatcaData['aadhaar_rp'] ?? '',
            'new_cha' => $fatcaData['new_cha'] ?? 'N',
            'log_name' => $fatcaData['log_name'] ?? '',
            'ubo_exch' => $fatcaData['ubo_exch'] ?? '',
            'ubo_isin' => $fatcaData['ubo_isin'] ?? '',
            'ubo_rel_li' => $fatcaData['ubo_rel_li'] ?? 'N',
            'ubo_categ' => $fatcaData['ubo_categ'] ?? '',
            'ubo_pep_fl' => $fatcaData['ubo_pep_fl'] ?? 'N',
            'ubo_email' => $fatcaData['ubo_email'] ?? '',
            'ubo_smo_de' => $fatcaData['ubo_smo_de'] ?? 'N',
            'npo_form' => $fatcaData['npo_form'] ?? 'N',
            'npo_dcl' => $fatcaData['npo_dcl'] ?? 'N',
            'npo_rgno' => $fatcaData['npo_rgno'] ?? '',
            'filler1' => $fatcaData['filler1'] ?? '',
            'filler2' => $fatcaData['filler2'] ?? ''
        ];
		

		return [
			'reg_details' => [$reg]
		];
	}

	public function sendMailViaSMTP(string $to, string $subject, string $htmlContent): bool
	{
		try {
			$mail = new PHPMailer(true);

			$mail->isSMTP();
			
			// Enable detailed SMTP debugging
			// $mail->SMTPDebug = 4; // 4 = Low-level data output
			// $mail->Debugoutput = function($str, $level) {
			// 	error_log("SMTP DEBUG [$level]: " . trim($str));
			// };
			
			// Bypass SSL certificate verification for localhost/WAMP
			$mail->SMTPOptions = [
			    'ssl' => [
			        'verify_peer' => false,
			        'verify_peer_name' => false,
			        'allow_self_signed' => true
			    ]
			];
			
			$mail->Host       = $this->smtp['email_host'];
			$mail->SMTPAuth   = true;
			$mail->Username   = $this->smtp['from_email'];
			$mail->Password   = trim($this->smtp['from_email_password'], '"');
			$mail->SMTPSecure = $this->smtp['secure'];
			$mail->Port       = (int) $this->smtp['email_port'];

			// From (company driven)
			$mail->setFrom(
				$this->smtp['from_email'],
				$this->smtp['from_name'] ?? $this->company['name']
			);

			$mail->addAddress($to);

			// Optional CC / BCC
			if (!empty($this->smtp['cc_email'])) {
				$mail->addCC($this->smtp['cc_email']);
			}

			if (!empty($this->smtp['bcc_email'])) {
				$mail->addBCC($this->smtp['bcc_email']);
			}

			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body    = $htmlContent;
			$mail->AltBody = 'Please view this email in an HTML-capable email client.';

			return $mail->send();

		} catch (Exception $e) {
			// error_log('SMTP ERROR: ' . $e->getMessage() . ' | PHPMailer Info: ' . $mail->ErrorInfo);
			return false;
		}
	}
	
	  /*public function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT id, email, password_hash FROM vw_client_profiles WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }*/
    
    public function updateUserPassword($userId, $hashedPassword) {
        $stmt = $this->pdo->prepare("UPDATE vw_client_profiles SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$hashedPassword, $userId]);
    }
    
    public function validateToken($token) {
        // Implement token validation logic
        $stmt = $this->pdo->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['user_id'] : null;
    }
    
    public function logActivity($userId, $action, $description) {
        $stmt = $this->pdo->prepare("INSERT INTO user_activities (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt->execute([$userId, $action, $description, $ip]);
    }
    
    public function invalidateUserSessions($userId, $keepCurrent = false) {
        // Implement session invalidation logic
        // This depends on how you handle sessions/tokens
    }
	
/**
 * Get recommended funds with full details
 */
public function getRecommendedFundsWithDetails() {
    try {
        $sql = "SELECT 
                    r.`recmd_id`, 
                    r.`category_id`, 
                    r.`amc`, 
                    r.`invest_type`, 
                    r.`fund_cat`, 
                    r.`product_id`, 
                    r.`allocation`, 
                    r.`recmd_rational`,
                    a.`LONG_NAME` as amc_name,
                    s.`scheme_name` as fund_name,
                    s.`isin`,
                    s.`fund_cat` as product_category,
                    s.`scheme_type` as asset_class,
                    s.`scheme_type`,
                    rp.`risk_name` as risk_category
                FROM `tb_recommanded_funds` r
                LEFT JOIN `mtb_amc` a ON r.`amc` = a.`AMC_CODE`
                LEFT JOIN `mtb_scheme_master` s ON r.`product_id` = s.`scheme_code`
                LEFT JOIN `mtb_risk_profile` rp ON r.`category_id` = rp.`risk_code`
                WHERE 1 
                ORDER BY r.`category_id`, r.`recmd_id`";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getRecommendedFundsWithDetails: " . $e->getMessage());
        return [];
    }
}

	// Add these methods to your existing MfdDb class

	/**
 * Get tax saver funds from tb_taxsaver table
 */
public function getTaxSaverFunds() {
    try {
        $sql = "SELECT `id`, `amc_code`, `scheme_code`, `rational` 
                FROM `tb_taxsaver` 
                WHERE 1 
                ORDER BY `id`";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getTaxSaverFunds: " . $e->getMessage());
        return [];
    }
}

/**
 * Get tax saver funds by AMC code
 */
public function getTaxSaverFundsByAmc($amc_code) {
    try {
        $sql = "SELECT `id`, `amc_code`, `scheme_code`, `rational` 
                FROM `tb_taxsaver` 
                WHERE `amc_code` = ? 
                ORDER BY `id`";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$amc_code]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getTaxSaverFundsByAmc: " . $e->getMessage());
        return [];
    }
}

/**
 * Get specific tax saver fund details
 */
public function getTaxSaverDetails($amc_code, $scheme_code) {
    try {
        $sql = "SELECT `id`, `amc_code`, `scheme_code`, `rational` 
                FROM `tb_taxsaver` 
                WHERE `amc_code` = ? AND `scheme_code` = ? 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$amc_code, $scheme_code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getTaxSaverDetails: " . $e->getMessage());
        return false;
    }
}
	/**
	 * Get tax saver fund by ID
	 */
	public function getTaxSaverFundsById($id) {
		try {
			$sql = "SELECT `id`, `amc_code`, `product_code`, `rational` 
					FROM `tb_taxsaver` 
					WHERE `id` = ? 
					LIMIT 1";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$id]);
			$taxSaverFund = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$taxSaverFund) {
				return false;
			}
			
			// Enhance with additional details
			$amc_code = $taxSaverFund['amc_code'];
			$product_code = $taxSaverFund['product_code'];
			
			$fundDetails = $this->getMfMasterByCode($product_code);
			$amcDetails = $this->getAmcMasterByCode($amc_code);
			
			return array_merge($taxSaverFund, [
				'fund_details' => $fundDetails ?: [],
				'amc_details' => $amcDetails ?: []
			]);
			
		} catch (Exception $e) {
			//error_log("Error in getTaxSaverFundsById: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get all FAQs
	 */
	public function getFaqs() {
		try {
			$sql = "SELECT * FROM `tb_faqs` WHERE 1 ORDER BY `faq_id`";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			//error_log("Error in getFaqs: " . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get FAQs by specific page
	 */
	public function getFaqsByPage($page) {
		try {
			$sql = "SELECT * FROM `tb_faqs` 
					WHERE `faq_page` = ? 
					ORDER BY `faq_id`";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$page]);
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			//error_log("Error in getFaqsByPage: " . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get specific FAQ by ID
	 */
	public function getFaqsById($faq_id) {
		try {
			$sql = "SELECT * FROM `tb_faqs` 
					WHERE `faq_id` = ? 
					LIMIT 1";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([$faq_id]);
			return $stmt->fetch(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			//error_log("Error in getFaqsById: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get FAQs by multiple pages (if needed for future)
	 */
	public function getFaqsByPages($pages) {
		try {
			$placeholders = implode(',', array_fill(0, count($pages), '?'));
			$sql = "SELECT * FROM `tb_faqs` 
					WHERE `faq_page` IN ($placeholders) 
					ORDER BY `faq_page`, `faq_id`";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($pages);
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			//error_log("Error in getFaqsByPages: " . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get scheme names by fund house, category, and scheme type (equity/hybrid/debt)
	 * from mtb_scheme_master table
	 */
	public function getSchemesByHouseAndCategory($amc_code, $fund_category, $scheme_type = null) {
		try {
			$sql = "SELECT `scheme_code`, `scheme_name` 
					FROM `mtb_scheme_master`
					WHERE `amc_code` = ?
					AND `fund_cat` = ?";
			
			$params = [$amc_code, $fund_category];
			
			// If scheme_type is provided (equity, hybrid, debt), add to filter
			if (!empty($scheme_type)) {
				$sql .= " AND `scheme_type` = ?";
				$params[] = $scheme_type;
			}
			
			$sql .= " ORDER BY `scheme_name` ASC";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($params);
			
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			error_log("Error in getSchemesByHouseAndCategory: " . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get all schemes by fund house and category (multiple scheme types at once)
	 */
	public function getSchemesByHouseCategoryAndTypes($amc_code, $fund_category, $scheme_types = ['equity', 'hybrid', 'debt']) {
		try {
			$placeholders = implode(',', array_fill(0, count($scheme_types), '?'));
			
			$sql = "SELECT `scheme_code`, `scheme_name`, `scheme_type`
					FROM `mtb_scheme_master`
					WHERE `amc_code` = ?
					AND `fund_cat` = ?
					AND `scheme_type` IN ($placeholders)
					ORDER BY `scheme_type`, `scheme_name` ASC";
			
			$params = array_merge([$amc_code, $fund_category], $scheme_types);
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($params);
			
			$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			// Group by scheme type
			$grouped = [];
			foreach ($result as $row) {
				$type = $row['scheme_type'];
				if (!isset($grouped[$type])) {
					$grouped[$type] = [];
				}
				$grouped[$type][] = [
					'scheme_code' => $row['scheme_code'],
					'scheme_name' => $row['scheme_name']
				];
			}
			
			return $grouped;
		} catch (Exception $e) {
			error_log("Error in getSchemesByHouseCategoryAndTypes: " . $e->getMessage());
			return [];
		}
	}

 

    public function getUserIdFromToken($token) {
        return $this->extractUserIdFromToken($token);
    }

    public function validateTokenExpiration($token) {
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $parts[1]))), true);
            
            if (isset($payload['exp'])) {
                $currentTime = time();
                if ($payload['exp'] < $currentTime) {
                    return false;
                }
                return true;
            }
        }
        
        return false;
    }

    // ─── Mandate Registration ───────────────────────────────────────

    /**
     * Get all mandates for a user
     */
   public function getMandatesByUserId($userId) {
    $sql = "SELECT id, mandate_id, user_id, client_code, client_name, member_code,
                   bank_name, bank_branch, amount, mandate_type, mandate_collection_type,
                   account_no, ac_type, ifsc_code, micr_no, start_date, end_date,
                   approved_date, date_of_upload, date_of_reupload, registration_date,
                   registration_id, umrn_no, reg_status, reg_remark, approved_remark,
                   error_remark, member_mandate_no, is_active, created_at, updated_at
            FROM mandate_details
            WHERE user_id = ?
            ORDER BY created_at DESC";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

    /**
     * Get a single mandate by its ID
     */
    public function getMandateById($mandateId) {
    $sql = "SELECT id, mandate_id, user_id, client_code, client_name, member_code,
                   bank_name, bank_branch, amount, mandate_type, mandate_collection_type,
                   account_no, ac_type, ifsc_code, micr_no, start_date, end_date,
                   approved_date, date_of_upload, date_of_reupload, registration_date,
                   registration_id, umrn_no, reg_status, reg_remark, approved_remark,
                   error_remark, member_mandate_no, is_active, created_at, updated_at
            FROM mandate_details
            WHERE id = ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$mandateId]);
    return $stmt->fetch();
}


    /**
     * Check if user already has an active mandate
     */
    public function getActiveMandateByUserId($userId) {
        $sql = "SELECT id, reg_status, start_date, end_date, amount
                FROM mandate_details
                WHERE user_id = ? AND is_active = 1
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    /**
     * Create a new mandate record
     * Returns the inserted ID on success, false on failure
     */
   public function createMandate($data) {
    $sql = "INSERT INTO mandate_details (
                user_id, amount, start_date, end_date, mandate_type, 
                mandate_collection_type, is_active, created_at, updated_at
            ) VALUES (
                :user_id, :amount, :start_date, :end_date, :mandate_type,
                :mandate_collection_type, 1, NOW(), NOW()
            )";

    $stmt = $this->pdo->prepare($sql);
    $success = $stmt->execute([
        ':user_id' => $data['user_id'],
        ':amount' => $data['amount'],
        ':start_date' => $data['start_date'],
        ':end_date' => $data['end_date'],
        ':mandate_type' => $data['mandate_type'] ?? 'X',
        ':mandate_collection_type' => $data['mandate_collection_type'] ?? 'ENACH',
    ]);

    return $success ? $this->pdo->lastInsertId() : false;
}


    /**
     * Update mandate_details row with NSE API response data
     */
   public function updateMandateFromNse($mandateId, $data) {
    error_log("updateMandateFromNse called for mandate #{$mandateId}");
    
    $sql = "UPDATE mandate_details SET
                mandate_id = :mandate_id,
                client_code = :client_code,
                client_name = :client_name,
                member_code = :member_code,
                bank_name = :bank_name,
                bank_branch = :bank_branch,
                amount = :amount,
                account_no = :account_no,
                ac_type = :ac_type,
                ifsc_code = :ifsc_code,
                micr_no = :micr_no,
                start_date = :start_date,
                end_date = :end_date,
                approved_date = :approved_date,
                date_of_upload = :date_of_upload,
                date_of_reupload = :date_of_reupload,
                registration_date = :registration_date,
                registration_id = :registration_id,
                umrn_no = :umrn_no,
                reg_status = :reg_status,
                reg_remark = :reg_remark,
                approved_remark = :approved_remark,
                error_remark = :error_remark,
                member_mandate_no = :member_mandate_no,
                updated_at = NOW()
            WHERE id = :id";
    
    // Use NULL for empty member_mandate_no to avoid UNIQUE constraint on empty strings
    $memberMandateNo = $data['member_mandate_no'] ?? null;
    if (is_string($memberMandateNo) && trim($memberMandateNo) === '') {
        $memberMandateNo = null;
    }
    
    $params = [
        ':id' => $mandateId,
        ':mandate_id' => $data['mandate_id'] ?? null,
        ':client_code' => $data['client_code'] ?? '',
        ':client_name' => $data['client_name'] ?? '',
        ':member_code' => $data['member_code'] ?? '',
        ':bank_name' => $data['bank_name'] ?? '',
        ':bank_branch' => $data['bank_branch'] ?? '',
        ':amount' => $data['amount'] ?? 0,
        ':account_no' => $data['account_no'] ?? '',
        ':ac_type' => $data['ac_type'] ?? 'SB',
        ':ifsc_code' => $data['ifsc_code'] ?? '',
        ':micr_no' => $data['micr_no'] ?? '',
        ':start_date' => $data['start_date'] ?? null,
        ':end_date' => $data['end_date'] ?? null,
        ':approved_date' => $data['approved_date'] ?? null,
        ':date_of_upload' => $data['date_of_upload'] ?? null,
        ':date_of_reupload' => $data['date_of_reupload'] ?? null,
        ':registration_date' => $data['registration_date'] ?? null,
        ':registration_id' => $data['registration_id'] ?? '',
        ':umrn_no' => $data['umrn_no'] ?? '',
        ':reg_status' => $data['reg_status'] ?? 'PENDING',
        ':reg_remark' => $data['reg_remark'] ?? '',
        ':approved_remark' => $data['approved_remark'] ?? '',
        ':error_remark' => $data['error_remark'] ?? '',
        ':member_mandate_no' => $memberMandateNo,
    ];
    
    $stmt = $this->pdo->prepare($sql);
    $result = $stmt->execute($params);
    $rowCount = $stmt->rowCount();
    error_log("updateMandateFromNse result=" . ($result ? 'true' : 'false') . " rows={$rowCount}");
    
    return $result;
}
/**
 * Get SIF fund categories for a specific AMC code
 */
public function getSifFundCategoriesByAmc($amc_code) {
    try {
        $sql = "SELECT DISTINCT 
                    `fund_cat` AS code,
                    `fund_cat` AS details
                FROM `mtb_scheme_master` 
                WHERE `amc_code` = :amc_code 
                    AND `amc_code` LIKE '%SIF'
                    AND `fund_cat` IS NOT NULL 
                    AND `fund_cat` != ''
                ORDER BY `fund_cat` ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':amc_code' => $amc_code]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getSifFundCategoriesByAmc: " . $e->getMessage());
        return [];
    }
}
/**
 * Get scheme master details by scheme_code
 */
public function getSchemeMasterByCode($scheme_code) {
    try {
        $sql = "SELECT `id`, `unique_sr_no`, `scheme_code`, `rta_scheme_code`, 
                       `amc_scheme_code`, `isin`, `amc_code`, `scheme_type`, 
                       `plan_type`, `scheme_name`, `purchase_allowed`, 
                       `purchase_transaction_mode`, `new_purchase_min_amount`, 
                       `additional_purchase_min_amount`, `additional_purchase_max_amount`, 
                       `purchase_amount_multiplier`, `purchase_cutoff_time`, 
                       `redemption_allowed`, `redemption_transaction_mode`, 
                       `redemption_min_qty`, `redemption_qty_multiplier`, 
                       `redemption_max_qty`, `redemption_min_amount`, 
                       `redemption_max_amount`, `redemption_amount_multiplier`, 
                       `redemption_cutoff_time`, `rta_agent_code`, `amc_active_flag`, 
                       `div_reinvest_flag`, `sip_allowed`, `stp_enabled`, `swp_enabled`, 
                       `switch_allowed`, `settlement_type`, `amc_ind`, `face_value`, 
                       `scheme_start_date`, `maturity_date`, `exit_load_flag`, `exit_load`, 
                       `lock_in_period_flag`, `lock_in_period`, `channel_partner_code`, 
                       `reopening_date`, `open_close_ended_scheme`, `fund_cat`, `cat_tag`, 
                       `created_at`, `updated_at`
                FROM `mtb_scheme_master` 
                WHERE `scheme_code` = ? 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$scheme_code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getSchemeMasterByCode: " . $e->getMessage());
        return false;
    }
}

/**
 * Get scheme master details by scheme_name (for searching)
 */
public function getSchemeMasterByName($scheme_name) {
    try {
        $sql = "SELECT `id`, `unique_sr_no`, `scheme_code`, `rta_scheme_code`, 
                       `amc_scheme_code`, `isin`, `amc_code`, `scheme_type`, 
                       `plan_type`, `scheme_name`, `purchase_allowed`, 
                       `purchase_transaction_mode`, `new_purchase_min_amount`, 
                       `additional_purchase_min_amount`, `additional_purchase_max_amount`, 
                       `purchase_amount_multiplier`, `purchase_cutoff_time`, 
                       `redemption_allowed`, `redemption_transaction_mode`, 
                       `redemption_min_qty`, `redemption_qty_multiplier`, 
                       `redemption_max_qty`, `redemption_min_amount`, 
                       `redemption_max_amount`, `redemption_amount_multiplier`, 
                       `redemption_cutoff_time`, `rta_agent_code`, `amc_active_flag`, 
                       `div_reinvest_flag`, `sip_allowed`, `stp_enabled`, `swp_enabled`, 
                       `switch_allowed`, `settlement_type`, `amc_ind`, `face_value`, 
                       `scheme_start_date`, `maturity_date`, `exit_load_flag`, `exit_load`, 
                       `lock_in_period_flag`, `lock_in_period`, `channel_partner_code`, 
                       `reopening_date`, `open_close_ended_scheme`, `fund_cat`, `cat_tag`, 
                       `created_at`, `updated_at`
                FROM `mtb_scheme_master` 
                WHERE `scheme_name` LIKE ? 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['%' . $scheme_name . '%']);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getSchemeMasterByName: " . $e->getMessage());
        return false;
    }

}

/**
 * Get mandate by member_mandate_id (unique identifier from NSE)
 */
public function getMandateByMemberMandateId($memberMandateId) {
    if (empty($memberMandateId)) {
        return null;
    }
    
    $sql = "SELECT id, mandate_id, user_id, client_code, client_name, member_code,
                   bank_name, bank_branch, amount, mandate_type, mandate_collection_type,
                   account_no, ac_type, ifsc_code, micr_no, start_date, end_date,
                   approved_date, date_of_upload, date_of_reupload, registration_date,
                   registration_id, umrn_no, reg_status, reg_remark, approved_remark,
                   error_remark, member_mandate_no, is_active, created_at, updated_at
            FROM mandate_details
            WHERE member_mandate_no = ?";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$memberMandateId]);
    return $stmt->fetch();
}

/**
 * Get mandate by client_code and registration_id
 */
public function getMandateByClientCodeAndRegId($clientCode, $registrationId)
{
    $sql = "SELECT id FROM mandate_details 
            WHERE client_code = ? AND registration_id = ? 
            LIMIT 1";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$clientCode, $registrationId]);
    return $stmt->fetch();
}

/**
 * Create a new mandate record from NSE data (when mandate wasn't created locally first)
 */
public function createMandateFromNseData($data) {
    $sql = "INSERT INTO mandate_details (
                user_id, mandate_id, client_code, client_name, member_code,
                bank_name, bank_branch, amount, mandate_type, mandate_collection_type,
                account_no, ac_type, ifsc_code, micr_no, start_date, end_date,
                approved_date, date_of_upload, date_of_reupload, registration_date,
                registration_id, umrn_no, reg_status, reg_remark, approved_remark,
                error_remark, member_mandate_no, is_active, created_at, updated_at
            ) VALUES (
                :user_id, :mandate_id, :client_code, :client_name, :member_code,
                :bank_name, :bank_branch, :amount, :mandate_type, :mandate_collection_type,
                :account_no, :ac_type, :ifsc_code, :micr_no, :start_date, :end_date,
                :approved_date, :date_of_upload, :date_of_reupload, :registration_date,
                :registration_id, :umrn_no, :reg_status, :reg_remark, :approved_remark,
                :error_remark, :member_mandate_no, 1, NOW(), NOW()
            )";
    
    // Try to find user by client_code
    $user = $this->getUserByClientCode($data['client_code'] ?? '');
    
    $params = [
        ':user_id' => $user['id'] ?? null,
        ':mandate_id' => $data['mandate_id'] ?? null,
        ':client_code' => $data['client_code'] ?? '',
        ':client_name' => $data['client_name'] ?? '',
        ':member_code' => $data['member_code'] ?? '',
        ':bank_name' => $data['bank_name'] ?? '',
        ':bank_branch' => $data['bank_branch'] ?? '',
        ':amount' => $data['amount'] ?? 0,
        ':mandate_type' => $data['mandate_type'] ?? 'X',
        ':mandate_collection_type' => $data['mandate_collection_type'] ?? 'ENACH',
        ':account_no' => $data['account_no'] ?? '',
        ':ac_type' => $data['ac_type'] ?? 'SB',
        ':ifsc_code' => $data['ifsc_code'] ?? '',
        ':micr_no' => $data['micr_no'] ?? '',
        ':start_date' => $data['start_date'] ?? null,
        ':end_date' => $data['end_date'] ?? null,
        ':approved_date' => $data['approved_date'] ?? null,
        ':date_of_upload' => $data['date_of_upload'] ?? null,
        ':date_of_reupload' => $data['date_of_reupload'] ?? null,
        ':registration_date' => $data['registration_date'] ?? null,
        ':registration_id' => $data['registration_id'] ?? '',
        ':umrn_no' => $data['umrn_no'] ?? '',
        ':reg_status' => $data['reg_status'] ?? 'PENDING',
        ':reg_remark' => $data['reg_remark'] ?? '',
        ':approved_remark' => $data['approved_remark'] ?? '',
        ':error_remark' => $data['error_remark'] ?? '',
        ':member_mandate_no' => $data['member_mandate_no'] ?? null,
    ];
    
    $stmt = $this->pdo->prepare($sql);
    $success = $stmt->execute($params);
    
    return $success ? $this->pdo->lastInsertId() : false;
}

/**
 * Get user by client_code
 */
public function getUserByClientCode($clientCode) {
    if (empty($clientCode)) {
        return null;
    }
    
    $sql = "SELECT id, CLIENT_CODE, PRIMARY_HOLDER_NAME, EMAIL 
            FROM vw_client_profiles 
            WHERE CLIENT_CODE = ? 
            LIMIT 1";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$clientCode]);
    return $stmt->fetch();
}


/**
 * Get all mandates with optional filters
 */
public function getAllMandates($filters = []) {
    $sql = "SELECT md.id, md.mandate_id, md.user_id, md.client_code, md.client_name,
                   md.member_code, md.bank_name, md.bank_branch, md.amount,
                   md.mandate_type, md.mandate_collection_type, md.account_no,
                   md.ac_type, md.ifsc_code, md.micr_no, md.start_date, md.end_date,
                   md.approved_date, md.date_of_upload, md.date_of_reupload,
                   md.registration_date, md.registration_id, md.umrn_no,
                   md.reg_status, md.reg_remark, md.approved_remark, md.error_remark,
                   md.member_mandate_no, md.is_active, md.created_at, md.updated_at,
                   u.PRIMARY_HOLDER_NAME as user_name, u.EMAIL as user_email
            FROM mandate_details md
            LEFT JOIN vw_client_profiles u ON md.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['client_code'])) {
        $sql .= " AND md.client_code = ?";
        $params[] = $filters['client_code'];
    }
    
    if (!empty($filters['reg_status'])) {
        $sql .= " AND md.reg_status = ?";
        $params[] = $filters['reg_status'];
    }
    
    if (!empty($filters['user_id'])) {
        $sql .= " AND md.user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['from_date'])) {
        $sql .= " AND md.registration_date >= ?";
        $params[] = $filters['from_date'];
    }
    
    if (!empty($filters['to_date'])) {
        $sql .= " AND md.registration_date <= ?";
        $params[] = $filters['to_date'];
    }
    
    if (!empty($filters['umrn_no'])) {
        $sql .= " AND md.umrn_no = ?";
        $params[] = $filters['umrn_no'];
    }
    
    $sql .= " ORDER BY md.created_at DESC";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}



/**
 * Get mandate by mandate_id (NSE mandate ID)
 */
public function getMandateByMandateId($mandateId) {
    if (empty($mandateId)) {
        return null;
    }
    
    $sql = "SELECT id, mandate_id, user_id, client_code, client_name, member_code,
                   bank_name, bank_branch, amount, mandate_type, mandate_collection_type,
                   account_no, ac_type, ifsc_code, micr_no, start_date, end_date,
                   approved_date, date_of_upload, date_of_reupload, registration_date,
                   registration_id, umrn_no, reg_status, reg_remark, approved_remark,
                   error_remark, member_mandate_no, is_active, created_at, updated_at
            FROM mandate_details
            WHERE mandate_id = ?";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$mandateId]);
    return $stmt->fetch();
}

/**
 * Get approved mandate by client code (for auto-selection)
 */
public function getApprovedMandateByClientCode($clientCode) {
    if (empty($clientCode)) {
        return null;
    }
    
    $sql = "SELECT id, mandate_id, user_id, client_code, client_name, member_code,
                   bank_name, bank_branch, amount, mandate_type, mandate_collection_type,
                   account_no, ac_type, ifsc_code, micr_no, start_date, end_date,
                   approved_date, date_of_upload, date_of_reupload, registration_date,
                   registration_id, umrn_no, reg_status, reg_remark, approved_remark,
                   error_remark, member_mandate_no, is_active, created_at, updated_at
            FROM mandate_details
            WHERE client_code = ? 
            AND reg_status = 'APPROVED'
            AND is_active = 1
            AND start_date <= CURDATE()
            AND end_date >= CURDATE()
            LIMIT 1";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$clientCode]);
    return $stmt->fetch();
}

/**
 * Get approved mandate by user ID (for auto-selection)
 */
public function getApprovedMandateByUserId($userId) {
    $sql = "SELECT id, mandate_id, user_id, client_code, client_name, member_code,
                   bank_name, bank_branch, amount, mandate_type, mandate_collection_type,
                   account_no, ac_type, ifsc_code, micr_no, start_date, end_date,
                   approved_date, date_of_upload, date_of_reupload, registration_date,
                   registration_id, umrn_no, reg_status, reg_remark, approved_remark,
                   error_remark, member_mandate_no, is_active, created_at, updated_at
            FROM mandate_details
            WHERE user_id = ? 
            AND reg_status = 'APPROVED'
            AND is_active = 1
            AND start_date <= CURDATE()
            AND end_date >= CURDATE()
            LIMIT 1";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Check if user has an active approved mandate
 */
public function hasActiveApprovedMandate($userId) {
    $mandate = $this->getApprovedMandateByUserId($userId);
    return !empty($mandate);
}

/**
 * Get mandate ID for approved mandate by user ID
 */
public function getApprovedMandateIdByUserId($userId) {
    $mandate = $this->getApprovedMandateByUserId($userId);
    return $mandate ? $mandate['mandate_id'] : null;
}

/**
 * Update client status from NSE sync
 */
public function updateClientStatus($userId, $fatcaDone, $activationStatus, $deactivationMessage): bool
{
    try {
        $this->pdo->beginTransaction();
        
        // Convert activation status to Y/N format
        $activationFlag = ($activationStatus === 'ACTIVE' || $activationStatus === 'Active') ? 'Y' : 'N';
        
        // Check if record exists
        $checkStmt = $this->pdo->prepare(
            "SELECT status_id FROM mtb_user_status WHERE us_id = :us_id FOR UPDATE"
        );
        $checkStmt->execute(['us_id' => $userId]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Update existing record
            $sql = "UPDATE mtb_user_status 
                    SET fatca_done = :fatca_done,
                        activation_status = :activation_status,
                        deactivation_message = :deactivation_message,
                        updated_at = NOW()
                    WHERE us_id = :us_id";
        } else {
            // Insert new record
            $sql = "INSERT INTO mtb_user_status (us_id, fatca_done, activation_status, deactivation_message, created_at, updated_at) 
                    VALUES (:us_id, :fatca_done, :activation_status, :deactivation_message, NOW(), NOW())";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'us_id' => $userId,
            'fatca_done' => $fatcaDone,
            'activation_status' => $activationFlag,
            'deactivation_message' => $deactivationMessage
        ]);
        
        // Update vw_client_profiles (UCC_ACTIVATION and FATCA_DECLARATION)
        $uccActivationVal = ($activationStatus === 'ACTIVE' || $activationStatus === 'Active' || $activationFlag === 'Y' || $activationStatus == 1) ? 1 : 0;
        $fatcaVal = ($fatcaDone === 'Y' || $fatcaDone == 1 || $fatcaDone === '1') ? 1 : 0;
        $profileStmt = $this->pdo->prepare(
            "UPDATE vw_client_profiles SET UCC_ACTIVATION = :ucc_activation, FATCA_DECLARATION = :fatca_declaration, UPDATED_AT = NOW() WHERE id = :id"
        );
        $profileStmt->execute([
            'ucc_activation' => $uccActivationVal,
            'fatca_declaration' => $fatcaVal,
            'id' => $userId
        ]);
        
        $this->pdo->commit();
        
        error_log("Updated client status for user {$userId}: fatca_done={$fatcaDone}, activation_status={$activationFlag}");
        
        return true;
        
    } catch (Exception $e) {
        $this->pdo->rollBack();
        error_log("Error updating client status: " . $e->getMessage());
        return false;
    }
}

public function updateUserCountry($userId, $country): bool
{
    if (empty($country)) return false;
    try {
        $countryName = $this->getCountryNameByCodeOrName($country);
        $sql = "UPDATE vw_client_profiles SET COUNTRY = ?, UPDATED_AT = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$countryName, $userId]);
    } catch (Exception $e) {
        error_log("Error updating user country: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all users with client codes
 */
public function getAllUsersWithClientCode(): array
{
    try {
        $sql = "SELECT id, CLIENT_CODE, PRIMARY_HOLDER_NAME, EMAIL 
                FROM vw_client_profiles 
                WHERE CLIENT_CODE IS NOT NULL 
                AND CLIENT_CODE != '' 
                ORDER BY id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error getting users with client codes: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user status including FATCA and activation info
 */
public function getUserCompleteStatus($userId): ?array
{
    try {
        $sql = "SELECT 
                    us.status_id,
                    us.us_id,
                    us.kyc_complient,
                    us.kyc_status_remark,
                    us.bank_details_submitted,
                    us.personal_details_submitted,
                    us.iin_physical_uploded,
                    us.cc_uploded,
                    us.kra_form_uploded,
                    us.ach_form_uploded,
                    us.fatca_done,
                    us.risk_profile_done,
                    us.activation_status,
                    us.deactivation_message,
                    us.pan_card,
                    us.driving_license,
                    us.passport,
                    us.adhar_card,
                    us.nominee_details_submitted,
                    us.created_at,
                    us.updated_at,
                    u.CLIENT_CODE,
                    u.PRIMARY_HOLDER_NAME,
                    u.EMAIL
                FROM mtb_user_status us
                LEFT JOIN vw_client_profiles u ON us.us_id = u.id
                WHERE us.us_id = :user_id
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
        
    } catch (Exception $e) {
        error_log("Error getting user complete status: " . $e->getMessage());
        return null;
    }
}
/**
 * Get all blog posts
 */
public function getAllBlogPosts() {
    try {
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                WHERE 1 
                ORDER BY `date` DESC, `created_at` DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getAllBlogPosts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get blog post by ID
 */
public function getBlogPostById($id) {
    try {
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                WHERE `id` = ? 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getBlogPostById: " . $e->getMessage());
        return false;
    }
}

/**
 * Get blog post by slug/url
 */
public function getBlogPostByUrl($url) {
    try {
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                WHERE `url` = ? 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$url]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getBlogPostByUrl: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent blog posts
 */
public function getRecentBlogPosts($limit = 6) {
    try {
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                WHERE 1 
                ORDER BY `date` DESC, `created_at` DESC 
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getRecentBlogPosts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get blog posts by topic/category
 */
public function getBlogPostsByTopic($topic, $limit = 0) {
    try {
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                WHERE `topic` = ? 
                ORDER BY `date` DESC, `created_at` DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$topic]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getBlogPostsByTopic: " . $e->getMessage());
        return [];
    }
}

/**
 * Get featured external blogs (if you have a separate table)
 */
public function getFeaturedExternalBlogs() {
    // If you have a separate table for external blogs, query it here
    // Otherwise return empty array
    return [];
}

/**
 * Get internal blog posts (all from tb_blog)
 */
public function getInternalBlogPosts($limit = 0, $featured = null) {
    try {
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                WHERE 1 
                ORDER BY `date` DESC, `created_at` DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getInternalBlogPosts: " . $e->getMessage());
        return [];
    }
}
/**
 * Get all media items from tb_media table
 */
public function getAllMediaItems() {
    try {
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE 1 
                ORDER BY `date` DESC, `created_at` DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getAllMediaItems: " . $e->getMessage());
        return [];
    }
}

/**
 * Get media item by ID
 */
public function getMediaItemById($id) {
    try {
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE `id` = ? 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getMediaItemById: " . $e->getMessage());
        return false;
    }
}

/**
 * Get media items by category
 */
public function getMediaItemsByCategory($category, $limit = 0) {
    try {
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE `category` = ? 
                ORDER BY `date` DESC, `created_at` DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getMediaItemsByCategory: " . $e->getMessage());
        return [];
    }
}

/**
 * Get media items by source
 */
public function getMediaItemsBySource($source, $limit = 0) {
    try {
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE `source` = ? 
                ORDER BY `date` DESC, `created_at` DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$source]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getMediaItemsBySource: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent media items with limit
 */
public function getRecentMediaItems($limit = 5) {
    try {
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE 1 
                ORDER BY `date` DESC, `created_at` DESC 
                LIMIT " . (int)$limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getRecentMediaItems: " . $e->getMessage());
        return [];
    }
}

/**
 * Get media coverage (articles from specific sources or types)
 * This can be customized based on your needs
 */
public function getMediaCoverage($limit = 0) {
    try {
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE `category` != 'Press Release' 
                ORDER BY `date` DESC, `created_at` DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getMediaCoverage: " . $e->getMessage());
        return [];
    }
}

/**
 * Get press releases (articles with category 'Press Release')
 */
public function getPressReleases($limit = 0) {
    try {
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE `category` = 'Press Release' 
                ORDER BY `date` DESC, `created_at` DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getPressReleases: " . $e->getMessage());
        return [];
    }
}

/**
 * Get distinct categories from tb_media
 */
public function getMediaCategories() {
    try {
        $sql = "SELECT DISTINCT `category` FROM `tb_media` WHERE `category` IS NOT NULL AND `category` != '' ORDER BY `category` ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error in getMediaCategories: " . $e->getMessage());
        return [];
    }
}

/**
 * Get distinct sources from tb_media
 */
public function getMediaSources() {
    try {
        $sql = "SELECT DISTINCT `source` FROM `tb_media` WHERE `source` IS NOT NULL AND `source` != '' ORDER BY `source` ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error in getMediaSources: " . $e->getMessage());
        return [];
    }
}

/**
 * Get media items by year
 */
public function getMediaItemsByYear($year, $limit = 0) {
    try {
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE YEAR(`date`) = ? 
                ORDER BY `date` DESC, `created_at` DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getMediaItemsByYear: " . $e->getMessage());
        return [];
    }
}
    public function getBlogById($id) {
    try {
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                WHERE `id` = ? 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getBlogById: " . $e->getMessage());
        return false;
    }
}

public function getRelatedPosts($currentId, $topic, $limit = 3) {
    try {
        $sql = "SELECT `id`, `title`, `topic`, `url`, `read_time` 
                FROM `tb_blog` 
                WHERE `topic` = ? AND `id` != ? 
                ORDER BY `date` DESC 
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$topic, $currentId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getRelatedPosts: " . $e->getMessage());
        return [];
    }

}
/**
 * Sync FATCA status between tb_users table and mtb_user_status
 */
public function syncFatcaStatus(int $userId): bool {
    try {
        // Check if FATCA details exist in fatca_details table
        $fatcaCheck = $this->getPdo()->prepare(
            "SELECT COUNT(*) as count FROM fatca_details WHERE user_id = ?"
        );
        $fatcaCheck->execute([$userId]);
        $hasFatca = ($fatcaCheck->fetch(PDO::FETCH_ASSOC)['count'] > 0);
        
        $fatcaDone = $hasFatca ? 'Y' : 'N';
        
        // Start transaction
        $this->getPdo()->beginTransaction();
        
        // UPDATE vw_client_profiles table
        $updateUser = $this->getPdo()->prepare(
            "UPDATE vw_client_profiles SET FATCA_DECLARATION = ? WHERE id = ?"
        );
        $updateUser->execute([$hasFatca ? 1 : 0, $userId]);
        
        // Update or insert into mtb_user_status
        $checkStmt = $this->getPdo()->prepare(
            "SELECT status_id FROM mtb_user_status WHERE us_id = ? FOR UPDATE"
        );
        $checkStmt->execute([$userId]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            $updateStatus = $this->getPdo()->prepare(
                "UPDATE mtb_user_status SET fatca_done = ?, updated_at = NOW() WHERE us_id = ?"
            );
            $updateStatus->execute([$fatcaDone, $userId]);
        } else {
            $insertStatus = $this->getPdo()->prepare(
                "INSERT INTO mtb_user_status (us_id, fatca_done, created_at, updated_at) VALUES (?, ?, NOW(), NOW())"
            );
            $insertStatus->execute([$userId, $fatcaDone]);
        }
        
        $this->getPdo()->commit();
        error_log("FATCA status synced for user {$userId}: fatca_done={$fatcaDone}");
        
        return true;
        
    } catch (Exception $e) {
        if ($this->getPdo()->inTransaction()) {
            $this->getPdo()->rollBack();
        }
        error_log("Error syncing FATCA status: " . $e->getMessage());
        return false;
    }
}
/**
 * Get latest blog posts
 * @param int $limit Number of posts to return
 * @param int|null $excludeId Blog ID to exclude (current post)
 * @return array Array of blog posts
 */
public function getLatestBlogs($limit = 5, $excludeId = null) {
    try {
        $sql = "SELECT id, title, description, image, topic, date, read_time, url 
                FROM tb_blog 
                WHERE 1=1";
        
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        
        $sql .= " ORDER BY date DESC, id DESC LIMIT :limit";
        
        // FIXED: Changed from $this->conn to $this->pdo
        $stmt = $this->pdo->prepare($sql);
        
        if ($excludeId !== null) {
            $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getLatestBlogs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get active testimonials
 */
public function getTestimonials() {
    try {
        $sql = "SELECT `id`, `name`, `role`, `text`, `image` FROM `tb_testimonials` WHERE `status` = 1 ORDER BY `sort_order` ASC, `id` DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getTestimonials: " . $e->getMessage());
        return [];
    }
}

// ─── USER STATUS HELPER METHODS ───────────────────────────────────────────

// Helper function to get a single status field from mtb_user_status table
public function getUserStatusField($userId, $field) {
    try {
        $fieldMap = [
            'kyc_complient' => 'kyc_complient',
            'fatca_submitted' => 'fatca_done',
            'risk_profile_submitted' => 'risk_profile_done',
            'personal_details_submitted' => 'personal_details_submitted',
            'nominee_details_submitted' => 'nominee_details_submitted',
            'bank_details_submitted' => 'bank_details_submitted',
            'account_activated' => 'activation_status'
        ];
        
        $dbField = $fieldMap[$field] ?? $field;
        
        $sql = "SELECT $dbField FROM mtb_user_status WHERE us_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return isset($result[$dbField]) ? $result[$dbField] : 'N';
    } catch (Exception $e) {
        error_log("Error getting user status field $field (DB field: $dbField): " . $e->getMessage());
        return 'N';
    }
}

// Check if user status record exists
public function userStatusRecordExists($userId) {
    try {
        $sql = "SELECT COUNT(*) as count FROM mtb_user_status WHERE us_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return ($result['count'] > 0);
    } catch (Exception $e) {
        error_log("Error checking if record exists: " . $e->getMessage());
        return false;
    }
}

// Helper function to update user status
public function updateUserStatusField($userId, $field, $value) {
    try {
        $fieldMap = [
            'kyc_complient' => 'kyc_complient',
            'fatca_submitted' => 'fatca_done',
            'risk_profile_submitted' => 'risk_profile_done',
            'personal_details_submitted' => 'personal_details_submitted',
            'nominee_details_submitted' => 'nominee_details_submitted',
            'bank_details_submitted' => 'bank_details_submitted',
            'account_activated' => 'activation_status'
        ];
        
        $dbField = $fieldMap[$field] ?? $field;
        
        // First, check current value to avoid unnecessary updates
        $currentValue = $this->getUserStatusField($userId, $field);
        
        if ($currentValue === $value) {
            return true; // Already has correct value
        }
        
        // Check if record exists
        if ($this->userStatusRecordExists($userId)) {
            // UPDATE existing record
            $sql = "UPDATE mtb_user_status SET $dbField = ?, updated_at = NOW() WHERE us_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$value, $userId]);
            
            if ($result && $stmt->rowCount() > 0) {
                return true;
            } else {
                return true;
            }
        } else {
            // INSERT new record
            $sql = "INSERT INTO mtb_user_status (us_id, $dbField, created_at, updated_at) 
                    VALUES (?, ?, NOW(), NOW())";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$userId, $value]);
            
            if ($result) {
                return true;
            } else {
                return false;
            }
        }
    } catch (Exception $e) {
        error_log("Error updating user status field $field (DB: $dbField): " . $e->getMessage());
        return false;
    }
}

// Function to ensure user status record exists with all default values
public function ensureUserStatusRecord($userId) {
    if ($this->userStatusRecordExists($userId)) {
        return true;
    }
    
    try {
        // Create record with all default 'N' values
        $sql = "INSERT INTO mtb_user_status (
                    us_id, 
                    kyc_complient,
                    fatca_done,
                    risk_profile_done,
                    personal_details_submitted,
                    nominee_details_submitted,
                    bank_details_submitted,
                    activation_status,
                    created_at,
                    updated_at
                ) VALUES (?, 'N', 'N', 'N', 'N', 'N', 'N', 'N', NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([$userId]);
        
        if ($result) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log("Error creating user status record: " . $e->getMessage());
        return false;
    }
}

// Function to sync user status from actual user data in tb_users table
public function getUserDataForSync($userId) {
    try {
        $sql = "SELECT 
                    FATCA_DECLARATION, 
                    RISK_CATEGORY_ID,
                    PRIMARY_HOLDER_PAN,
                    PRIMARY_HOLDER_NAME,
                    PRIMARY_HOLDER_DOB,
                    NOMINATION,
                    NOMINEE_1_NAME,
                    ACCOUNT_NO_1,
                    IFSC_CODE_1,
                    BD_BANK_CODE_1,
                    EMAIL,
                    INDIAN_MOBILE_NO,
                    CLIENT_CODE
                FROM tb_users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting user data for sync: " . $e->getMessage());
        return false;
    }
}

// Function to update kyc status remark
public function updateKycStatusRemark($userId, $remark) {
    try {
        $sql = "UPDATE mtb_user_status SET kyc_status_remark = ?, updated_at = NOW() WHERE us_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$remark, $userId]);
    } catch (Exception $e) {
        error_log("Error updating kyc status remark: " . $e->getMessage());
        return false;
    }
}

// Check if all requirements are met and activate account
public function checkAndActivateAccount($userId) {
    try {
        $statusFields = [
            'kyc_complient',
            'fatca_done',
            'risk_profile_done',
            'personal_details_submitted',
            'nominee_details_submitted',
            'bank_details_submitted'
        ];
        
        $fieldsList = implode(', ', $statusFields);
        $sql = "SELECT $fieldsList FROM mtb_user_status WHERE us_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $status = $stmt->fetch();
        
        if (!$status) {
            return false;
        }
        
        $allCompleted = true;
        foreach ($status as $field => $value) {
            if ($value != 'Y') {
                $allCompleted = false;
                break;
            }
        }
        
        if ($allCompleted) {
            $this->updateUserStatusField($userId, 'account_activated', 'Y');
            return true;
        } else {
            $this->updateUserStatusField($userId, 'account_activated', 'N');
            return false;
        }
    } catch (Exception $e) {
        error_log("Error checking account activation: " . $e->getMessage());
        return false;
    }
}

// Function to get user status with proper field mapping for frontend
public function getUserStatusForFrontend($userId) {
    try {
        $sql = "SELECT 
                    kyc_complient,
                    fatca_done,
                    risk_profile_done,
                    personal_details_submitted,
                    nominee_details_submitted,
                    bank_details_submitted,
                    activation_status,
                    kyc_status_remark,
                    deactivation_message
                FROM mtb_user_status WHERE us_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $dbStatus = $stmt->fetch();
        
        if (!$dbStatus) {
            return null;
        }
        
        return [
            'kyc_complient' => $dbStatus['kyc_complient'] ?? 'N',
            'fatca_done' => $dbStatus['fatca_done'] ?? 'N',
            'risk_profile_done' => $dbStatus['risk_profile_done'] ?? 'N',
            'personal_details_submitted' => $dbStatus['personal_details_submitted'] ?? 'N',
            'nominee_details_submitted' => $dbStatus['nominee_details_submitted'] ?? 'N',
            'bank_details_submitted' => $dbStatus['bank_details_submitted'] ?? 'N',
            'activation_status' => $dbStatus['activation_status'] ?? 'N',
            'kyc_status_remark' => $dbStatus['kyc_status_remark'] ?? '',
            'deactivation_message' => $dbStatus['deactivation_message'] ?? ''
        ];
    } catch (Exception $e) {
        error_log("Error getting user status for frontend: " . $e->getMessage());
        return null;
    }
}

// DEBUG function to check current FATCA status (READ-ONLY)
public function debugFATCAStatus($userId) {
    try {
        // 1. Get value from tb_users table
        $sql1 = "SELECT FATCA_DECLARATION FROM tb_users WHERE id = ?";
        $stmt1 = $this->pdo->prepare($sql1);
        $stmt1->execute([$userId]);
        $userResult = $stmt1->fetch();
        
        // 2. Get value from mtb_user_status table
        $sql2 = "SELECT fatca_done FROM mtb_user_status WHERE us_id = ?";
        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute([$userId]);
        $statusResult = $stmt2->fetch();
        
        return [
            'users_fatca' => $userResult['FATCA_DECLARATION'] ?? null,
            'status_fatca' => $statusResult['fatca_done'] ?? null
        ];
    } catch (Exception $e) {
        error_log("Debug FATCA error: " . $e->getMessage());
        return null;
    }
}

    // --- FATCA API Database Helper Methods ---
    
    /**
     * Get FATCA details from tb_users table for a user ID
     */
    public function getUserFatcaInfo($userId) {
        try {
            $sql = "SELECT id, PRIMARY_HOLDER_PAN, PRIMARY_HOLDER_NAME, PRIMARY_HOLDER_DOB, email, FATCA_DECLARATION FROM tb_users WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error in getUserFatcaInfo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get PRIMARY_HOLDER_PAN for a user ID
     */
    public function getUserPanById($userId) {
        try {
            $sql = "SELECT PRIMARY_HOLDER_PAN FROM tb_users WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            return $row ? $row['PRIMARY_HOLDER_PAN'] : null;
        } catch (Exception $e) {
            error_log("Error in getUserPanById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update/insert FATCA declaration status in both tables atomically
     */
    public function setFatcaDeclarationStatus($userId, $isDone) {
        try {
            $this->pdo->beginTransaction();
            
            $decValue = $isDone ? 1 : 0;
            $doneValue = $isDone ? 'Y' : 'N';
            
            // 1. Update tb_users table FATCA_DECLARATION
            $sql1 = "UPDATE tb_users SET FATCA_DECLARATION = ?, UPDATED_AT = NOW() WHERE id = ?";
            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->execute([$decValue, $userId]);
            
            // 2. Check if mtb_user_status record exists
            $sqlCheck = "SELECT status_id FROM mtb_user_status WHERE us_id = ? FOR UPDATE";
            $stmtCheck = $this->pdo->prepare($sqlCheck);
            $stmtCheck->execute([$userId]);
            $exists = $stmtCheck->fetch();
            
            if ($exists) {
                // Update existing record
                $sql2 = "UPDATE mtb_user_status SET fatca_done = ?, updated_at = NOW() WHERE us_id = ?";
                $stmt2 = $this->pdo->prepare($sql2);
                $stmt2->execute([$doneValue, $userId]);
            } else {
                // Insert new record
                $sql2 = "INSERT INTO mtb_user_status (us_id, fatca_done, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
                $stmt2 = $this->pdo->prepare($sql2);
                $stmt2->execute([$userId, $doneValue]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error in setFatcaDeclarationStatus: " . $e->getMessage());
            return false;
        }
    }

    // --- Master Data API Database Helper Methods ---

    /**
     * Get SIF fund categories by AMC code
     */
    public function getSifFundCategories($amcCode) {
        try {
            $sql = "SELECT DISTINCT 
                        `fund_cat` AS code,
                        `fund_cat` AS details
                    FROM `mtb_scheme_master` 
                    WHERE `amc_code` = :amc_code 
                        AND `amc_code` LIKE '%SIF'
                        AND `fund_cat` IS NOT NULL 
                        AND `fund_cat` != ''
                    ORDER BY `fund_cat` ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':amc_code' => $amcCode]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getSifFundCategories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get scheme master purchase limits and names by product code
     */
    public function getSchemeMasterDetails($productCode) {
        try {
            $sql = "SELECT 
                        scheme_code as product_code,
                        scheme_name as product_long_name,
                        new_purchase_min_amount,
                        additional_purchase_min_amount
                    FROM mtb_scheme_master 
                    WHERE scheme_code = :product_code 
                       OR isin = :product_code_isin 
                       OR amc_scheme_code = :product_code_amc
                    ORDER BY CASE WHEN settlement_type = 'NRM' THEN 0 ELSE 1 END ASC, scheme_code ASC
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':product_code' => $productCode,
                ':product_code_isin' => $productCode,
                ':product_code_amc' => $productCode
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getSchemeMasterDetails: " . $e->getMessage());
            return false;
        }
    }

    // --- Recommended Funds API Database Helper Methods ---

    /**
     * Get risk profile details by code
     */
    public function getRiskProfileDetailsByCode($riskCode) {
        try {
            $sql = "SELECT 
                        `id`, 
                        `risk_code`, 
                        `risk_name`, 
                        `risk_description`, 
                        `capital_erosion_percentage`, 
                        `details`, 
                        `equity_allocation`, 
                        `debt_allocation`, 
                        `gold_allocation`, 
                        `cash_allocation`, 
                        `portfolio_description`, 
                        `color_code`, 
                        `border_color`, 
                        `text_color`, 
                        `is_active`, 
                        `created_at`, 
                        `updated_at`, 
                        `created_by`, 
                        `updated_by`
                    FROM `mtb_risk_profile`
                    WHERE `risk_code` = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$riskCode]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getRiskProfileDetailsByCode: " . $e->getMessage());
            return false;
        }
    }





    /**
     * Get recommended funds with details and resolved scheme codes
     */
    public function getRecommendedFundsWithSchemeInfo($categoryId) {
        try {
            $sql = "SELECT 
                        r.`recmd_id`, 
                        r.`category_id`, 
                        r.`amc`, 
                        r.`invest_type`, 
                        r.`fund_cat`, 
                        r.`product_id`, 
                        r.`allocation`, 
                        r.`recmd_rational`,
                        a.`LONG_NAME` as amc_name,
                        rp.`risk_name`,
                        rp.`risk_description`,
                        rp.`color_code`,
                        rp.`border_color`,
                        rp.`text_color`
                    FROM `tb_recommanded_funds` r
                    LEFT JOIN `mtb_amc` a ON r.`amc` = a.`AMC_CODE`
                    LEFT JOIN `mtb_risk_profile` rp ON r.`category_id` = rp.`risk_code`
                    WHERE r.`category_id` = ?
                    ORDER BY r.`allocation` DESC, r.`recmd_id`";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$categoryId]);
            $funds = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($funds)) {
                return [];
            }
            
            $processedFunds = [];
            foreach ($funds as $fund) {
                $schemeName = $fund['product_id'];
                
                $schemeSql = "SELECT `scheme_code`, `scheme_name`, `scheme_type`, `isin` 
                              FROM `mtb_scheme_master` 
                              WHERE `scheme_name` = :scheme_name 
                              OR `scheme_name` LIKE :scheme_name_like
                              LIMIT 1";
                
                $schemeStmt = $this->pdo->prepare($schemeSql);
                $schemeStmt->execute([
                    ':scheme_name' => $schemeName,
                    ':scheme_name_like' => '%' . $schemeName . '%'
                ]);
                $schemeData = $schemeStmt->fetch(PDO::FETCH_ASSOC);
                
                // Clean up scheme name display
                $displayName = $schemeName;
                $displayName = preg_replace('/_\w+$/', '', $displayName);
                $displayName = preg_replace('/\s+-\s+\w+$/', '', $displayName);
                $displayName = trim($displayName);
                
                $processedFunds[] = [
                    'recmd_id' => $fund['recmd_id'],
                    'category_id' => $fund['category_id'],
                    'amc' => $fund['amc'],
                    'amc_name' => $fund['amc_name'] ?? $fund['amc'],
                    'invest_type' => $fund['invest_type'],
                    'fund_cat' => $fund['fund_cat'],
                    'product_id' => $fund['product_id'],
                    'allocation' => $fund['allocation'],
                    'recmd_rational' => $fund['recmd_rational'],
                    'scheme_name' => $displayName,
                    'scheme_code' => $schemeData ? $schemeData['scheme_code'] : $fund['product_id'],
                    'scheme_type' => $schemeData ? $schemeData['scheme_type'] : '',
                    'isin' => $schemeData ? $schemeData['isin'] : '',
                    'risk_name' => $fund['risk_name'],
                    'risk_description' => $fund['risk_description'],
                    'color_code' => $fund['color_code'],
                    'border_color' => $fund['border_color'],
                    'text_color' => $fund['text_color']
                ];
            }
            return $processedFunds;
        } catch (Exception $e) {
            error_log("Error in getRecommendedFundsWithSchemeInfo: " . $e->getMessage());
            throw $e;
        }
    }

    public function getSipSwpDataByClientCode($clientCode) {
        $sql = "SELECT `id`, `registration_type`, `member_code`, `client_code`, `client_name`, `pg_bank_ref_no`, `xsip_registration_no`, `xsip_registration_date`, `amc_name`, `rta_scheme_code`, `scheme_name`, `frequency_type`, `start_date`, `end_date`, `installments_amount`, `brokerage`, `entry_by`, `nse_mandate_id`, `dpc_flag`, `dp_trans`, `sub_broker`, `euin_no`, `euin_declaration`, `first_order_today`, `folio_number`, `status`, `remarks`, `sub_broker_arn`, `no_of_installments`, `exchange_remark`, `health_declaration_flag`, `nominee_dob`, `disclaimer_flag`, `internal_ref_no`, `primary_holder_email`, `primary_holder_mobile`, `second_holder_email`, `second_holder_mobile`, `third_holder_email`, `third_holder_mobile`, `member_unique_id`, `updated_at`, `stp_registration_no`, `from_amc_name`, `to_amc_name`, `from_scheme_name`, `to_scheme_name`, `stp_registration_date`, `stp_start_date`, `stp_end_date`, `trxn_mode`, `transfer_amount`, `transfer_units`, `no_of_transfers`, `first_order_todays_flag`, `sub_br_code`, `sub_br_arn_code`, `buy_sell_type`, `from_nse_scheme_code`, `to_nse_scheme_code`, `swp_registration_no`, `swp_registration_date`, `swp_start_date`, `swp_end_date`, `withdrawl_amount`, `withdrawal_units`, `no_of_withdrawls`, `bank_account_no`, `created_at` 
                FROM `tb_sip_data_by_nse` 
                WHERE `client_code` = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$clientCode]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getSipSwpDataByClientCode: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateUserKycComplaint($userId, $status = '1')
    {
        $sql = "UPDATE tb_users SET KYC_COMPLAINT = ?, UPDATED_AT = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $userId]);
    }

    public function getFoliosForScheme($clientCode, $schemeCode)
    {
        $folios = [];

        // The scheme_code could be ISIN or MD5 of scheme_name/fund_name
        $sql = "
            SELECT folio FROM tb_cas_transactions WHERE client_code = ? AND (TRIM(isin) = ? OR MD5(TRIM(scheme_name)) = ? OR TRIM(scheme_name) = ?) AND folio IS NOT NULL AND folio != ''
            UNION
            SELECT folio FROM tb_cas_summary WHERE client_code = ? AND (TRIM(isin) = ? OR MD5(TRIM(fund_name)) = ? OR TRIM(fund_name) = ?) AND folio IS NOT NULL AND folio != ''
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clientCode, $schemeCode, $schemeCode, $schemeCode, $clientCode, $schemeCode, $schemeCode, $schemeCode]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (!in_array($row['folio'], $folios)) {
                $folios[] = $row['folio'];
            }
        }

        // Fetch PAN and Name from vw_client_profiles for mailback transactions
        $pan = '';
        $name = '';
        $stmt_user = $this->pdo->prepare("SELECT PRIMARY_HOLDER_PAN, PRIMARY_HOLDER_NAME FROM vw_client_profiles WHERE CLIENT_CODE = ?");
        $stmt_user->execute([$clientCode]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $pan = $user_data['PRIMARY_HOLDER_PAN'] ?? '';
            $name = $user_data['PRIMARY_HOLDER_NAME'] ?? '';
        }

        if (!empty($pan) || !empty($name)) {
            $sql_mailback = "
                SELECT folio_number as folio FROM tb_transaction_mailback 
                WHERE (pan1 = ? OR investor_name = ?) 
                AND (TRIM(isin) = ? OR MD5(TRIM(scheme_name)) = ? OR TRIM(scheme_name) = ?) 
                AND folio_number IS NOT NULL AND folio_number != ''
            ";
            $stmt_mailback = $this->pdo->prepare($sql_mailback);
            $stmt_mailback->execute([$pan, $name, $schemeCode, $schemeCode, $schemeCode]);
            $mailback_rows = $stmt_mailback->fetchAll(PDO::FETCH_ASSOC);

            foreach ($mailback_rows as $row) {
                if (!in_array($row['folio'], $folios)) {
                    $folios[] = $row['folio'];
                }
            }
        }

        return $folios;
    }
}
?>
