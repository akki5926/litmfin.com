<?php
if (!isset($_SESSION)) { session_start(); }
//if(!isset($_SESSION['user'])) { header("Location: index.php"); }
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log'); 

// Set default timezone globally for all PHP date/time functions
date_default_timezone_set('Asia/Kolkata');

$autoloadPath = __DIR__ . "/../api/vendor/autoload.php";
if (!file_exists($autoloadPath)) {
    die("<strong>Fatal Error:</strong> Composer autoload file missing at <code>$autoloadPath</code>. Please run <code>composer install</code> in the <code>api/</code> directory.");
}
require_once $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class MySQLDB
{
    protected static ?mysqli $connect = null;

    private $config_data = null;
    private $smtp;
    private $company;
    private $cors;
    private $table_columns_cache = [];

    // =========================================================
    // CONSTRUCTOR — mirrors mfddb.php: always loads config on
    // every new instance, shares the static DB connection.
    // =========================================================
    public function __construct()
    {
        /**
         * -------------------------------------------------
         * LOAD CONFIG PATH (same as mfddb.php)
         * path_config.php is at: v4/api/db/path_config.php
         * database.php is at:    v4/admin/database.php
         * -------------------------------------------------
         */
        $pathConfig = __DIR__ . '/../api/db/path_config.php';

        if (!file_exists($pathConfig)) {
            throw new \Exception('path_config.php not found at: ' . $pathConfig);
        }

        $mapPath = require $pathConfig;

        if (!file_exists($mapPath)) {
            throw new \Exception('domain-map.php missing at: ' . $mapPath);
        }

        require_once $mapPath;

        if (!defined('CONFIG_PATH')) {
            throw new \Exception('CONFIG_PATH not defined after loading domain-map.php');
        }

        if (!file_exists(CONFIG_PATH)) {
            throw new \Exception('Config file not found: ' . CONFIG_PATH);
        }

        /**
         * -------------------------------------------------
         * LOAD CONFIG
         * -------------------------------------------------
         */
        $this->config_data = parse_ini_file(CONFIG_PATH, true);

        if (!$this->config_data) {
            throw new \Exception('Failed to parse config file');
        }

        /**
         * -------------------------------------------------
         * VALIDATE REQUIRED SECTIONS
         * -------------------------------------------------
         */
        foreach (['database', 'smtp', 'company', 'cors'] as $section) {
            if (!isset($this->config_data[$section])) {
                throw new \Exception("Missing [$section] section in config");
            }
        }

        $this->smtp    = $this->config_data['smtp'];
        $this->company = $this->config_data['company'];
        $this->cors    = $this->config_data['cors'];

        /**
         * -------------------------------------------------
         * CONNECT DATABASE (mysqli — shared via static)
         * -------------------------------------------------
         */
        if (!self::$connect) {
            $dbConfig = $this->config_data['database'];

            foreach (['host', 'username', 'password', 'dbname'] as $key) {
                if (!array_key_exists($key, $dbConfig)) {
                throw new \Exception("Missing DB config key: {$key}");
                }
            }

            self::$connect = new mysqli(
                $dbConfig['host'],
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['dbname']
            );

            if (self::$connect->connect_error) {
            throw new \Exception("DB Connection failed: " . self::$connect->connect_error);
            }

            self::$connect->set_charset($dbConfig['charset'] ?? 'utf8mb4');

            //error_log("CONFIG_PATH: " . CONFIG_PATH);
        }
    }
	
    public function getConnection()
    {
        return self::$connect;
    }



    // =========================================================
    // CONFIG GETTERS (always populated via constructor)
    // =========================================================

    public function getCompanyConfig(): array
    {
        return $this->config_data['company'] ?? [];
    }

    public function getSmtpConfig(): array
    {
        return $this->config_data['smtp'] ?? [];
    }

    public function getCorsConfig(): array
    {
        return $this->config_data['cors'] ?? [];
    }

    public function getSiteName(): string
    {
        return $this->company['name'] ?? '';
    }

    // =========================================================
    // CORE DB METHODS
    // =========================================================

    public function safeEscape($value)
    {
        if ($value === null) {
            return '';
        }
        return self::$connect->real_escape_string((string)$value);
    }

    public function escape($value)
    {
        if ($value === null) {
            return '';
        }
        $value = (string)$value;
        if (self::$connect instanceof mysqli) {
            return self::$connect->real_escape_string($value);
        }
        return addslashes($value);
    }

    public function getError()
    {
        return self::$connect->error;
    }

    public function disconnect()
    {
        if (self::$connect) {
            self::$connect->close();
            self::$connect = null;
        }
    }

    public function query($query)
    {
        $dbconn = self::$connect;

        while ($dbconn->more_results()) {
            $dbconn->next_result();
            if ($result = $dbconn->store_result()) {
                $result->free();
            }
        }

        return $dbconn->query($query);
    }

    public function insertQuery($query)
    {
        $result = self::$connect->query($query);
        if (!$result) {
            return false;
        }
        return mysqli_insert_id(self::$connect);
    }

    public function select($query)
    {
        $rows   = [];
        $result = $this->query($query);
        if ($result === false) {
            return false;
        }
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        if (self::$connect->more_results()) {
            self::$connect->next_result();
        }
        return $rows;
    }

    public function selectSingle($query)
    {
        $result = $this->query($query);
        if ($result === false) {
            return false;
        }
        if ($result->num_rows < 1) {
            return false;
        }
        $row = $result->fetch_assoc();
        if (self::$connect->more_results()) {
            self::$connect->next_result();
        }
        $result->free();
        return $row;
    }

    public function selectPrepared($query, $types = '', $params = [])
    {
        $conn = self::$connect;

        if (empty($params)) {
            return $this->select($query);
        }

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
            $sql = $query;
            foreach ($params as $p) {
                $escaped = "'" . $this->safeEscape((string)$p) . "'";
                $sql = preg_replace('/\?/', $escaped, $sql, 1);
            }
            return $this->select($sql);
        }

        if (!empty($params)) {
            if (empty($types)) {
                $types = '';
                foreach ($params as $p) {
                    if (is_int($p))   $types .= 'i';
                    elseif (is_float($p)) $types .= 'd';
                    else              $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        $stmt->close();

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function preparedQuery($sql, $params = [])
    {
        try {
            $conn = self::$connect;
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                error_log("SQL Prepare Error: " . $conn->error);
                return false;
            }

            if (!empty($params)) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }

            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (\Exception $e) {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

//==========================================================================
    // FAQ MANAGEMENT METHODS (FIXED - NO STORED PROCEDURES)
    //==========================================================================
    
    /**
     * Display all FAQs - Direct query only
     */
    public function displayAllFAQs()
    {
        $conn = $this->getConnection();
        $query = "SELECT `faq_id`, `faq_page`, `faq_question`, `faq_ans` FROM `tb_faqs` ORDER BY `faq_id` DESC";
        $result = $conn->query($query);
        
        if (!$result) {
            error_log("Query failed: " . $conn->error);
            return false;
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    /**
     * Add new FAQ - Direct insert only
     */
    public function addFaq($faqPage, $faqHeading, $faqdetail)
    {
        $conn = $this->getConnection();
        $faqPage = $this->safeEscape($faqPage);
        $faqHeading = $this->safeEscape($faqHeading);
        $faqdetail = $this->safeEscape($faqdetail);
        
        $query = "INSERT INTO `tb_faqs` (`faq_page`, `faq_question`, `faq_ans`) VALUES ('$faqPage', '$faqHeading', '$faqdetail')";
        $result = $conn->query($query);
        
        if (!$result) {
            error_log("Insert failed: " . $conn->error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get single FAQ details by ID - Direct select only
     */
    public function getSingleFaqDetail($getFaqId)
    {
        $getFaqId = intval($getFaqId);
        $conn = $this->getConnection();
        
        $query = "SELECT * FROM `tb_faqs` WHERE `faq_id` = $getFaqId";
        $result = $conn->query($query);
        
        if (!$result) {
            error_log("Select failed: " . $conn->error);
            return false;
        }
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        return [$row];
    }
    
    /**
     * Modify/Update FAQ - Direct update only
     */
    public function ModifyingFaq($faqPage, $faqHeading, $faqDetail, $faqId)
    {
        $conn = $this->getConnection();
        $faqPage = $this->safeEscape($faqPage);
        $faqHeading = $this->safeEscape($faqHeading);
        $faqDetail = $this->safeEscape($faqDetail);
        $faqId = intval($faqId);
        
        $query = "UPDATE `tb_faqs` SET `faq_page` = '$faqPage', `faq_question` = '$faqHeading', `faq_ans` = '$faqDetail' WHERE `faq_id` = $faqId";
        $result = $conn->query($query);
        
        if (!$result) {
            error_log("Update failed: " . $conn->error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Delete FAQ by ID - Direct delete only
     */
    public function deleteFaq($faqId)
    {
        $faqId = intval($faqId);
        $conn = $this->getConnection();
        
        $query = "DELETE FROM `tb_faqs` WHERE `faq_id` = $faqId";
        $result = $conn->query($query);
        
        if (!$result) {
            error_log("Delete failed: " . $conn->error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get FAQ count
     */
    public function getFaqCount()
    {
        $result = $this->selectSingle("SELECT COUNT(*) as total FROM `tb_faqs`");
        if($result == false) {
            return 0;
        }
        return $result['total'];
    }
    
    /**
     * Get FAQs by page name
     */
    public function getFaqsByPage($pageName)
    {
        $dbconn = $this->getConnection();
        $pageName = $this->safeEscape($pageName);
        $sql = "SELECT * FROM `tb_faqs` WHERE `faq_page` = '$pageName' ORDER BY `faq_id` ASC";
        return $this->select($sql);
    }

    //==========================================================================
    // ADMIN USER MANAGEMENT METHODS
    //==========================================================================
    
   public function checkAdminUserLogin($username, $password) {
    // Get the connection
    $conn = $this->getConnection();
    
    // Fetch the user record by username
    $query = "SELECT ad_id, ad_user_name, ad_user_pass, admin_role FROM vw_admin_profiles WHERE ad_user_name = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Prepare failed in checkAdminUserLogin: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = array();
    while($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['ad_user_pass'])) {
            $data[] = $row;
            break;
        }
    }
    
    $stmt->close();
    return $data;
}
    
    public function addSubAdminToDb($username, $password)
    {
        $dbconn = $this->getConnection();
        $username = $this->safeEscape($username);
        $password = password_hash($password, PASSWORD_BCRYPT);
        
        $sql = "SELECT * FROM `vw_admin_profiles` WHERE `ad_user_name` = '$username' ";
        $rows = $this->selectSingle($sql);
        
        if($rows == false) {
            $query = "INSERT INTO vw_admin_profiles (ad_user_name, ad_user_pass) VALUES ('$username', '$password')";
            return $this->insertQuery($query);
        }
        return false;
    }
    
    public function getSubAdmins()
    {
        $sql = "SELECT * FROM `vw_admin_profiles` WHERE `admin_role` = 0 OR `admin_role` IS NULL";
        return $this->select($sql);
    }

    public function getAllAdminUsers()
    {
        $sql = "SELECT * FROM `vw_admin_profiles` ORDER BY `ad_id` ASC";
        return $this->select($sql);
    }

    public function addAdminUser($username, $password, $email, $role)
    {
        $dbconn = $this->getConnection();
        $username = $this->safeEscape($username);
        $email = $this->safeEscape($email);
        $password = password_hash($password, PASSWORD_BCRYPT);
        $role = intval($role);
        
        $sql = "SELECT * FROM `vw_admin_profiles` WHERE `ad_user_name` = '$username'";
        $rows = $this->selectSingle($sql);
        
        if($rows == false) {
            $query = "INSERT INTO vw_admin_profiles (ad_user_name, ad_user_pass, email_id, admin_role) VALUES ('$username', '$password', '$email', $role)";
            return $this->insertQuery($query);
        }
        return false;
    }

    public function updateAdminUser($id, $username, $email, $role)
    {
        $dbconn = $this->getConnection();
        $id = intval($id);
        $username = $this->safeEscape($username);
        $email = $this->safeEscape($email);
        $role = intval($role);
        
        $sql = "SELECT * FROM `vw_admin_profiles` WHERE `ad_user_name` = '$username' AND `ad_id` != $id";
        $rows = $this->selectSingle($sql);
        
        if($rows == false) {
            $query = "UPDATE `vw_admin_profiles` SET `ad_user_name` = '$username', `email_id` = '$email', `admin_role` = $role WHERE `ad_id` = $id";
            return $this->query($query);
        }
        return false;
    }
    
    public function assignSubAdminToUser($userId, $subadminId)
    {
        $userId = intval($userId);
        // Handle empty/null values for "No Sub Admin" option
        if (empty($subadminId)) {
            $query = "UPDATE vw_client_profiles SET assigned_subadmin_id = NULL WHERE id = $userId";
        } else {
            $subadminId = intval($subadminId);
            $query = "UPDATE vw_client_profiles SET assigned_subadmin_id = $subadminId WHERE id = $userId";
        }
        return $this->query($query);
    }

    public function updateSubAdmin($id, $newUsername)
    {
        $dbconn = $this->getConnection();
        $id = intval($id);
        $newUsername = $this->safeEscape($newUsername);
        
        $sql = "SELECT * FROM `vw_admin_profiles` WHERE `ad_user_name` = '$newUsername' AND `ad_id` != $id";
        $rows = $this->selectSingle($sql);
        
        if($rows == false) {
            $query = "UPDATE `vw_admin_profiles` SET `ad_user_name`='$newUsername' WHERE `ad_id` = $id";
            return $this->query($query);
        }
        return false;
    }
    
    public function updateSubAdminPass($id, $newpass)
    {
        $id = intval($id);
        $newpass = password_hash($newpass, PASSWORD_BCRYPT);
        $query = "UPDATE `vw_admin_profiles` SET `ad_user_pass` = ? WHERE `ad_id` = ?";
        $conn = $this->getConnection();
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed in updateSubAdminPass: " . $conn->error);
            return false;
        }
        $stmt->bind_param("si", $newpass, $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    public function deleteSubAdmin($id)
    {
        $id = intval($id);
        $query = "DELETE FROM `vw_admin_profiles` WHERE `ad_id` = $id";
        return $this->query($query);
    }
    
    public function changeAdminPasswrod($new_pass, $admin_Id, $old_pass)
    {
        $admin_Id = intval($admin_Id);
        $conn = $this->getConnection();

        // Fetch current hash, verify old password, then update with bcrypt
        $stmt = $conn->prepare("SELECT `ad_user_pass` FROM `vw_admin_profiles` WHERE `ad_id` = ?");
        if (!$stmt) {
            error_log("Prepare failed in changeAdminPasswrod: " . $conn->error);
            return false;
        }
        $stmt->bind_param("i", $admin_Id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($old_pass, $row['ad_user_pass'])) {
            return false; // Old password does not match
        }

        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $upd = $conn->prepare("UPDATE `vw_admin_profiles` SET `ad_user_pass` = ? WHERE `ad_id` = ?");
        if (!$upd) {
            error_log("Prepare failed in changeAdminPasswrod update: " . $conn->error);
            return false;
        }
        $upd->bind_param("si", $new_hash, $admin_Id);
        $res = $upd->execute();
        $upd->close();
        return $res;
    }

    public function changeBrokersPasswrod($new_pass)
    {
        $conn = $this->getConnection();
        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE `vw_admin_profiles` SET `ad_user_pass` = ? WHERE `admin_role` = 1");
        if (!$stmt) {
            error_log("Prepare failed in changeBrokersPasswrod: " . $conn->error);
            return false;
        }
        $stmt->bind_param("s", $new_hash);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    //==========================================================================
    // USER MANAGEMENT METHODS
    //==========================================================================
    
    public function getUserUccById($userId)
    {
        $userId = intval($userId);
        $sql = "SELECT CLIENT_CODE FROM vw_client_profiles WHERE id = $userId";
        $result = $this->selectSingle($sql);
        return ($result && isset($result['CLIENT_CODE'])) ? $result['CLIENT_CODE'] : '';
    }
    
    public function getAllUCC()
    {
        $sql = "SELECT `CLIENT_CODE`, `id`, `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` IS NOT NULL AND `CLIENT_CODE` != '' ORDER BY `CLIENT_CODE`";
        return $this->select($sql);
    }

    public function getuserUCC($userId)
    {
        $userId = intval($userId);
        $result = $this->selectSingle("SELECT CLIENT_CODE FROM vw_client_profiles WHERE id = $userId");
        return ($result && isset($result['CLIENT_CODE'])) ? $result['CLIENT_CODE'] : '';
    }
                              
    public function isUCCExists($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $sql = "SELECT * FROM `vw_client_profiles` WHERE `CLIENT_CODE` = '$CLIENT_CODE'";
        return $this->selectSingle($sql);
    }
    
    public function getUCCName($CLIENT_CODE)
    {
        //error_log("getUCCName called with CLIENT_CODE: " . $CLIENT_CODE);
        $dbconn = $this->getConnection();
        $CLIENT_CODE_safe = $this->safeEscape($CLIENT_CODE);
        $sql = "SELECT PRIMARY_HOLDER_NAME FROM `vw_client_profiles` WHERE `CLIENT_CODE` = '$CLIENT_CODE_safe'";
        //error_log("getUCCName SQL: " . $sql);
        $result = $this->selectSingle($sql);
        //error_log("getUCCName DB result: " . print_r($result, true));
        if($result == false) {
            //error_log("getUCCName returning false for CLIENT_CODE: " . $CLIENT_CODE);
            return false;
        } else {
            //error_log("getUCCName returning name: '" . $result["PRIMARY_HOLDER_NAME"] . "' for CLIENT_CODE: " . $CLIENT_CODE);
            return $result["PRIMARY_HOLDER_NAME"];
        }
    }
    
   public function displayAllUsers()
{
    $sql = "SELECT * FROM vw_client_profiles ORDER BY id DESC";
    return $this->select($sql);
}

    public function activateUser($userId)
    {
        $userId = intval($userId);
        $query = "UPDATE vw_client_profiles SET UCC_ACTIVATION = 1 WHERE id = $userId";
        return $this->query($query);
    }

    public function getAllPwdResetList()
    {
        // Fetching FROM vw_client_profiles table instead of tb_sign_up
        $sql = "SELECT id, INDIAN_MOBILE_NO, EMAIL, RESET_TOKEN, UPDATED_AT FROM `vw_client_profiles` WHERE `RESET_TOKEN` IS NOT NULL AND `RESET_TOKEN` != '' ORDER BY `UPDATED_AT` DESC";
        return $this->select($sql) ?: [];
    }
    
    public function getActivatedUsers()
    {
        $sql = "SELECT u.id, u.INDIAN_MOBILE_NO AS us_mobile_no, u.EMAIL AS us_email_address,
                       u.PRIMARY_HOLDER_NAME, u.CLIENT_CODE, u.UCC_ACTIVATION, c.cas_file, c.uploaded_date
        FROM `vw_client_profiles` AS u
        LEFT JOIN `tb_cas_stm` AS c ON u.CLIENT_CODE = c.CLIENT_CODE 
        WHERE u.UCC_ACTIVATION = 1 
        GROUP BY u.CLIENT_CODE, u.PRIMARY_HOLDER_NAME
        ORDER BY u.CLIENT_CODE ASC";
        
        return $this->select($sql);
    }
    
    public function getActivatedPortfolio()
    {
        $sql = "SELECT u.CLIENT_CODE, u.id, u.PRIMARY_HOLDER_NAME, u.AUTO_EMAIL, f.include_family, f.head_UCC 
        FROM vw_client_profiles u
        LEFT JOIN tb_family f ON u.CLIENT_CODE = f.head_UCC
        WHERE u.UCC_ACTIVATION = 1
        GROUP BY u.CLIENT_CODE, u.PRIMARY_HOLDER_NAME, f.include_family, f.head_UCC";
        //error_log("getActivatedPortfolio Query: " . $sql);
        return $this->select($sql);
    }
    
    public function getActivatedPortfolio4subAdmin($subAdminId)
    {
        $subAdminId = intval($subAdminId);
        
        $sql = "SELECT u.CLIENT_CODE, u.id, u.PRIMARY_HOLDER_NAME, u.AUTO_EMAIL, f.include_family, f.head_UCC 
            FROM vw_client_profiles u
            LEFT JOIN tb_family f ON u.CLIENT_CODE = f.head_UCC
            WHERE u.UCC_ACTIVATION = 1 AND u.assigned_subadmin_id = $subAdminId
            GROUP BY u.CLIENT_CODE, u.PRIMARY_HOLDER_NAME, f.include_family, f.head_UCC";
        
        return $this->select($sql);
    }

    public function isClientAssignedToSubadmin($clientCode, $subAdminId)
    {
        $clientCode = $this->safeEscape($clientCode);
        $subAdminId = intval($subAdminId);
        $sql = "SELECT COUNT(*) as cnt FROM vw_client_profiles WHERE CLIENT_CODE = '$clientCode' AND assigned_subadmin_id = $subAdminId";
        $res = $this->selectSingle($sql);
        return isset($res['cnt']) && $res['cnt'] > 0;
    }

    public function getCasPortfolioClients4subAdmin($subAdminId)
    {
        $subAdminId = intval($subAdminId);
        $sql = "SELECT c.CLIENT_CODE, 
                       COALESCE(u.PRIMARY_HOLDER_NAME, c.client_name) as client_name, 
                       MAX(c.uploaded_date) as last_updated, 
                       u.EMAIL 
                FROM tb_cas_stm c 
                INNER JOIN vw_client_profiles u ON c.CLIENT_CODE = u.CLIENT_CODE 
                WHERE u.assigned_subadmin_id = $subAdminId
                GROUP BY c.CLIENT_CODE, u.PRIMARY_HOLDER_NAME, c.client_name, u.EMAIL
                ORDER BY last_updated DESC";
        return $this->select($sql);
    }

    public function getAutoEmailStats4subAdmin($subAdminId) {
        $subAdminId = intval($subAdminId);
        $sql = "SELECT 
                    SUM(CASE WHEN AUTO_EMAIL = 1 THEN 1 ELSE 0 END) as enabled_count,
                    SUM(CASE WHEN AUTO_EMAIL = 0 THEN 1 ELSE 0 END) as disabled_count,
                    SUM(CASE WHEN AUTO_EMAIL IS NULL THEN 1 ELSE 0 END) as null_count,
                    COUNT(*) as total_count
                FROM vw_client_profiles
                WHERE assigned_subadmin_id = $subAdminId";
        $result = $this->selectSingle($sql);
        
        if (!$result) {
            return ['enabled_count' => 0, 'disabled_count' => 0, 'null_count' => 0, 'total_count' => 0];
        }
        
        return $result;
    }

    public function getAllUCC4subAdmin($subAdminId)
    {
        $subAdminId = intval($subAdminId);
        $sql = "SELECT `CLIENT_CODE`, `id`, `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` IS NOT NULL AND `CLIENT_CODE` != '' AND `assigned_subadmin_id` = $subAdminId ORDER BY `CLIENT_CODE`";
        return $this->select($sql);
    }

    public function getAllUCCFromUsers4subAdmin($subAdminId)
    {
        $subAdminId = intval($subAdminId);
        $sql = "SELECT u.CLIENT_CODE, u.PRIMARY_HOLDER_NAME 
                FROM tb_users u
                INNER JOIN vw_client_profiles cp ON u.CLIENT_CODE = cp.CLIENT_CODE
                WHERE u.CLIENT_CODE IS NOT NULL 
                AND u.CLIENT_CODE != '' 
                AND u.PRIMARY_HOLDER_NAME IS NOT NULL 
                AND u.PRIMARY_HOLDER_NAME != ''
                AND cp.assigned_subadmin_id = $subAdminId
                ORDER BY u.CLIENT_CODE";
        return $this->select($sql);
    }
    
    public function getProfileData($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $sql = "SELECT * FROM vw_client_profiles WHERE CLIENT_CODE = '$CLIENT_CODE'";
        return $this->select($sql);
    }

    public function getUserByClientCode($clientCode)
    {
        $clientCode = $this->safeEscape($clientCode);
        $sql = "SELECT * FROM vw_client_profiles WHERE CLIENT_CODE = '$clientCode' LIMIT 1";
        return $this->selectSingle($sql);
    }

    public function deleteUserByClientCode($clientCode)
    {
        $clientCodeEscaped = $this->safeEscape($clientCode);
        if (empty($clientCodeEscaped)) {
            return ['success' => false, 'message' => 'Invalid or empty UCC code'];
        }

        // Fetch user information first
        $sqlUser = "SELECT * FROM `tb_users` WHERE `CLIENT_CODE` = '$clientCodeEscaped' LIMIT 1";
        $user = $this->selectSingle($sqlUser);

        $userId = intval($user['id'] ?? 0);
        $email = $this->safeEscape($user['EMAIL'] ?? '');
        $pan = $this->safeEscape($user['PRIMARY_HOLDER_PAN'] ?? '');

        // Array of deletion queries to execute across user related tables
        $queries = [];

        // 1. Primary user record
        $queries[] = "DELETE FROM `tb_users` WHERE `CLIENT_CODE` = '$clientCodeEscaped'";
        if ($userId > 0) {
            $queries[] = "DELETE FROM `tb_users` WHERE `id` = $userId";
        }

        // 2. CAS Statements & Portfolios
        $queries[] = "DELETE FROM `tb_cas_stm` WHERE `CLIENT_CODE` = '$clientCodeEscaped'";
        $queries[] = "DELETE FROM `tb_cas_summary` WHERE `client_code` = '$clientCodeEscaped'";
        $queries[] = "DELETE FROM `tb_cas_transactions` WHERE `client_code` = '$clientCodeEscaped'";

        // 3. Family mappings
        $queries[] = "DELETE FROM `tb_family` WHERE `head_UCC` = '$clientCodeEscaped' OR `member_UCC` = '$clientCodeEscaped'";

        // 4. Investments & AIF/PMS NAV data
        $queries[] = "DELETE FROM `tb_investments` WHERE `CLIENT_CODE` = '$clientCodeEscaped'";
        if ($userId > 0) {
            $queries[] = "DELETE FROM `tb_investments` WHERE `user_id` = $userId";
        }
        $queries[] = "DELETE FROM `tb_aif_nav` WHERE `CLIENT_CODE` = '$clientCodeEscaped'";
        $queries[] = "DELETE FROM `tb_aif_payout` WHERE `CLIENT_CODE` = '$clientCodeEscaped'";
        $queries[] = "DELETE FROM `tb_pms_nav` WHERE `CLIENT_CODE` = '$clientCodeEscaped'";

        // 5. Goals
        $queries[] = "DELETE FROM `assigned_goals` WHERE `ucc` = '$clientCodeEscaped'";
        $queries[] = "DELETE FROM `goals` WHERE `ucc` = '$clientCodeEscaped'";
        if ($userId > 0) {
            $queries[] = "DELETE FROM `tb_assign_goals` WHERE `user_id` = $userId";
            $queries[] = "DELETE FROM `tb_goals` WHERE `user_id` = $userId";
        }

        // 6. Mandates
        $queries[] = "DELETE FROM `mandate_details` WHERE `client_code` = '$clientCodeEscaped'";
        $queries[] = "DELETE FROM `tb_mandate_details` WHERE `client_code` = '$clientCodeEscaped'";
        if ($userId > 0) {
            $queries[] = "DELETE FROM `mandate_details` WHERE `user_id` = $userId";
            $queries[] = "DELETE FROM `tb_mandate_details` WHERE `user_id` = $userId";
        }

        // 7. SIP records
        $queries[] = "DELETE FROM `tb_sip_data_by_nse` WHERE `client_code` = '$clientCodeEscaped'";
        if ($userId > 0) {
            $queries[] = "DELETE FROM `tb_sipresponse` WHERE `user_id` = $userId";
            $queries[] = "DELETE FROM `tb_sip_data` WHERE `user_id` = $userId";
            $queries[] = "DELETE FROM `tb_sip_record` WHERE `user_id` = $userId";
        }

        // 8. FATCA & additional compliance records
        if ($userId > 0) {
            $queries[] = "DELETE FROM `fatca_details` WHERE `user_id` = $userId";
        }
        if (!empty($pan)) {
            $queries[] = "DELETE FROM `fatca_details` WHERE `pan_no` = '$pan'";
        }

        // Execute all deletion queries safely
        foreach ($queries as $q) {
            try {
                $this->query($q);
            } catch (\Throwable $e) {
                // Ignore missing table / query errors during cascading delete
            }
        }

        return ['success' => true, 'message' => "User data for UCC '$clientCode' deleted successfully."];
    }

    public function getMasterDescription($table, $codeField, $code, $descField) 
    {
        if (empty($code) || $code == '0') return '-';
        
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $codeField = preg_replace('/[^a-zA-Z0-9_]/', '', $codeField);
        $descField = preg_replace('/[^a-zA-Z0-9_]/', '', $descField);
        
        $code = $this->safeEscape($code);
        
        try {
            $sql = "SELECT $descField as description FROM $table WHERE $codeField = '$code' LIMIT 1";
            $result = $this->selectSingle($sql);
            
            if ($result && isset($result['description']) && !empty($result['description'])) {
                return $result['description'];
            }
        } catch (Exception $e) {
            error_log("Master data lookup failed for $table: " . $e->getMessage());
        }
        
        return $code;
    }

    //==========================================================================
    // FUND MANAGEMENT METHODS
    //==========================================================================
    
    public function getFundsSchemeCodeList()
    {
        $sql = "SELECT DISTINCT amc_code, scheme_code AS product_code, scheme_name AS product_long_name, fund_cat AS asset_class FROM mtb_scheme_master ORDER BY amc_code, scheme_name";
        return $this->select($sql);
    }
    
    public function deleteHoldingFunds($userid)
    {
        $userid = intval($userid);
        $query = "DELETE FROM mtb_holding_info WHERE us_id = $userid";
        return $this->query($query);
    }

    public function testFundDB($amcCode, $productCode)
    {
        $dbconn = $this->getConnection();
        $amcCode = $this->safeEscape($amcCode);
        $productCode = $this->safeEscape($productCode);
        
        $sql = "SELECT * FROM mtb_scheme_master WHERE amc_code = '$amcCode' AND scheme_code = '$productCode'";
        return $this->select($sql);
    }

    public function getallRiskcategories()
    {
        $sql = "SELECT * FROM risk_category ORDER BY id";
        return $this->select($sql);
    }

    public function getAllRecmdFunds()
    {
        $sql = "SELECT r.*, rc.category_name FROM tb_recommanded_funds r 
                LEFT JOIN risk_category rc ON r.category_id = rc.id 
                ORDER BY r.category_id, r.recmd_id";
        return $this->select($sql);
    }

    public function getAllUniqueAmcCode()
    {
        $sql = "SELECT DISTINCT amc_code, LONG_NAME FROM mtb_amc ORDER BY LONG_NAME";
        return $this->select($sql);
    }

    public function getallinvestType($invest)
    {
        $dbconn = $this->getConnection();
        $invest = $this->safeEscape($invest);
        
        if($invest == 'mutualfund') {
            return $this->getAllUniqueAmcCode();
        }
        return array();
    }

    public function getAllFundHouseSchemeNames($FundHouseCode, $fundCategoryName)
    {
        $dbconn = $this->getConnection();
        $FundHouseCode = $this->safeEscape($FundHouseCode);
        $fundCategoryName = $this->safeEscape($fundCategoryName);
        
        $sql = "SELECT scheme_code AS product_code, scheme_name AS product_long_name FROM mtb_scheme_master 
                WHERE amc_code = '$FundHouseCode' AND fund_cat = '$fundCategoryName'
                ORDER BY scheme_name";
        
        return $this->select($sql);
    }
    
    public function insertRec($Risk_id, $invest_type, $fund_house, $fund_cat, $scheme, $allocation, $rational)
    {
        $dbconn = $this->getConnection();
        $Risk_id = intval($Risk_id);
        $invest_type = $this->safeEscape($invest_type);
        $fund_house = $this->safeEscape($fund_house);
        $fund_cat = $this->safeEscape($fund_cat);
        $scheme = $this->safeEscape($scheme);
        $allocation = floatval($allocation);
        $rational = $this->safeEscape($rational);
        
        $query = "INSERT INTO tb_recommanded_funds (category_id, invest_type, amc, fund_cat, product_id, allocation, recmd_rational) 
                  VALUES ($Risk_id, '$invest_type', '$fund_house', '$fund_cat', '$scheme', $allocation, '$rational')";
        
        return $this->query($query);
    }

  public function getRecommendationData($riskId)
{
    $riskId = intval($riskId);
    
    $sql = "SELECT 
                r.recmd_id,
                r.invest_type, 
                r.fund_cat, 
                r.amc, 
                a.LONG_NAME as fund_name,
                r.product_id as scheme_name,  
                r.allocation, 
                r.recmd_rational
            FROM tb_recommanded_funds r
            LEFT JOIN mtb_amc a ON r.amc = a.AMC_CODE
            WHERE r.category_id = $riskId
            ORDER BY r.recmd_id";
    
    //error_log("getRecommendationData Query for risk $riskId: " . $sql);
    
    $result = $this->select($sql);
    
    //error_log("getRecommendationData returned " . count($result) . " rows for risk $riskId");
    
    if (!empty($result) && count($result) > 0) {
        //error_log("First row data for risk $riskId: " . print_r($result[0], true));
    }
    
    return $result;
}

    public function getcount($riskId)
    {
        $riskId = intval($riskId);
        $result = $this->selectSingle("SELECT COUNT(*) as datacount FROM tb_recommanded_funds WHERE category_id = $riskId");
        return ($result) ? $result["datacount"] : 0;
    }

    public function deletefund($ID)
    {
        $ID = intval($ID);
        $query = "DELETE FROM tb_recommanded_funds WHERE recmd_id = $ID";
        return $this->query($query);
    }

public function getRecData($id)
{
    $id = intval($id);
    
    $sql = "SELECT 
                r.*,
                a.LONG_NAME as amc_name,
                r.product_id as scheme_name  
            FROM tb_recommanded_funds r
            LEFT JOIN mtb_amc a ON r.amc = a.AMC_CODE
            WHERE r.recmd_id = $id";
    
    //error_log("getRecData Query for id $id: " . $sql);
    
    $result = $this->select($sql);
    //error_log("getRecData returned " . count($result) . " rows");
    
    return $result;
}

    public function updateRec($ID, $allocation, $rational)
    {
        $ID = intval($ID);
        $dbconn = $this->getConnection();
        $allocation = floatval($allocation);
        $rational = $this->safeEscape($rational);
        
        $query = "UPDATE tb_recommanded_funds SET allocation = $allocation, recmd_rational = '$rational' WHERE recmd_id = $ID";
        return $this->query($query);
    }

    public function getAMCName($amccode)
    {
		//error_log("old method getAMCName called");
        $dbconn = $this->getConnection();
        $amccode = $this->safeEscape($amccode);
        $sql = "SELECT LONG_NAME FROM `mtb_amc` WHERE `AMC_CODE` = '$amccode'";
        $result = $this->selectSingle($sql);
        if($result == false) {
            return false;
        } else {
            return $result["LONG_NAME"];
        }
    }

    public function getFundInfo($amccode, $productcode)
    {
        $dbconn = $this->getConnection();
        $amccode = $this->safeEscape($amccode);
        $productcode = $this->safeEscape($productcode);
        
        $sql = "SELECT *, scheme_code AS product_code, scheme_name AS product_long_name, fund_cat AS asset_class FROM `mtb_scheme_master` WHERE `amc_code` = '$amccode' AND `scheme_code` = '$productcode'";
        return $this->selectSingle($sql);
    }
    
    public function getFundName($amccode, $fundcode)
    {
        $dbconn = $this->getConnection();
        $amccode = $this->safeEscape($amccode);
        $fundcode = $this->safeEscape($fundcode);
        
        $sql = "SELECT scheme_name AS product_long_name FROM `mtb_scheme_master` WHERE `amc_code` = '$amccode' AND `scheme_code` = '$fundcode'";
        $result = $this->selectSingle($sql);
        if($result == false) {
            return false;
        } else {
            return $result["product_long_name"];
        }
    }

    public function getFundAssetClass($amccode, $productcode)
    {
        $dbconn = $this->getConnection();
        $amccode = $this->safeEscape($amccode);
        $productcode = $this->safeEscape($productcode);
        
        $sql = "SELECT fund_cat AS asset_class FROM `mtb_scheme_master` WHERE `amc_code` = '$amccode' AND `scheme_code` = '$productcode'";
        //error_log("getFundAssetClass:".$sql);
        $result = $this->selectSingle($sql);
        if($result == false) {
            return false;
        } else {
            return $result["asset_class"];
        }
    }
    
    public function getFundHouseName($amccode)
    {
        $dbconn = $this->getConnection();
        $amccode = $this->safeEscape($amccode);
        $sql = "SELECT LONG_NAME FROM `mtb_amc` WHERE `AMC_CODE` = '$amccode'";
        $result = $this->selectSingle($sql);
        if($result == false) {
            return false;
        } else {
            return $result["LONG_NAME"];
        }
    }
    
    public function getFundNAV($amccode, $productcode)
    {
        $dbconn = $this->getConnection();
        $amccode = $this->safeEscape($amccode);
        $productcode = $this->safeEscape($productcode);
        
        $sql = "SELECT tn.* FROM mtb_scheme_master tm 
                JOIN tb_nav tn ON tm.isin = tn.isin 
                WHERE tm.amc_code = '$amccode' AND tm.scheme_code = '$productcode'
                ORDER BY tn.nav_date DESC LIMIT 1";
        //error_log("getFundNAV:".$sql);
        return $this->selectSingle($sql);
    }
    
    public function getFundNAVDate()
    {
        $sql = "SELECT nav_date FROM `tb_nav` ORDER BY nav_date DESC LIMIT 1";
        $result = $this->selectSingle($sql);
        if($result == false) {
            return date('Y-m-d');
        } else {
            return $result["nav_date"];
        }
    }
    
    public function getMFAssetClass($amccode, $productcode)
    {
        $dbconn = $this->getConnection();
        $amccode = $this->safeEscape($amccode);
        $productcode = $this->safeEscape($productcode);
        
        $sql = "SELECT fund_cat AS asset_class FROM `mtb_scheme_master` WHERE `amc_code` = '$amccode' AND `scheme_code` = '$productcode'";
        $result = $this->selectSingle($sql);
        if($result == false) {
            return false;
        } else {
            return $result['asset_class'];
        }
    }

    //==========================================================================
    // API AND CONFIGURATION METHODS
    //==========================================================================
    
    public function getApiDetails()
    {
        $sql = "SELECT * FROM api_configuration LIMIT 1";
        return $this->select($sql);
    }

    public function getUserCount()
    {
        $result = $this->selectSingle("SELECT COUNT(*) as totaluser FROM vw_client_profiles WHERE UCC_ACTIVATION = 1");
        return ($result) ? $result["totaluser"] : 0;
    }

    public function getMutualFundCount()
    {
        $result = $this->selectSingle("SELECT COUNT(DISTINCT scheme_code) as total FROM mtb_scheme_master");
        return ($result) ? $result["total"] : 0;
    }

    public function getRecommendedCount()
    {
        $result = $this->selectSingle("SELECT COUNT(*) as total FROM tb_recommanded_funds");
        return ($result) ? $result["total"] : 0;
    }

    public function getAccountType($accountType)
    {
        return $accountType; // Placeholder - implement as needed
    }

    public function getBankName($accountType)
    {
        return "Bank Name"; // Placeholder - implement as needed
    }

    public function getadminOtp()
    {
        try {
            // Get the latest random_number from vw_admin_profiles for super admin
            $result = $this->selectSingle("SELECT random_number FROM vw_admin_profiles WHERE admin_role = 1 ORDER BY ad_id DESC LIMIT 1");
            return ($result && isset($result['random_number'])) ? $result['random_number'] : '';
        } catch (Exception $e) {
            error_log("Error in getadminOtp: " . $e->getMessage());
            return '';
        }
    }

    public function updateRandomnumber($random)
    {
        try {
            $dbconn = $this->getConnection();
            $random = $this->safeEscape($random);
            // Update the super admin record with the OTP
            $query = "UPDATE vw_admin_profiles SET random_number = '$random' WHERE admin_role = 1";
            return $this->query($query);
        } catch (\Exception $e) {
            error_log("Error in updateRandomnumber: " . $e->getMessage());
            return false;
        }
    }

    public function updatepassword($password)
    {
        $pass = password_hash($password, PASSWORD_BCRYPT);
        $query = "UPDATE vw_admin_profiles SET ad_user_pass = '$pass' WHERE admin_role = 1";
        return $this->query($query);
    }

    //==========================================================================
    // NAV MANAGEMENT METHODS
    //==========================================================================
    
    public function updateNAV($isin, $nav, $nav_date, $scheme_code, $scheme_name)
    {
        $dbconn = $this->getConnection();
        $isin = $this->safeEscape($isin);
        $nav_date = $this->safeEscape($nav_date);
        $scheme_code = $this->safeEscape($scheme_code);
        $scheme_name = $this->safeEscape($scheme_name);
        $nav = floatval($nav);

        $sql = "SELECT * FROM `tb_nav` WHERE `isin` = '$isin'";
        $rows = $this->selectSingle($sql);
        
        if($rows == false) {
            $query = "INSERT INTO `tb_nav`(`isin`, `nav`, `nav_date`, `scheme_code`, `scheme_name`) 
                      VALUES ('$isin', $nav, '$nav_date', '$scheme_code', '$scheme_name')";
            $result = $this->query($query);
            return $result ? "Inserted" : "Failed";
        } else {
            // Check if the data is actually different before updating
            if (floatval($rows['nav']) == $nav && 
                $rows['nav_date'] == $nav_date && 
                $rows['scheme_code'] == $scheme_code && 
                $rows['scheme_name'] == $scheme_name) {
                return "already exist";
            }
            
            $query = "UPDATE `tb_nav` SET nav = $nav, `nav_date` = '$nav_date', 
                      `scheme_code` = '$scheme_code', `scheme_name` = '$scheme_name' 
                      WHERE `isin` = '$isin'";
            $result = $this->query($query);
            return $result ? "Updated" : "Failed";
        }
    }
    
    public function getRecentSIFNAVRecords($limit = 500)
    {
        $limit = intval($limit);
        $query = "SELECT slno, isin, scheme_name, nav, DATE_FORMAT(nav_date, '%d-%b-%Y') as nav_date, scheme_code 
                  FROM tb_nav 
                  WHERE scheme_code LIKE '%SIF%' 
                  ORDER BY slno DESC 
                  LIMIT $limit";
        return $this->select($query);
    }
    
    public function searchNAVRecords($searchTerm, $limit = 100)
    {
        $limit = intval($limit);
        $term = $this->safeEscape($searchTerm);
        $query = "SELECT slno, isin, scheme_name, nav, DATE_FORMAT(nav_date, '%d-%b-%Y') as nav_date, scheme_code 
                  FROM tb_nav 
                  WHERE isin = '$term' 
                     OR scheme_code = '$term' 
                     OR scheme_name LIKE '%$term%'
                  ORDER BY nav_date DESC 
                  LIMIT $limit";
        return $this->select($query);
    }
    
    public function getRecordCountsByType()
    {
        $query = "SELECT 
                    SUM(CASE WHEN scheme_name LIKE '%Reinvest%' OR scheme_name LIKE '%Dividend%' THEN 1 ELSE 0 END) as reinvest_count,
                    SUM(CASE WHEN scheme_name NOT LIKE '%Reinvest%' AND scheme_name NOT LIKE '%Dividend%' THEN 1 ELSE 0 END) as growth_count,
                    COUNT(*) as total_count
                  FROM tb_nav";
        return $this->selectSingle($query);
    }

    //==========================================================================
    // TRANSACTION METHODS
    //==========================================================================

	public function getAllTransactions_old($userUCC, $prodCode = "")
	{
		$dbconn 	= $this->getConnection();
		$userUCC 	= $this->safeEscape($userUCC);
		$prodCode 	= $this->safeEscape($prodCode);
		
		//error_log("=== STEP 1: Basic query without any joins ===");
		
		// Get user's PAN and Name
		$user_sql = "SELECT PRIMARY_HOLDER_PAN, PRIMARY_HOLDER_NAME FROM vw_client_profiles WHERE CLIENT_CODE = '$userUCC'";
		$user_data = $this->select($user_sql);
		
		if (empty($user_data)) {
			error_log("No user found for CLIENT_CODE: $userUCC");
			return [];
		}
		
		$user_pan = $user_data[0]['PRIMARY_HOLDER_PAN'];
		$user_name = $this->safeEscape($user_data[0]['PRIMARY_HOLDER_NAME']);
		//error_log("User PAN: " . $user_pan . ", User Name: " . $user_name);
		
		// STEP 1: Simple query - only tb_transaction_mailback, direct PAN match
		$conditions = [];
		if (!empty($user_pan)) {
			$conditions[] = "t.pan1 = '$user_pan'";
		}
		if (!empty($user_name)) {
			$conditions[] = "t.investor_name = '$user_name'";
		}
		
		if (empty($conditions)) {
			error_log("No valid PAN or Name found for CLIENT_CODE: $userUCC");
			return [];
		}

		$whereClause = implode(' OR ', $conditions);

		// STEP 1: Simple query - only tb_transaction_mailback, direct PAN or Name match
		$sql = "
			SELECT *
			FROM tb_transaction_mailback t
			WHERE ($whereClause)
		";

		if (!empty($prodCode)) {
			$sql .= " AND t.product_code = '$prodCode'";
		}

		$sql .= " ORDER BY t.transaction_date ASC";
		
		//error_log("Step 1 SQL: " . $sql);
		$result = $this->select($sql);
		//error_log("Step 1 - Rows returned (direct PAN or Name match): " . count($result));
		
		// Show first 3 rows as sample
        /*
		if (count($result) > 0) {
			error_log("Step 1 - Sample row 1: " . print_r(array_slice($result[0], 0, 5), true));
		}
		*/
		return $result;
	}
	
	

    public function getAllTransactions($userUCC, $prodCode = "")
	{
		$dbconn   = $this->getConnection();
		$userUCC  = $this->safeEscape($userUCC);
		$prodCode = $this->safeEscape($prodCode);

		$sql = "SELECT 
			t.*,
			sm.isin,
			t.product_code AS final_product_code
		FROM vw_client_profiles u
		INNER JOIN tb_transaction_mailback t 
			ON t.pan1 = u.PRIMARY_HOLDER_PAN
			AND t.pan1 IS NOT NULL 
			AND t.pan1 != ''
		LEFT JOIN (
			SELECT 
				TRIM(UPPER(channel_partner_code)) AS code,
				MAX(isin) AS isin
			FROM mtb_scheme_master
			GROUP BY TRIM(UPPER(channel_partner_code))
		) sm
		ON sm.code = TRIM(UPPER(t.product_code))
		WHERE u.CLIENT_CODE = '{$userUCC}'";

		// Optional product filter
		if (!empty($prodCode)) {
			$sql .= " 
				AND TRIM(UPPER(t.product_code)) = TRIM(UPPER('{$prodCode}'))
			";
		}

		$sql .= " ORDER BY t.transaction_date DESC";

		//error_log("Query sql: " . print_r($sql, true));

		$result = $this->select($sql);

		//error_log("Query Result: " . print_r($result, true));

		return $result;
	}
    public function INSERTDATATransaction($amc_code, $product_code, $folio_no, $unit_Allo, $nav_bought, $userId, $amount, $currentvalue)
    {
        $dbconn = $this->getConnection();
        $amc_code = $this->safeEscape($amc_code);
        $product_code = $this->safeEscape($product_code);
        $folio_no = $this->safeEscape($folio_no);
        $unit_Allo = floatval($unit_Allo);
        $nav_bought = floatval($nav_bought);
        $userId = intval($userId);
        $amount = floatval($amount);
        $currentvalue = floatval($currentvalue);
        
        $query = "INSERT INTO mtb_holding_info (amc_code, product_code, folio_no, units_allocated, bought.nav, t.transaction_type, t.folio_number, us_id, purchase_amount, current_value, bought_date) 
                  VALUES ('$amc_code', '$product_code', '$folio_no', $unit_Allo, $nav_bought, $userId, $amount, $currentvalue, NOW())";
        
        return $this->query($query);
    }

    public function getTransactionDetails($fromDate = "", $toDate = "", $userUCC = "", $transactionType = "")
    {
        $dbconn = $this->getConnection();
        $fromDate = $this->safeEscape($fromDate);
        $toDate = $this->safeEscape($toDate);
        $userUCC = $this->safeEscape($userUCC);
        $transactionType = $this->safeEscape($transactionType);
        
        $sql = "SELECT 
                    t.id as slno,
                    u.CLIENT_CODE as iin,
                    COALESCE(NULLIF(t.pan1, ''), w.PAN_NO) as pan_no,
                    t.investor_name,
                    t.process_date,
                    t.transaction_number as trxn_num,
                    t.transaction_type as trxn_head,
                    t.product_code as prod_code,
                    t.amc_code as fund,
                    t.folio_number as folio_num,
                    t.scheme_code,
                    t.fund_desc,
                    t.price,
                    t.units,
                    t.amount,
                    t.transaction_mode as trxn_mode,
                    t.transaction_status as trxn_status,
                    s.scheme_name as master_scheme_name,
                    t.transaction_number,
                    t.transaction_date,
                    t.nav, t.transaction_type, t.folio_number,
                    t.pan1
                FROM tb_transaction_mailback t
                LEFT JOIN mtb_scheme_master s ON t.scheme_code = s.rta_scheme_code";
		
													 
							   
        $sql .= " LEFT JOIN (
                          SELECT FOLIOCHK, MAX(PAN_NO) AS PAN_NO 
                          FROM tb_mailback_wbr9 
                          WHERE PAN_NO IS NOT NULL AND PAN_NO != ''
                          GROUP BY FOLIOCHK
                      ) w ON t.folio_number = w.FOLIOCHK
                  LEFT JOIN vw_client_profiles u ON u.PRIMARY_HOLDER_PAN = COALESCE(NULLIF(t.pan1, ''), w.PAN_NO)";
		 
        
        $whereConditions = [];
        
        // Add conditions only if values are provided
        if (!empty($userUCC)) {
            $whereConditions[] = "u.CLIENT_CODE = '$userUCC'";
        }
        
        if (!empty($fromDate) && !empty($toDate)) {
            $whereConditions[] = "DATE(t.transaction_date) BETWEEN '$fromDate' AND '$toDate'";
        }
        
        if (!empty($transactionType)) {
            $whereConditions[] = "t.transaction_type = '$transactionType'";
        }
        
        // Add WHERE clause if there are conditions
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $sql .= " ORDER BY t.transaction_date DESC";
        
        return $this->select($sql);
    }

    public function getSipDetails($fromDate, $toDate, $userUCC)
    {
        $dbconn = $this->getConnection();
        $fromDate = $this->safeEscape($fromDate);
        $toDate = $this->safeEscape($toDate);
        $userUCC = $this->safeEscape($userUCC);
        
        $sql = "SELECT s.* FROM tb_mailback_sip_wbr49 s
                INNER JOIN vw_client_profiles u ON s.pan = u.PRIMARY_HOLDER_PAN
                WHERE u.CLIENT_CODE = '$userUCC'";
        
        if (!empty($fromDate) && !empty($toDate)) {
            $sql .= " AND DATE(s.fromdate) BETWEEN '$fromDate' AND '$toDate'";
        }
        
        $sql .= " ORDER BY s.fromdate DESC";
        
        return $this->select($sql);
    }

    public function getschemename($product_code)
    {
        $dbconn = $this->getConnection();
        $product_code = $this->safeEscape($product_code);
        
        $sql = "SELECT scheme_name AS product_long_name FROM mtb_scheme_master WHERE scheme_code = '$product_code' LIMIT 1";
        $result = $this->selectSingle($sql);
        
        if ($result && isset($result["product_long_name"])) {
            return $result["product_long_name"];
        } else {
            return $product_code;
        }
    }
    
	/*
    public function getSipResponseFromDb($userUCC)
    {
        $dbconn = $this->getConnection();
        $userUCC = $this->safeEscape($userUCC);
        
        $sql = "SELECT s.* FROM tb_mailback_sip_wbr49 s
                INNER JOIN vw_client_profiles u ON s.pan = u.PRIMARY_HOLDER_PAN
                WHERE u.CLIENT_CODE = '$userUCC'";
        $sql .= " AND (s.`cease_status` = '' OR s.`cease_status` IS NULL)";
        $sql .= " ORDER BY s.`fromdate` DESC";
        
        return $this->select($sql);
    }
	*/
    public function getSipResponseFromDbDatewise($fromDate, $toDate, $userUCC)
    {
        $dbconn = $this->getConnection();
        $fromDate = $this->safeEscape($fromDate);
        $toDate = $this->safeEscape($toDate);
        $userUCC = $this->safeEscape($userUCC);
    
        $sql = "SELECT s.* FROM tb_mailback_sip_wbr49 s ";
    
        if (!empty($userUCC)) {
            $sql .= "INNER JOIN vw_client_profiles u ON s.pan = u.PRIMARY_HOLDER_PAN ";
            $sql .= "WHERE u.CLIENT_CODE = '$userUCC'";
        } else {
            $sql .= "WHERE 1";
        }
    
        if (!empty($fromDate)) {
            $sql .= " AND s.fromdate >= '$fromDate'";
        }
    
        if (!empty($toDate)) {
            $sql .= " AND s.fromdate <= '$toDate'";
        }
        
        $sql .= " ORDER BY s.fromdate DESC";
    
        return $this->select($sql);
    }
    
    public function saveNseApiTransactionsInDb($customer_id, $investor_name, $trx_type, $amount, $units, $all_units, $amc_name, $scheme_name, $registrar_id, $folio_no, $user_code, $user_trxn_no, $application_no, $payment_mode, $instrm_no, $bank_name, $acc_no, $payment_ref_no, $entry_date, $authorized_date_time, $trx_status, $fund_trans_date, $remarks, $rta_status, $rejected_reason, $rejection_date, $umrn_no, $fundclear_date, $target_product)
    {
        if ($folio_no !== null) {
            $parts = explode('/', $folio_no);
            $folio_no = trim($parts[0]);
        }
        
        $dbconn = $this->getConnection();
        $user_trxn_no = intval($user_trxn_no);
        
        $selectQuery = "SELECT user_trxn_no FROM tb_transactions_by_nseapi WHERE user_trxn_no = $user_trxn_no";
        $result = $this->query($selectQuery);

        if ($result->num_rows == 0) {
            $insertQuery = "INSERT INTO tb_transactions_by_nseapi (
                customer_id, investor_name, trx_type, amount, units, all_units, amc_name, scheme_name, 
                registrar_id, folio_no, user_code, user_trxn_no, application_no, payment_mode, instrm_no, 
                bank_name, acc_no, payment_ref_no, entry_date, authorized_date_time, trx_status, 
                fund_trans_date, remarks, rta_status, rejected_reason, rejection_date, umrn_no, 
                fundclear_date, target_product
            ) VALUES (
                '$customer_id', '$investor_name', '$trx_type', $amount, $units, '$all_units', '$amc_name', 
                '$scheme_name', '$registrar_id', '$folio_no', '$user_code', $user_trxn_no, $application_no, 
                '$payment_mode', '$instrm_no', '$bank_name', '$acc_no', '$payment_ref_no', '$entry_date', 
                '$authorized_date_time', '$trx_status', '$fund_trans_date', '$remarks', '$rta_status', 
                '$rejected_reason', '$rejection_date', '$umrn_no', '$fundclear_date', '$target_product'
            )";
            return $this->query($insertQuery);
        } else {
            $updateQuery = "UPDATE tb_transactions_by_nseapi SET
                customer_id = '$customer_id', investor_name = '$investor_name', trx_type = '$trx_type', 
                amount = $amount, units = $units, all_units = '$all_units', amc_name = '$amc_name', 
                scheme_name = '$scheme_name', registrar_id = '$registrar_id', folio_no = '$folio_no', 
                user_code = '$user_code', application_no = '$application_no', payment_mode = '$payment_mode', 
                instrm_no = '$instrm_no', bank_name = '$bank_name', acc_no = '$acc_no', 
                payment_ref_no = '$payment_ref_no', entry_date = '$entry_date', 
                authorized_date_time = '$authorized_date_time', trx_status = '$trx_status', 
                fund_trans_date = '$fund_trans_date', remarks = '$remarks', rta_status = '$rta_status', 
                rejected_reason = '$rejected_reason', rejection_date = '$rejection_date', umrn_no = '$umrn_no', 
                fundclear_date = '$fundclear_date', target_product = '$target_product'
            WHERE user_trxn_no = $user_trxn_no";
            
            return $this->query($updateQuery);
        }
    }
    
    public function saveSipResponseInDb($customer_id, $investor_name, $foliono, $auto_trxn_no, $instrm_ac_type, $amc_code, $product_code, $today, $fromscheme, $toscheme, $targetproduct, $frequency, $instdate, $instrm_date, $amount, $unit, $achamount, $topup, $topupamt, $topupfrequency, $payment_mechanism, $until_cancelled, $ifsc_code, $mobile_no, $email, $debit_amount_type, $cheq_micr_no, $umrn, $debitac, $noofinstall, $debitbank, $CLIENT_CODE_activation_flag, $unique_ref_no, $remarks, $ceaseinitiatedby, $auto_trxn_type, $sub_broker_arn, $arn_emp_code, $fh_pan_no, $guardian, $hold_nature_desc, $branch, $broker_code, $long_name, $application_no, $fromdate, $todate, $ach_from_date, $ach_end_date, $cease_date, $date_of_birth, $cease_status, $sys_trxn_auth_date, $sys_cease_auth_date, $trx_status, $freedom_sip, $freedom_sip_tenure, $freedom_swp_amount, $freedom_target_scheme, $sip_reg_no, $entry_date, $bank_dec_flag)
    {
        if (!empty($fh_pan_no)) {
            $fh_pan_no = strtoupper(preg_replace('/\s+/', '', $fh_pan_no));
            if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $fh_pan_no)) {
                return "Invalid PAN Number.";
            }
        }
        if ($foliono !== null) {
            $parts = explode('/', $foliono);
            $foliono = trim($parts[0]);
        }
        
        $dbconn = $this->getConnection();
        $auto_trxn_no = $this->safeEscape($auto_trxn_no);
        
        $sql = "SELECT COUNT(*) as count FROM tb_mailback_sip_wbr49  
                WHERE customer_id = '$customer_id' AND auto_trxn_no = '$auto_trxn_no'";
    
        $rows = $this->selectSingle($sql);
        
        if($rows == false || $rows['count'] == 0) {
            $sql = "INSERT INTO tb_mailback_sip_wbr49 (
                customer_id, investor_name, foliono, auto_trxn_no, instrm_ac_type, amc_code,
                product_code, today, fromscheme, toscheme, targetproduct, frequency, instdate,
                instrm_date, amount, unit, achamount, topup, topupamt, topupfrequency,
                payment_mechanism, until_cancelled, ifsc_code, mobile_no, email, debit_amount_type,
                cheq_micr_no, umrn, debitac, noofinstall, debitbank, CLIENT_CODE_activation_flag,
                unique_ref_no, remarks, ceaseinitiatedby, auto_trxn_type, sub_broker_arn,
                arn_emp_code, fh_pan_no, guardian, hold_nature_desc, branch, broker_code,
                long_name, application_no, fromdate, todate, ach_from_date, ach_end_date,
                cease_date, date_of_birth, cease_status, sys_trxn_auth_date, sys_cease_auth_date,
                trx_status, freedom_sip, freedom_sip_tenure, freedom_swp_amount,
                freedom_target_scheme, sip_reg_no, entry_date, bank_dec_flag
            ) VALUES (
                '$customer_id', '$investor_name', '$foliono', '$auto_trxn_no', '$instrm_ac_type', '$amc_code',
                '$product_code', '$today', '$fromscheme', '$toscheme', '$targetproduct', '$frequency', '$instdate',
                '$instrm_date', $amount, $unit, $achamount, '$topup', $topupamt, '$topupfrequency',
                '$payment_mechanism', '$until_cancelled', '$ifsc_code', '$mobile_no', '$email', '$debit_amount_type',
                '$cheq_micr_no', '$umrn', '$debitac', $noofinstall, '$debitbank', '$CLIENT_CODE_activation_flag',
                '$unique_ref_no', '$remarks', '$ceaseinitiatedby', '$auto_trxn_type', '$sub_broker_arn',
                '$arn_emp_code', '$fh_pan_no', '$guardian', '$hold_nature_desc', '$branch', '$broker_code',
                '$long_name', '$application_no', '$fromdate', '$todate', '$ach_from_date', '$ach_end_date',
                '$cease_date', '$date_of_birth', '$cease_status', '$sys_trxn_auth_date', '$sys_cease_auth_date',
                '$trx_status', '$freedom_sip', '$freedom_sip_tenure', $freedom_swp_amount,
                '$freedom_target_scheme', '$sip_reg_no', '$entry_date', '$bank_dec_flag'
            )";
            $this->query($sql);
            return "Inserted";
        } else {
            $query = "UPDATE tb_mailback_sip_wbr49 SET 
                investor_name = '$investor_name',
                foliono = '$foliono',
                instrm_ac_type = '$instrm_ac_type',
                product_code = '$product_code',
                today = '$today',
                fromscheme = '$fromscheme',
                toscheme = '$toscheme',
                targetproduct = '$targetproduct',
                frequency = '$frequency',
                instdate = '$instdate',
                instrm_date = '$instrm_date',
                amount = $amount,
                unit = $unit,
                achamount = $achamount,
                topup = '$topup',
                topupamt = $topupamt,
                topupfrequency = '$topupfrequency',
                payment_mechanism = '$payment_mechanism',
                until_cancelled = '$until_cancelled',
                ifsc_code = '$ifsc_code',
                mobile_no = '$mobile_no',
                email = '$email',
                debit_amount_type = '$debit_amount_type',
                cheq_micr_no = '$cheq_micr_no',
                umrn = '$umrn',
                debitac = '$debitac',
                noofinstall = $noofinstall,
                debitbank = '$debitbank',
                CLIENT_CODE_activation_flag = '$CLIENT_CODE_activation_flag',
                unique_ref_no = '$unique_ref_no',
                remarks = '$remarks',
                ceaseinitiatedby = '$ceaseinitiatedby',
                auto_trxn_type = '$auto_trxn_type',
                sub_broker_arn = '$sub_broker_arn',
                arn_emp_code = '$arn_emp_code',
                fh_pan_no = '$fh_pan_no',
                guardian = '$guardian',
                hold_nature_desc = '$hold_nature_desc',
                branch = '$branch',
                broker_code = '$broker_code',
                long_name = '$long_name',
                application_no = '$application_no',
                fromdate = '$fromdate',
                todate = '$todate',
                ach_from_date = '$ach_from_date',
                ach_end_date = '$ach_end_date',
                cease_date = '$cease_date',
                date_of_birth = '$date_of_birth',
                cease_status = '$cease_status',
                sys_trxn_auth_date = '$sys_trxn_auth_date',
                sys_cease_auth_date = '$sys_cease_auth_date',
                trx_status = '$trx_status',
                freedom_sip = '$freedom_sip',
                freedom_sip_tenure = '$freedom_sip_tenure',
                freedom_swp_amount = $freedom_swp_amount,
                freedom_target_scheme = '$freedom_target_scheme',
                sip_reg_no = '$sip_reg_no',
                entry_date = '$entry_date',
                bank_dec_flag = '$bank_dec_flag'
            WHERE customer_id = '$customer_id' AND auto_trxn_no = '$auto_trxn_no'";
            
            $this->query($query);
            return "Updated";
        }
    }

    public function updateSipCeaseInDb($customer_id, $foliono, $amount, $unit, $cease_date, $cease_status, $sip_reg_no, $sys_cease_auth_date)
    {
        $dbconn = $this->getConnection();
        $customer_id = $this->safeEscape($customer_id);
        $foliono = $this->safeEscape($foliono);
        $amount = floatval($amount);
        $unit = floatval($unit);
        $cease_date = $this->safeEscape($cease_date);
        $cease_status = $this->safeEscape($cease_status);
        $sip_reg_no = $this->safeEscape($sip_reg_no);
        $sys_cease_auth_date = $this->safeEscape($sys_cease_auth_date);
        
        $query = "UPDATE tb_mailback_sip_wbr49 
                  SET `cease_date` = '$cease_date', 
                      `cease_status` = '$cease_status', 
                      `sys_cease_auth_date` = '$sys_cease_auth_date' 
                  WHERE `customer_id` = '$customer_id' 
                    AND `foliono` = '$foliono' 
                    AND `amount` = $amount 
                    AND `unit` = $unit 
                    AND `sip_reg_no` = '$sip_reg_no'";
        
        return $this->query($query);
    }

    //==========================================================================
    // CAMS/KARVY TRANSACTION METHODS
    //==========================================================================
    
    public function insertCamsTrxType($trxType, $trxDesc, $trxDbCr, $trxSign, $trxRemarks, $remarks)
    {
        $dbconn = $this->getConnection();
        $trxType = $this->safeEscape($trxType);
        $trxDesc = $this->safeEscape($trxDesc);
        $trxDbCr = $this->safeEscape($trxDbCr);
        $trxSign = $this->safeEscape($trxSign);
        $trxRemarks = $this->safeEscape($trxRemarks);
        $remarks = $this->safeEscape($remarks);
        
        $query = "INSERT INTO tb_cams_trx_type (trx_type, trx_desc, trx_db_cr, trx_sign, remarks, user_remarks)
                  VALUES ('$trxType', '$trxDesc', '$trxDbCr', '$trxSign', '$trxRemarks', '$remarks')
                  ON DUPLICATE KEY UPDATE 
                  trx_desc = VALUES(trx_desc),
                  trx_db_cr = VALUES(trx_db_cr),
                  trx_sign = VALUES(trx_sign),
                  remarks = VALUES(remarks),
                  user_remarks = VALUES(user_remarks)";
        
        return $this->query($query);
    }
    
    public function insertKarvyMailBack($data)
    {
        if (empty($data)) {
            return false;
        }
        
        $dbconn = $this->getConnection();
        $success = true;
        
        foreach ($data as $row) {
            $columns = implode("`, `", array_keys($row));
            $values = array();
            
            foreach ($row as $value) {
                $values[] = "'" . $this->safeEscape($value) . "'";
            }
            
            $valuesStr = implode(", ", $values);
            $query = "INSERT INTO tb_transaction_mailback (`$columns`) VALUES ($valuesStr)";
            
            if (!$this->query($query)) {
                $success = false;
                error_log("Failed to insert mailback data: " . $this->getError());
            }
        }
        
        return $success;
    }

    //==========================================================================
    // CUSTOM / UTILITY / EXTRACT / API METHODS (Added to replace inline SQL)
    //==========================================================================
    
    public function getAmcCodes() {
        $sql = "SELECT DISTINCT amc_code FROM mtb_scheme_master ORDER BY amc_code ASC";
        return $this->select($sql);
    }
    
    public function getSchemeTypesByAmc($amc_code) {
        $sql = "SELECT DISTINCT scheme_type FROM mtb_scheme_master WHERE amc_code = ? ORDER BY scheme_type ASC";
        return $this->selectPrepared($sql, "s", [$amc_code]);
    }
    
    public function getFundCategoriesByAmcAndType($amc_code, $scheme_type) {
        $sql = "SELECT DISTINCT fund_cat FROM mtb_scheme_master WHERE amc_code = ? AND scheme_type = ? ORDER BY fund_cat ASC";
        return $this->selectPrepared($sql, "ss", [$amc_code, $scheme_type]);
    }
    
    public function getSchemesByAmcTypeAndCat($amc_code, $scheme_type, $fund_cat) {
        $sql = "SELECT scheme_code, scheme_name FROM mtb_scheme_master WHERE amc_code = ? AND scheme_type = ? AND fund_cat = ? ORDER BY scheme_name ASC";
        return $this->selectPrepared($sql, "sss", [$amc_code, $scheme_type, $fund_cat]);
    }

    //==========================================================================
    // ACTIVE FUNDS UTILITY METHODS
    //==========================================================================
    
    public function getActiveFundISINs()
    {
        $sql = "SELECT DISTINCT isin FROM tb_transaction_mailback WHERE isin IS NOT NULL AND isin != ''";
        $result = $this->selectPrepared($sql);
        if ($result === false) {
            return [];
        }
        return array_column($result, 'isin');
    }

    public function getTableColumns($table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $query = "SHOW COLUMNS FROM `$table`";
        $result = $this->select($query);
        $columns = [];
        if ($result) {
            foreach ($result as $row) {
                $columns[] = $row['Field'];
            }
        }
        return $columns;
    }

    public function getRecordByConditions($table, $primaryKey, $conditionsStr) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $primaryKey = preg_replace('/[^a-zA-Z0-9_]/', '', $primaryKey);
        $query = "SELECT `$primaryKey` FROM `$table` WHERE $conditionsStr";
        return $this->selectSingle($query);
    }

    public function updateRecordByConditions($table, $primaryKey, $slNo, $fieldDataStr) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $primaryKey = preg_replace('/[^a-zA-Z0-9_]/', '', $primaryKey);
        $slNo = intval($slNo);
        $query = "UPDATE `$table` SET $fieldDataStr WHERE `$primaryKey` = $slNo";
        return $this->query($query);
    }

    public function insertRecordRaw($table, $fieldStr, $colStr) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $query = "INSERT INTO `$table`(`$fieldStr`) VALUES ($colStr)";
        return $this->insertQuery($query);
    }

    public function checkAssignedGoal($iin, $amc_code, $fund_code) {
        $iin = $this->safeEscape($iin);
        $amc_code = $this->safeEscape($amc_code);
        $fund_code = $this->safeEscape($fund_code);
        return $this->selectSingle("SELECT id FROM assigned_goals WHERE IIN='$iin' AND amc_code='$amc_code' AND fund_code='$fund_code'");
    }

    public function updateAssignedGoalId($id, $goal_id) {
        $id = intval($id);
        $goal_id = intval($goal_id);
        return $this->query("UPDATE assigned_goals SET goal_id='$goal_id' WHERE id='$id'");
    }

    public function insertAssignedGoalOld($iin, $amc_code, $fund_code, $goal_id) {
        $iin = $this->safeEscape($iin);
        $amc_code = $this->safeEscape($amc_code);
        $fund_code = $this->safeEscape($fund_code);
        $goal_id = intval($goal_id);
        return $this->insertQuery("INSERT INTO assigned_goals (IIN, amc_code, fund_code, goal_id) VALUES ('$iin', '$amc_code', '$fund_code', '$goal_id')");
    }

    public function getGoalsDropdownByIin($iin) {
        $iin = $this->safeEscape($iin);
        return $this->select("SELECT id, name FROM goals WHERE iin='$iin'");
    }

    public function checkDuplicateWbr9($target_table, $folio, $product, $clos_bal, $rupee_bal) {
        $target_table = preg_replace('/[^a-zA-Z0-9_]/', '', $target_table);
        $folio = $this->safeEscape($folio);
        $product = $this->safeEscape($product);
        $clos_bal = $this->safeEscape($clos_bal);
        $rupee_bal = $this->safeEscape($rupee_bal);
        $check_sql = "SELECT id FROM `$target_table` WHERE `FOLIOCHK` = '$folio' AND `PRODUCT` = '$product' AND `CLOS_BAL` = '$clos_bal' AND `RUPEE_BAL` = '$rupee_bal' LIMIT 1";
        return $this->selectSingle($check_sql);
    }

    public function checkDuplicateTransactionMfsd201($target_table, $folio, $trxn_no, $trxn_id) {
        $target_table = preg_replace('/[^a-zA-Z0-9_]/', '', $target_table);
        $folio = $this->safeEscape($folio);
        $trxn_no = $this->safeEscape($trxn_no);
        $trxn_id = $this->safeEscape($trxn_id);
        $check_sql = "SELECT m.id FROM `$target_table` m INNER JOIN `tb_mailback_trans_wbr10_k201_addon` a ON m.id = a.main_id WHERE m.`FOLIO_CHK` = '$folio' AND m.`TRXNNO` = '$trxn_no' AND a.`transaction_id` = '$trxn_id' LIMIT 1";
        return $this->selectSingle($check_sql);
    }
    
    public function checkDuplicateTransactionWbr10($target_table, $folio, $trxn_no) {
        $target_table = preg_replace('/[^a-zA-Z0-9_]/', '', $target_table);
        $folio = $this->safeEscape($folio);
        $trxn_no = $this->safeEscape($trxn_no);
        $check_sql = "SELECT id FROM `$target_table` WHERE `FOLIO_CHK` = '$folio' AND `TRXNNO` = '$trxn_no' LIMIT 1";
        return $this->selectSingle($check_sql);
    }

    public function checkDuplicateSipWbr49($target_table, $folio, $auto_trno) {
        $target_table = preg_replace('/[^a-zA-Z0-9_]/', '', $target_table);
        $folio = $this->safeEscape($folio);
        $auto_trno = $this->safeEscape($auto_trno);
        $check_sql = "SELECT id FROM `$target_table` WHERE `folio_number` = '$folio' AND `auto_trno` = '$auto_trno' LIMIT 1";
        return $this->selectSingle($check_sql);
    }

    public function deleteRecordById($target_table, $id) {
        $target_table = preg_replace('/[^a-zA-Z0-9_]/', '', $target_table);
        $id = intval($id);
        return $this->query("DELETE FROM `$target_table` WHERE id = $id");
    }

    public function getIsinFromSchemeMaster($amc_code, $scheme_code) {
        $amc_code = $this->safeEscape($amc_code);
        $scheme_code = $this->safeEscape($scheme_code);
        $isin_sql = "SELECT isin, scheme_name FROM `mtb_scheme_master` WHERE `channel_partner_code` = CONCAT('$amc_code', '$scheme_code') LIMIT 1";
        return $this->selectSingle($isin_sql);
    }

    public function getPanFromWbr9($folio) {
        $folio = $this->safeEscape($folio);
        // Try to fetch a record that explicitly has a non-empty PAN first
        $pan_sql = "SELECT * FROM `tb_mailback_wbr9` WHERE `FOLIOCHK` = '$folio'  AND PAN_NO IS NOT NULL AND PAN_NO != '' LIMIT 1";
        $pan_res = $this->selectSingle($pan_sql);
        return $pan_res;
    }

    public function insertSplitTransaction($record) {
        $pan1 = isset($record['pan1']) ? trim($record['pan1']) : null;
        if (!empty($pan1)) {
            $pan1 = strtoupper(preg_replace('/\s+/', '', $pan1));
            if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan1)) {
                throw new Exception("Invalid PAN Number.");
            }
        }
        $main_record = [
            'FOLIO_CHK' => isset($record['folio_number']) ? trim($record['folio_number']) : null,
            'TRXNNO' => isset($record['transaction_number']) ? $record['transaction_number'] : null,
            'TRXNMODE' => isset($record['transaction_mode']) ? $record['transaction_mode'] : null,
            'TRADDATE' => isset($record['transaction_date']) ? $record['transaction_date'] : null,
            'PURPRICE' => isset($record['price']) ? $record['price'] : null,
            'UNITS' => isset($record['units']) ? $record['units'] : null,
            'AMOUNT' => isset($record['amount']) ? $record['amount'] : null,
            'TRXNSUBTYP' => isset($record['transaction_sub_type']) ? $record['transaction_sub_type'] : null,
            'TRXN_SIGN' => isset($record['transaction_flag']) ? $record['transaction_flag'] : null,
            'ISIN' => isset($record['isin']) ? trim($record['isin']) : null,
            'PAN1' => $pan1,
            'scheme_name' => isset($record['scheme_name']) ? trim($record['scheme_name']) : null
        ];
        
        $conn = $this->getConnection();
        $conn->begin_transaction();
        try {
            if (!$this->insertRecord('tb_mailback_trans_wbr10_k201_main', $main_record)) {
                throw new Exception("Failed to insert main split record");
            }
            $main_id = $conn->insert_id;
            
            $record['main_id'] = $main_id;
            // insertRecord automatically filters missing columns (like folio_number) using DESCRIBE, keeping only addon columns
            if (!$this->insertRecord('tb_mailback_trans_wbr10_k201_addon', $record)) {
                throw new Exception("Failed to insert addon split record");
            }
            
            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            return false;
        }
    }

    //==========================================================================
    // MAILBACK SYNC METHODS
    //==========================================================================
    public function getSyncStatus()
    {
        $status = [
            'required' => false,
            'messages' => []
        ];

        // Check main split table for missing PAN1 or ISIN
        $check = $this->selectSingle("SELECT 1 FROM tb_mailback_trans_wbr10_k201_main WHERE PAN1 IS NULL OR PAN1 = '' OR ISIN IS NULL OR ISIN = '' LIMIT 1");
        
        if ($check) {
            $status['required'] = true;
            $status['messages'][] = ['type' => 'warning', 'text' => 'Sync is required. Missing PAN or ISIN detected. Please click "Sync DB with Mailback".'];
        }

        return $status;
    }

    public function syncMailbackData()
    {
        $conn = $this->getConnection();
        $logs = [];

        $t = function() { return microtime(true); };
        $elapsed = function($start) { return round(microtime(true) - $start, 2) . 's'; };

        // ── Helper: pre-normalise a column into a generated stored column so
        //    the JOIN can use an index instead of calling TRIM/UPPER per row.
        //    We normalise once into a temp table keyed on the clean value.

        // 1. Update product_code where empty — simple, no join, fast.
        $t1 = $t();
        $sql = "UPDATE tb_transaction_mailback 
                SET product_code = CONCAT(IFNULL(amc_code, ''), IFNULL(scheme_code, ''))
                WHERE product_code IS NULL OR product_code = ''";
        if ($conn->query($sql)) {
            $logs[] = "[" . $elapsed($t1) . "] product_code updated: " . $conn->affected_rows . " rows";
        } else {
            $logs[] = "[" . $elapsed($t1) . "] ERROR product_code: " . $conn->error;
        }

        // 2. PAN / fund_desc from WBR9 — rewrite JOIN to avoid TRIM() on large table.
        //    Strategy: normalise folio keys into a temp table first, then JOIN on exact match.
        $t2 = $t();
        $conn->query("DROP TEMPORARY TABLE IF EXISTS _tmp_wbr9_folio");
        // GROUP BY folio — one row per folio.
        // PAN priority: PAN_NO → GUARD_PAN → JOINT1_PAN (first non-empty wins).
        $conn->query("CREATE TEMPORARY TABLE _tmp_wbr9_folio (
            folio VARCHAR(50) NOT NULL,
            pan   VARCHAR(20),
            sch   VARCHAR(255),
            PRIMARY KEY (folio)
        ) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SELECT
            TRIM(FOLIOCHK) AS folio,
            MAX(
                CASE
                    WHEN PAN_NO    IS NOT NULL AND TRIM(PAN_NO)    != '' THEN TRIM(PAN_NO)
                    WHEN GUARD_PAN IS NOT NULL AND TRIM(GUARD_PAN) != '' THEN TRIM(GUARD_PAN)
                    WHEN JOINT1_PAN IS NOT NULL AND TRIM(JOINT1_PAN) != '' THEN TRIM(JOINT1_PAN)
                    ELSE NULL
                END
            ) AS pan,
            MAX(SCH_NAME) AS sch
        FROM tb_mailback_wbr9
        WHERE FOLIOCHK IS NOT NULL AND FOLIOCHK != ''
        GROUP BY TRIM(FOLIOCHK)");

        $sql2 = "UPDATE tb_transaction_mailback tmb
                 JOIN _tmp_wbr9_folio w ON TRIM(tmb.folio_number) = w.folio
                 SET tmb.pan1      = COALESCE(NULLIF(tmb.pan1, ''), w.pan),
                     tmb.fund_desc = COALESCE(NULLIF(tmb.fund_desc, ''), w.sch)
                 WHERE tmb.pan1 IS NULL OR tmb.pan1 = ''
                    OR tmb.fund_desc IS NULL OR tmb.fund_desc = ''";
        if ($conn->query($sql2)) {
            $logs[] = "[" . $elapsed($t2) . "] PAN/FundDesc updated: " . $conn->affected_rows . " rows";
        } else {
            $logs[] = "[" . $elapsed($t2) . "] ERROR PAN/FundDesc: " . $conn->error;
        }
        $conn->query("DROP TEMPORARY TABLE IF EXISTS _tmp_wbr9_folio");

        // 3. ISIN via product_code — build temp table of normalised scheme_master codes.
        $t3 = $t();
        $conn->query("DROP TEMPORARY TABLE IF EXISTS _tmp_sm_product");
        $conn->query("CREATE TEMPORARY TABLE _tmp_sm_product (
            code VARCHAR(30) NOT NULL,
            isin VARCHAR(20),
            PRIMARY KEY (code)
        ) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SELECT TRIM(UPPER(channel_partner_code)) AS code, MAX(isin) AS isin
        FROM mtb_scheme_master
        WHERE channel_partner_code IS NOT NULL AND channel_partner_code != ''
          AND isin IS NOT NULL AND isin != ''
        GROUP BY TRIM(UPPER(channel_partner_code))");

        $sql3 = "UPDATE tb_transaction_mailback tmb
                 JOIN _tmp_sm_product sm ON TRIM(UPPER(tmb.product_code)) = sm.code
                 SET tmb.isin = sm.isin
                 WHERE (tmb.isin IS NULL OR tmb.isin = '')";
        if ($conn->query($sql3)) {
            $logs[] = "[" . $elapsed($t3) . "] ISIN Pass 1 updated: " . $conn->affected_rows . " rows";
        } else {
            $logs[] = "[" . $elapsed($t3) . "] ERROR ISIN Pass 1: " . $conn->error;
        }
        $conn->query("DROP TEMPORARY TABLE IF EXISTS _tmp_sm_product");

        // 4. ISIN fallback via scheme_code.
        $t4 = $t();
        $conn->query("DROP TEMPORARY TABLE IF EXISTS _tmp_sm_scheme");
        $conn->query("CREATE TEMPORARY TABLE _tmp_sm_scheme (
            code VARCHAR(30) NOT NULL,
            isin VARCHAR(20),
            PRIMARY KEY (code)
        ) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SELECT TRIM(UPPER(rta_scheme_code)) AS code, MAX(isin) AS isin
        FROM mtb_scheme_master
        WHERE rta_scheme_code IS NOT NULL AND rta_scheme_code != ''
          AND isin IS NOT NULL AND isin != ''
        GROUP BY TRIM(UPPER(rta_scheme_code))");

        $sql4 = "UPDATE tb_transaction_mailback tmb
                 JOIN _tmp_sm_scheme sm ON TRIM(UPPER(tmb.scheme_code)) = sm.code
                 SET tmb.isin = sm.isin
                 WHERE (tmb.isin IS NULL OR tmb.isin = '')";
        if ($conn->query($sql4)) {
            $logs[] = "[" . $elapsed($t4) . "] ISIN Pass 2 updated: " . $conn->affected_rows . " rows";
        } else {
            $logs[] = "[" . $elapsed($t4) . "] ERROR ISIN Pass 2: " . $conn->error;
        }
        $conn->query("DROP TEMPORARY TABLE IF EXISTS _tmp_sm_scheme");

        // Count records in tb_transaction_mailback that still have no PAN
        // after sync — all three sources (PAN_NO, GUARD_PAN, JOINT1_PAN) were empty.
        $t5 = $t();
        $pan_missing_result = $conn->query(
            "SELECT folio_number, investor_name FROM tb_transaction_mailback WHERE (pan1 IS NULL OR pan1 = '')"
        );
        $pan_missing_count = 0;
        $missing_folios = [];
        if ($pan_missing_result) {
            while ($pan_row = $pan_missing_result->fetch_assoc()) {
                $pan_missing_count++;
                if (!empty($pan_row['folio_number'])) {
                    $name = !empty($pan_row['investor_name']) ? trim($pan_row['investor_name']) : 'Unknown';
                    $missing_folios[$pan_row['folio_number']] = $pan_row['folio_number'] . ' (' . $name . ')';
                }
            }
            $pan_missing_result->free();
        }
        $logs['pan_missing'] = $pan_missing_count;
        if ($pan_missing_count > 0) {
            $folio_str = implode(', ', array_values($missing_folios));
            $logs[] = "[" . $elapsed($t5) . "] WARNING: $pan_missing_count transaction record(s) still have no PAN after sync (PAN_NO, GUARD_PAN, JOINT1_PAN all empty in WBR9). Folios: " . $folio_str;
        } else {
            $logs[] = "[" . $elapsed($t5) . "] PAN check: all records have PAN filled.";
        }

        return $logs;
    }

    //==========================================================================

    // INVESTMENT METHODS (BONDS, PMS, AIF)
    //==========================================================================
    
    public function addInvestment($CLIENT_CODE, $investmentType, $name, $issuer, $assetClass, $faceValue, $transactionType, $costValue, $interestRate, $units, $transactionRate, $bondPayoutDate, $transactionDate, $currentDate, $currentRate, $currentValue, $interestPayout, $cagr, $Mdate, $payoutAmt, $action, $slno)
    {
        $dbconn = $this->getConnection();
        
        // Handle NULL dates
        $Mdate = (empty($Mdate)) ? "NULL" : "'" . $this->safeEscape($Mdate) . "'";
        $transactionDate = (empty($transactionDate)) ? "NULL" : "'" . $this->safeEscape($transactionDate) . "'";
        $bondPayoutDate = (empty($bondPayoutDate)) ? "NULL" : "'" . $this->safeEscape($bondPayoutDate) . "'";
        $currentDate = (empty($currentDate)) ? "NULL" : "'" . $this->safeEscape($currentDate) . "'";
        
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $investmentType = $this->safeEscape($investmentType);
        $name = $this->safeEscape($name);
        $issuer = $this->safeEscape($issuer);
        $assetClass = $this->safeEscape($assetClass);
        $faceValue = floatval($faceValue);
        $transactionType = $this->safeEscape($transactionType);
        $costValue = floatval($costValue);
        $interestRate = floatval($interestRate);
        $units = floatval($units);
        $transactionRate = floatval($transactionRate);
        $currentRate = floatval($currentRate);
        $currentValue = floatval($currentValue);
        $interestPayout = $this->safeEscape($interestPayout);
        $cagr = floatval($cagr);
        $payoutAmt = floatval($payoutAmt);
        $slno = intval($slno);

        if($this->isUCCExists($CLIENT_CODE) === false) {
            return "Error: CLIENT_CODE $CLIENT_CODE does not exist.";
        }
        
        if($action == "add") {
            // Prevent exact duplicate: same client + type + name + transaction_date + transaction_type
            $checkDate = (empty($transactionDate) || $transactionDate === 'NULL') ? 'IS NULL' : '= ' . $transactionDate;
            $dupSql = "SELECT * FROM tb_investments 
                    WHERE CLIENT_CODE = '$CLIENT_CODE' 
                    AND invest_type = '$investmentType' 
                    AND investment_name = '$name' 
                    AND transaction_date $checkDate 
                    AND transaction_type = '$transactionType'";
            $dupRows = $this->selectSingle($dupSql);
            $isDuplicate = ($dupRows !== false);

            if(!$isDuplicate) {
                $query = "INSERT INTO `tb_investments` (
                    `CLIENT_CODE`, `invest_type`, `investment_name`, `issuer_name`, `asset_class`, 
                    `face_value`, `transaction_type`, `transaction_amount`, `interest_rate`, `units`, 
                    `transaction_rate`, `payout_date`, `transaction_date`, `current_date`, `payout_frequency`, 
                    `current_rate`, `current_value`, `cagr`, `payout_amt`, `maturity_date`
                ) VALUES (
                    '$CLIENT_CODE', '$investmentType', '$name', '$issuer', '$assetClass',
                    $faceValue, '$transactionType', $costValue, $interestRate, $units,
                    $transactionRate, $bondPayoutDate, $transactionDate, $currentDate, '$interestPayout',
                    $currentRate, $currentValue, $cagr, $payoutAmt, $Mdate
                )";
                $this->query($query);
                $typeLabel = strtoupper($investmentType);
                return "New $typeLabel investment: $name added successfully.";
            } else {
                return "Error: Duplicate entry - a $investmentType record for $name with the same transaction date and type already exists.";
            }
        } else if($action == "edit") {
            $query = "UPDATE `tb_investments` SET 
                `investment_name` = '$name',
                `issuer_name` = '$issuer', 
                `asset_class` = '$assetClass', 
                `face_value` = $faceValue, 
                `transaction_type` = '$transactionType',
                `transaction_amount` = $costValue, 
                `interest_rate` = $interestRate, 
                `units` = $units, 
                `transaction_rate` = $transactionRate, 
                `payout_date` = $bondPayoutDate, 
                `transaction_date` = $transactionDate,
                `current_date` = $currentDate, 
                `payout_frequency` = '$interestPayout', 
                `current_rate` = $currentRate, 
                `current_value` = $currentValue, 
                `cagr` = $cagr,
                `maturity_date` = $Mdate, 
                `payout_amt` = $payoutAmt 
                WHERE `slno` = $slno";
            
            if ($this->query($query)) {
                return "$name updated successfully.";
            } else {
                return "Failed to update investment: $name.";
            }
        }
        
        return "Invalid action specified.";
    }
    
    public function removeInvestment($investmentId, $iname)
    {
        $investmentId = intval($investmentId);
        $query = "DELETE FROM `tb_investments` WHERE `slno` = $investmentId";
        
        if ($this->query($query)) {
            return "$iname deleted successfully.";
        } else {
            return "Failed to delete bond investment: $iname.";
        }
    }
    
    public function getInvestmentData($investmentType, $CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $investmentType = $this->safeEscape($investmentType);
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT * FROM tb_investments WHERE invest_type = '$investmentType' AND CLIENT_CODE = '$CLIENT_CODE' ORDER BY transaction_date DESC";
        return $this->select($query);
    }
    
    public function getInvestmentDataById($investmentType, $investmentId)
    {
        $investmentId = intval($investmentId);
        $dbconn = $this->getConnection();
        $investmentType = $this->safeEscape($investmentType);
        
        $query = "SELECT * FROM tb_investments WHERE invest_type = '$investmentType' AND slno = $investmentId";
        return $this->selectSingle($query);
    }
    
    public function getOtherTransactions($userUCC, $invType = "")
    {
        $dbconn = $this->getConnection();
        $userUCC = $this->safeEscape($userUCC);
        $invType = $this->safeEscape($invType);
        
        if(!empty($invType)) {
            $sql = "SELECT * FROM `tb_investments` WHERE `CLIENT_CODE` = '$userUCC' AND `invest_type` = '$invType' ORDER BY `transaction_date` DESC";
        } else {
            $sql = "SELECT * FROM `tb_investments` WHERE `CLIENT_CODE` = '$userUCC' ORDER BY `transaction_date` DESC";
        }
        
        return $this->select($sql);
    }
    
    public function getOtherInvestmentsByAssetClass($assetClass = "")
    {
        $dbconn = $this->getConnection();
        $assetClass = $this->safeEscape($assetClass);
        
        if (!empty($assetClass)) {
            $sql = "SELECT DISTINCT investment_name FROM (
                SELECT investment_name, asset_class FROM mtb_other_investment WHERE investment_name IS NOT NULL AND TRIM(investment_name) != ''
                UNION
                SELECT investment_name, asset_class FROM tb_investments WHERE (invest_type = 'bond' OR invest_type = 'bond_div') AND investment_name IS NOT NULL AND TRIM(investment_name) != ''
            ) AS combined_bonds 
            WHERE LOWER(TRIM(asset_class)) = LOWER(TRIM('$assetClass')) OR asset_class IS NULL OR TRIM(asset_class) = ''
            ORDER BY investment_name ASC";
        } else {
            $sql = "SELECT DISTINCT investment_name FROM (
                SELECT investment_name FROM mtb_other_investment WHERE investment_name IS NOT NULL AND TRIM(investment_name) != ''
                UNION
                SELECT investment_name FROM tb_investments WHERE (invest_type = 'bond' OR invest_type = 'bond_div') AND investment_name IS NOT NULL AND TRIM(investment_name) != ''
            ) AS combined_bonds 
            ORDER BY investment_name ASC";
        }
        return $this->select($sql);
    }
    
    public function getOtherInvestmentsByName($name)
    {
        $dbconn = $this->getConnection();
        $name = $this->safeEscape($name);
        $sql = "SELECT * FROM tb_investments WHERE `investment_name` = '$name'";
        return $this->selectSingle($sql);
    }
    
    public function getOtherInvestmentsNAVByName($name)
    {
        $dbconn = $this->getConnection();
        $name = $this->safeEscape($name);
        $sql = "SELECT * FROM tb_other_investment_nav WHERE `name` = '$name' ORDER BY nav_date DESC LIMIT 1";
        return $this->selectSingle($sql);
    }

    public function getBondDetails($innvstName)
    {
        $dbconn = $this->getConnection();
        $innvstNameSafe = $this->safeEscape(trim($innvstName));
        if (empty($innvstNameSafe)) {
            return false;
        }

        // 1. Try exact match
        $query = "SELECT * FROM mtb_other_investment WHERE investment_name = '$innvstNameSafe'";
        $result = $this->selectSingle($query);
        if ($result) {
            return $result;
        }

        // 2. Try prefix match (master investment_name starts with $innvstName)
        $query = "SELECT * FROM mtb_other_investment WHERE investment_name LIKE '$innvstNameSafe%'";
        $result = $this->selectSingle($query);
        if ($result) {
            return $result;
        }

        // 3. Try reverse prefix match ($innvstName starts with master investment_name)
        $query = "SELECT * FROM mtb_other_investment WHERE '$innvstNameSafe' LIKE CONCAT(investment_name, '%')";
        $result = $this->selectSingle($query);
        if ($result) {
            return $result;
        }

        // 4. Try partial substring match
        $query = "SELECT * FROM mtb_other_investment WHERE investment_name LIKE '%$innvstNameSafe%' OR '$innvstNameSafe' LIKE CONCAT('%', investment_name, '%')";
        $result = $this->selectSingle($query);
        if ($result) {
            return $result;
        }

        return false;
    }
    
	public function getBondFirstPayoutDate(DateTime $purchasedDate, string $interestPayDateStr, string $payoutFreq): DateTime
    {
        $purchasedYear  = (int)$purchasedDate->format('Y');
        $purchasedMonth = (int)$purchasedDate->format('n');
        $purchasedDay   = (int)$purchasedDate->format('j');

        $freq = strtolower(trim($payoutFreq));

        $payDay    = null;
        $baseMonth = null;

        if (!empty($interestPayDateStr)) {
            if (preg_match('/^(\d{1,2})[-\s\/]+([A-Za-z]{3,})/i', $interestPayDateStr, $m)) {
                $payDay = (int)$m[1];
                $monthStr = $m[2];
                $baseMonth = (int)date('n', strtotime("1 $monthStr 2000"));
            } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $interestPayDateStr, $m)) {
                $baseMonth = (int)$m[2];
                $payDay = (int)$m[3];
            } elseif (preg_match('/^(\d{1,2})[-\s\/]+(\d{1,2})[-\s\/]+(\d{4})/', $interestPayDateStr, $m)) {
                $payDay = (int)$m[1];
                $baseMonth = (int)$m[2];
            }
        }

        if (!$payDay || !$baseMonth) {
            $payDay    = $purchasedDay;
            $baseMonth = $purchasedMonth;
        }

        $scheduledMonths = [];

        switch ($freq) {
            case 'monthly':
                for ($m = 1; $m <= 12; $m++) {
                    $scheduledMonths[] = $m;
                }
                break;
            case 'quarterly':
            case 'quaterly':
                for ($i = 0; $i < 4; $i++) {
                    $m = (($baseMonth - 1 + ($i * 3)) % 12) + 1;
                    $scheduledMonths[] = $m;
                }
                sort($scheduledMonths);
                break;
            case 'semi annually':
            case 'semi-annually':
            case 'half - yearly':
            case 'half yearly':
                for ($i = 0; $i < 2; $i++) {
                    $m = (($baseMonth - 1 + ($i * 6)) % 12) + 1;
                    $scheduledMonths[] = $m;
                }
                sort($scheduledMonths);
                break;
            case 'annually':
            default:
                $scheduledMonths = [$baseMonth];
                break;
        }

        $candidates = [];
        for ($y = $purchasedYear - 1; $y <= $purchasedYear + 2; $y++) {
            foreach ($scheduledMonths as $m) {
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $m, $y);
                $actualDay   = min($payDay, $daysInMonth);
                $candStr     = sprintf('%04d-%02d-%02d', $y, $m, $actualDay);
                $candDate    = date_create($candStr);
                if ($candDate >= $purchasedDate) {
                    $candidates[] = $candDate;
                }
            }
        }

        usort($candidates, function($a, $b) {
            return $a <=> $b;
        });

        return !empty($candidates) ? $candidates[0] : clone $purchasedDate;
    }
    
    public function calculatePayouts($invstName, $payoutDates)
    {
		//echo "<br> calculatePayouts called <br>";
        $cashflows = [];
        $timeperiods = [];
        $totalPayoutAmt = 0;
        $retStr = "Bond details not found in bond masters table for: $invstName";
        
        $bondDetails = $this->getBondDetails($invstName);
        if($bondDetails == false) {
            return [
                'message' => $retStr,
                'cashflows' => $cashflows,
                'timeperiods' => $timeperiods,
                'total_payout' => $totalPayoutAmt,
            ];
        }

		//echo "<br> calculatePayouts: bon details found  <br>";
        $interestRate = $bondDetails['interest_rate'] / 100;
        $faceValue = $bondDetails['face_value'];
        $payout_frequency = strtolower($bondDetails['payout_frequency']);
        $interestPayDateStr = isset($bondDetails['interest_pay_date']) ? trim($bondDetails['interest_pay_date']) : '';
        
		//echo "<br>"."Payout calculation for  bond : <br>Ineterst Rate: ".$interestRate." Face value : ".$faceValue."Payout Frequency: ".$payout_frequency;
        if(($interestRate > 0) && ($bondDetails == true) && !empty($payoutDates)) {
            
            foreach($payoutDates as $paydate) {
                if (!isset($paydate['purchase_date']) || !isset($paydate['last_pay_date'])) {
                    continue;
                }
                
                $purchasedDate = date_create($paydate['purchase_date']);
                                $units = isset($paydate['units']) ? $paydate['units'] : 1;
                $lastPayoutDate = date_create($paydate['last_pay_date']);
                
                $refPayDate = !empty($paydate['first_pay_date']) ? $paydate['first_pay_date'] : $interestPayDateStr;
                $payDateRefStr = !empty($interestPayDateStr) ? $interestPayDateStr : $refPayDate;
				
                if (strcasecmp($payout_frequency, "on maturity") == 0) {
                    $currentDate = date_create();
                    $maturityDate = date_create($bondDetails['redemption_date']);
                    
                    if ($currentDate >= $maturityDate) {
                        $timeDiff = date_diff($purchasedDate, $maturityDate)->days;
                        $timeDiffYears = intdiv($timeDiff, 365);
                        $timeDiffRemainingDays = fmod($timeDiff, 365);
                        
                        if($timeDiffYears > 0) {
                            $NewFaceValue = $faceValue * $units * pow((1 + $interestRate), $timeDiffYears);
                            $interestPerPayout = $NewFaceValue - ($faceValue * $units);
                            $totalPayoutAmt += $interestPerPayout;
                        }
                        if($timeDiffRemainingDays > 0) {
                            $interestPerPayout = ($faceValue * $units * $interestRate * $timeDiffRemainingDays) / 365;
                            $totalPayoutAmt += $interestPerPayout;
                        }
                        $cashflows[] = $totalPayoutAmt;
                        $timeperiods[] = strtotime($maturityDate->format('Y-m-d'));
                    }
                } else {
                    $payoutDate = $this->getBondFirstPayoutDate($purchasedDate, $payDateRefStr, $payout_frequency);

                    while ($payoutDate <= $lastPayoutDate) {
                        $timeDiff = date_diff($purchasedDate, $payoutDate)->days;
                        if ($timeDiff > 0) {
                        $interestPerPayout = ($faceValue * $units * $interestRate * $timeDiff) / 365;
                        $totalPayoutAmt += $interestPerPayout;
                        
                        $cashflows[] = $interestPerPayout;
                        $timeperiods[] = strtotime($payoutDate->format('Y-m-d'));
                        }
                        
                        $purchasedDate = clone $payoutDate;
                    
                        switch ($payout_frequency) {
                            case 'monthly':
                                $payoutDate->add(new DateInterval('P1M'));
                                break;
                            case 'quarterly':
                            case 'quaterly':
                                $payoutDate->add(new DateInterval('P3M'));
                                break;
                            case 'semi annually':
                            case 'semi-annually':
                            case 'half - yearly':
                            case 'half yearly':
                                $payoutDate->add(new DateInterval('P6M'));
                                break;
                            case 'annually':
                                $payoutDate->add(new DateInterval('P1Y'));
                                break;
                            default:
                                $payoutDate->add(new DateInterval('P1Y'));
                                break;
                        }
                    }
                }
            }
            $retStr = "$totalPayoutAmt Total Payout(s) calculated";
        } else {
            $retStr = "Payout not applicable!";
        }
        
        return [
            'message' => $retStr,
            'cashflows' => $cashflows,
            'timeperiods' => $timeperiods,
            'total_payout' => $totalPayoutAmt,
        ];
    }
    
    //==========================================================================
    // PMS (Portfolio Management Services) METHODS
    //==========================================================================
    
    public function getPmsByUCC($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $sql = "SELECT DISTINCT investment_name FROM `tb_investments` WHERE `CLIENT_CODE` = '$CLIENT_CODE' AND `invest_type` = 'pms'";
        return $this->select($sql);
    }
    
    public function updatePmsNav($CLIENT_CODE, $name, $navDate, $navAmount, $action, $slno)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $name = $this->safeEscape($name);
        $navAmount = floatval($navAmount);
        $slno = intval($slno);
        
        $navDate = (empty($navDate)) ? "NULL" : "'" . $this->safeEscape($navDate) . "'";
        
        if($this->isUCCExists($CLIENT_CODE) === false) {
            return "Error: CLIENT_CODE $CLIENT_CODE does not exist.";
        }
        
        if($action == "add") {
            $sql = "SELECT * FROM tb_pms_nav WHERE CLIENT_CODE = '$CLIENT_CODE' AND pms_name = '$name'";
            $rows = $this->selectSingle($sql);
            
            if($rows == false) {
                $query = "INSERT INTO `tb_pms_nav`(`CLIENT_CODE`, `pms_name`, `cur_value`, `nav_date`) 
                          VALUES ('$CLIENT_CODE','$name',$navAmount,$navDate)";
                $this->query($query);
                return "New PMS $name NAV added successfully.";
            } else {
                $query = "UPDATE `tb_pms_nav` SET `cur_value` = $navAmount, `nav_date` = $navDate 
                          WHERE CLIENT_CODE = '$CLIENT_CODE' AND pms_name = '$name'";
                $this->query($query);
                return "PMS $name NAV updated successfully.";
            }
        } else if($action == "edit") {
            $query = "UPDATE `tb_pms_nav` SET `cur_value` = $navAmount, `nav_date` = $navDate 
                      WHERE `slno` = $slno";
            if ($this->query($query)) {
                return "PMS $name NAV updated successfully.";
            } else {
                return "Failed to update NAV of PMS $name.";
            }
        }
        
        return "Invalid action";
    }
    
    public function getPmsNavDataBySlno($nav_slno)
    {
        $nav_slno = intval($nav_slno);
        $query = "SELECT * FROM tb_pms_nav WHERE slno = $nav_slno";
        return $this->selectSingle($query);
    }
    
    public function removePmsNav($investmentId, $iname)
    {
        $investmentId = intval($investmentId);
        $query = "DELETE FROM `tb_pms_nav` WHERE `slno` = $investmentId";
        
        if ($this->query($query)) {
            return "$iname deleted successfully.";
        } else {
            return "Failed to delete PMS investment: $iname.";
        }
    }
    
    public function getPmsNavData($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $query = "SELECT * FROM `tb_pms_nav` WHERE CLIENT_CODE = '$CLIENT_CODE' ORDER BY pms_name";
        return $this->select($query);
    }
    
    public function getPmsNavDatabyName($CLIENT_CODE, $pmsName)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $pmsName = $this->safeEscape($pmsName);
        
        $query = "SELECT * FROM `tb_pms_nav` WHERE CLIENT_CODE = '$CLIENT_CODE' AND pms_name = '$pmsName'";
        return $this->selectSingle($query);
    }
    
    //==========================================================================
    // AIF (Alternative Investment Fund) METHODS
    //==========================================================================
    
    public function getAifByUCC($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $sql = "SELECT DISTINCT investment_name FROM `tb_investments` WHERE `CLIENT_CODE` = '$CLIENT_CODE' AND `invest_type` = 'aif'";
        return $this->select($sql);
    }
    
    public function updateAifNav($CLIENT_CODE, $name, $navDate, $navAmount, $action, $slno)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $name = $this->safeEscape($name);
        $navAmount = floatval($navAmount);
        $slno = intval($slno);
        
        $navDate = (empty($navDate)) ? "NULL" : "'" . $this->safeEscape($navDate) . "'";
        
        if($this->isUCCExists($CLIENT_CODE) === false) {
            return "Error: CLIENT_CODE $CLIENT_CODE does not exist.";
        }
        
        if($action == "add") {
            $sql = "SELECT * FROM tb_aif_nav WHERE CLIENT_CODE = '$CLIENT_CODE' AND aif_name = '$name'";
            $rows = $this->selectSingle($sql);
            
            if($rows == false) {
                $query = "INSERT INTO `tb_aif_nav`(`CLIENT_CODE`, `aif_name`, `cur_value`, `nav_date`) 
                          VALUES ('$CLIENT_CODE','$name',$navAmount,$navDate)";
                $this->query($query);
                return "New AIF $name NAV added successfully.";
            } else {
                $query = "UPDATE `tb_aif_nav` SET `cur_value` = $navAmount, `nav_date` = $navDate 
                          WHERE CLIENT_CODE = '$CLIENT_CODE' AND aif_name = '$name'";
                $this->query($query);
                return "AIF $name NAV updated successfully.";
            }
        } else if($action == "edit") {
            $query = "UPDATE `tb_aif_nav` SET `cur_value` = $navAmount, `nav_date` = $navDate 
                      WHERE `slno` = $slno";
            if ($this->query($query)) {
                return "AIF $name NAV updated successfully.";
            } else {
                return "Failed to update NAV of AIF $name.";
            }
        }
        
        return "Invalid action";
    }
    
    public function getAifNavDataBySlno($nav_slno)
    {
        $nav_slno = intval($nav_slno);
        $query = "SELECT * FROM tb_aif_nav WHERE slno = $nav_slno";
        return $this->selectSingle($query);
    }
    
    public function removeAifNav($investmentId, $iname)
    {
        $investmentId = intval($investmentId);
        $query = "DELETE FROM `tb_aif_nav` WHERE `slno` = $investmentId";
        
        if ($this->query($query)) {
            return "$iname deleted successfully.";
        } else {
            return "Failed to delete AIF investment: $iname.";
        }
    }
    
    public function getAifNavData($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $query = "SELECT * FROM `tb_aif_nav` WHERE CLIENT_CODE = '$CLIENT_CODE' ORDER BY aif_name";
        return $this->select($query);
    }
    
    public function getAifNavDatabyName($CLIENT_CODE, $aifName)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $aifName = $this->safeEscape($aifName);
        
        $query = "SELECT * FROM `tb_aif_nav` WHERE CLIENT_CODE = '$CLIENT_CODE' AND aif_name = '$aifName'";
        return $this->selectSingle($query);
    }
    
    public function getAifPayouts($CLIENT_CODE, $aifName)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $aifName = $this->safeEscape($aifName);
        
        $query = "SELECT * FROM `tb_aif_payout` WHERE CLIENT_CODE = '$CLIENT_CODE' AND aif_name = '$aifName' ORDER BY payout_date DESC";
        return $this->select($query);
    }

    public function getAifPayoutData($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $query = "SELECT * FROM `tb_aif_payout` WHERE CLIENT_CODE = '$CLIENT_CODE' ORDER BY payout_date DESC";
        return $this->select($query);
    }
    
    public function updateAifPayout($CLIENT_CODE, $name, $date, $amount, $action, $slno, $remarks)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $name = $this->safeEscape($name);
        $amount = floatval($amount);
        $slno = intval($slno);
        $remarks = $this->safeEscape($remarks);
        
        $date = (empty($date)) ? "NULL" : "'" . $this->safeEscape($date) . "'";
        
        if($this->isUCCExists($CLIENT_CODE) === false) {
            return "Error: CLIENT_CODE $CLIENT_CODE does not exist.";
        }
        
        if($action == "add") {
            $query = "INSERT INTO `tb_aif_payout`(`CLIENT_CODE`, `aif_name`, `payout_amount`, `payout_date`, `remarks`) 
                      VALUES ('$CLIENT_CODE','$name',$amount,$date,'$remarks')";
            $this->query($query);
            return "New AIF $name Payout added successfully.";
        } else if($action == "edit") {
            $query = "UPDATE `tb_aif_payout` SET 
                      `payout_amount` = $amount, 
                      `payout_date` = $date, 
                      `remarks` = '$remarks' 
                      WHERE `slno` = $slno";
            if ($this->query($query)) {
                return "AIF $name Payout updated successfully.";
            } else {
                return "Failed to update Payout of AIF $name.";
            }
        }
        
        return "Invalid action";
    }
    
    public function getAifPayoutDataBySlno($slno)
    {
        $slno = intval($slno);
        $query = "SELECT * FROM tb_aif_payout WHERE slno = $slno";
        return $this->selectSingle($query);
    }
    
    public function removeAifPayout($investmentId, $iname)
    {
        $investmentId = intval($investmentId);
        $query = "DELETE FROM `tb_aif_payout` WHERE `slno` = $investmentId";
        
        if ($this->query($query)) {
            return "$iname deleted successfully.";
        } else {
            return "Failed to delete AIF payout: $iname.";
        }
    }

    public function calculateAIFPayouts($CLIENT_CODE, $aifName)
    {
        $cashflows = [];
        $timeperiods = [];
        $totalPayoutAmt = 0;
        $retStr = "No AIF Payouts found for: $aifName";
        
        $aifPayoutDetails = $this->getAifPayouts($CLIENT_CODE, $aifName);
        if(empty($aifPayoutDetails)) {
            return [
                'message' => $retStr,
                'cashflows' => $cashflows,
                'timeperiods' => $timeperiods,
                'total_payout' => $totalPayoutAmt,
            ];
        } else {
            foreach($aifPayoutDetails as $aifPayout) {
                $payoutAmt = $aifPayout['payout_amount'];
                $payoutDate = $aifPayout['payout_date'];

                $cashflows[] = $payoutAmt;
                $timeperiods[] = strtotime($payoutDate);
                $totalPayoutAmt += $payoutAmt;
            }
            $retStr = "$totalPayoutAmt Total Payout(s) calculated";
        }
        
        return [
            'message' => $retStr,
            'cashflows' => $cashflows,
            'timeperiods' => $timeperiods,
            'total_payout' => $totalPayoutAmt,
        ];
    }

    //==========================================================================
    // FAMILY MANAGEMENT METHODS
    //==========================================================================
    
    public function addFamily($CLIENT_CODE, $CLIENT_CODE1, $rel1, $CLIENT_CODE2, $rel2, $CLIENT_CODE3, $rel3, $action, $slno)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $CLIENT_CODE1 = $this->safeEscape($CLIENT_CODE1);
        $rel1 = $this->safeEscape($rel1);
        $CLIENT_CODE2 = $this->safeEscape($CLIENT_CODE2);
        $rel2 = $this->safeEscape($rel2);
        $CLIENT_CODE3 = $this->safeEscape($CLIENT_CODE3);
        $rel3 = $this->safeEscape($rel3);
        $slno = intval($slno);
        
        if($this->isUCCExists($CLIENT_CODE) === false) {
            return "Error: CLIENT_CODE $CLIENT_CODE does not exist.";
        }
        
        if($action == "add") {
            $sql = "SELECT * FROM tb_family WHERE head_UCC = '$CLIENT_CODE'";
            $rows = $this->selectSingle($sql);
            
            if($rows == false) {
                $query = "INSERT INTO `tb_family`(`head_UCC`, `member1_UCC`, `member1_rel`, 
                          `member2_UCC`, `member2_rel`, `member3_UCC`, `member3_rel`) 
                          VALUES ('$CLIENT_CODE','$CLIENT_CODE1','$rel1','$CLIENT_CODE2','$rel2','$CLIENT_CODE3','$rel3')";
                $this->query($query);
                return "Family members added successfully.";
            } else {
                return "Family record for $CLIENT_CODE already exists.";
            }
        } else if($action == "edit") {
            $query = "UPDATE `tb_family` SET 
                      `head_UCC`='$CLIENT_CODE',
                      `member1_UCC`='$CLIENT_CODE1',
                      `member1_rel`='$rel1',
                      `member2_UCC`='$CLIENT_CODE2',
                      `member2_rel`='$rel2',
                      `member3_UCC`='$CLIENT_CODE3',
                      `member3_rel`='$rel3' 
                      WHERE `slno` = $slno";
            
            if ($this->query($query)) {
                return "Family record updated successfully.";
            } else {
                return "Failed to update family record.";
            }
        }
        
        return "Invalid action";
    }
    
    public function getFamilies($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT f.*, 
                  (SELECT `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` = f.head_UCC LIMIT 1) AS head_PRIMARY_HOLDER_NAME,
                  (SELECT `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` = f.member1_UCC LIMIT 1) AS member1_PRIMARY_HOLDER_NAME,
                  (SELECT `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` = f.member2_UCC LIMIT 1) AS member2_PRIMARY_HOLDER_NAME,
                  (SELECT `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` = f.member3_UCC LIMIT 1) AS member3_PRIMARY_HOLDER_NAME
                  FROM tb_family f 
                  WHERE head_UCC = '$CLIENT_CODE'";
        
        return $this->select($query);
    }
    
    public function getFamiliesByAnyCLIENT_CODE($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT f.*,
                  h.PRIMARY_HOLDER_NAME AS head_PRIMARY_HOLDER_NAME,
                  m1.PRIMARY_HOLDER_NAME AS member1_PRIMARY_HOLDER_NAME,
                  m2.PRIMARY_HOLDER_NAME AS member2_PRIMARY_HOLDER_NAME,
                  m3.PRIMARY_HOLDER_NAME AS member3_PRIMARY_HOLDER_NAME
                FROM tb_family f
                LEFT JOIN vw_client_profiles h ON h.CLIENT_CODE = f.head_UCC
                LEFT JOIN vw_client_profiles m1 ON m1.CLIENT_CODE = f.member1_UCC
                LEFT JOIN vw_client_profiles m2 ON m2.CLIENT_CODE = f.member2_UCC
                LEFT JOIN vw_client_profiles m3 ON m3.CLIENT_CODE = f.member3_UCC
                WHERE f.head_UCC = '$CLIENT_CODE'
                   OR f.member1_UCC = '$CLIENT_CODE'
                   OR f.member2_UCC = '$CLIENT_CODE'
                   OR f.member3_UCC = '$CLIENT_CODE'";
        
        return $this->select($query);
    }    

    public function getFamiliesByAnyIIN($iin)
    {
        $iin = $this->safeEscape($iin);
        $query = "SELECT f.*,
                  h.PRIMARY_HOLDER_NAME AS head_PRIMARY_HOLDER_NAME,
                  m1.PRIMARY_HOLDER_NAME AS member1_PRIMARY_HOLDER_NAME,
                  m2.PRIMARY_HOLDER_NAME AS member2_PRIMARY_HOLDER_NAME,
                  m3.PRIMARY_HOLDER_NAME AS member3_PRIMARY_HOLDER_NAME
                FROM tb_family f
                LEFT JOIN vw_client_profiles h ON h.CLIENT_CODE = f.head_UCC
                LEFT JOIN vw_client_profiles m1 ON m1.CLIENT_CODE = f.member1_UCC
                LEFT JOIN vw_client_profiles m2 ON m2.CLIENT_CODE = f.member2_UCC
                LEFT JOIN vw_client_profiles m3 ON m3.CLIENT_CODE = f.member3_UCC
                WHERE f.head_UCC = '$iin'
                   OR f.member1_UCC = '$iin'
                   OR f.member2_UCC = '$iin'
                   OR f.member3_UCC = '$iin'";
        //error_log("MySQLDB::getFamiliesByAnyIIN SQL: " . $query . " | Parameter: " . $iin);
        return $this->select($query);
    }

    public function getFamiliesById($slno)
    {
        $slno = intval($slno);
        
        $query = "SELECT f.*,
                  (SELECT `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` = f.head_UCC LIMIT 1) AS head_PRIMARY_HOLDER_NAME,
                  (SELECT `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` = f.member1_UCC LIMIT 1) AS member1_PRIMARY_HOLDER_NAME,
                  (SELECT `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` = f.member2_UCC LIMIT 1) AS member2_PRIMARY_HOLDER_NAME,
                  (SELECT `PRIMARY_HOLDER_NAME` FROM `vw_client_profiles` WHERE `CLIENT_CODE` = f.member3_UCC LIMIT 1) AS member3_PRIMARY_HOLDER_NAME
                  FROM tb_family f 
                  WHERE f.slno = $slno";
        
        return $this->selectSingle($query);
    }
    
    public function deleteFamily($slno)
    {
        $slno = intval($slno);
        
        $selectQuery = "SELECT `head_UCC` FROM `tb_family` WHERE `slno` = $slno";
        $headUCCResult = $this->selectSingle($selectQuery);
        
        if (!$headUCCResult) {
            return "Family record not found.";
        }
        
        $headUCC = $headUCCResult['head_UCC'];
        $deleteQuery = "DELETE FROM `tb_family` WHERE `slno` = $slno";
        
        if ($this->query($deleteQuery)) {
            return "Family record with head_UCC '$headUCC' deleted successfully.";
        } else {
            return "Failed to delete Family record for head_UCC '$headUCC'.";
        }
    }
    
		   public function getFamilyByUCC($UCC)
		{
			$dbconn = $this->getConnection();
			$UCC = $this->safeEscape($UCC);
			$query = "SELECT * FROM `tb_family` WHERE `head_UCC` = '$UCC' LIMIT 1";
			$result = $this->selectSingle($query);
			return $result;
		}
		/**
 * Update existing family record
 */
public function updateFamily($slno, $headUCC, $UCC1, $rel1, $UCC2, $rel2, $UCC3, $rel3) {
    $dbconn = $this->getConnection();
    $slno = intval($slno);
    $headUCC = $this->safeEscape($headUCC);
    $UCC1 = $this->safeEscape($UCC1);
    $rel1 = $this->safeEscape($rel1);
    $UCC2 = $this->safeEscape($UCC2);
    $rel2 = $this->safeEscape($rel2);
    $UCC3 = $this->safeEscape($UCC3);
    $rel3 = $this->safeEscape($rel3);
    
    $query = "UPDATE `tb_family` SET 
              `head_UCC` = '$headUCC',
              `member1_UCC` = '$UCC1',
              `member1_rel` = '$rel1',
              `member2_UCC` = '$UCC2',
              `member2_rel` = '$rel2',
              `member3_UCC` = '$UCC3',
              `member3_rel` = '$rel3'
              WHERE `slno` = $slno";
    
    if ($this->query($query)) {
        return "Family record updated successfully.";
    } else {
        return "Failed to update family record: " . $this->getError();
    }
}

public function updateFamilyIncludeState($iin, $includeFamily)
{
    $iin = $this->safeEscape($iin);
    $includeFamily = intval($includeFamily);
    $sql = "UPDATE tb_family SET include_family = $includeFamily WHERE head_UCC = '$iin'";
    return $this->query($sql);
}

    //==========================================================================
    // CAS STATEMENT METHODS
    //==========================================================================
    
    public function saveCasStm($CLIENT_CODE, $path, $name = '', $pan = '')
    {
        $dbconn = $this->getConnection();
        
        // Ensure columns exist for name and pan (Safe automatic database alteration)
        $checkCol = $dbconn->query("SHOW COLUMNS FROM `tb_cas_stm` LIKE 'client_name'");
        if ($checkCol && $checkCol->num_rows == 0) {
            $dbconn->query("ALTER TABLE `tb_cas_stm` ADD COLUMN `client_name` VARCHAR(255) NULL AFTER `CLIENT_CODE`, ADD COLUMN `pan_no` VARCHAR(50) NULL AFTER `client_name`");
        }
        
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $path = $this->safeEscape($path);
        $name = $this->safeEscape($name);
        $pan = $this->safeEscape($pan);
        
        $sql = "SELECT * FROM `tb_cas_stm` WHERE `CLIENT_CODE` = '$CLIENT_CODE' AND `cas_file` = '$path'";
        $rows = $this->selectSingle($sql);
        
        if($rows == false) {
            $query = "INSERT INTO `tb_cas_stm` (`CLIENT_CODE`, `client_name`, `pan_no`, `cas_file`, `uploaded_date`) VALUES ('$CLIENT_CODE', '$name', '$pan', '$path', NOW())";
            $this->query($query);
            return "Inserted";
        } else {
            $query = "UPDATE `tb_cas_stm` SET `client_name` = '$name', `pan_no` = '$pan', `uploaded_date` = NOW() WHERE `CLIENT_CODE` = '$CLIENT_CODE' AND `cas_file` = '$path'";
            $this->query($query);
            return "Updated";
        }
    }
    
    public function getCasDetails($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $sql = "SELECT * FROM `tb_cas_stm` WHERE `CLIENT_CODE` = '$CLIENT_CODE' ORDER BY `uploaded_date` DESC";
        return $this->select($sql);
    }

    public function getCasPortfolioClients()
    {
        $sql = "SELECT c.CLIENT_CODE, 
                       COALESCE(u.PRIMARY_HOLDER_NAME, c.client_name) as client_name, 
                       MAX(c.uploaded_date) as last_updated, 
                       u.EMAIL 
                FROM tb_cas_stm c 
                LEFT JOIN vw_client_profiles u ON c.CLIENT_CODE = u.CLIENT_CODE 
                GROUP BY c.CLIENT_CODE, u.PRIMARY_HOLDER_NAME, c.client_name, u.EMAIL
                ORDER BY last_updated DESC";
        return $this->select($sql);
    }

    //==========================================================================
    // COMPANY INFO METHODS
    //==========================================================================
    
    public function getCompanyInfo()
    {
        $sql = "SELECT * FROM tb_company_info LIMIT 1";
        return $this->selectSingle($sql);
    }

    public function updateCompanyInfo($registeredAddress, $corporateAddress, $supportEmail, $supportPhone)
    {
        $dbconn = $this->getConnection();
        $registeredAddress = $this->safeEscape($registeredAddress);
        $corporateAddress = $this->safeEscape($corporateAddress);
        $supportEmail = $this->safeEscape($supportEmail);
        $supportPhone = $this->safeEscape($supportPhone);
        
        $query = "INSERT INTO tb_company_info (id, registered_address, corporate_address, support_email, support_phone)
                  VALUES (1, '$registeredAddress', '$corporateAddress', '$supportEmail', '$supportPhone')
                  ON DUPLICATE KEY UPDATE 
                  registered_address = '$registeredAddress', 
                  corporate_address = '$corporateAddress',
                  support_email = '$supportEmail', 
                  support_phone = '$supportPhone'";
        
        return $this->query($query);
    }

    //==========================================================================
    // RECOMMENDATION METHODS (Other Investments)
    //==========================================================================
    
    public function getRecommendationOtherInvData()
    {
        $query = "SELECT * FROM tb_other_inv_rec ORDER BY investment_type, asset_class, name";
        return $this->select($query);
    }
    
    public function getRecOtherInvDataByslno($slno)
    {
        $slno = intval($slno);
        $query = "SELECT * FROM tb_other_inv_rec WHERE `slno` = $slno";
        return $this->selectSingle($query);
    }
    
    public function insertRecomadation($invest_type, $asset_class, $name, $issuer, $rational, $slno, $action)
    {
        $dbconn = $this->getConnection();
        $invest_type = $this->safeEscape($invest_type);
        $asset_class = $this->safeEscape($asset_class);
        $name = $this->safeEscape($name);
        $issuer = $this->safeEscape($issuer);
        $rational = $this->safeEscape($rational);
        $slno = intval($slno);
        
        if($action == "add") {
            $sql = "SELECT * FROM tb_other_inv_rec WHERE name = '$name' AND issuer = '$issuer'";
            $rows = $this->selectSingle($sql);
            
            if($rows == false) {
                $query = "INSERT INTO `tb_other_inv_rec`(`investment_type`, `asset_class`, `name`, `issuer`, `rational`) 
                          VALUES ('$invest_type','$asset_class','$name','$issuer','$rational')";
                $this->query($query);
                return "New Investment $name added successfully.";
            } else {
                $query = "UPDATE `tb_other_inv_rec` SET 
                          `investment_type` = '$invest_type',
                          `asset_class` = '$asset_class',
                          `issuer` = '$issuer',
                          `rational` = '$rational' 
                          WHERE `slno` = " . $rows['slno'];
                $this->query($query);
                return "Investment $name updated successfully.";
            }
        } else if($action == "edit") {
            $query = "UPDATE `tb_other_inv_rec` SET 
                      `investment_type` = '$invest_type',
                      `asset_class` = '$asset_class',
                      `name` = '$name',
                      `issuer` = '$issuer',
                      `rational` = '$rational' 
                      WHERE `slno` = $slno";
            
            if ($this->query($query)) {
                return "Investment $name updated successfully.";
            } else {
                return "Failed to update Investment $name.";
            }
        }
        
        return "Invalid action";
    }
    
    public function delRecOtherInvDataByslno($slno, $iname)
    {
        $slno = intval($slno);
        $query = "DELETE FROM `tb_other_inv_rec` WHERE `slno` = $slno";
        
        if ($this->query($query)) {
            return "$iname deleted successfully.";
        } else {
            return "Failed to delete investment: $iname.";
        }
    }

  
    //==========================================================================
    // GOAL CATEGORY METHODS
    //==========================================================================
    
    public function addGoalCategory($name, $description) {
        $dbconn = $this->getConnection();
        $name = $this->safeEscape($name);
        $description = $this->safeEscape($description);
        
        $query = "INSERT INTO goal_categories (name, description) VALUES (?, ?)";
        $stmt = $dbconn->prepare($query);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $dbconn->error);
            return false;
        }
        
        $stmt->bind_param("ss", $name, $description);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    public function getAllGoalCategories() {
        $dbconn = $this->getConnection();
        $query = "SELECT * FROM goal_categories ORDER BY created_at DESC";
        $result = $dbconn->query($query);
        
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
    
    public function updateGoalCategory($id, $name, $description) {
        $dbconn = $this->getConnection();
        $id = intval($id);
        $name = $this->safeEscape($name);
        $description = $this->safeEscape($description);
        
        $query = "UPDATE goal_categories SET name = ?, description = ? WHERE id = ?";
        $stmt = $dbconn->prepare($query);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $dbconn->error);
            return false;
        }
        
        $stmt->bind_param("ssi", $name, $description, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    public function deleteGoalCategory($id) {
        $dbconn = $this->getConnection();
        $id = intval($id);
        
        $query = "DELETE FROM goal_categories WHERE id = ?";
        $stmt = $dbconn->prepare($query);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $dbconn->error);
            return false;
        }
        
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    public function getGoalCategoryByName($name) {
        $dbconn = $this->getConnection();
        $name = $this->safeEscape($name);
        
        $query = "SELECT * FROM goal_categories WHERE name = ?";
        $stmt = $dbconn->prepare($query);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $dbconn->error);
            return null;
        }
        
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    
    public function getGoalCategoryCount() {
        $dbconn = $this->getConnection();
        $query = "SELECT COUNT(*) AS total FROM goal_categories";
        $result = $dbconn->query($query);
        
        if ($result) {
            $row = $result->fetch_assoc();
            return $row['total'];
        }
        return 0;
    }
    
    public function getGoalCategoriesPaginated($limit, $offset) {
        $dbconn = $this->getConnection();
        $limit = intval($limit);
        $offset = intval($offset);
        
        $query = "SELECT * FROM goal_categories ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $result = $dbconn->query($query);
        
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
    
    //==========================================================================
    // GOAL MANAGEMENT METHODS
    //==========================================================================
    
    public function addGoal($name, $category_id, $amount, $goal_date, $remarks, $CLIENT_CODE) {
        $dbconn = $this->getConnection();
        $category_id = intval($category_id);
        $amount = floatval($amount);
        
        $query = "INSERT INTO goals (name, category_id, amount_required, goal_date, remarks, ucc) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $dbconn->prepare($query);
        
        if (!$stmt) {
            error_log("addGoal prepare failed: " . $dbconn->error);
            return false;
        }
        
        $stmt->bind_param("sidsss", $name, $category_id, $amount, $goal_date, $remarks, $CLIENT_CODE);
        $success = $stmt->execute();
        if (!$success) error_log("addGoal execute failed: " . $stmt->error);
        $stmt->close();
        return $success;
    }
    
    public function updateGoal($id, $name, $category_id, $amount, $goal_date, $remarks, $CLIENT_CODE) {
        $dbconn = $this->getConnection();
        $id = intval($id);
        $category_id = intval($category_id);
        $amount = floatval($amount);
        
        $query = "UPDATE goals SET 
                  name = ?, 
                  category_id = ?, 
                  amount_required = ?, 
                  goal_date = ?, 
                  remarks = ? 
                  WHERE id = ? AND ucc = ?";
        
        $stmt = $dbconn->prepare($query);
        if (!$stmt) {
            error_log("updateGoal prepare failed: " . $dbconn->error);
            return false;
        }
        
        $stmt->bind_param("sidssis", $name, $category_id, $amount, $goal_date, $remarks, $id, $CLIENT_CODE);
        $success = $stmt->execute();
        if (!$success) error_log("updateGoal execute failed: " . $stmt->error);
        $stmt->close();
        return $success;
    }
    
    public function deleteGoal($id, $CLIENT_CODE) {
        $dbconn = $this->getConnection();
        $id = intval($id);
        
        $query = "DELETE FROM goals WHERE id = ? AND ucc = ?";
        $stmt = $dbconn->prepare($query);
        
        if (!$stmt) {
            error_log("deleteGoal prepare failed: " . $dbconn->error);
            return false;
        }
        
        $stmt->bind_param("is", $id, $CLIENT_CODE);
        $success = $stmt->execute();
        if (!$success) error_log("deleteGoal execute failed: " . $stmt->error);
        $stmt->close();
        return $success;
    }
    
    public function getGoalsPaginated($CLIENT_CODE, $limit, $offset) {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        $limit = intval($limit);
        $offset = intval($offset);
        
        $query = "SELECT g.id, g.ucc, g.name, g.category_id, g.amount_required, g.goal_date, g.remarks, g.created_at,
                         gc.name AS category_name 
                  FROM goals g
                  LEFT JOIN goal_categories gc ON g.category_id = gc.id 
                  WHERE g.ucc = '$CLIENT_CODE' 
                  ORDER BY g.created_at DESC 
                  LIMIT $limit OFFSET $offset";
        
        $result = $dbconn->query($query);
        
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        } else {
            error_log("getGoalsPaginated query failed: " . $dbconn->error);
        }
        return $data;
    }
    
    public function getGoalsByUCC($CLIENT_CODE) {
        return $this->getGoalsByCLIENT_CODE($CLIENT_CODE);
    }
    
    public function getGoalsByCLIENT_CODE($CLIENT_CODE) {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT g.*, gc.name AS category_name 
                  FROM goals g
                  LEFT JOIN goal_categories gc ON g.category_id = gc.id 
                  WHERE g.ucc = '$CLIENT_CODE' 
                  ORDER BY g.created_at DESC";
        
        $result = $dbconn->query($query);
        
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        } else {
            error_log("getGoalsByCLIENT_CODE query failed: " . $dbconn->error);
        }
        return $data;
    }
    
    public function getGoalsCount($CLIENT_CODE) {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT COUNT(*) AS total FROM goals WHERE ucc = '$CLIENT_CODE'";
        $result = $dbconn->query($query);
        
        if ($result) {
            $row = $result->fetch_assoc();
            return $row['total'];
        }
        error_log("getGoalsCount query failed: " . $dbconn->error);
        return 0;
    }
    
    //==========================================================================
    // GOAL ASSIGNMENT METHODS
    //==========================================================================
    
    public function assignGoalToFolio($CLIENT_CODE, $investment_type, $amc_code, $fundCode, $asset_class, $fund_name, $goalId) {
        $dbconn = $this->getConnection();
        $goalId = intval($goalId);
        
        // Check if assignment already exists
        $checkQuery = "SELECT id FROM assigned_goals WHERE ucc = ? AND amc_code = ? AND fund_code = ?";
        $stmt = $dbconn->prepare($checkQuery);
        if (!$stmt) {
            error_log("assignGoalToFolio check prepare failed: " . $dbconn->error);
            return false;
        }
        $stmt->bind_param("sss", $CLIENT_CODE, $amc_code, $fundCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->fetch_assoc();
        $stmt->close();
        
        if ($exists) {
            // Update existing assignment
            $query = "UPDATE assigned_goals SET goal_id = ?, investment_type = ?, asset_class = ?, fund_name = ? 
                      WHERE ucc = ? AND amc_code = ? AND fund_code = ?";
            $stmt = $dbconn->prepare($query);
            if (!$stmt) {
                error_log("assignGoalToFolio update prepare failed: " . $dbconn->error);
                return false;
            }
            $stmt->bind_param("issssss", $goalId, $investment_type, $asset_class, $fund_name, $CLIENT_CODE, $amc_code, $fundCode);
        } else {
            // Insert new assignment
            $query = "INSERT INTO assigned_goals (ucc, investment_type, amc_code, fund_code, asset_class, fund_name, goal_id) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $dbconn->prepare($query);
            if (!$stmt) {
                error_log("assignGoalToFolio insert prepare failed: " . $dbconn->error);
                return false;
            }
            $stmt->bind_param("ssssssi", $CLIENT_CODE, $investment_type, $amc_code, $fundCode, $asset_class, $fund_name, $goalId);
        }
        
        $success = $stmt->execute();
        if (!$success) error_log("assignGoalToFolio execute failed: " . $stmt->error);
        $stmt->close();
        return $success;
    }
    
    public function getAssignedGoals($CLIENT_CODE) {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT 
                    a.*,
                    g.name AS goal_name,
                    g.amount_required,
                    g.goal_date,
                    g.remarks
                  FROM assigned_goals a
                  JOIN goals g ON a.goal_id = g.id
                  WHERE a.ucc = '$CLIENT_CODE'";
        
        return $this->select($query);
    }
    
    public function getAssignedGoalsWithDetails($CLIENT_CODE) {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT 
                    a.*,
                    g.name AS goal_name,
                    g.amount_required,
                    g.goal_date,
                    g.remarks,
                    gc.name AS category_name,
                    gc.id AS category_id
                  FROM assigned_goals a
                  JOIN goals g ON a.goal_id = g.id
                  LEFT JOIN goal_categories gc ON g.category_id = gc.id
                  WHERE a.ucc = '$CLIENT_CODE'";
        
        $result = $dbconn->query($query);
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        } else {
            error_log("getAssignedGoalsWithDetails query failed: " . $dbconn->error);
        }
        return $data;
    }
    
    public function unassignGoal($assignment_id) {
        $dbconn = $this->getConnection();
        $assignment_id = intval($assignment_id);
        
        $query = "DELETE FROM assigned_goals WHERE id = ?";
        $stmt = $dbconn->prepare($query);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $dbconn->error);
            return false;
        }
        
        $stmt->bind_param("i", $assignment_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    public function getGoalsWithFundsByCLIENT_CODE($CLIENT_CODE) {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT g.id AS goal_id, g.name AS goal_name, gc.name AS category, 
                         g.amount_required, g.goal_date, g.remarks,
                         a.amc_code, a.fund_code, a.fund_name, a.asset_class
                  FROM goals g
                  LEFT JOIN goal_categories gc ON g.category_id = gc.id
                  LEFT JOIN assigned_goals a ON g.id = a.goal_id AND a.ucc = g.ucc
                  WHERE g.ucc = '$CLIENT_CODE'
                  ORDER BY g.id";
        
        return $this->select($query);
    }

   

   //==========================================================================
    // SCHEME MASTER METHODS
    //==========================================================================
   
    public function getAmcMaster()
    {
        $sql = "SELECT AMC_CODE as code, LONG_NAME as details FROM mtb_amc ORDER BY LONG_NAME";
        return $this->select($sql);
    }
 
    public function schemeExists($schemeCode)
    {
        $dbconn = $this->getConnection();
        $schemeCode = $this->safeEscape($schemeCode);
        
        $sql = "SELECT 1 FROM mtb_scheme_master WHERE scheme_code = '$schemeCode' LIMIT 1";
        $result = $this->selectSingle($sql);
        
        return ($result !== false);
    }

    public function insertScheme($data)
    {
        $dbconn = $this->getConnection();
        
        // Escape all values with null handling
        $unique_sr_no = $this->safeEscape($data['unique_sr_no'] ?? '');
        $scheme_code = $this->safeEscape($data['scheme_code'] ?? '');
        $rta_scheme_code = $this->safeEscape($data['rta_scheme_code'] ?? '');
        $amc_scheme_code = $this->safeEscape($data['amc_scheme_code'] ?? '');
        $isin = $this->safeEscape($data['isin'] ?? '');
        $amc_code = $this->safeEscape($data['amc_code'] ?? '');
        $scheme_type = $this->safeEscape($data['scheme_type'] ?? '');
        $plan_type = $this->safeEscape($data['plan_type'] ?? '');
        $scheme_name = $this->safeEscape($data['scheme_name'] ?? '');
        $purchase_allowed = $this->safeEscape($data['purchase_allowed'] ?? '');
        $purchase_transaction_mode = $this->safeEscape($data['purchase_transaction_mode'] ?? '');
        $new_purchase_min_amount = $this->safeEscape($data['new_purchase_min_amount'] ?? '0');
        $additional_purchase_min_amount = $this->safeEscape($data['additional_purchase_min_amount'] ?? '0');
        $additional_purchase_max_amount = $this->safeEscape($data['additional_purchase_max_amount'] ?? '0');
        $purchase_amount_multiplier = $this->safeEscape($data['purchase_amount_multiplier'] ?? '1');
        $purchase_cutoff_time = $this->safeEscape($data['purchase_cutoff_time'] ?? '');
        $redemption_allowed = $this->safeEscape($data['redemption_allowed'] ?? '');
        $redemption_transaction_mode = $this->safeEscape($data['redemption_transaction_mode'] ?? '');
        $redemption_min_qty = $this->safeEscape($data['redemption_min_qty'] ?? '0');
        $redemption_qty_multiplier = $this->safeEscape($data['redemption_qty_multiplier'] ?? '1');
        $redemption_max_qty = $this->safeEscape($data['redemption_max_qty'] ?? '0');
        $redemption_min_amount = $this->safeEscape($data['redemption_min_amount'] ?? '0');
        $redemption_max_amount = $this->safeEscape($data['redemption_max_amount'] ?? '0');
        $redemption_amount_multiplier = $this->safeEscape($data['redemption_amount_multiplier'] ?? '1');
        $redemption_cutoff_time = $this->safeEscape($data['redemption_cutoff_time'] ?? '');
        $rta_agent_code = $this->safeEscape($data['rta_agent_code'] ?? '');
        $amc_active_flag = $this->safeEscape($data['amc_active_flag'] ?? '');
        $div_reinvest_flag = $this->safeEscape($data['div_reinvest_flag'] ?? '');
        $sip_allowed = $this->safeEscape($data['sip_allowed'] ?? '');
        $stp_enabled = $this->safeEscape($data['stp_enabled'] ?? '');
        $swp_enabled = $this->safeEscape($data['swp_enabled'] ?? '');
        $switch_allowed = $this->safeEscape($data['switch_allowed'] ?? '');
        $settlement_type = $this->safeEscape($data['settlement_type'] ?? '');
        $amc_ind = $this->safeEscape($data['amc_ind'] ?? '');
        $face_value = $this->safeEscape($data['face_value'] ?? '0');
        $scheme_start_date = $this->safeEscape($data['scheme_start_date'] ?? '');
        $maturity_date = $this->safeEscape($data['maturity_date'] ?? '');
        $exit_load_flag = $this->safeEscape($data['exit_load_flag'] ?? '');
        $exit_load = $this->safeEscape($data['exit_load'] ?? '0');
        $lock_in_period_flag = $this->safeEscape($data['lock_in_period_flag'] ?? '');
        $lock_in_period = $this->safeEscape($data['lock_in_period'] ?? '0');
        $channel_partner_code = $this->safeEscape($data['channel_partner_code'] ?? '');
        $reopening_date = $this->safeEscape($data['reopening_date'] ?? '');
        $open_close_ended_scheme = $this->safeEscape($data['open_close_ended_scheme'] ?? '');
        $fund_cat = $this->safeEscape($data['fund_cat'] ?? 'Other');
        $cat_tag = $this->safeEscape($data['cat_tag'] ?? '');
        
        $sql = "INSERT INTO mtb_scheme_master
        (
            unique_sr_no, scheme_code, rta_scheme_code, amc_scheme_code, isin, amc_code,
            scheme_type, plan_type, scheme_name, purchase_allowed, purchase_transaction_mode,
            new_purchase_min_amount, additional_purchase_min_amount, additional_purchase_max_amount,
            purchase_amount_multiplier, purchase_cutoff_time, redemption_allowed, redemption_transaction_mode,
            redemption_min_qty, redemption_qty_multiplier, redemption_max_qty, redemption_min_amount,
            redemption_max_amount, redemption_amount_multiplier, redemption_cutoff_time, rta_agent_code,
            amc_active_flag, div_reinvest_flag, sip_allowed, stp_enabled, swp_enabled, switch_allowed,
            settlement_type, amc_ind, face_value, scheme_start_date, maturity_date, exit_load_flag,
            exit_load, lock_in_period_flag, lock_in_period, channel_partner_code, reopening_date,
            open_close_ended_scheme, fund_cat, cat_tag
        )
        VALUES
        (
            '$unique_sr_no', '$scheme_code', '$rta_scheme_code', '$amc_scheme_code', '$isin', '$amc_code',
            '$scheme_type', '$plan_type', '$scheme_name', '$purchase_allowed', '$purchase_transaction_mode',
            '$new_purchase_min_amount', '$additional_purchase_min_amount', '$additional_purchase_max_amount',
            '$purchase_amount_multiplier', '$purchase_cutoff_time', '$redemption_allowed', '$redemption_transaction_mode',
            '$redemption_min_qty', '$redemption_qty_multiplier', '$redemption_max_qty', '$redemption_min_amount',
            '$redemption_max_amount', '$redemption_amount_multiplier', '$redemption_cutoff_time', '$rta_agent_code',
            '$amc_active_flag', '$div_reinvest_flag', '$sip_allowed', '$stp_enabled', '$swp_enabled', '$switch_allowed',
            '$settlement_type', '$amc_ind', '$face_value', '$scheme_start_date', '$maturity_date', '$exit_load_flag',
            '$exit_load', '$lock_in_period_flag', '$lock_in_period', '$channel_partner_code', '$reopening_date',
            '$open_close_ended_scheme', '$fund_cat', '$cat_tag'
        )";
        
        return $this->insertQuery($sql);
    }

    public function upsertScheme($data)
    {
        if (empty($data['scheme_code'])) {
            return false;
        }
        
        if ($this->schemeExists($data['scheme_code'])) {
            // Update existing
            $dbconn = $this->getConnection();
            $scheme_code = $this->safeEscape($data['scheme_code']);
            
            $updateFields = [];
            foreach ($data as $key => $value) {
                if ($key != 'scheme_code') {
                    $safeValue = $this->safeEscape($value);
                    $updateFields[] = "`$key` = '$safeValue'";
                }
            }
            
            $sql = "UPDATE mtb_scheme_master SET " . implode(", ", $updateFields) . " WHERE scheme_code = '$scheme_code'";
            $result = $this->query($sql);
            return $result ? 'Updated' : false;
        } else {
            // Insert new
            $id = $this->insertScheme($data);
            return $id ? 'Inserted' : false;
        }
    }

    //==========================================================================
    // PAYOUT METHODS
    //==========================================================================
    
    public function updatePayout2($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT slno, purchased_date, payout_date, interest_rate, interest_payout, face_value, units 
                  FROM tb_investments WHERE CLIENT_CODE = '$CLIENT_CODE'";
        
        $result = $this->query($query);
        $passStatus = 0;
        $failStatus = 0;
        
        while ($row = $result->fetch_assoc()) {
            $purchasedDate = new DateTime($row['purchased_date']);
            $firstPayoutDate = new DateTime($row['payout_date']);
            $units = $row['units'];
            $interestRate = $row['interest_rate'] / 100;
            $faceValue = $row['face_value'];
            
            $currentDate = new DateTime();
            $totalPayoutAmt = 0;
            $tempPurchasedDate = clone $purchasedDate;
            $tempPayoutDate = clone $firstPayoutDate;

            while ($tempPayoutDate <= $currentDate) {
                $timeDiff = $tempPurchasedDate->diff($tempPayoutDate)->days;
                $tempPurchasedDate = clone $tempPayoutDate;
                $interestPerPayout = ($faceValue * $units * $interestRate * $timeDiff) / 365;
                $totalPayoutAmt += $interestPerPayout;
                
                switch ($row['interest_payout']) {
                    case 'Monthly':
                        $tempPayoutDate->add(new DateInterval('P1M'));
                        break;
                    case 'Quarterly':
                        $tempPayoutDate->add(new DateInterval('P3M'));
                        break;
                    case 'Semi-annually':
                        $tempPayoutDate->add(new DateInterval('P6M'));
                        break;
                    case 'Annually':
                        $tempPayoutDate->add(new DateInterval('P1Y'));
                        break;
                    default:
                        $tempPayoutDate->add(new DateInterval('P1Y'));
                        break;
                }
            }
            
            $investmentId = $row['slno'];
            $updateQuery = "UPDATE tb_investments SET payout_amt = $totalPayoutAmt WHERE slno = $investmentId";
            
            if ($this->query($updateQuery)) {
                $passStatus++;
            } else {
                $failStatus++;
            }
        }
        
        $retStr = "";
        if($passStatus > 0) $retStr = $passStatus . " Payout updated";
        if($failStatus > 0) {
            if($retStr != "") $retStr .= ", ";
            $retStr .= $failStatus . " Payout update fail";
        }
        
        return $retStr;
    }
    
    public function updatePayout($CLIENT_CODE)
    {
        $dbconn = $this->getConnection();
        $CLIENT_CODE = $this->safeEscape($CLIENT_CODE);
        
        $query = "SELECT * FROM tb_investments WHERE CLIENT_CODE = '$CLIENT_CODE'";
        $result = $this->query($query);
        $payoutData = array();

        while ($row = $result->fetch_assoc()) {
            $transactionDate = new DateTime($row['transaction_date']);
            $firstPayoutDate = new DateTime($row['payout_date']);
            $units = $row['units'];
            $interestRate = $row['interest_rate'] / 100;
            $faceValue = $row['face_value'];
            $name = $row['investment_name'];

            $currentDate = new DateTime();
            $totalPayoutAmt = 0;
            $tempTransactionDate = clone $transactionDate;
            $tempPayoutDate = clone $firstPayoutDate;

            while ($tempPayoutDate <= $currentDate) {
                $timeDiff = $tempTransactionDate->diff($tempPayoutDate)->days;
                $tempTransactionDate = clone $tempPayoutDate;
                $interestPerPayout = ($faceValue * $units * $interestRate * $timeDiff) / 365;
                $totalPayoutAmt += $interestPerPayout;

                $payoutData[] = array(
                    'slno' => $row['slno'],
                    'CLIENT_CODE' => $row['CLIENT_CODE'],
                    'invest_type' => $row['invest_type'],
                    'name' => $name,
                    'asset_class' => $row['asset_class'],
                    'face_value' => $row['face_value'],
                    'cost_value' => $row['transaction_amount'],
                    'interest_rate' => $row['interest_rate'],
                    'units' => $units,
                    'transaction_rate' => $row['transaction_rate'],
                    'payout_date' => $tempPayoutDate->format('Y-m-d'),
                    'transaction_date' => $tempTransactionDate->format('Y-m-d'),
                    'interest_payout' => $row['payout_frequency'],
                    'payout_amt' => $interestPerPayout,
                    'current_rate' => $row['current_rate'],
                    'current_value' => $row['current_value'],
                    'current_date' => $row['current_date'],
                    'maturity_date' => $row['maturity_date']
                );

                switch ($row['payout_frequency']) {
                    case 'Monthly':
                        $tempPayoutDate->add(new DateInterval('P1M'));
                        break;
                    case 'Quarterly':
                        $tempPayoutDate->add(new DateInterval('P3M'));
                        break;
                    case 'Semi-annually':
                        $tempPayoutDate->add(new DateInterval('P6M'));
                        break;
                    case 'Annually':
                        $tempPayoutDate->add(new DateInterval('P1Y'));
                        break;
                    default:
                        $tempPayoutDate->add(new DateInterval('P1Y'));
                        break;
                }
            }
        }
        
        return $payoutData;
    }

    public function calculatePayoutOnMaturity($invstName, $units, $purchaseDate, $payoutDates = array())
    {
        $cashflows = [];
        $timeperiods = [];
        $totalPayoutAmt = 0;
        $retStr = "Payout not applicable!";
        
        $bondDetails = $this->getBondDetails($invstName);
        if($bondDetails == false) {
            return [
                'message' => "Bond details not found for: $invstName",
                'cashflows' => $cashflows,
                'timeperiods' => $timeperiods,
                'total_payout' => $totalPayoutAmt,
            ];
        }
        
        $interestRate = $bondDetails['interest_rate'] / 100;
        $faceValue = $bondDetails['face_value'];
        $payout_frequency = strtolower($bondDetails['payout_frequency']);
        $maturityDate = new DateTime($bondDetails['redemption_date']);
        $currentDate = new DateTime();
        
        if(($interestRate > 0) && ($bondDetails != false)) {
            
            if(strcasecmp($payout_frequency, "on maturity") == 0) {
                if ($currentDate >= $maturityDate) {
                    $purchasedDate = new DateTime($purchaseDate);
                    $timeDiff = $purchasedDate->diff($maturityDate)->days;
                    $timeDiffYears = intdiv($timeDiff, 365);
                    $timeDiffRemainingDays = $timeDiff % 365;
                    
                    if($timeDiffYears > 0) {
                        $totalPayoutAmt = $faceValue * $units * pow((1 + $interestRate), $timeDiffYears) - ($faceValue * $units);
                    }
                    if($timeDiffRemainingDays > 0) {
                        $interestPerDay = ($faceValue * $units * $interestRate) / 365;
                        $totalPayoutAmt += $interestPerDay * $timeDiffRemainingDays;
                    }
                    
                    $cashflows[] = $totalPayoutAmt;
                    $timeperiods[] = strtotime($maturityDate->format('Y-m-d'));
                }
                $retStr = "$totalPayoutAmt Total Payout(s) calculated";
            } else {
                // Handle periodic payouts
                return $this->calculatePayouts($invstName, [$payoutDates]);
            }
        }
        
        return [
            'message' => $retStr,
            'cashflows' => $cashflows,
            'timeperiods' => $timeperiods,
            'total_payout' => $totalPayoutAmt,
        ];
    }

    //==========================================================================
    // UTILITY METHODS
    //==========================================================================
    
    public function decrypt($string, $key = 15)
    {
        $result = '';
        $string = base64_decode($string);
        $key = (string)$key;
        
        for($i = 0; $i < strlen($string); $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr($key, ($i % strlen($key)) - 1, 1);
            $char = chr(ord($char) - ord($keychar));
            $result .= $char;
        }
        return $result;
    }
    
    public function updateLog($msg, $remarks = "")
    {
        $dbconn = $this->getConnection();
        $msg = $this->safeEscape($msg);
        $remarks = $this->safeEscape($remarks);
        
        $query = "INSERT INTO `tb_admin_log`(`logmsg`, `remarks`) VALUES ('$msg', '$remarks')";
        return $this->query($query);
    }

    public function getMailbackTargetTable($doc) {
        $map = [
            'MFSD201' => 'tb_transaction_mailback', 
            'WBR10'   => 'tb_transaction_mailback', 
            'WBR49'   => 'tb_mailback_sip_wbr49', 
            'WBR9'    => 'tb_mailback_wbr9'
        ];
        return $map[$doc] ?? 'tb_transaction_mailback';
    }

    public function handleMailbackDuplicates($document_type, $record)
    {
        $target_table = $this->getMailbackTargetTable($document_type);
        $result = ['skipped' => false, 'is_update' => false];

        // Prevent duplicates for WBR9
        if ($target_table === 'tb_mailback_wbr9') {
            $folio = $this->safeEscape($record['FOLIOCHK'] ?? '');
            $product = $this->safeEscape($record['PRODUCT'] ?? '');
            $clos_bal = $this->safeEscape($record['CLOS_BAL'] ?? 0);
            $rupee_bal = $this->safeEscape($record['RUPEE_BAL'] ?? 0);
            
            $check_sql = "SELECT id FROM `$target_table` WHERE `FOLIOCHK` = '$folio' AND `PRODUCT` = '$product' AND `CLOS_BAL` = '$clos_bal' AND `RUPEE_BAL` = '$rupee_bal' LIMIT 1";
            if ($this->selectSingle($check_sql)) {
                $result['skipped'] = true;
                return $result;
            }
        }
        
        // Prevent duplicates for Transaction tables (WBR10 / MFSD201)
        if ($target_table === 'tb_transaction_mailback' && !empty($record['folio_number']) && !empty($record['transaction_number'])) {
            if ($document_type === 'MFSD201') {
                $folio = $this->safeEscape($record['folio_number'] ?? '');
                $trxn_no = $this->safeEscape($record['transaction_number'] ?? '');
                $trxn_id = $this->safeEscape($record['transaction_id'] ?? '');
                
                $check_sql = "SELECT id FROM `$target_table` WHERE `folio_number` = '$folio' AND `transaction_number` = '$trxn_no' AND `transaction_id` = '$trxn_id' LIMIT 1";
            } else {
                $folio = $this->safeEscape($record['folio_number']);
                $trxn_no = $this->safeEscape($record['transaction_number']);
                
                $check_sql = "SELECT id FROM `$target_table` WHERE `folio_number` = '$folio' AND `transaction_number` = '$trxn_no'";
                
                if (isset($record['seq_no']) && $record['seq_no'] !== '') {
                    $seq_no = $this->safeEscape($record['seq_no']);
                    $check_sql .= " AND `seq_no` = '$seq_no'";
                }
                
                $check_sql .= " LIMIT 1";
            }
            
            $existing = $this->selectSingle($check_sql);
            if ($existing) {
                $this->query("DELETE FROM `$target_table` WHERE id = " . $existing['id']);
                $this->query("DELETE FROM `tb_transaction_mailback_extra_data` WHERE mailback_id = " . $existing['id']);
                $result['is_update'] = true;
            }
        }
        
        // Prevent duplicates for SIP tables (WBR49)
        if ($target_table === 'tb_mailback_sip_wbr49' && !empty($record['folio_number']) && !empty($record['auto_trno'])) {
            $folio = $this->safeEscape($record['folio_number']);
            $auto_trno = $this->safeEscape($record['auto_trno']);
            
            $check_sql = "SELECT id FROM `$target_table` WHERE `folio_number` = '$folio' AND `auto_trno` = '$auto_trno' LIMIT 1";
            $existing = $this->selectSingle($check_sql);
            if ($existing) {
                $this->query("DELETE FROM `$target_table` WHERE id = " . $existing['id']);
                $result['is_update'] = true;
            }
        }

        return $result;
    }

    public function insertMailbackRecord($document_type, $record)
    {
        $target_table = $this->getMailbackTargetTable($document_type);
        if ($this->insertRecord($target_table, $record)) {
            if ($target_table === 'tb_transaction_mailback') {
                $mailback_id = $this->getConnection()->insert_id;
                if ($mailback_id > 0) {
                    $record['mailback_id'] = $mailback_id;
                    $this->insertRecord('tb_transaction_mailback_extra_data', $record);
                }
            }
            return true;
        }
        return false;
    }

    public function insertRecord($table, $record)
    {
        $conn = $this->getConnection();
        if (!$conn) return false;
        
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if (empty($table)) return false;
        
        // Get actual columns from the table to prevent "Unknown column" errors
        if (!isset($this->table_columns_cache[$table])) {
            $result = $conn->query("DESCRIBE `$table`");
            if (!$result) {
                error_log("Describe failed for table `$table`: " . $conn->error);
                return false;
            }
            $table_columns = [];
            while($row = $result->fetch_assoc()) {
                $table_columns[] = $row['Field'];
            }
            $result->free();
            $this->table_columns_cache[$table] = $table_columns;
        }
        $table_columns = $this->table_columns_cache[$table];

        // Filter the record to only include columns that exist in the table
        $record = array_intersect_key($record, array_flip($table_columns));

        if (empty($record)) {
            error_log("No valid columns to insert for table `$table` after filtering.");
            return false;
        }

        $cols = array_keys($record);
        $placeholders = implode(", ", array_fill(0, count($cols), "?"));
        $sql = "INSERT INTO `$table` (`" . implode("`, `", $cols) . "`) VALUES ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        $types = "";
        $values = [];
        foreach ($record as $value) {
            if (is_int($value)) {
                $types .= "i";
            } elseif (is_float($value)) {
                $types .= "d";
            } else {
                $types .= "s";
                $value = (string)$value;
            }
            $values[] = $value;
        }
        
        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    public function saveUploadLog($rta, $docType, $fileName, $inserted, $skipped, $panMissing = 0)
    {
        $conn = $this->getConnection();
        $rta       = $this->safeEscape($rta);
        $docType   = $this->safeEscape($docType);
        $fileName  = $this->safeEscape($fileName);
        $inserted  = intval($inserted);
        $skipped   = intval($skipped);
        $panMissing = intval($panMissing);

        // Add pan_missing column if it doesn't exist yet (safe one-time migration)
        $checkCol = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_upload_logs' AND COLUMN_NAME = 'pan_missing'");
        if ($checkCol && $checkCol->num_rows === 0) {
            $conn->query("ALTER TABLE tb_upload_logs ADD COLUMN pan_missing INT NOT NULL DEFAULT 0");
        }

        $sql = "INSERT INTO tb_upload_logs (rta, document_type, original_filename, inserted, skipped, pan_missing)
                VALUES ('$rta', '$docType', '$fileName', $inserted, $skipped, $panMissing)";

        return $this->query($sql);
    }
    
    public function getUploadLogs($limit = null) {
        $sql = "SELECT * FROM tb_upload_logs ORDER BY created_at DESC";
        if ($limit !== null && is_numeric($limit)) {
            $sql .= " LIMIT " . intval($limit);
        }
        return $this->select($sql);
    }

    public function clearUploadLogs() {
        $sql = "TRUNCATE TABLE tb_upload_logs";
        return $this->query($sql);
    }

    /**
     * Return folio numbers and names from tb_transaction_mailback where
     * pan1 is still empty after sync — for display in Recent Uploads warning.
     */
    public function getMissingPanRecords($limit = 100) {
        $sql = "SELECT folio_number, MAX(fund_desc) as fund_desc, MAX(investor_name) as investor_name, MAX(original_filename) as original_filename, MAX(transaction_date) as transaction_date
                FROM tb_transaction_mailback
                WHERE (pan1 IS NULL OR pan1 = '')
                GROUP BY folio_number
                ORDER BY folio_number
                LIMIT " . intval($limit);
        $records = $this->select($sql);
        
        if (empty($records)) {
            return [];
        }
        
        $investorNames = [];
        foreach ($records as $rec) {
            $name = trim($rec['investor_name'] ?? '');
            if ($name !== '') {
                $investorNames[] = $this->safeEscape($name);
            }
        }
        
        if (!empty($investorNames)) {
            $namesList = "'" . implode("','", array_unique($investorNames)) . "'";
            $panSql = "SELECT investor_name, MAX(pan1) as suggested_pan 
                       FROM tb_transaction_mailback 
                       WHERE investor_name IN ($namesList) 
                         AND pan1 IS NOT NULL AND pan1 != '' 
                       GROUP BY investor_name 
                       HAVING COUNT(DISTINCT pan1) = 1";
            $panRecords = $this->select($panSql);
            
            $panMap = [];
            if ($panRecords) {
                foreach ($panRecords as $pr) {
                    $panMap[$pr['investor_name']] = $pr['suggested_pan'];
                }
            }
            
            foreach ($records as &$rec) {
                $name = trim($rec['investor_name'] ?? '');
                $rec['suggested_pan'] = $panMap[$name] ?? '';
            }
        }
        
        return $records;
    }
    
    public function getUserByToken($token)
    {
        $conn = $this->getConnection();
        $token = $this->safeEscape($token);
        
        $sql = "SELECT id, CLIENT_CODE, PRIMARY_HOLDER_NAME FROM vw_client_profiles 
                WHERE AUTH_TOKEN = '$token' OR REMEMBER_TOKEN = '$token' LIMIT 1";
        
        return $this->selectSingle($sql);
    }
    
    public function verifyOldPassword($userId, $oldPassword) {
        $conn = $this->getConnection();

        $query = "SELECT ad_user_pass FROM vw_admin_profiles WHERE ad_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed in verifyOldPassword: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return password_verify($oldPassword, $row['ad_user_pass']);
        }
        
        $stmt->close();
        return false;
    }
    
    public function updateAdminPassword($userId, $newPassword) {
        $conn = $this->getConnection();
        $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $query = "UPDATE vw_admin_profiles SET ad_user_pass = ? WHERE ad_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            error_log("Prepare failed in updateAdminPassword: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("si", $newPasswordHash, $userId);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function getAdminUserById($userId) {
        $conn = $this->getConnection();
        
        $query = "SELECT ad_id, ad_user_name, ad_user_pass, admin_role FROM vw_admin_profiles WHERE ad_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            error_log("Prepare failed in getAdminUserById: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user;
    }

    public function migrateOldUsers() {
        $sql = "SELECT s.*, p.* 
                FROM tb_sign_up s 
                LEFT JOIN tb_user_profile_detail p ON s.us_id = p.us_id";
        $oldUsers = $this->select($sql);
        
        if (!$oldUsers) {
            return false;
        }
        
        $successCount = 0;
        $errorCount = 0;
        $errorDetails = [];
        $existCount = 0;
        $existDetails = [];
        
        foreach ($oldUsers as $user) {
            // Map fields
            $id = (int)$user['us_id'];
            $clientCode = $this->safeEscape($user['iin'] ?? '');
            $primaryHolderName = $this->safeEscape($user['usr_full_name'] ?? '');
            $taxStatus = $this->safeEscape($user['usr_tax_status'] ?? '');
            $gender = $this->safeEscape($user['usr_gender'] ?? '');
            $primaryHolderDob = $this->safeEscape($user['dob'] ?? '');
            $occupationCode = $this->safeEscape($user['usr_occupation'] ?? '');
            $holdingNature = $this->safeEscape($user['holding_status'] ?? '');
            $primaryHolderPan = $this->safeEscape($user['usr_pan_no'] ?? '');
            $accountType = $this->safeEscape($user['usr_acct_type'] ?? '');
            $bankName = $this->safeEscape($user['usr_bank_name'] ?? '');
            $accountNo = $this->safeEscape($user['usr_bank_account_no'] ?? '');
            $bankHolderName = $this->safeEscape($user['usr_accout_holder_name'] ?? '');
            $ifscCode = $this->safeEscape($user['usr_bank_ifsc_code'] ?? '');
            $bankBranch = $this->safeEscape($user['usr_bank_branch_name'] ?? '');
            $address = $this->safeEscape($user['usr_address'] ?? '');
            $city = $this->safeEscape($user['usr_city'] ?? '');
            $state = $this->safeEscape($user['usr_state'] ?? '');
            $pincode = $this->safeEscape($user['usr_pin_code'] ?? '');
            $country = $this->safeEscape($user['usr_nationality'] ?? '');
            $email = $this->safeEscape($user['us_email_address'] ?? '');
            $mobile = $this->safeEscape($user['us_mobile_no'] ?? '');
            
            $nomineeName = $this->safeEscape($user['usr_nominee_name'] ?? '');
            $nomineeRelation = $this->safeEscape($user['usr_nominee_relation'] ?? '');
            $nomineeDob = $this->safeEscape($user['usr_nominee_dob'] ?? '');
            $nomineeGuardian = $this->safeEscape($user['usr_nominee_gaurd_name'] ?? '');
            $guardian = $this->safeEscape($user['usr_nominee_gaurd_name'] ?? '');
            
            $passwordHash = $this->safeEscape($user['us_password'] ?? '');
            $resetToken = $this->safeEscape($user['us_reset_token'] ?? '');
            $activationStatus = (int)($user['activation_status'] ?? 0);
            $riskCategoryId = (int)($user['risk_category_id'] ?? 0);
            $assignedSubadminId = (int)($user['assigned_subadmin_id'] ?? 0);
            
            // Handle dates properly
            $createdAt = !empty($user['last_modified']) ? "'" . $this->safeEscape($user['last_modified']) . "'" : "NOW()";
            if(empty($primaryHolderDob) || $primaryHolderDob == '0000-00-00') { $primaryHolderDob = 'NULL'; } else { $primaryHolderDob = "'$primaryHolderDob'"; }
            if(empty($nomineeDob) || $nomineeDob == '0000-00-00') { $nomineeDob = 'NULL'; } else { $nomineeDob = "'$nomineeDob'"; }
            
            // Check if user already exists
            $checkSql = "SELECT id FROM vw_client_profiles WHERE id = $id";
            $exists = $this->selectSingle($checkSql);
            
            if (!$exists) {
                $insertSql = "INSERT INTO vw_client_profiles (
                    id, CLIENT_CODE, PRIMARY_HOLDER_NAME, TAX_STATUS, GENDER, PRIMARY_HOLDER_DOB, OCCUPATION_CODE, HOLDING_NATURE, GUARDIAN, 
                    PRIMARY_HOLDER_PAN, BD_BANK_CODE_1, ACCOUNT_TYPE_1, ACCOUNT_NO_1, BANK_HOLDER_NAME_1, IFSC_CODE_1, BANK_BRANCH_1, 
                    ADDRESS_1, CITY, STATE, PINCODE, COUNTRY, EMAIL, INDIAN_MOBILE_NO, NOMINEE_1_NAME, NOMINEE_1_RELATIONSHIP, 
                    NOMINEE_1_DOB, NOMINEE_1_GUARDIAN, PASSWORD_HASH, RESET_TOKEN, UCC_ACTIVATION, RISK_CATEGORY_ID, 
                    assigned_subadmin_id, CREATED_AT, UPDATED_AT
                ) VALUES (
                    $id, '$clientCode', '$primaryHolderName', '$taxStatus', '$gender', $primaryHolderDob, '$occupationCode', '$holdingNature', '$guardian', 
                    '$primaryHolderPan', '$bankName','$accountType', '$accountNo', '$bankHolderName', '$ifscCode', '$bankBranch', 
                    '$address', '$city', '$state', '$pincode', '$country', '$email', '$mobile', '$nomineeName', '$nomineeRelation', 
                    $nomineeDob, '$nomineeGuardian', '$passwordHash', '$resetToken', $activationStatus, $riskCategoryId, 
                    $assignedSubadminId, $createdAt, NOW()
                )";
                
                if ($this->query($insertSql)) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errorMsg = $this->getError();
                    error_log("Failed to insert user $id: " . $errorMsg);
                    $errorDetails[] = "User ID $id: " . $errorMsg;
                }
            } else {
                $existCount++;
                $existDetails[] = "User ID $id (CLIENT_CODE: " . ($clientCode ?: 'N/A') . "): Already exists in the users table.";
            }
        }
        
        return [
            'total' => count($oldUsers),
            'success' => $successCount,
            'errors' => $errorCount,
            'exists' => $existCount,
            'error_details' => $errorDetails,
            'exist_details' => $existDetails
        ];
    }

    public function batchUpsertSchemes(array $batch): array
    {
        if (empty($batch)) {
            return ['inserted' => 0, 'updated' => 0];
        }
 
        $dbconn = $this->getConnection();
 
        // ── STEP 1: ensure UNIQUE KEY exists on scheme_code ──────────────
        $idxCheck = $dbconn->query(
            "SELECT COUNT(*) as cnt
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'mtb_scheme_master'
                AND INDEX_NAME   = 'uq_scheme_code'"
        );
        if ($idxCheck) {
            $idxRow = $idxCheck->fetch_assoc();
            if ((int)($idxRow['cnt'] ?? 0) === 0) {
                $dbconn->query(
                    "ALTER TABLE mtb_scheme_master
                     ADD UNIQUE KEY uq_scheme_code (scheme_code)"
                );
                //error_log('batchUpsertSchemes: created missing UNIQUE KEY uq_scheme_code');
            }
        }
 
        // ── STEP 2: column list ───────────────────────────────────────────
        $columns = [
            'unique_sr_no', 'scheme_code', 'rta_scheme_code', 'amc_scheme_code',
            'isin', 'amc_code', 'scheme_type', 'plan_type', 'scheme_name',
            'purchase_allowed', 'purchase_transaction_mode',
            'new_purchase_min_amount', 'additional_purchase_min_amount',
            'additional_purchase_max_amount', 'purchase_amount_multiplier',
            'purchase_cutoff_time', 'redemption_allowed',
            'redemption_transaction_mode', 'redemption_min_qty',
            'redemption_qty_multiplier', 'redemption_max_qty',
            'redemption_min_amount', 'redemption_max_amount',
            'redemption_amount_multiplier', 'redemption_cutoff_time',
            'rta_agent_code', 'amc_active_flag', 'div_reinvest_flag',
            'sip_allowed', 'stp_enabled', 'swp_enabled', 'switch_allowed',
            'settlement_type', 'amc_ind', 'face_value',
            'scheme_start_date', 'maturity_date',
            'exit_load_flag', 'exit_load',
            'lock_in_period_flag', 'lock_in_period',
            'channel_partner_code', 'reopening_date',
            'open_close_ended_scheme', 'fund_cat', 'cat_tag',
        ];
 
        $colCount   = count($columns);
        $colListSql = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
 
        // Every column updated on duplicate EXCEPT the unique key itself
        $updateParts = [];
        foreach ($columns as $col) {
            if ($col === 'scheme_code') continue;
            $updateParts[] = "`$col` = VALUES(`$col`)";
        }
        // Uncomment if your table has an updated_at column:
        // $updateParts[] = "`updated_at` = NOW()";
        $onDupSql = implode(', ', $updateParts);
 
        $rowPh           = '(' . implode(',', array_fill(0, $colCount, '?')) . ')';
        $allPlaceholders = implode(',', array_fill(0, count($batch), $rowPh));
 
        $sql = "INSERT INTO mtb_scheme_master ($colListSql)
                VALUES $allPlaceholders
                ON DUPLICATE KEY UPDATE $onDupSql";
 
        // ── STEP 3: COUNT existing rows for this batch BEFORE upsert ─────
        // This is the only reliable approach across all MySQL versions.
        // $dbconn->info = null for prepared stmts (doesn't work on WAMP).
        // affected_rows = 0 for no-op rows (data unchanged) — also wrong.
        // Pre-counting is exact and adds only one lightweight COUNT query.
        $batchCodes   = array_column($batch, 'scheme_code');
        $batchSize    = count($batchCodes);
        $cphList      = implode(',', array_fill(0, $batchSize, '?'));
        $cTypes       = str_repeat('s', $batchSize);
 
        $existsBefore = 0;
        $cStmt = $dbconn->prepare(
            "SELECT COUNT(*) as cnt FROM mtb_scheme_master
              WHERE scheme_code IN ($cphList)"
        );
        if ($cStmt) {
            $cStmt->bind_param($cTypes, ...$batchCodes);
            $cStmt->execute();
            $cResult      = $cStmt->get_result();
            $existsBefore = $cResult ? (int)$cResult->fetch_assoc()['cnt'] : 0;
            $cStmt->close();
        }
 
        // ── STEP 4: build params — RAW values, NO safeEscape() ───────────
        // Prepared statements escape internally. safeEscape() before
        // bind_param() causes double-escaping → values never match on
        // subsequent runs → ON DUPLICATE KEY never fires → always INSERT.
        $params   = [];
        $types    = '';
        $dateCols = ['scheme_start_date', 'maturity_date', 'reopening_date'];
 
        foreach ($batch as $row) {
            foreach ($columns as $col) {
                $val = $row[$col] ?? null;
                if ($val === null || $val === '') {
                    $params[] = in_array($col, $dateCols, true) ? null : '';
                } else {
                    $params[] = (string)$val;   // RAW — no safeEscape()
                }
                $types .= 's';
            }
        }
 
        // ── STEP 5: execute the batch upsert ─────────────────────────────
        $stmt = $dbconn->prepare($sql);
        if (!$stmt) {
            throw new Exception(
                'batchUpsertSchemes prepare failed: ' . $dbconn->error .
                ' | SQL snippet: ' . substr($sql, 0, 200)
            );
        }
 
        $stmt->bind_param($types, ...$params);
 
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('batchUpsertSchemes execute failed: ' . $err);
        }
        $stmt->close();
 
        // ── STEP 6: calculate counts from pre-count ───────────────────────
        // existsBefore = rows that already existed  → UPDATED (or no-op)
        // batchSize - existsBefore = brand new rows → INSERTED
        $updated  = $existsBefore;
        $inserted = $batchSize - $existsBefore;
 
        return ['inserted' => $inserted, 'updated' => $updated];
    }
 
    // ── Run once if you have duplicate rows from earlier bad syncs ────────
    public function deduplicateSchemeMaster(): int
    {
        $dbconn = $this->getConnection();
        $dbconn->query(
            "DELETE t1 FROM mtb_scheme_master t1
             INNER JOIN mtb_scheme_master t2
             WHERE t1.id > t2.id
               AND t1.scheme_code = t2.scheme_code"
        );
        return (int)$dbconn->affected_rows;
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
				$this->smtp['from_name'] 
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

		} catch (\Exception $e) {
			// error_log('SMTP ERROR: ' . $e->getMessage() . ' | PHPMailer Info: ' . $mail->ErrorInfo);
			return false;
		}
	}
	
	//==========================================================================
    // CITY PINCODE MANAGEMENT METHODS (UPDATED FOR YOUR TABLE STRUCTURES)
    //==========================================================================
    
    /**
     * Get all cities with pagination and search
     * Joins with mtb_state and mtb_country tables
     */
    public function getCitiesList($search = '', $limit = 50, $offset = 0) {
        $conn = $this->getConnection();
        
        // Query with joins to get state and country names
        $sql = "SELECT 
                    c.sl_no as id, 
                    c.city as city_name, 
                    c.state_code, 
                    c.country_code, 
                    c.pincode,
                    s.state_name,
                    cnt.country_name
                FROM mtb_city_pincode c
                LEFT JOIN mtb_state s ON c.state_code = s.state_code
                LEFT JOIN mtb_country cnt ON c.country_code = cnt.country_code
                WHERE 1=1";
        
        $countSql = "SELECT COUNT(*) as total FROM mtb_city_pincode WHERE 1=1";
        
        if(!empty($search)) {
            $searchTerm = "%$search%";
            $sql .= " AND (c.city LIKE ? OR c.pincode LIKE ? OR c.state_code LIKE ? OR s.state_name LIKE ? OR cnt.country_name LIKE ?)";
            $countSql .= " AND (city LIKE ? OR pincode LIKE ? OR state_code LIKE ?)";
            
            $sql .= " ORDER BY c.city ASC LIMIT ? OFFSET ?";
            
            $stmt = $conn->prepare($sql);
            if($stmt) {
                $stmt->bind_param("sssssii", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                $cities = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                // Get total count
                $countStmt = $conn->prepare($countSql);
                if($countStmt) {
                    $countStmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    $total = $countResult->fetch_assoc()['total'];
                    $countStmt->close();
                } else {
                    $total = 0;
                }
            } else {
                $cities = [];
                $total = 0;
            }
        } else {
            $sql .= " ORDER BY c.city ASC LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($sql);
            if($stmt) {
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                $cities = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                $countResult = $conn->query($countSql);
                $total = $countResult ? $countResult->fetch_assoc()['total'] : 0;
            } else {
                $cities = [];
                $total = 0;
            }
        }
        
        return ['data' => $cities, 'total' => $total];
    }
    
    /**
     * Save city (insert or update)
     */
    public function saveCity($id, $city_name, $state_code, $country_code, $pincode) {
        $conn = $this->getConnection();
        
        // Format city name
        $city_name = ucwords(strtolower($city_name));
        $state_code = strtoupper($state_code);
        $country_code = strtoupper($country_code);
        
        if($id > 0) {
            // Update existing record
            $sql = "UPDATE mtb_city_pincode SET 
                    city = ?, 
                    state_code = ?, 
                    country_code = ?, 
                    pincode = ?
                    WHERE sl_no = ?";
            $stmt = $conn->prepare($sql);
            if($stmt) {
                $stmt->bind_param("ssssi", $city_name, $state_code, $country_code, $pincode, $id);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
        } else {
            // Insert new record
            $sql = "INSERT INTO mtb_city_pincode (city, state_code, country_code, pincode) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if($stmt) {
                $stmt->bind_param("ssss", $city_name, $state_code, $country_code, $pincode);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
        }
        return false;
    }
    
    /**
     * Get single city by ID
     */
    public function getCityById($id) {
        $conn = $this->getConnection();
        $sql = "SELECT 
                    c.sl_no as id, 
                    c.city as city_name, 
                    c.state_code, 
                    c.country_code, 
                    c.pincode,
                    s.state_name,
                    cnt.country_name
                FROM mtb_city_pincode c
                LEFT JOIN mtb_state s ON c.state_code = s.state_code
                LEFT JOIN mtb_country cnt ON c.country_code = cnt.country_code
                WHERE c.sl_no = ?";
        $stmt = $conn->prepare($sql);
        if($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $city = $result->fetch_assoc();
            $stmt->close();
            return $city;
        }
        return false;
    }
    
    /**
     * Delete city by ID
     */
    public function deleteCity($id) {
        $conn = $this->getConnection();
        $sql = "DELETE FROM mtb_city_pincode WHERE sl_no = ?";
        $stmt = $conn->prepare($sql);
        if($stmt) {
            $stmt->bind_param("i", $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
    
    /**
     * Get all states for dropdown
     */
    public function getAllStates() {
        $conn = $this->getConnection();
        $sql = "SELECT state_code, state_name FROM mtb_state ORDER BY state_name";
        $result = $conn->query($sql);
        
        $states = [];
        if($result) {
            while($row = $result->fetch_assoc()) {
                $states[] = $row;
            }
        }
        return $states;
    }
    
    /**
     * Get all countries for dropdown
     */
    public function getAllCountries() {
        $conn = $this->getConnection();
        $sql = "SELECT country_code, country_name FROM mtb_country ORDER BY country_name";
        $result = $conn->query($sql);
        
        $countries = [];
        if($result) {
            while($row = $result->fetch_assoc()) {
                $countries[] = $row;
            }
        }
        return $countries;
    }
    
    /**
     * Bulk upload cities from file data
     */
    public function bulkUploadCities($data, $header) {
        $conn = $this->getConnection();
        
        // Find column indices
        $city_idx = array_search('CITY', $header);
        $state_idx = array_search('STATE_CODE', $header);
        $country_idx = array_search('COUNTRY_CODE', $header);
        $pincode_idx = array_search('PINCODE', $header);
        
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        
        $conn->begin_transaction();
        
        try {
            // First, verify state codes exist
            $validStates = [];
            $stateCheck = $conn->query("SELECT state_code FROM mtb_state");
            if ($stateCheck) {
                while($row = $stateCheck->fetch_assoc()) {
                    $validStates[$row['state_code']] = true;
                }
            }
            
            // Verify country codes exist
            $validCountries = [];
            $countryCheck = $conn->query("SELECT country_code FROM mtb_country");
            if ($countryCheck) {
                while($row = $countryCheck->fetch_assoc()) {
                    $validCountries[$row['country_code']] = true;
                }
            }
            
            $checkStmt = $conn->prepare("SELECT sl_no FROM mtb_city_pincode WHERE city = ? AND state_code = ? AND pincode = ? LIMIT 1");
            $insertStmt = $conn->prepare("INSERT INTO mtb_city_pincode (city, state_code, country_code, pincode) VALUES (?, ?, ?, ?)");
            
            for($i = 1; $i < count($data); $i++) {
                $row = $data[$i];
                if(count($row) < 4) {
                    $skipped++;
                    continue;
                }
                
                $city = trim($row[$city_idx] ?? '');
                $state = trim($row[$state_idx] ?? '');
                $country = trim($row[$country_idx] ?? '');
                $pincode = trim($row[$pincode_idx] ?? '');
                
                // Validate required fields
                if(empty($city) || empty($state) || empty($country) || empty($pincode)) {
                    $skipped++;
                    continue;
                }
                
                // Validate pincode format (6 digits)
                if(!preg_match('/^\d{6}$/', $pincode)) {
                    $skipped++;
                    continue;
                }
                
                // Validate state code exists
                if(!isset($validStates[$state])) {
                    $skipped++;
                    $errors[] = "State code '$state' not found in mtb_state table";
                    continue;
                }
                
                // Validate country code exists
                if(!isset($validCountries[$country])) {
                    $skipped++;
                    $errors[] = "Country code '$country' not found in mtb_country table";
                    continue;
                }
                
                $city = ucwords(strtolower($city));
                $state = strtoupper($state);
                $country = strtoupper($country);
                
                // Check if exists
                if($checkStmt && $insertStmt) {
                    $checkStmt->bind_param("sss", $city, $state, $pincode);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    
                    if($checkResult->num_rows == 0) {
                        $insertStmt->bind_param("ssss", $city, $state, $country, $pincode);
                        if($insertStmt->execute()) {
                            $inserted++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        $skipped++;
                    }
                }
            }
            
            if($checkStmt) $checkStmt->close();
            if($insertStmt) $insertStmt->close();
            
            $conn->commit();
            
            $message = "Upload complete: {$inserted} inserted, {$skipped} skipped";
            if(!empty($errors)) {
                $message .= ". " . implode(", ", array_slice($errors, 0, 5));
                if(count($errors) > 5) $message .= "...";
            }
            
            return ['inserted' => $inserted, 'skipped' => $skipped, 'message' => $message];
        } catch(Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
	
	// Add these methods to your MySQLDB class (before the closing brace)
//==========================================================================
    // NSE CREDENTIALS MANAGEMENT METHODS
    //==========================================================================

    /**
     * Get all NSE credentials
     */
    public function getAllNSECredentials() {
        try {
            $conn = $this->getConnection();
            $sql = "CALL sp_nse_get_all()";
            $result = $conn->query($sql);
            
            if (!$result) {
                error_log("Failed to fetch NSE credentials: " . $conn->error);
                return [];
            }
            
            $credentials = [];
            while ($row = $result->fetch_assoc()) {
                // Convert status to integer
                $row['status'] = (int)$row['status'];
                $credentials[] = $row;
            }
            return $credentials;
        } catch (Exception $e) {
            error_log("Error in getAllNSECredentials: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single NSE credential by ID
     */
    public function getNSECredentialById($id) {
        try {
            $id = intval($id);
            $conn = $this->getConnection();
            
            $sql = "CALL sp_nse_get_by_id(?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Prepare failed in getNSECredentialById: " . $conn->error);
                return false;
            }
            
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $stmt->close();
                return false;
            }
            
            $credential = $result->fetch_assoc();
            $stmt->close();
            
            // Convert status to integer
            $credential['status'] = (int)$credential['status'];
            
            return $credential;
        } catch (Exception $e) {
            error_log("Error in getNSECredentialById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add new NSE credential
     */
    public function addNSECredential($data) {
        $conn = $this->getConnection();
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Convert status to integer (0 or 1)
            $status = 0; // default inactive
            if (isset($data['status'])) {
                if ($data['status'] === 'active' || $data['status'] === 1 || $data['status'] === '1') {
                    $status = 1;
                } else {
                    $status = 0;
                }
            }
            
            // If status is active (1), set all existing records to inactive first
            if ($status == 1) {
                $deactivateSql = "CALL sp_nse_deactivate_all()";
                if (!$conn->query($deactivateSql)) {
                    throw new Exception("Failed to deactivate existing active credentials");
                }
            }
            
            $sql = "CALL sp_nse_insert(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $name = $data['name'];
            $tm_code = $data['tm_code'];
            $secret_key = $data['secret_key'];
            $member_key = $data['member_key'] ?? '';
            $login_user_id = $data['login_user_id'] ?? '';
            $arn_number = $data['arn_number'] ?? '';
            $euin_number = $data['euin_number'] ?? '';
            $environment = $data['environment'] ?? 'Live';
            $api_url = $data['api_url'] ?? '';
            $notes = $data['notes'] ?? '';
            
            $stmt->bind_param("sssssssssis", $name, $tm_code, $secret_key, $member_key, 
                              $login_user_id, $arn_number, $euin_number, $environment, $api_url, $status, $notes);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $id = $conn->insert_id;
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            return ['success' => true, 'id' => $id, 'message' => 'Credentials added successfully'];
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error adding NSE credentials: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add credentials: ' . $e->getMessage()];
        }
    }

    /**
     * Update NSE credential
     */
    public function updateNSECredential($id, $data) {
        $id = intval($id);
        $conn = $this->getConnection();
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Convert status to integer (0 or 1)
            $status = 0;
            if (isset($data['status'])) {
                if ($data['status'] === 'active' || $data['status'] === 1 || $data['status'] === '1') {
                    $status = 1;
                } else {
                    $status = 0;
                }
            }
            
            // If status is active (1), set all other records to inactive
            if ($status == 1) {
                $deactivateSql = "CALL sp_nse_deactivate_others(?)";
                $deactivateStmt = $conn->prepare($deactivateSql);
                if (!$deactivateStmt) {
                    throw new Exception("Prepare failed for deactivation: " . $conn->error);
                }
                $deactivateStmt->bind_param("i", $id);
                if (!$deactivateStmt->execute()) {
                    throw new Exception("Failed to deactivate other active credentials");
                }
                $deactivateStmt->close();
            }
            
            // Replace dynamic update with Stored Procedure call
            $sql = "CALL sp_nse_update(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $secret_key_param = empty($data['secret_key']) ? null : $data['secret_key'];
            $member_key = $data['member_key'] ?? '';
            $login_user_id = $data['login_user_id'] ?? '';
            $arn_number = $data['arn_number'] ?? '';
            $euin_number = $data['euin_number'] ?? '';
            $environment = $data['environment'] ?? 'Live';
            $api_url = $data['api_url'] ?? '';
            $notes = $data['notes'] ?? '';

            $stmt->bind_param("issssssssiss", 
                $id, 
                $data['name'], 
                $data['tm_code'], 
                $member_key, 
                $login_user_id, 
                $arn_number, 
                $euin_number, 
                $environment, 
                $api_url, 
                $status, 
                $notes, 
                $secret_key_param
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            return ['success' => true, 'message' => 'Credentials updated successfully', 'affected_rows' => $affectedRows];
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error updating NSE credentials: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update credentials: ' . $e->getMessage()];
        }
    }

    /**
     * Activate a specific NSE credential and deactivate all others
     * Database uses: 0 = inactive, 1 = active
     */
    public function activateNSECredential($id) {
        $id = intval($id);
        $conn = $this->getConnection();
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First, verify the credential exists
            $checkSql = "CALL sp_nse_get_by_id(?)";
            $checkStmt = $conn->prepare($checkSql);
            if (!$checkStmt) {
                throw new Exception("Prepare failed in check: " . $conn->error);
            }
            
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows == 0) {
                $checkStmt->close();
                throw new Exception("Credential with ID $id not found");
            }
            $checkStmt->close();
            
            // Deactivate all credentials (set status to 0)
            $deactivateSql = "CALL sp_nse_deactivate_all()";
            if (!$conn->query($deactivateSql)) {
                throw new Exception("Failed to deactivate all credentials: " . $conn->error);
            }
            
            // Activate the selected credential (set status to 1)
            $activateSql = "CALL sp_nse_update_status(?, 1)";
            $activateStmt = $conn->prepare($activateSql);
            if (!$activateStmt) {
                throw new Exception("Prepare failed for activation: " . $conn->error);
            }
            
            $activateStmt->bind_param("i", $id);
            if (!$activateStmt->execute()) {
                throw new Exception("Failed to activate credential: " . $activateStmt->error);
            }
            
            $affectedRows = $activateStmt->affected_rows;
            $activateStmt->close();
            
            if ($affectedRows == 0) {
                throw new Exception("Failed to activate credential with ID $id");
            }
            
            // Commit transaction
            $conn->commit();
            
            //error_log("Credential ID $id activated successfully");
            
            return ['success' => true, 'message' => 'Credential activated successfully'];
            
        } catch (\Exception $e) {
            $conn->rollback();
            error_log("Error activating NSE credentials: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delete NSE credential
     */
    public function deleteNSECredential($id) {
        $id = intval($id);
        $conn = $this->getConnection();
        
        // Check if trying to delete active credential
        $checkSql = "CALL sp_nse_get_by_id(?)";
        $checkStmt = $conn->prepare($checkSql);
        if ($checkStmt) {
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $credential = $result->fetch_assoc();
            $checkStmt->close();
            
            if ($credential && $credential['status'] == 1) {
                return ['success' => false, 'message' => 'Cannot delete active credentials. Please deactivate them first.'];
            }
        }
        
        $sql = "CALL sp_nse_delete(?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Prepare failed in deleteNSECredential: " . $conn->error);
            return ['success' => false, 'message' => 'Database error'];
        }
        
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            return ['success' => true, 'message' => 'Credentials deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete credentials'];
        }
    }

    /**
     * Get active NSE credentials
     */
    public function getActiveNSECredentials() {
        $conn = $this->getConnection();
        $sql = "CALL sp_nse_get_active_api_credentials()";
        
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $credential = $result->fetch_assoc();
            // Convert status to string for consistency
            $credential['status'] = 'active';
            return $credential;
        }
        return false;
    }

    /**
     * Get ARN number from active NSE credentials
     */
    public function getAdvisorDetails(): ?array {
        $conn = $this->getConnection();
        $sql = "CALL sp_nse_get_active_arn()";

        $result = $conn->query($sql);
        $data = null;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $data = [
                'name' => $row['name'] ?? '',
                'arn_number' => $row['arn_number'] ?? ''
            ];
            $result->free();
        }

        // Clean up any remaining result sets to prevent "Commands out of sync"
        while ($conn->more_results()) {
            $conn->next_result();
            if ($res = $conn->use_result()) {
                $res->free();
            }
        }

        return $data;
    }
public function saveNseSipDataToTable($reportData) {
    $conn = $this->getConnection();
    
    if (empty($reportData) || !is_array($reportData)) {
        return ['success' => false, 'message' => 'No data provided', 'inserted' => 0, 'updated' => 0];
    }
    
    $inserted = 0;
    $updated = 0;
    $errors = array();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Ensure unique key exists
        $checkKey = $conn->query("SHOW INDEX FROM tb_sip_data_by_nse WHERE Key_name = 'uq_xsip_registration_no'");
        if ($checkKey && $checkKey->num_rows == 0) {
            $conn->query("ALTER TABLE tb_sip_data_by_nse ADD UNIQUE KEY uq_xsip_registration_no (xsip_registration_no)");
        }
        
        // Column list without raw_response
        $columns = [
            'registration_type', 'status', 'member_code', 'client_code', 'client_name', 'pg_bank_ref_no',
            'xsip_registration_no', 'xsip_registration_date', 'amc_name', 'rta_scheme_code',
            'scheme_name', 'frequency_type', 'start_date', 'end_date', 'installments_amount',
            'brokerage', 'entry_by', 'nse_mandate_id', 'dpc_flag', 'dp_trans', 'sub_broker',
            'euin_no', 'euin_declaration', 'first_order_today', 'folio_number', 'remarks',
            'sub_broker_arn', 'no_of_installments', 'exchange_remark', 'health_declaration_flag',
            'nominee_dob', 'disclaimer_flag', 'internal_ref_no', 'primary_holder_email',
            'primary_holder_mobile', 'second_holder_email', 'second_holder_mobile',
            'third_holder_email', 'third_holder_mobile', 'member_unique_id',
            'stp_registration_no', 'from_amc_name', 'to_amc_name', 'from_scheme_name', 'to_scheme_name',
            'stp_registration_date', 'stp_start_date', 'stp_end_date', 'trxn_mode', 'transfer_amount',
            'transfer_units', 'no_of_transfers', 'first_order_todays_flag', 'sub_br_code',
            'sub_br_arn_code', 'buy_sell_type', 'from_nse_scheme_code', 'to_nse_scheme_code',
            'swp_registration_no', 'swp_registration_date', 'swp_start_date', 'swp_end_date',
            'withdrawl_amount', 'withdrawal_units', 'no_of_withdrawls', 'bank_account_no'
        ];
        
        $columnCount = count($columns);
        $placeholders = implode(', ', array_fill(0, $columnCount, '?'));
        $columnList = implode(', ', $columns);
        
        // Build the INSERT query dynamically
        $sql = "INSERT INTO tb_sip_data_by_nse ($columnList) VALUES ($placeholders)
        ON DUPLICATE KEY UPDATE ";
        
        // Add update clauses for each column (except xsip_registration_no which is the unique key)
        $updates = [];
        foreach ($columns as $col) {
            if ($col != 'xsip_registration_no') {
                $updates[] = "$col = VALUES($col)";
            }
        }
        $sql .= implode(', ', $updates);
        $sql .= ", updated_at = NOW()";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $conn->error);
        }
        
        foreach ($reportData as $index => $row) {
            $convertDate = function($dateStr) {
                if (empty($dateStr) || trim($dateStr) == '' || trim($dateStr) == ' ') {
                    return null;
                }
                $date = DateTime::createFromFormat('d M Y', trim($dateStr));
                if (!$date) {
                    $date = DateTime::createFromFormat('d-M-Y', trim($dateStr));
                }
                if (!$date) {
                    $date = DateTime::createFromFormat('Y-m-d', trim($dateStr));
                }
                return $date ? $date->format('Y-m-d') : null;
            };
            
            $xsip_registration_no = $row['xsip_registration_no'] ?? '';
            $registration_type = 'SIP';
            
            // Fallback for STP and SWP records to ensure we have a unique identifier for the UPSERT
            if (empty($xsip_registration_no)) {
                if (!empty($row['swp_registration_no'])) {
                    $xsip_registration_no = $row['swp_registration_no'];
                    $registration_type = 'SWP';
                } elseif (!empty($row['stp_registration_no'])) {
                    $xsip_registration_no = $row['stp_registration_no'];
                    $registration_type = 'STP';
                }
            }
            
            if (empty($xsip_registration_no)) {
                $errors[] = "Record $index: Skipped - missing registration number";
                continue;
            }
            
            $folio_number = isset($row['folio_number']) && $row['folio_number'] != ' ' ? $row['folio_number'] : null;
            if ($folio_number !== null) {
                $parts = explode('/', $folio_number);
                $folio_number = trim($parts[0]);
            }
            
            // Prepare all values without raw_response
            $values = [
                $registration_type,
                isset($row['status']) && $row['status'] != ' ' ? $row['status'] : null,
                $row['member_code'] ?? null,
                $row['client_code'] ?? null,
                $row['client_name'] ?? null,
                isset($row['pg_bank_ref_no']) && $row['pg_bank_ref_no'] != ' ' ? $row['pg_bank_ref_no'] : null,
                $xsip_registration_no,
                $convertDate($row['xsip_registration_date'] ?? ''),
                $row['amc_name'] ?? null,
                $row['rta_scheme_code'] ?? null,
                $row['scheme_name'] ?? null,
                $row['frequency_type'] ?? null,
                $convertDate($row['start_date'] ?? ''),
                $convertDate($row['end_date'] ?? ''),
                isset($row['installments_amount']) && $row['installments_amount'] != '' ? floatval($row['installments_amount']) : null,
                isset($row['brokerage']) && $row['brokerage'] != '' ? floatval($row['brokerage']) : null,
                $row['entry_by'] ?? null,
                isset($row['nse_mandate_id']) && $row['nse_mandate_id'] != ' ' ? $row['nse_mandate_id'] : null,
                isset($row['dpc_flag']) && $row['dpc_flag'] != ' ' ? $row['dpc_flag'] : null,
                isset($row['dp_trans']) && $row['dp_trans'] != ' ' ? $row['dp_trans'] : null,
                isset($row['sub_broker']) && $row['sub_broker'] != ' ' ? $row['sub_broker'] : null,
                isset($row['euin_no']) && $row['euin_no'] != ' ' ? $row['euin_no'] : null,
                isset($row['euin_declaration']) && $row['euin_declaration'] != ' ' ? $row['euin_declaration'] : null,
                isset($row['first_order_today']) && $row['first_order_today'] != ' ' ? $row['first_order_today'] : null,
                $folio_number,
                isset($row['remarks']) && $row['remarks'] != ' ' ? $row['remarks'] : null,
                isset($row['sub_broker_arn']) && $row['sub_broker_arn'] != ' ' ? $row['sub_broker_arn'] : null,
                isset($row['no_of_installments']) && $row['no_of_installments'] != '' ? intval($row['no_of_installments']) : null,
                isset($row['exchange_remark']) && $row['exchange_remark'] != ' ' ? $row['exchange_remark'] : null,
                isset($row['health_declaration_flag']) && $row['health_declaration_flag'] != ' ' ? $row['health_declaration_flag'] : null,
                $convertDate($row['nominee_dob'] ?? ''),
                isset($row['disclaimer_flag']) && $row['disclaimer_flag'] != ' ' ? $row['disclaimer_flag'] : null,
                isset($row['internal_ref_no']) && $row['internal_ref_no'] != ' ' ? $row['internal_ref_no'] : null,
                isset($row['primary_holder_email']) && $row['primary_holder_email'] != ' ' ? $row['primary_holder_email'] : null,
                isset($row['primary_holder_mobile']) && $row['primary_holder_mobile'] != ' ' ? $row['primary_holder_mobile'] : null,
                isset($row['second_holder_email']) && $row['second_holder_email'] != ' ' ? $row['second_holder_email'] : null,
                isset($row['second_holder_mobile']) && $row['second_holder_mobile'] != ' ' ? $row['second_holder_mobile'] : null,
                isset($row['third_holder_email']) && $row['third_holder_email'] != ' ' ? $row['third_holder_email'] : null,
                isset($row['third_holder_mobile']) && $row['third_holder_mobile'] != ' ' ? $row['third_holder_mobile'] : null,
                $row['member_unique_id'] ?? null,
                isset($row['stp_registration_no']) && $row['stp_registration_no'] != ' ' ? $row['stp_registration_no'] : null,
                isset($row['from_amc_name']) && $row['from_amc_name'] != ' ' ? $row['from_amc_name'] : null,
                isset($row['to_amc_name']) && $row['to_amc_name'] != ' ' ? $row['to_amc_name'] : null,
                isset($row['from_scheme_name']) && $row['from_scheme_name'] != ' ' ? $row['from_scheme_name'] : null,
                isset($row['to_scheme_name']) && $row['to_scheme_name'] != ' ' ? $row['to_scheme_name'] : null,
                $convertDate($row['stp_registration_date'] ?? ''),
                $convertDate($row['stp_start_date'] ?? ''),
                $convertDate($row['stp_end_date'] ?? ''),
                isset($row['trxn_mode']) && $row['trxn_mode'] != ' ' ? $row['trxn_mode'] : null,
                isset($row['transfer_amount']) && $row['transfer_amount'] != '' ? floatval($row['transfer_amount']) : null,
                isset($row['transfer_units']) && $row['transfer_units'] != '' ? floatval($row['transfer_units']) : null,
                isset($row['no_of_transfers']) && $row['no_of_transfers'] != '' ? intval($row['no_of_transfers']) : null,
                isset($row['first_order_todays_flag']) && $row['first_order_todays_flag'] != ' ' ? $row['first_order_todays_flag'] : null,
                isset($row['sub_br_code']) && $row['sub_br_code'] != ' ' ? $row['sub_br_code'] : null,
                isset($row['sub_br_arn_code']) && $row['sub_br_arn_code'] != ' ' ? $row['sub_br_arn_code'] : null,
                isset($row['buy_sell_type']) && $row['buy_sell_type'] != ' ' ? $row['buy_sell_type'] : null,
                isset($row['from_nse_scheme_code']) && $row['from_nse_scheme_code'] != ' ' ? $row['from_nse_scheme_code'] : null,
                isset($row['to_nse_scheme_code']) && $row['to_nse_scheme_code'] != ' ' ? $row['to_nse_scheme_code'] : null,
                isset($row['swp_registration_no']) && $row['swp_registration_no'] != ' ' ? $row['swp_registration_no'] : null,
                $convertDate($row['swp_registration_date'] ?? ''),
                $convertDate($row['swp_start_date'] ?? ''),
                $convertDate($row['swp_end_date'] ?? ''),
                isset($row['withdrawl_amount']) && $row['withdrawl_amount'] != '' ? floatval($row['withdrawl_amount']) : null,
                isset($row['withdrawal_units']) && $row['withdrawal_units'] != '' ? floatval($row['withdrawal_units']) : null,
                isset($row['no_of_withdrawls']) && $row['no_of_withdrawls'] != '' ? intval($row['no_of_withdrawls']) : null,
                isset($row['bank_account_no']) && $row['bank_account_no'] != ' ' ? $row['bank_account_no'] : null
            ];
            
            // Create type string (all 's' for strings, nulls are handled by MySQL)
            $types = str_repeat('s', count($values));
            
            // Bind parameters dynamically
            $stmt->bind_param($types, ...$values);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows == 1) {
                    $inserted++;
                } else {
                    $updated++;
                }
            } else {
                $errors[] = "Record $index: Failed - " . $stmt->error;
                error_log("Record $index: Failed - " . $stmt->error);
            }
        }
        
        $stmt->close();
        $conn->commit();
        
        $message = "Saved: $inserted inserted, $updated updated";
        if (!empty($errors)) {
            $message .= ". Errors: " . count($errors);
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'inserted' => $inserted,
            'updated' => $updated,
            'total_records' => count($reportData),
            'errors' => $errors
        );
        
    } catch (\Exception $e) {
        $conn->rollback();
        error_log("Error in saveNseSipDataToTable: " . $e->getMessage());
        return array(
            'success' => false,
            'message' => "Database error: " . $e->getMessage(),
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors
        );
    }
    }

    public function getSchemeMasterDiagnostics($testCode) {
        $dbconn = $this->getConnection();
        
        $r1  = $dbconn->query("SELECT COUNT(*) as cnt FROM mtb_scheme_master");
        $totalRows = $r1 ? (int)$r1->fetch_assoc()['cnt'] : -1;

        $r2  = $dbconn->query("SELECT COUNT(DISTINCT scheme_code) as cnt FROM mtb_scheme_master");
        $distinctCodes = $r2 ? (int)$r2->fetch_assoc()['cnt'] : -1;

        $r3  = $dbconn->query(
            "SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'mtb_scheme_master'
                AND INDEX_NAME   = 'uq_scheme_code'"
        );
        $hasUniqueKey = $r3 ? ((int)$r3->fetch_assoc()['cnt'] > 0) : false;

        $r4  = $dbconn->query(
            "SELECT scheme_code, COUNT(*) as cnt
               FROM mtb_scheme_master
              GROUP BY scheme_code
             HAVING cnt > 1
              LIMIT 5"
        );
        $duplicateSamples = [];
        if ($r4) {
            while ($row = $r4->fetch_assoc()) {
                $duplicateSamples[] = $row;
            }
        }

        $testCodeSafe = $this->safeEscape($testCode);

        $dbconn->query("INSERT INTO mtb_scheme_master (scheme_code, scheme_name, fund_cat)
             VALUES ('$testCodeSafe', 'Test Row Original', 'Test')
             ON DUPLICATE KEY UPDATE scheme_name = VALUES(scheme_name)");
        $info1        = $dbconn->info;
        $affected1    = $dbconn->affected_rows;

        $dbconn->query("INSERT INTO mtb_scheme_master (scheme_code, scheme_name, fund_cat)
             VALUES ('$testCodeSafe', 'Test Row UPDATED', 'Test')
             ON DUPLICATE KEY UPDATE scheme_name = VALUES(scheme_name)");
        $info2        = $dbconn->info;
        $affected2    = $dbconn->affected_rows;

        $r5 = $dbconn->query("SELECT scheme_name FROM mtb_scheme_master WHERE scheme_code = '$testCodeSafe'");
        $storedName = $r5 ? $r5->fetch_assoc()['scheme_name'] : 'NOT FOUND';

        $r6 = $dbconn->query("SELECT COUNT(*) as cnt FROM mtb_scheme_master WHERE scheme_code = '$testCodeSafe'");
        $preCount = $r6 ? (int)$r6->fetch_assoc()['cnt'] : 0;

        $dbconn->query("DELETE FROM mtb_scheme_master WHERE scheme_code = '$testCodeSafe'");

        return [
            'totalRows' => $totalRows,
            'distinctCodes' => $distinctCodes,
            'hasUniqueKey' => $hasUniqueKey,
            'duplicateSamples' => $duplicateSamples,
            'info1' => $info1,
            'affected1' => $affected1,
            'info2' => $info2,
            'affected2' => $affected2,
            'storedName' => $storedName,
            'preCount' => $preCount
        ];
    }

    public function getNseSipDataFromDB($clientCode = '', $fromDate = '', $toDate = '') {
    $conn = $this->getConnection();

    $conditions = [];
    $params     = [];
    $types      = '';

    if (!empty($clientCode)) {
        $conditions[] = "client_code = ?";
        $params[]     = $clientCode;
        $types       .= 's';
    }

    if (!empty($fromDate) && !empty($toDate)) {
        $from = (new DateTime($fromDate))->format('Y-m-d');
        $to   = (new DateTime($toDate))->format('Y-m-d');

        $conditions[] = "xsip_registration_date BETWEEN ? AND ?";
        $params[]     = $from;
        $params[]     = $to;
        $types       .= 'ss';
    } elseif (!empty($fromDate)) {
        $from         = (new DateTime($fromDate))->format('Y-m-d');
        $conditions[] = "xsip_registration_date >= ?";
        $params[]     = $from;
        $types       .= 's';
    } elseif (!empty($toDate)) {
        $to           = (new DateTime($toDate))->format('Y-m-d');
        $conditions[] = "xsip_registration_date <= ?";
        $params[]     = $to;
        $types       .= 's';
    }

    $sql = "SELECT * FROM tb_sip_data_by_nse";
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY xsip_registration_date DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new \Exception("Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data   = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    $stmt->close();
    return $data;
}
//==========================================================================
    // BLOG MANAGEMENT METHODS
    //==========================================================================
    
    /**
     * Get all blogs
     * @return array List of all blogs
     */
    public function getAllBlogs() {
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                ORDER BY `created_at` DESC";
        return $this->select($sql);
    }
    
    /**
     * Get single blog by ID
     * @param int $id Blog ID
     * @return array|false Blog data or false if not found
     */
    public function getBlogById($id) {
        $id = intval($id);
        $sql = "SELECT * FROM `tb_blog` WHERE `id` = $id";
        return $this->selectSingle($sql);
    }
    
    /**
     * Create new blog post
     * @param array $data Blog data (title, topic, description, url, date, image, read_time)
     * @return int|false Insert ID or false on failure
     */
    public function createBlog($data) {
        $title = $this->safeEscape($data['title'] ?? '');
        $topic = $this->safeEscape($data['topic'] ?? '');
        $description = $this->safeEscape($data['description'] ?? '');
        $url = $this->safeEscape($data['url'] ?? '');
        $date = !empty($data['date']) ? $this->safeEscape($data['date']) : 'NULL';
        $image = $this->safeEscape($data['image'] ?? '');
        $read_time = $this->safeEscape($data['read_time'] ?? '');
        
        if (empty($title) || empty($topic) || empty($description)) {
            return false;
        }
        
        $dateValue = ($date === 'NULL') ? 'NULL' : "'$date'";
        $imageValue = empty($image) ? "''" : "'$image'";
        $readTimeValue = empty($read_time) ? "''" : "'$read_time'";
        $urlValue = empty($url) ? "''" : "'$url'";
        
        $sql = "INSERT INTO `tb_blog` (`title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at`) 
                VALUES ('$title', '$topic', '$description', $urlValue, $dateValue, $imageValue, $readTimeValue, NOW())";
        
        return $this->insertQuery($sql);
    }
    
    /**
     * Update existing blog post
     * @param int $id Blog ID
     * @param array $data Updated blog data
     * @return bool True on success, false on failure
     */
    public function updateBlog($id, $data) {
        $id = intval($id);
        
        $title = $this->safeEscape($data['title'] ?? '');
        $topic = $this->safeEscape($data['topic'] ?? '');
        $description = $this->safeEscape($data['description'] ?? '');
        $url = $this->safeEscape($data['url'] ?? '');
        $date = !empty($data['date']) ? $this->safeEscape($data['date']) : 'NULL';
        $read_time = $this->safeEscape($data['read_time'] ?? '');
        
        if (empty($title) || empty($topic) || empty($description) || $id <= 0) {
            return false;
        }
        
        $updateFields = [];
        $updateFields[] = "`title` = '$title'";
        $updateFields[] = "`topic` = '$topic'";
        $updateFields[] = "`description` = '$description'";
        $updateFields[] = "`url` = " . (empty($url) ? "''" : "'$url'");
        $updateFields[] = "`date` = " . ($date === 'NULL' ? "NULL" : "'$date'");
        $updateFields[] = "`read_time` = " . (empty($read_time) ? "''" : "'$read_time'");
        
        // Add image update if provided
        if (isset($data['image']) && !empty($data['image'])) {
            $image = $this->safeEscape($data['image']);
            $updateFields[] = "`image` = '$image'";
        }
        
        $sql = "UPDATE `tb_blog` SET " . implode(", ", $updateFields) . " WHERE `id` = $id";
        
        return $this->query($sql);
    }
    
    /**
     * Delete blog post
     * @param int $id Blog ID
     * @return bool True on success, false on failure
     */
    public function deleteBlog($id) {
        $id = intval($id);
        
        // First get the image path to delete the file
        $blog = $this->getBlogById($id);
        if ($blog && !empty($blog['image'])) {
            // Path relative to admin folder (since this is called from admin)
            $image_path = dirname(__DIR__) . '/uploads/blog/' . $blog['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $sql = "DELETE FROM `tb_blog` WHERE `id` = $id";
        return $this->query($sql);
    }
    
    /**
     * Get blog image path by ID
     * @param int $id Blog ID
     * @return string|false Image filename or false if not found
     */
    public function getBlogImage($id) {
        $id = intval($id);
        $sql = "SELECT `image` FROM `tb_blog` WHERE `id` = $id";
        $result = $this->selectSingle($sql);
        return ($result && isset($result['image'])) ? $result['image'] : false;
    }
    
    /**
     * Update blog image only
     * @param int $id Blog ID
     * @param string $image Image filename
     * @return bool True on success, false on failure
     */
    public function updateBlogImage($id, $image) {
        $id = intval($id);
        $image = $this->safeEscape($image);
        
        $sql = "UPDATE `tb_blog` SET `image` = '$image' WHERE `id` = $id";
        return $this->query($sql);
    }
    
    /**
     * Get total count of blogs
     * @return int Total number of blogs
     */
    public function getBlogsCount() {
        $result = $this->selectSingle("SELECT COUNT(*) as total FROM `tb_blog`");
        return $result ? $result['total'] : 0;
    }
    
    /**
     * Get blogs with pagination
     * @param int $limit Number of records per page
     * @param int $offset Offset for pagination
     * @return array List of blogs
     */
    public function getBlogsPaginated($limit, $offset) {
        $limit = intval($limit);
        $offset = intval($offset);
        
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                ORDER BY `created_at` DESC 
                LIMIT $limit OFFSET $offset";
        
        return $this->select($sql);
    }
    
    /**
     * Search blogs by title or topic
     * @param string $keyword Search keyword
     * @return array Matching blogs
     */
    public function searchBlogs($keyword) {
        $keyword = $this->safeEscape($keyword);
        $sql = "SELECT `id`, `title`, `topic`, `description`, `url`, `date`, `image`, `read_time`, `created_at` 
                FROM `tb_blog` 
                WHERE `title` LIKE '%$keyword%' OR `topic` LIKE '%$keyword%' OR `description` LIKE '%$keyword%'
                ORDER BY `created_at` DESC";
        
        return $this->select($sql);
    }
	public function getBlogCount() {
    $result = $this->selectSingle("SELECT COUNT(*) as total FROM `tb_blog`");
    return $result ? $result['total'] : 0;
}

    //==========================================================================
    // TESTIMONIAL MANAGEMENT METHODS
    //==========================================================================
    
    /**
     * Get all testimonials
     * @return array List of all testimonials
     */
    public function getAllTestimonials() {
        $sql = "SELECT `id`, `name`, `role`, `text`, `image`, `status`, `sort_order`, `created_at` 
                FROM `tb_testimonials` 
                ORDER BY `sort_order` ASC, `id` DESC";
        return $this->select($sql);
    }
    
    /**
     * Get single testimonial by ID
     * @param int $id Testimonial ID
     * @return array|false Testimonial data or false if not found
     */
    public function getTestimonialById($id) {
        $id = intval($id);
        $sql = "SELECT * FROM `tb_testimonials` WHERE `id` = $id";
        return $this->selectSingle($sql);
    }
    
    /**
     * Create new testimonial
     * @param array $data Testimonial data
     * @return int|false Insert ID or false on failure
     */
    public function createTestimonial($data) {
        $name = $this->safeEscape($data['name'] ?? '');
        $role = $this->safeEscape($data['role'] ?? '');
        $text = $this->safeEscape($data['text'] ?? '');
        $image = $this->safeEscape($data['image'] ?? '');
        $sort_order = intval($data['sort_order'] ?? 0);
        $status = intval($data['status'] ?? 1);
        
        if (empty($name) || empty($text)) {
            return false;
        }
        
        $imageValue = empty($image) ? "NULL" : "'$image'";
        $roleValue = empty($role) ? "NULL" : "'$role'";
        
        $sql = "INSERT INTO `tb_testimonials` (`name`, `role`, `text`, `image`, `sort_order`, `status`, `created_at`) 
                VALUES ('$name', $roleValue, '$text', $imageValue, $sort_order, $status, NOW())";
        
        return $this->insertQuery($sql);
    }
    
    /**
     * Update existing testimonial
     * @param int $id Testimonial ID
     * @param array $data Updated testimonial data
     * @return bool True on success, false on failure
     */
    public function updateTestimonial($id, $data) {
        $id = intval($id);
        
        $name = $this->safeEscape($data['name'] ?? '');
        $role = $this->safeEscape($data['role'] ?? '');
        $text = $this->safeEscape($data['text'] ?? '');
        $sort_order = intval($data['sort_order'] ?? 0);
        $status = intval($data['status'] ?? 1);
        
        if ($id <= 0 || empty($name) || empty($text)) {
            return false;
        }
        
        $updateFields = [];
        $updateFields[] = "`name` = '$name'";
        $updateFields[] = "`role` = " . (empty($role) ? "NULL" : "'$role'");
        $updateFields[] = "`text` = '$text'";
        $updateFields[] = "`sort_order` = $sort_order";
        $updateFields[] = "`status` = $status";
        
        // Add image update if provided
        if (isset($data['image'])) {
            $image = $this->safeEscape($data['image']);
            $updateFields[] = "`image` = " . (empty($image) ? "NULL" : "'$image'");
        }
        
        $sql = "UPDATE `tb_testimonials` SET " . implode(", ", $updateFields) . " WHERE `id` = $id";
        
        return $this->query($sql);
    }
    
    /**
     * Delete testimonial
     * @param int $id Testimonial ID
     * @return bool True on success, false on failure
     */
    public function deleteTestimonial($id) {
        $id = intval($id);
        
        // First get the image path to delete the file
        $testi = $this->getTestimonialById($id);
        if ($testi && !empty($testi['image'])) {
            // Path relative to admin folder
            $image_path = dirname(__DIR__) . '/uploads/testimonials/' . $testi['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $sql = "DELETE FROM `tb_testimonials` WHERE `id` = $id";
        return $this->query($sql);
    }
    
    /**
     * Get testimonial image filename by ID
     * @param int $id Testimonial ID
     * @return string|false Image filename or false if not found
     */
    public function getTestimonialImage($id) {
        $id = intval($id);
        $sql = "SELECT `image` FROM `tb_testimonials` WHERE `id` = $id";
        $result = $this->selectSingle($sql);
        return ($result && isset($result['image'])) ? $result['image'] : false;
    }

    /**
     * Get total count of testimonials
     * @return int Total number of testimonials
     */
    public function getTestimonialCount() {
        $result = $this->selectSingle("SELECT COUNT(*) as cnt FROM `tb_testimonials`");
        return $result ? (int)$result['cnt'] : 0;
    }
//==========================================================================
    // MEDIA MANAGEMENT METHODS
    //==========================================================================
 
   public function getBlogStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_blogs,
                    SUM(CASE WHEN date IS NOT NULL AND date <= CURDATE() THEN 1 ELSE 0 END) as published_blogs,
                    SUM(CASE WHEN date IS NULL OR date > CURDATE() THEN 1 ELSE 0 END) as draft_blogs,
                    SUM(CASE WHEN image IS NOT NULL AND image != '' THEN 1 ELSE 0 END) as blogs_with_images
                FROM `tb_blog`";
        return $this->selectSingle($sql);
    }

    /**
     * Clean up invalid icon/image names
     */
    public function cleanUpCorruptedMediaAndBlogs() {
        $this->query("UPDATE `tb_media` SET `icon` = '' WHERE `icon` IS NOT NULL AND `icon` REGEXP '[^a-zA-Z0-9_.-]'");
        $this->query("UPDATE `tb_blog` SET `image` = '' WHERE `image` IS NOT NULL AND `image` REGEXP '[^a-zA-Z0-9_.-]'");
    }
    
    /**
     * Get total number of unique media categories
     */
    public function getUniqueMediaCategoryCount() {
        $result = $this->selectSingle("SELECT COUNT(DISTINCT `category`) as cnt FROM `tb_media` WHERE `category` IS NOT NULL AND `category` != ''");
        return $result ? (int)$result['cnt'] : 0;
    }
    
    /**
     * Get count of published blogs
     */
    public function getPublishedBlogCount() {
        $result = $this->selectSingle("SELECT COUNT(*) as cnt FROM `tb_blog` WHERE `date` IS NOT NULL AND `date` <= CURDATE()");
        return $result ? (int)$result['cnt'] : 0;
    }
   
    /**
     * Get all media items
     * @return array List of all media
     */
    public function getAllMedia() {
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                ORDER BY `id` DESC";
        return $this->select($sql);
    }
    
    /**
     * Get single media by ID
     * @param int $id Media ID
     * @return array|false Media data or false if not found
     */
    public function getMediaById($id) {
        $id = intval($id);
        $sql = "SELECT * FROM `tb_media` WHERE `id` = $id";
        return $this->selectSingle($sql);
    }
    
    /**
     * Create new media entry
     * @param array $data Media data (title, url, description, source, date, category, icon)
     * @return int|false Insert ID or false on failure
     */
    public function createMedia($data) {
        $title = $this->safeEscape($data['title'] ?? '');
        $url = $this->safeEscape($data['url'] ?? '');
        $description = $this->safeEscape($data['description'] ?? '');
        $source = $this->safeEscape($data['source'] ?? '');
        $date = !empty($data['date']) ? $this->safeEscape($data['date']) : 'NULL';
        $category = $this->safeEscape($data['category'] ?? '');
        $icon = $this->safeEscape($data['icon'] ?? '');
        
        if (empty($title)) {
            return false;
        }
        
        $dateValue = ($date === 'NULL') ? 'NULL' : "'$date'";
        $iconValue = empty($icon) ? "''" : "'$icon'";
        $urlValue = empty($url) ? "''" : "'$url'";
        $descriptionValue = empty($description) ? "''" : "'$description'";
        $sourceValue = empty($source) ? "''" : "'$source'";
        $categoryValue = empty($category) ? "''" : "'$category'";
        
        $sql = "INSERT INTO `tb_media` (`title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`) 
                VALUES ('$title', $urlValue, $descriptionValue, $sourceValue, $dateValue, $categoryValue, $iconValue, NOW())";
        
        return $this->insertQuery($sql);
    }
    
    /**
     * Update existing media entry
     * @param int $id Media ID
     * @param array $data Updated media data
     * @return bool True on success, false on failure
     */
    public function updateMedia($id, $data) {
        $id = intval($id);
        
        $title = $this->safeEscape($data['title'] ?? '');
        $url = $this->safeEscape($data['url'] ?? '');
        $description = $this->safeEscape($data['description'] ?? '');
        $source = $this->safeEscape($data['source'] ?? '');
        $date = !empty($data['date']) ? $this->safeEscape($data['date']) : 'NULL';
        $category = $this->safeEscape($data['category'] ?? '');
        
        if (empty($title) || $id <= 0) {
            return false;
        }
        
        $updateFields = [];
        $updateFields[] = "`title` = '$title'";
        $updateFields[] = "`url` = " . (empty($url) ? "''" : "'$url'");
        $updateFields[] = "`description` = " . (empty($description) ? "''" : "'$description'");
        $updateFields[] = "`source` = " . (empty($source) ? "''" : "'$source'");
        $updateFields[] = "`date` = " . ($date === 'NULL' ? "NULL" : "'$date'");
        $updateFields[] = "`category` = " . (empty($category) ? "''" : "'$category'");
        $updateFields[] = "`updated_at` = NOW()";
        
        // Add icon update if provided
        if (isset($data['icon']) && !empty($data['icon'])) {
            $icon = $this->safeEscape($data['icon']);
            $updateFields[] = "`icon` = '$icon'";
        }
        
        $sql = "UPDATE `tb_media` SET " . implode(", ", $updateFields) . " WHERE `id` = $id";
        
        return $this->query($sql);
    }
    
    /**
     * Delete media entry
     * @param int $id Media ID
     * @return bool True on success, false on failure
     */
    public function deleteMedia($id) {
        $id = intval($id);
        
        // First get the icon path to delete the file
        $media = $this->getMediaById($id);
        if ($media && !empty($media['icon'])) {
            $icon_path = dirname(__DIR__) . '/uploads/media/' . $media['icon'];
            if (file_exists($icon_path)) {
                unlink($icon_path);
            }
        }
        
        $sql = "DELETE FROM `tb_media` WHERE `id` = $id";
        return $this->query($sql);
    }
    
    /**
     * Get media icon path by ID
     * @param int $id Media ID
     * @return string|false Icon filename or false if not found
     */
    public function getMediaIcon($id) {
        $id = intval($id);
        $sql = "SELECT `icon` FROM `tb_media` WHERE `id` = $id";
        $result = $this->selectSingle($sql);
        return ($result && isset($result['icon'])) ? $result['icon'] : false;
    }
    
    /**
     * Update media icon only
     * @param int $id Media ID
     * @param string $icon Icon filename
     * @return bool True on success, false on failure
     */
    public function updateMediaIcon($id, $icon) {
        $id = intval($id);
        $icon = $this->safeEscape($icon);
        
        $sql = "UPDATE `tb_media` SET `icon` = '$icon', `updated_at` = NOW() WHERE `id` = $id";
        return $this->query($sql);
    }
    
    /**
     * Get total count of media
     * @return int Total number of media items
     */
    public function getMediaCount() {
        $result = $this->selectSingle("SELECT COUNT(*) as total FROM `tb_media`");
        return $result ? $result['total'] : 0;
    }
    
    /**
     * Get media with pagination
     * @param int $limit Number of records per page
     * @param int $offset Offset for pagination
     * @return array List of media
     */
    public function getMediaPaginated($limit, $offset) {
        $limit = intval($limit);
        $offset = intval($offset);
        
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                ORDER BY `id` DESC 
                LIMIT $limit OFFSET $offset";
        
        return $this->select($sql);
    }
    
    /**
     * Search media by title, description, or category
     * @param string $keyword Search keyword
     * @return array Matching media
     */
    public function searchMedia($keyword) {
        $keyword = $this->safeEscape($keyword);
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE `title` LIKE '%$keyword%' 
                   OR `description` LIKE '%$keyword%' 
                   OR `category` LIKE '%$keyword%'
                   OR `source` LIKE '%$keyword%'
                ORDER BY `id` DESC";
        
        return $this->select($sql);
    }
    
    /**
     * Get media by category
     * @param string $category Category name
     * @return array List of media in the category
     */
    public function getMediaByCategory($category) {
        $category = $this->safeEscape($category);
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at`, `updated_at` 
                FROM `tb_media` 
                WHERE `category` = '$category'
                ORDER BY `id` DESC";
        
        return $this->select($sql);
    }
    
    /**
     * Get all unique categories
     * @return array List of unique categories
     */
    public function getMediaCategories() {
        $sql = "SELECT DISTINCT `category` FROM `tb_media` WHERE `category` IS NOT NULL AND `category` != '' ORDER BY `category`";
        $result = $this->select($sql);
        
        $categories = [];
        foreach ($result as $row) {
            $categories[] = $row['category'];
        }
        return $categories;
    }
    
    /**
     * Get media count by category
     * @return array Category-wise counts
     */
    public function getMediaCountByCategory() {
        $sql = "SELECT `category`, COUNT(*) as total FROM `tb_media` GROUP BY `category` ORDER BY `total` DESC";
        return $this->select($sql);
    }
    
    /**
     * Bulk delete media items
     * @param array $ids Array of media IDs to delete
     * @return array Result with success and failure counts
     */
    public function bulkDeleteMedia($ids) {
        if (empty($ids) || !is_array($ids)) {
            return ['success' => 0, 'failed' => 0];
        }
        
        $success = 0;
        $failed = 0;
        
        foreach ($ids as $id) {
            if ($this->deleteMedia($id)) {
                $success++;
            } else {
                $failed++;
            }
        }
        
        return ['success' => $success, 'failed' => $failed];
    }
    
    /**
     * Get latest media items
     * @param int $limit Number of items to fetch
     * @return array Latest media
     */
    public function getLatestMedia($limit = 5) {
        $limit = intval($limit);
        $sql = "SELECT `id`, `title`, `url`, `description`, `source`, `date`, `category`, `icon`, `created_at` 
                FROM `tb_media` 
                ORDER BY `created_at` DESC 
                LIMIT $limit";
        
        return $this->select($sql);
    }
    
    /**
     * Update media status (if you add a status column)
     * @param int $id Media ID
     * @param int $status Status (0=Inactive, 1=Active)
     * @return bool True on success
     */
    public function updateMediaStatus($id, $status) {
        $id = intval($id);
        $status = intval($status);
        
        $sql = "UPDATE `tb_media` SET `status` = $status, `updated_at` = NOW() WHERE `id` = $id";
        return $this->query($sql);
    }

// New Method added by Akhilesh, 18-April-2026
	/*
	 cat_tag		in new scheme_master : 	Aggressive Hybrid,	
										Aggressive Hybrid,FO	
										Aggressive Hybrid,Re	
										Aggressive Hybrid,Se	
										Balanced Advantage	
										Bond	
										Bond,Sectoral	
										Children Fund	
										Conservative Hybrid	
										Conservative Hybrid,	
										ELSS	
										ELSS,Sectoral	
										ETF	
										ETF,FOF	
										ETF,FOF,Bond	
										ETF,FOF,Bond,Sectora	
										ETF,FOF,Sectoral	
										ETF,Sectoral	
										FOF	
										FOF,Bond	
										FOF,Sectoral	
										Index Fund	
										Index Fund,Bond	
										Index Fund,Bond,Sect	
										
		scheme_type  in new scheme_master : DEBT , EQUITY , ETF , LIQUID , OVERNIGHT
	 
		fund_cat     in new scheme_master : Commodity, Debt , Flexi Cap , Hybrid , Large Cap , Mid Cap , 
	                                     Multi Cap , Other, Other Equity, Small Cap
	*/									
		
	public function getFundAssetClassMapping($isincode)
	{
		$dbconn   = $this->getConnection();
		$isincode = $this->safeEscape($isincode);

		$sql = "
			SELECT 
				fund_cat,
				cat_tag
			FROM mtb_scheme_master
			WHERE isin = '$isincode'
			LIMIT 1
		";

		$result = $this->selectSingle($sql);

		if ($result == false) {
			return false;
		}

		$fundCat = trim($result['fund_cat'] ?? '');
		$catTag  = trim($result['cat_tag'] ?? '');

		/*
		|--------------------------------------------------------------------------
		| NORMALIZE
		|--------------------------------------------------------------------------
		*/

		$fundCatNormalized = strtolower($fundCat);
		$catTagNormalized  = strtolower($catTag);

		/*
		|--------------------------------------------------------------------------
		| STEP 1 : GOLD OVERRIDE
		|--------------------------------------------------------------------------
		*/

		if ($fundCatNormalized == 'commodity') {
			return 'GOLD';
		}

		/*
		|--------------------------------------------------------------------------
		| STEP 2 : CAT TAG MAPPING
		|--------------------------------------------------------------------------
		*/

		$catTagMap = [

			// HYBRID
			'aggressive hybrid'       => 'HYBRID',
			'aggressive hybrid,fo'    => 'HYBRID',
			'aggressive hybrid,re'    => 'HYBRID',
			'aggressive hybrid,se'    => 'HYBRID',
			'balanced advantage'      => 'HYBRID',
			'conservative hybrid'     => 'HYBRID',
			'conservative hybrid,'    => 'HYBRID',

			// DEBT
			'bond'                    => 'DEBT',
			'bond,sectoral'           => 'DEBT',
			'children fund'           => 'DEBT',
			'fof,bond'                => 'DEBT',
			'etf,fof,bond'            => 'DEBT',
			'etf,fof,bond,sectora'    => 'DEBT',
			'index fund,bond'         => 'DEBT',
			'index fund,bond,sect'    => 'DEBT',

			// EQUITY
			'elss'                    => 'EQUITY',
			'elss,sectoral'           => 'EQUITY',
			'etf'                     => 'EQUITY',
			'etf,fof'                 => 'EQUITY',
			'etf,sectoral'            => 'EQUITY',
			'etf,fof,sectoral'        => 'EQUITY',
			'fof'                     => 'EQUITY',
			'fof,sectoral'            => 'EQUITY',
			'index fund'              => 'EQUITY',
		];

		if (isset($catTagMap[$catTagNormalized])) {
			return $catTagMap[$catTagNormalized];
		}

		/*
		|--------------------------------------------------------------------------
		| STEP 3 : FUND CATEGORY FALLBACK
		|--------------------------------------------------------------------------
		*/

		$fundCatMap = [

			'commodity'     => 'GOLD',

			'debt'          => 'DEBT',

			'hybrid'        => 'HYBRID',

			'flexi cap'     => 'EQUITY',
			'large cap'     => 'EQUITY',
			'mid cap'       => 'EQUITY',
			'multi cap'     => 'EQUITY',
			'other'         => 'EQUITY',
			'other equity'  => 'EQUITY',
			'small cap'     => 'EQUITY',
		];

		if (isset($fundCatMap[$fundCatNormalized])) {
			return $fundCatMap[$fundCatNormalized];
		}

		/*
		|--------------------------------------------------------------------------
		| DEFAULT FALLBACK
		|--------------------------------------------------------------------------
		*/

		error_log(
			"Unknown asset class mapping for ISIN: {$isincode}, " .
			"fund_cat='{$fundCat}', cat_tag='{$catTag}'"
		);

		return 'EQUITY';
	}
	
	public function getFundAssetClassByISIN($isincode)
	{
		$dbconn   = $this->getConnection();
		$isincode = $this->safeEscape($isincode);

		$sql = "
			SELECT 
				asset_type
			FROM mtb_scheme_master
			WHERE isin = '$isincode'
			LIMIT 1
		";

		$result = $this->selectSingle($sql);
		
		if ($result == false || empty($result['asset_type'])) {
			return false;
		}
		
		return strtoupper(trim($result['asset_type']));

	}
	
	/**
	 * Efficiently populates the asset_type column for all records in mtb_scheme_master.
	 * It scans distinct ISINs, calls getFundAssetClassMapping() for each, and 
	 * performs bulk updates to minimize database write overhead.
	 */
	public function populateAllAssetTypes()
	{
		$dbconn = $this->getConnection();
		
		$sql = "UPDATE mtb_scheme_master m
		LEFT JOIN tb_custom_fund_category c ON m.isin = c.isin AND c.isin IS NOT NULL AND c.isin != ''
		SET m.asset_type = COALESCE(c.asset_type, CASE 
			-- 1: GOLD Override
			WHEN LOWER(TRIM(m.fund_cat)) = 'commodity' THEN 'GOLD'

			-- 2: CAT TAG MAPPING
			WHEN LOWER(TRIM(m.cat_tag)) IN ('aggressive hybrid', 'aggressive hybrid,fo', 'aggressive hybrid,re', 'aggressive hybrid,se', 'balanced advantage', 'conservative hybrid', 'conservative hybrid,') THEN 'HYBRID'
			WHEN LOWER(TRIM(m.cat_tag)) IN ('bond', 'bond,sectoral', 'children fund', 'fof,bond', 'etf,fof,bond', 'etf,fof,bond,sectora', 'index fund,bond', 'index fund,bond,sect') THEN 'DEBT'
			WHEN LOWER(TRIM(m.cat_tag)) IN ('elss', 'elss,sectoral', 'etf', 'etf,fof', 'etf,sectoral', 'etf,fof,sectoral', 'fof', 'fof,sectoral', 'index fund') THEN 'EQUITY'

			-- 3: FUND CATEGORY FALLBACK
			WHEN LOWER(TRIM(m.fund_cat)) = 'debt' THEN 'DEBT'
			WHEN LOWER(TRIM(m.fund_cat)) = 'hybrid' THEN 'HYBRID'
			WHEN LOWER(TRIM(m.fund_cat)) IN ('flexi cap', 'large cap', 'mid cap', 'multi cap', 'other', 'other equity', 'small cap') THEN 'EQUITY'

			-- DEFAULT FALLBACK
			ELSE 'EQUITY'
		END)";
		
		$this->query($sql);
		
		// Returns the number of affected rows (rows that were actually modified)
		return $dbconn->affected_rows;
	}
	
	public function getFundNAVByISIN($isincode)
    {
        $dbconn 	= $this->getConnection();
        $isincode 	= $this->safeEscape($isincode);
        
        $sql = "SELECT tn.* FROM mtb_scheme_master tm 
                JOIN tb_nav tn ON tm.isin = tn.isin 
                WHERE tm.isin = '$isincode' 
                ORDER BY tn.nav_date DESC LIMIT 1";
        //error_log("getFundNAVByISIN:".$sql);
        return $this->selectSingle($sql);
    }
    
    public function getLatestMcapByIsin($isin)
	{
		$sql = "SELECT large_cap, mid_cap, small_cap, cash, debt
				FROM tb_funds_mcap_data
				WHERE isin = :isin
				ORDER BY month_year DESC
				LIMIT 1";

		//$pdo = $this->getPDO();
		//$stmt = $pdo->prepare($sql);
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([':isin' => $isin]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	
	public function getSipRegDetailsFromDb($userUCC)
	{
		$dbconn = $this->getConnection();	
		$userUCC = $this->safeEscape($userUCC);
		
		$sql = "SELECT * FROM tb_sip_data_by_nse WHERE `client_code` = '$userUCC'";
		
		$result = $this ->query($sql); 
		//echo $sql;
		return $result;
	}
	
	// =========================================================
    // CONNECTION HELPERS
    // =========================================================
	/*
	 * Get PDO connection using the same database configuration as MySQLi
	 * 
	 * @return PDO PDO connection object
	 * @throws Exception If PDO extension is not available or connection fails
	 */
	public function getPDO(): PDO
	{
		// Ensure database config is loaded (in case constructor hasn't been called yet)
		if ($this->config_data === null) {
			$this->__construct();
		}
		
		// Check if PDO extension is available
		if (!extension_loaded('pdo_mysql')) {
			throw new \Exception('PDO MySQL extension is not loaded');
		}
		
		$dbConfig = $this->config_data['database'] ?? [];
		
		// Validate required config keys
		$requiredKeys = ['host', 'username', 'password', 'dbname'];
		foreach ($requiredKeys as $key) {
			if (!isset($dbConfig[$key])) {
				throw new \Exception("Missing DB config key: {$key}");
			}
		}
		
		// Build DSN
		$host = $dbConfig['host'];
		$dbname = $dbConfig['dbname'];
		$charset = $dbConfig['charset'] ?? 'utf8mb4';
		$port = $dbConfig['port'] ?? 3306;
		
		$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
		
		// Optional: Add socket if configured
		if (isset($dbConfig['socket']) && !empty($dbConfig['socket'])) {
			$dsn .= ";unix_socket={$dbConfig['socket']}";
		}
		
		// PDO options
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_PERSISTENT => false,
			PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
			PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
		];
		
		try {
			$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
			return $pdo;
		} catch (PDOException $e) {
			throw new \Exception("PDO Connection failed: " . $e->getMessage());
		}
	}
	

	//Additional Security Note:
	//Your code is vulnerable to SQL injection. 
	// Consider using prepared statements instead of safeEscape():

	public function getSipResponseFromDb($userUCC)
	{
		$dbconn = $this->getConnection();
		
		// Specify only needed columns instead of SELECT *
		$sql = "SELECT client_name, registration_type, scheme_name, rta_scheme_code, amc_name, frequency_type,
						installments_amount, start_date, end_date, status,
						swp_start_date, swp_end_date, withdrawl_amount,
						stp_start_date, stp_end_date, transfer_amount
				FROM `tb_sip_data_by_nse` 
				WHERE `client_code` = ? AND `status` = 'ACTIVE'
				ORDER BY `start_date` DESC";
		$stmt = $dbconn->prepare($sql);
		$stmt->bind_param("s", $userUCC);
		$stmt->execute();
		$result = $stmt->get_result();
		
		return $result->fetch_all(MYSQLI_ASSOC); // PHP 5.3+ shorter syntax
	}
		
	public function getIsinByFundRTACode($fundCode) 
	{
		$dbconn = $this->getConnection();
		
		$sql = "SELECT isin FROM `mtb_scheme_master` WHERE `rta_scheme_code` = ? AND `isin` IS NOT NULL AND `isin` != '' LIMIT 1";
		$stmt = $dbconn->prepare($sql);
		
		if ($stmt) {
			$stmt->bind_param("s", $fundCode);
			$stmt->execute();
			$result = $stmt->get_result();
			if ($row = $result->fetch_assoc()) {
				return $row['isin'];
			}
		}
		
		echo "<br> ".$sql;
		
		return null;
	}

	
	public function getIsinByFundNameAndCode($fund_name, $fundCode, $amc_name) 
	{
		$dbconn = $this->getConnection();
		
        // Priority 1: Search by RTA Scheme Code + First word of Fund Name (Prevents AMC collisions)
        if (!empty($fundCode) && !empty($fund_name)) {
            $first_word = explode(' ', trim($fund_name))[0];
            $like_pattern = $first_word . '%';
            $sql = "SELECT isin FROM `mtb_scheme_master` WHERE `rta_scheme_code` = ? AND `scheme_name` LIKE ? AND isin IS NOT NULL AND isin != '' LIMIT 1";
		$stmt = $dbconn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $fundCode, $like_pattern);
		$stmt->execute();
		$result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $stmt->close();
                    return $row['isin'];
                }
                $stmt->close();
            }
        }

        // Priority 2: Exact Scheme Name Match (Case Insensitive)
        if (!empty($fund_name)) {
            $sql = "SELECT isin FROM `mtb_scheme_master` WHERE LOWER(`scheme_name`) = LOWER(?) AND isin IS NOT NULL AND isin != '' LIMIT 1";
            $stmt = $dbconn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $fund_name);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $stmt->close();
                    return $row['isin'];
                }
                $stmt->close();
            }
        }

        // Priority 3: Fuzzy matching based on base name (since Fund_Code is often empty for SWPs)
        if (!empty($fund_name)) {
            // Clean up the fund name from the SIP/SWP data to remove plan/option details to find the base name
            $cleaned_fund_name = preg_replace('/-?\s*(REGULAR|DIRECT)\s*(PLAN)?\s*(-|–)?\s*(GROWTH|GR|IDCW|DIVIDEND.*)?/i', '', $fund_name);
            // Clean up double spaces which might break LIKE % matching
            $cleaned_fund_name = preg_replace('/\s+/', ' ', $cleaned_fund_name);
            $cleaned_fund_name = trim($cleaned_fund_name);
            
            // DEBUG: See exactly how the fund name is being cleaned for the database search
            //error_log("DEBUG getIsinByFundNameAndCode: Original='{$fund_name}', Cleaned='{$cleaned_fund_name}'");

            if (!empty($cleaned_fund_name)) {
                // Search for scheme names that START WITH the cleaned base name
                $sql = "SELECT isin, scheme_name FROM `mtb_scheme_master` WHERE `scheme_name` LIKE ? AND isin IS NOT NULL AND isin != ''";
                $stmt = $dbconn->prepare($sql);
                if ($stmt) {
                    $like_pattern = $cleaned_fund_name . "%";
                    $stmt->bind_param("s", $like_pattern);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $candidates = [];
		while ($row = $result->fetch_assoc()) {
                        $candidates[] = $row;
                    }
                    $stmt->close();

                    if (count($candidates) === 1) {
                        return $candidates[0]['isin'];
                    } elseif (count($candidates) > 1) {
                        // Gather features from the original fund name
                        $orig_upper = strtoupper($fund_name);
                        $has_direct = strpos($orig_upper, 'DIRECT') !== false;
                        $has_regular = strpos($orig_upper, 'REGULAR') !== false;
                        $has_growth = strpos($orig_upper, 'GROWTH') !== false;
                        $has_idcw = strpos($orig_upper, 'IDCW') !== false;
                        $has_dividend = strpos($orig_upper, 'DIVIDEND') !== false;

                        $best_score = -999;
                        $best_isin = null;

                        foreach ($candidates as $cand) {
                            $cand_name = strtoupper($cand['scheme_name']);
                            $score = 0;
                            
                            $cand_has_direct = strpos($cand_name, 'DIRECT') !== false;
                            $cand_has_regular = strpos($cand_name, 'REGULAR') !== false;
                            $cand_has_growth = strpos($cand_name, 'GROWTH') !== false;
                            $cand_has_idcw = strpos($cand_name, 'IDCW') !== false;
                            $cand_has_dividend = strpos($cand_name, 'DIVIDEND') !== false;
			
                            // Score Plan Type (Direct vs Regular)
                            if ($has_direct && $cand_has_direct) $score += 10;
                            elseif ($has_regular && $cand_has_regular) $score += 10;
                            elseif ($has_direct && $cand_has_regular) $score -= 10;
                            elseif ($has_regular && $cand_has_direct) $score -= 10;

                            // Score Option Type (Growth vs Dividend/IDCW)
                            if ($has_growth && $cand_has_growth) $score += 10;
                            elseif ($has_idcw && $cand_has_idcw) $score += 10;
                            elseif ($has_dividend && $cand_has_dividend) $score += 10;
                            
                            // Penalize Option Type mismatches
                            if ($has_growth && ($cand_has_idcw || $cand_has_dividend)) $score -= 10;
                            if (($has_idcw || $has_dividend) && $cand_has_growth) $score -= 10;

                            // Use string similarity as a tie-breaker (adds 0 to 1 point)
                            similar_text($orig_upper, $cand_name, $similarity_pct);
                            $score += ($similarity_pct / 100);

                            if ($score > $best_score) {
                                $best_score = $score;
                                $best_isin = $cand['isin'];
                            }
                        }
                        
                        if ($best_isin) {
                            return $best_isin;
                        }
                    }
                }
            }
        }

        // Priority 4: Naked RTA Scheme Code Fallback (Last resort)
        if (!empty($fundCode)) {
            $sql = "SELECT isin FROM `mtb_scheme_master` WHERE `rta_scheme_code` = ? AND isin IS NOT NULL AND isin != '' LIMIT 1";
            $stmt = $dbconn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $fundCode);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $stmt->close();
				return $row['isin'];
			}
                $stmt->close();
            }
		}
		
        error_log("getIsinByFundNameAndCode: No ISIN found for Fund='{$fund_name}', Code='{$fundCode}', AMC='{$amc_name}'");
		return null;
	}

    public function getAMCNamebyISIN($isin)
    {
        $dbconn = $this->getConnection();
        $amccode = $this->safeEscape($isin);
        $sql = "SELECT amc_code FROM `mtb_scheme_master` WHERE `isin` = '$isin'";
        $result = $this->selectSingle($sql);
        if($result == false) {
            return false;
        } else {
            return $result["amc_code"];
        }
    }
    
	public function getFundNamebyISIN($isin)
    {
        $dbconn = $this->getConnection();
        $amccode = $this->safeEscape($isin);
        $sql = "SELECT scheme_name FROM `mtb_scheme_master` WHERE `isin` = '$isin'";
        $result = $this->selectSingle($sql);
        if($result == false) {
            return false;
        } else {
            return $result["scheme_name"];
        }
    }	
 
	//==========================================================================
    // AUTO EMAIL PREFERENCE METHODS
    //==========================================================================
    
    /**
     * Update user's auto email preference
     * @param int $userId User ID
     * @param int $autoEmail 0 or 1
     * @return bool True on success, false on failure
     */
    public function updateAutoEmailPreference($userId, $autoEmail) {
        $userId = intval($userId);
        $autoEmail = $autoEmail ? 1 : 0; // Convert to 0 or 1
        
        $sql = "UPDATE vw_client_profiles SET AUTO_EMAIL = $autoEmail, UPDATED_AT = NOW() WHERE id = $userId";
        return $this->query($sql);
    }
    
    /**
     * Get user's auto email preference
     * @param int $userId User ID
     * @return int 0 or 1 (default 0)
     */
    public function getAutoEmailPreference($userId) {
        $userId = intval($userId);
        $sql = "SELECT AUTO_EMAIL FROM vw_client_profiles WHERE id = $userId LIMIT 1";
        $result = $this->selectSingle($sql);
        
        if ($result && isset($result['AUTO_EMAIL'])) {
            return intval($result['AUTO_EMAIL']);
        }
        return 0; // Default value
    }
    
    /**
     * Check if auto email is enabled for a user
     * @param int $userId User ID
     * @return bool True if auto email is enabled, false otherwise
     */
    public function isAutoEmailEnabled($userId) {
        return $this->getAutoEmailPreference($userId) === 1;
    }
    
    /**
     * Get all users with auto email disabled
     * @return array List of users with AUTO_EMAIL = 0
     */
    public function getUsersWithAutoEmailDisabled() {
        $sql = "SELECT id, EMAIL, PRIMARY_HOLDER_NAME, CLIENT_CODE 
                FROM vw_client_profiles 
                WHERE AUTO_EMAIL = 0 OR AUTO_EMAIL IS NULL
                ORDER BY id";
        return $this->select($sql);
    }
    
    /**
     * Get all users with auto email enabled
     * @return array List of users with AUTO_EMAIL = 1
     */
    public function getUsersWithAutoEmailEnabled() {
        $sql = "SELECT id, EMAIL, PRIMARY_HOLDER_NAME, CLIENT_CODE 
                FROM vw_client_profiles 
                WHERE AUTO_EMAIL = 1
                ORDER BY id";
        return $this->select($sql);
    }
    
    /**
     * Bulk update auto email preference for multiple users
     * @param array $userIds Array of user IDs
     * @param int $autoEmail 0 or 1
     * @return array Result with success and failed counts
     */
    public function bulkUpdateAutoEmailPreference($userIds, $autoEmail) {
        if (empty($userIds) || !is_array($userIds)) {
            return ['success' => 0, 'failed' => 0];
        }
        
        $autoEmail = $autoEmail ? 1 : 0;
        
        $success = 0;
        $failed = 0;
        
        foreach ($userIds as $userId) {
            if ($this->updateAutoEmailPreference($userId, $autoEmail)) {
                $success++;
            } else {
                $failed++;
            }
        }
        
        return ['success' => $success, 'failed' => $failed];
    }
    
    /**
     * Get auto email statistics
     * @return array Stats including enabled count, disabled count, total
     */
    public function getAutoEmailStats() {
        $sql = "SELECT 
                    SUM(CASE WHEN AUTO_EMAIL = 1 THEN 1 ELSE 0 END) as enabled_count,
                    SUM(CASE WHEN AUTO_EMAIL = 0 THEN 1 ELSE 0 END) as disabled_count,
                    SUM(CASE WHEN AUTO_EMAIL IS NULL THEN 1 ELSE 0 END) as null_count,
                    COUNT(*) as total_count
                FROM vw_client_profiles";
        $result = $this->selectSingle($sql);
        
        if (!$result) {
            return ['enabled_count' => 0, 'disabled_count' => 0, 'null_count' => 0, 'total_count' => 0];
        }
        
        return $result;
    }



    // --- Nifty Data Update Operations ---

    public function insertOrUpdateNifty500($date, $nifty500) {
        $stmt = self::$connect->prepare(
            "INSERT INTO nifty_historical_data (trade_date, nifty500) 
             VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE 
             nifty500 = VALUES(nifty500)"
        );
        $stmt->bind_param("sd", $date, $nifty500);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $error = $stmt->error;
        $stmt->close();
        return ['success' => $success, 'affected_rows' => $affected_rows, 'error' => $error];
    }

    public function insertOrUpdateNiftyData($date, $nifty50, $nifty500) {
        $stmt = self::$connect->prepare(
            "INSERT INTO nifty_historical_data (trade_date, nifty50, nifty500) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE 
             nifty50 = COALESCE(VALUES(nifty50), nifty50),
             nifty500 = COALESCE(VALUES(nifty500), nifty500)"
        );
        $stmt->bind_param("sdd", $date, $nifty50, $nifty500);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $error = $stmt->error;
        $stmt->close();
        return ['success' => $success, 'affected_rows' => $affected_rows, 'error' => $error];
    }

    public function insertOrUpdateManualNiftyData($date, $nifty50, $nifty500) {
        $stmt = self::$connect->prepare(
            "INSERT INTO nifty_historical_data (trade_date, nifty50, nifty500) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE 
             nifty50 = VALUES(nifty50), 
             nifty500 = VALUES(nifty500)"
        );
        $stmt->bind_param("sdd", $date, $nifty50, $nifty500);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $error = $stmt->error;
        $stmt->close();
        return ['success' => $success, 'affected_rows' => $affected_rows, 'error' => $error];
    }

    public function getNiftyDatabaseStatus($limit = 10) {
        $result = self::$connect->query("SELECT MAX(trade_date) AS last_date FROM nifty_historical_data");
        $lastDate = $result->fetch_assoc()['last_date'];

        $countResult = self::$connect->query("SELECT COUNT(*) as total FROM nifty_historical_data");
        $totalRecords = $countResult->fetch_assoc()['total'];

        $recentResult = self::$connect->query(
            "SELECT trade_date, nifty50, nifty500 
             FROM nifty_historical_data 
             ORDER BY trade_date DESC 
             LIMIT " . intval($limit)
        );
        $recentRecords = [];
        if ($recentResult) {
            while ($row = $recentResult->fetch_assoc()) {
                $recentRecords[] = $row;
            }
        }

        return [
            'last_date' => $lastDate,
            'total_records' => $totalRecords,
            'recent_records' => $recentRecords
        ];
    }

    public function insertOrUpdateMcapData($isin, $month_year, $large_cap, $mid_cap, $small_cap, $cash, $debt) {
        $stmt = self::$connect->prepare(
            "INSERT INTO tb_funds_mcap_data (isin, month_year, large_cap, mid_cap, small_cap, cash, debt)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
             large_cap = VALUES(large_cap), 
             mid_cap = VALUES(mid_cap), 
             small_cap = VALUES(small_cap), 
             cash = VALUES(cash), 
             debt = VALUES(debt)"
        );
        if (!$stmt) {
            return ['success' => false, 'error' => self::$connect->error];
        }
        $stmt->bind_param("ssddddd", $isin, $month_year, $large_cap, $mid_cap, $small_cap, $cash, $debt);
        $success = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        return ['success' => $success, 'error' => $error];
    }


    public function getDynamicId($dbtable, $fieldname, $data, $fieldid) {
        $dbtable = self::$connect->real_escape_string($dbtable);
        $fieldname = self::$connect->real_escape_string($fieldname);
        $data = self::$connect->real_escape_string($data);
        $fieldid = self::$connect->real_escape_string($fieldid);
        $query = "SELECT `$fieldid` FROM `$dbtable` WHERE `$fieldname` = '$data' LIMIT 1";
        $result = self::$connect->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row[$fieldid];
        }
        return 0;
    }

    public function getDynamicIdFromAlias($dbtable, $fieldname, $data, $fieldid) {
        $data = self::$connect->real_escape_string($data);
        $query = "SELECT actualname FROM aliastable WHERE aliasname = '$data' LIMIT 1";
        $result = self::$connect->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $this->getDynamicId($dbtable, $fieldname, $row["actualname"], $fieldid);
        }
        return 0;
    }

    public function insertOrUpdateMFMasterBulk($str, $amcCode, $productCode, $fieldsName) {
        $amcCode = self::$connect->real_escape_string($amcCode);
        $productCode = self::$connect->real_escape_string($productCode);
        
        $query = "SELECT sl_no FROM tb_mf_master WHERE amc_code = '$amcCode' AND product_code = '$productCode' LIMIT 1";
        $result = self::$connect->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $slNo = $row['sl_no'];
            
            $updateSet = [];
            for ($i = 0; $i < 47; $i++) {
                if (isset($fieldsName[$i]) && isset($str[$i])) {
                    $updateSet[] = "`".$self::$connect->real_escape_string($fieldsName[$i])."` = ".$str[$i];
                }
            }
            if (count($updateSet) > 0) {
                $query1 = "UPDATE tb_mf_master SET " . implode(", ", $updateSet) . " WHERE sl_no = $slNo";
                $result1 = self::$connect->query($query1);
                return $result1 ? "Updated Successfully" : "Updation Failed";
            }
            return "Updation Failed";
        } else {
            $cols = [];
            $vals = [];
            for ($i = 0; $i < 47; $i++) {
                if (isset($fieldsName[$i]) && isset($str[$i])) {
                    $cols[] = "`".$self::$connect->real_escape_string($fieldsName[$i])."`";
                    $vals[] = $str[$i];
                }
            }
            if (count($cols) > 0) {
                $query2 = "INSERT INTO tb_mf_master (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
                $result2 = self::$connect->query($query2);
                return $result2 ? "Inserted Successfully" : "Insert Failed";
            }
            return "Insert Failed";
        }
    }

    public function insertAdminLog($logmsg, $remarks) {
        $logmsg = self::$connect->real_escape_string($logmsg);
        $remarks = $remarks !== null ? "'" . self::$connect->real_escape_string($remarks) . "'" : "NULL";
        $query = "INSERT INTO `tb_admin_log`(`logmsg`, `remarks`) VALUES ('$logmsg', $remarks)";
        return self::$connect->query($query);
    }

    public function insertDynamicRecord($databasetable, $fieldList, $fieldValue) {
        $databasetable = self::$connect->real_escape_string($databasetable);
        
        $whereClause = [];
        foreach ($fieldList as $i => $field) {
            $whereClause[] = "`" . self::$connect->real_escape_string($field) . "` = '" . self::$connect->real_escape_string($fieldValue[$i]) . "'";
        }
        $query = "SELECT * FROM `$databasetable` WHERE " . implode(" AND ", $whereClause) . " LIMIT 1";
        $recResult = self::$connect->query($query);
        
        if ($recResult && $recResult->num_rows == 0) {
            $cols = [];
            $vals = [];
            foreach ($fieldList as $i => $field) {
                $cols[] = "`" . self::$connect->real_escape_string($field) . "`";
                $vals[] = "'" . self::$connect->real_escape_string($fieldValue[$i]) . "'";
            }
            $insertQuery = "INSERT INTO `$databasetable` (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
            $insertTable = self::$connect->query($insertQuery);
            return true;
        }
        return false;
    }


    public function insertDynamicRow($dbtable, $insertFields, $insertValues) {
        $dbtable = self::$connect->real_escape_string($dbtable);
        $fieldsStr = implode(", ", $insertFields);
        $valuesStr = implode(", ", $insertValues);
        $query = "INSERT INTO `$dbtable` ($fieldsStr) VALUES ($valuesStr)";
        return self::$connect->query($query);
    }

    public function deleteDynamicRows($dbtable, $uniqueFields, $row) {
        $dbtable = self::$connect->real_escape_string($dbtable);
        $whereParts = [];
        foreach ($uniqueFields as $field) {
            $val = self::$connect->real_escape_string($row[$field]);
            $whereParts[] = "`$field` = '$val'";
        }
        $whereStr = implode(" AND ", $whereParts);
        $query = "DELETE FROM `$dbtable` WHERE $whereStr";
        return self::$connect->query($query);
    }


    public function countDynamicRows($dbtable, $uniqueFields, $row) {
        $dbtable = self::$connect->real_escape_string($dbtable);
        $whereParts = [];
        foreach ($uniqueFields as $field) {
            $val = self::$connect->real_escape_string($row[$field]);
            $whereParts[] = "`$field` = '$val'";
        }
        $whereStr = implode(" AND ", $whereParts);
        $query = "SELECT COUNT(*) FROM `$dbtable` WHERE $whereStr";
        $result = self::$connect->query($query);
        if ($result) {
            return $result->fetch_row()[0];
        }
        return 0;
    }


    public function insertOrUpdateDynamicRecordWithConditions($db_table, $primaryKey, $fieldValues, $conditionsStr, $tableColumns) {
        $db_table = self::$connect->real_escape_string($db_table);
        $primaryKey = self::$connect->real_escape_string($primaryKey);
        
        $fields = [];
        $values = [];
        $firstField = null;
        $firstValue = null;

        foreach ($fieldValues as $field => $value) {
            if (array_key_exists($field, $tableColumns) || in_array($field, $tableColumns, true)) {
                $fields[] = "`" . self::$connect->real_escape_string($field) . "`";
                if ($value === null || $value === 'NULL' || $value === '') {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . self::$connect->real_escape_string($value) . "'";
                }
                if ($firstField === null) {
                    $firstField = $field;
                    $firstValue = $value;
                }
            }
        }

        if (empty($fields)) {
            return "Insert Failed: No valid fields to insert";
        }

        if (!empty($conditionsStr)) {
            $query = "SELECT `$primaryKey` FROM `$db_table` WHERE $conditionsStr";
            $result = self::$connect->query($query);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $recordId = $row[$primaryKey];
                
                $updateFields = [];
                for ($i = 0; $i < count($fields); $i++) {
                    if ($values[$i] !== "NULL") {
                        $updateFields[] = $fields[$i] . " = " . $values[$i];
                    }
                }

                if (!empty($updateFields)) {
                    $updateQuery = "UPDATE `$db_table` SET " . implode(", ", $updateFields) . " WHERE `$primaryKey` = '$recordId'";
                    $result1 = self::$connect->query($updateQuery);
                    
                    if ($result1) {
                        return "Updated Successfully";
                    } else {
                        return "Updation Failed - " . self::$connect->error;
                    }
                } else {
                    return "Updated Successfully (No changes)";
                }
            }
        }

        $insertQuery = "INSERT INTO `$db_table` (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
        $result = self::$connect->query($insertQuery);
        
        if ($result) {
            return "Inserted Successfully";
        } else {
            return "<br>Insert Failed ($firstField: $firstValue) - " . self::$connect->error;
        }
    }


    public function getDistinctISINs() {
        $pdo = $this->getPDO();
        $sql = "
            SELECT DISTINCT 
                TRIM(isin) AS isin,
                MAX(scheme_name) AS scheme_name
            FROM tb_transaction_mailback
            WHERE isin IS NOT NULL AND isin != ''
            GROUP BY isin
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertFundYahooData($f) {
        $pdo = $this->getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO fund_yahoo_data 
            (isin, scheme_name, month, top_holdings, sector_allocation, trailing_returns, risk_statistics, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                top_holdings = VALUES(top_holdings),
                sector_allocation = VALUES(sector_allocation),
                trailing_returns = VALUES(trailing_returns),
                risk_statistics = VALUES(risk_statistics),
                status = VALUES(status),
                error_message = VALUES(error_message)
        ");
        
        $stmt->execute([
            $f['isin'] ?? null,
            $f['scheme_name'] ?? null,
            $f['month'],
            json_encode($f['top_holdings'] ?? []),
            json_encode($f['sector_allocation'] ?? []),
            json_encode($f['trailing_returns'] ?? []),
            json_encode($f['risk_statistics'] ?? []),
            $f['status'],
            $f['error'] ?? null
        ]);
    }


    public function getValidCasTransactions($client_code) {
        $stmt = self::$connect->prepare("SELECT * FROM tb_cas_transactions WHERE client_code = ? AND validation_status NOT LIKE '%Mismatch%' ORDER BY date ASC");
        $stmt->bind_param("s", $client_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        return $transactions;
    }


    public function upsertAssignedGoal($iin, $folio, $fund_code, $goal_id) {
        $iin = self::$connect->real_escape_string($iin);
        $folio = self::$connect->real_escape_string($folio);
        $fund_code = self::$connect->real_escape_string($fund_code);
        $goal_id = self::$connect->real_escape_string($goal_id);

        $check = $this->selectSingle("SELECT id FROM assigned_goals WHERE IIN='$iin' AND amc_code='$folio' AND fund_code='$fund_code'");
        if ($check) {
            $query = "UPDATE assigned_goals SET goal_id='$goal_id' WHERE id='{$check['id']}'";
        } else {
            $query = "INSERT INTO assigned_goals (IIN, amc_code, fund_code, goal_id) VALUES ('$iin', '$folio', '$fund_code', '$goal_id')";
        }
        return $this->query($query);
    }

    public function getGoalsByIIN($iin) {
        $iin = self::$connect->real_escape_string($iin);
        return $this->select("SELECT id, name FROM goals WHERE iin='$iin'");
    }


    public function updateUserAutoEmail($userId, $autoEmail) {
        $userId = (int)$userId;
        $autoEmail = (int)$autoEmail;
        $query = "UPDATE vw_client_profiles SET AUTO_EMAIL = $autoEmail WHERE id = $userId";
        return self::$connect->query($query);
    }


    public function fetchCasTableData($subAdminId = null) {
        if ($subAdminId !== null) {
            $subAdminId = intval($subAdminId);
            $sql = "SELECT c.*, u.PRIMARY_HOLDER_NAME, u.PRIMARY_HOLDER_PAN 
                    FROM tb_cas_stm c
                    INNER JOIN vw_client_profiles u ON c.CLIENT_CODE = u.CLIENT_CODE
                    WHERE u.assigned_subadmin_id = $subAdminId
                    ORDER BY c.slno DESC";
        } else {
            $sql = "SELECT c.*, u.PRIMARY_HOLDER_NAME, u.PRIMARY_HOLDER_PAN 
                    FROM tb_cas_stm c
                    LEFT JOIN vw_client_profiles u ON c.CLIENT_CODE = u.CLIENT_CODE
                    ORDER BY c.slno DESC";
        }
        return $this->select($sql) ?: [];
    }

    public function getCasFile($client_code) {
        $stmtSel = self::$connect->prepare("SELECT cas_file FROM tb_cas_stm WHERE CLIENT_CODE = ?");
        $stmtSel->bind_param("s", $client_code);
        $stmtSel->execute();
        $resSel = $stmtSel->get_result();
        if ($resSel && $rowSel = $resSel->fetch_assoc()) {
            return $rowSel['cas_file'];
        }
        return null;
    }

    public function deleteCasStatements($client_code) {
        $stmtDel = self::$connect->prepare("DELETE FROM tb_cas_stm WHERE CLIENT_CODE = ?");
        $stmtDel->bind_param("s", $client_code);
        return $stmtDel->execute();
    }

    public function deleteCasSummary($client_code) {
        $stmtDelSum = self::$connect->prepare("DELETE FROM tb_cas_summary WHERE client_code = ?");
        $stmtDelSum->bind_param("s", $client_code);
        return $stmtDelSum->execute();
    }

    public function deleteCasTransactions($client_code) {
        $stmtDelTxn = self::$connect->prepare("DELETE FROM tb_cas_transactions WHERE client_code = ?");
        $stmtDelTxn->bind_param("s", $client_code);
        return $stmtDelTxn->execute();
    }

    public function insertCasSummary($client_code, $amc, $fund_name, $folio, $isin, $opening_balance, $reported_closing, $calculated_closing, $unit_difference, $status) {
        $stmtSum = self::$connect->prepare("INSERT INTO tb_cas_summary (client_code, amc, fund_name, folio, isin, opening_balance, reported_closing, calculated_closing, unit_difference, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtSum->bind_param("sssssdddds", $client_code, $amc, $fund_name, $folio, $isin, $opening_balance, $reported_closing, $calculated_closing, $unit_difference, $status);
        $stmtSum->execute();
        return $stmtSum->affected_rows;
    }

    public function insertCasTransaction($client_code, $folio, $scheme_name, $amc, $transaction_type, $date, $amount, $units, $nav, $description, $advisor_arn, $isin, $validation_status) {
        $stmtTxn = self::$connect->prepare("INSERT INTO tb_cas_transactions (client_code, folio, scheme_name, amc, transaction_type, date, amount, units, nav, description, advisor_arn, isin, validation_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtTxn->bind_param("ssssssdddssss", $client_code, $folio, $scheme_name, $amc, $transaction_type, $date, $amount, $units, $nav, $description, $advisor_arn, $isin, $validation_status);
        $stmtTxn->execute();
        return $stmtTxn->affected_rows;
    }


    public function updateMissingPanByFolio($folio, $pan) {
        $folioEscaped = self::$connect->real_escape_string($folio);
        $panEscaped = self::$connect->real_escape_string($pan);
        $sql = "UPDATE tb_transaction_mailback SET pan1 = '$panEscaped' WHERE folio_number = '$folioEscaped' AND (pan1 IS NULL OR pan1 = '')";
        return $this->query($sql);
    }


    public function getDatabaseConfig() {
        return $this->config_data['database'] ?? [];
    }


    public function checkClientExistsByCode($clientCode) {
        $clientCode = $this->safeEscape($clientCode);
        $sql = "SELECT id FROM tb_users WHERE CLIENT_CODE = '$clientCode'";
        $res = $this->select($sql);
        return !empty($res);
    }

    public function getClientDBRecordByCode($clientCode) {
        $clientCode = $this->safeEscape($clientCode);
        $sql = "SELECT * FROM tb_users WHERE CLIENT_CODE = '$clientCode' LIMIT 1";
        $res = $this->select($sql);
        return !empty($res) ? $res[0] : null;
    }

    public function updateClientRecordFromReport($clientData) {
        $userId = isset($clientData['userId']) ? (int)$clientData['userId'] : 0;
        $clientCode = $this->safeEscape($clientData['clientCode']);
        $name = $this->safeEscape($clientData['primaryHolderName']);
        $pan = $this->safeEscape($clientData['primaryHolderPan']);
        $email = $this->safeEscape($clientData['email']);
        $mobile = $this->safeEscape($clientData['mobileNo']);
        $dob = $this->safeEscape($clientData['formattedDob']);
        $tax = $this->safeEscape($clientData['taxStatusCode']);
        $hold = $this->safeEscape($clientData['holdingNatureCode']);
        $acc = $this->safeEscape($clientData['accountNo1']);
        $ifsc = $this->safeEscape($clientData['ifscCode1']);
        $bank = $this->safeEscape($clientData['bdBankCode']);

        if ($userId > 0) {
            $sql = "UPDATE tb_users SET PRIMARY_HOLDER_NAME='$name', PAN_NO='$pan', EMAIL='$email', INDIAN_MOBILE_NO='$mobile', DOB='$dob', TAX_STATUS='$tax', HOLDING_NATURE='$hold', ACCOUNT_NO='$acc', IFSC_CODE='$ifsc', DEFAULT_BANK='$bank' WHERE id = $userId";
            $this->update($sql);
        } else {
            $sql = "UPDATE tb_users SET PRIMARY_HOLDER_NAME='$name', PAN_NO='$pan', EMAIL='$email', INDIAN_MOBILE_NO='$mobile', DOB='$dob', TAX_STATUS='$tax', HOLDING_NATURE='$hold', ACCOUNT_NO='$acc', IFSC_CODE='$ifsc', DEFAULT_BANK='$bank' WHERE CLIENT_CODE = '$clientCode'";
            $this->update($sql);
        }
    }

    public function insertClientRecordFromReport($clientData) {
        $clientCode = $this->safeEscape($clientData['clientCode']);
        $name = $this->safeEscape($clientData['primaryHolderName']);
        $pan = $this->safeEscape($clientData['primaryHolderPan']);
        $email = $this->safeEscape($clientData['email']);
        $mobile = $this->safeEscape($clientData['mobileNo']);
        $dob = $this->safeEscape($clientData['formattedDob']);
        $tax = $this->safeEscape($clientData['taxStatusCode']);
        $hold = $this->safeEscape($clientData['holdingNatureCode']);
        $acc = $this->safeEscape($clientData['accountNo1']);
        $ifsc = $this->safeEscape($clientData['ifscCode1']);
        $bank = $this->safeEscape($clientData['bdBankCode']);
        $created = $this->safeEscape($clientData['nseCreatedAt']);

    $sql = "INSERT INTO tb_users (
                CLIENT_CODE, PRIMARY_HOLDER_NAME, PRIMARY_HOLDER_PAN,
                EMAIL, INDIAN_MOBILE_NO, PRIMARY_HOLDER_DOB,
                TAX_STATUS, HOLDING_NATURE,
                ACCOUNT_NO_1, IFSC_CODE_1, BD_BANK_CODE_1,
                CREATED_AT, UPDATED_AT
            ) VALUES (
                '$clientCode', '$name', '$pan',
                '$email', '$mobile', '$dob',
                '$tax', '$hold',
                '$acc', '$ifsc', '$bank',
                '$created', NOW()
            )";
    return $this->insertQuery($sql);
}
    
	  /**
 * Resolve tax status code from display name via mtb_tax_status
 * Table: code | tax_status
 */
public function resolveTaxStatusCode($taxStatusDisplay) {
    if (empty($taxStatusDisplay)) return '';

    // If it already looks like a code (numeric), return as-is
    if (is_numeric($taxStatusDisplay)) return $taxStatusDisplay;

    $escaped = $this->safeEscape(trim($taxStatusDisplay));

    // First try exact match on tax_status display name
    $row = $this->selectSingle(
        "SELECT code FROM mtb_tax_status WHERE tax_status = '$escaped' LIMIT 1"
    );
    if ($row) return $row['code'];

    // Fallback: case-insensitive match
    $row = $this->selectSingle(
        "SELECT code FROM mtb_tax_status WHERE UPPER(tax_status) = UPPER('$escaped') LIMIT 1"
    );
    if ($row) return $row['code'];

    // Return original value if no match found
    return $taxStatusDisplay;
}

/**
 * Resolve holding nature code from display name via mtb_client_holding
 * Table: code | details
 */
public function resolveHoldingNatureCode($holdingNatureDisplay) {
    if (empty($holdingNatureDisplay)) return '';

    $escaped = $this->safeEscape(trim($holdingNatureDisplay));

    // First try exact match on details
    $row = $this->selectSingle(
        "SELECT code FROM mtb_client_holding WHERE details = '$escaped' LIMIT 1"
    );
    if ($row) return $row['code'];

    // Fallback: case-insensitive match
    $row = $this->selectSingle(
        "SELECT code FROM mtb_client_holding WHERE UPPER(details) = UPPER('$escaped') LIMIT 1"
    );
    if ($row) return $row['code'];

    // If it already looks like a short code (e.g. SI/JO/AS), return as-is
    if (strlen($holdingNatureDisplay) <= 3) return $holdingNatureDisplay;

    return $holdingNatureDisplay;
}

/**
 * Resolve bank code from bank name via mtb_billdeskbank
 * Table: BD_BANK_CODE | BD_BANK_NAME
 */
public function resolveBankCode($bankName) {
    if (empty($bankName)) return '';

    $escaped = $this->safeEscape(trim($bankName));

    // First try exact match on BD_BANK_NAME
    $row = $this->selectSingle(
        "SELECT BD_BANK_CODE FROM mtb_billdeskbank WHERE BD_BANK_NAME = '$escaped' AND BANK_CATEGORY = 'Net Banking' LIMIT 1"
    );
    if ($row) return $row['BD_BANK_CODE'];

    // Fallback: case-insensitive match
    $row = $this->selectSingle(
        "SELECT BD_BANK_CODE FROM mtb_billdeskbank WHERE UPPER(BD_BANK_NAME) = UPPER('$escaped') AND BANK_CATEGORY = 'Net Banking' LIMIT 1"
    );
    if ($row) return $row['BD_BANK_CODE'];

    // Fallback: partial match (e.g. "HDFC Bank" matches "HDFC")
    $row = $this->selectSingle(
        "SELECT BD_BANK_CODE FROM mtb_billdeskbank WHERE BD_BANK_NAME LIKE '%$escaped%' AND BANK_CATEGORY = 'Net Banking' LIMIT 1"
    );
    if ($row) return $row['BD_BANK_CODE'];

    // If already looks like a bank code (short uppercase), return as-is
    if (strlen($bankName) <= 10 && strtoupper($bankName) === $bankName) return $bankName;

    return $bankName;
    }
	
	 /**
 * Get all UCC from tb_users table
 * This is used for the master data autocomplete functionality
 * 
 * @return array List of UCC with client names from tb_users
 */
public function getAllUCCFromUsers()
{
    $sql = "SELECT CLIENT_CODE, PRIMARY_HOLDER_NAME 
            FROM tb_users 
            WHERE CLIENT_CODE IS NOT NULL 
            AND CLIENT_CODE != '' 
            AND PRIMARY_HOLDER_NAME IS NOT NULL 
            AND PRIMARY_HOLDER_NAME != ''
            ORDER BY CLIENT_CODE";
    return $this->select($sql);
}
/**
 * Update client master data in tb_users table
 * This is a dedicated method for updating existing client records
 * 
 * @param array $apiData Client master data from NSE API
 * @return array Result with success status and message
 */
public function updateClientMasterData($apiData)
{
    $conn = $this->getConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $clientCode = $this->safeEscape($apiData['client_code'] ?? '');
        
        if (empty($clientCode)) {
            throw new Exception('Client code is required');
        }

        // Check if user exists in tb_users
        $existingUser = $this->getClientByCode($clientCode);
        
        if ($existingUser === false) {
            // User doesn't exist, insert instead
            error_log("User with CLIENT_CODE: $clientCode not found in tb_users, inserting new record");
            $conn->rollback();
            return $this->saveClientMasterData($apiData);
        }

        //error_log("Updating existing user with CLIENT_CODE: $clientCode");
        //error_log("Existing user data: " . print_r($existingUser, true));

        // Helper function to concatenate name parts
        $concatName = function($first, $middle, $last) {
            $first = trim($first ?? '');
            $middle = trim($middle ?? '');
            $last = trim($last ?? '');
            $parts = array_filter([$first, $middle, $last]);
            return !empty($parts) ? implode(' ', $parts) : '';
        };
        
        // Helper function to format date
        $formatDate = function($dateStr) {
            if (empty($dateStr) || trim($dateStr) === ' ' || trim($dateStr) === '') {
                return null;
            }
            $dateStr = trim($dateStr);
            
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $matches)) {
                return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dateStr, $matches)) {
                return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr)) {
                return $dateStr;
            }
            return $dateStr;
        };
        
        // Helper to get value or empty if null/space
        $getValue = function($val) {
            if ($val === null) return '';
            $val = trim($val);
            if ($val === ' ' || $val === '') return '';
            return $val;
        };

        // Helper to format gender to 1 character ('M', 'F', 'O')
        $formatGender = function($genderStr) {
            if (empty($genderStr)) return '';
            $g = strtoupper(trim((string)$genderStr));
            if ($g === 'MALE' || $g === 'M') return 'M';
            if ($g === 'FEMALE' || $g === 'F') return 'F';
            if ($g === 'OTHER' || $g === 'OTHERS' || $g === 'O') return 'O';
            return substr($g, 0, 1);
        };

        // Helper to format nomination to 1 character ('Y', 'N')
        $formatNomination = function($nomVal) {
            if (empty($nomVal)) return '';
            $n = strtoupper(trim((string)$nomVal));
            if ($n === 'Y' || $n === 'YES' || $n === 'OPT-IN' || $n === 'OPT_IN') return 'Y';
            if ($n === 'N' || $n === 'NO' || $n === 'OPT-OUT' || $n === 'OPT_OUT') return 'N';
            return substr($n, 0, 1);
        };
        
        // ================================================================
        // MASTER TABLE LOOKUP FUNCTIONS - ADDED FOR updateClientMasterData
        // ================================================================
        
        /**
         * Get tax status code from mtb_tax_status
         * Table: code, tax_status
         */
        $getTaxStatusCode = function($taxStatusDisplay) {
            if (empty($taxStatusDisplay) || trim($taxStatusDisplay) === ' ') {
                error_log("TAX_STATUS: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($taxStatusDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("TAX_STATUS lookup (update): Looking for '$original'");
            
            $queries = [
                "SELECT `code` FROM `mtb_tax_status` WHERE `tax_status` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_tax_status` WHERE UPPER(`tax_status`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_tax_status` WHERE `tax_status` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("TAX_STATUS QUERY (update): $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    //error_log("TAX_STATUS FOUND (update): '$original' -> '{$result['code']}'");
                    return $result['code'];
                }
            }
            
            error_log("TAX_STATUS NOT FOUND (update) for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get occupation code from mtb_occupation_code
         * Table: code, details
         */
        $getOccupationCode = function($occupationDisplay) {
            if (empty($occupationDisplay) || trim($occupationDisplay) === ' ') {
                error_log("OCCUPATION_CODE: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($occupationDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("OCCUPATION_CODE lookup (update): Looking for '$original'");
            
            $queries = [
                "SELECT `code` FROM `mtb_occupation_code` WHERE `details` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_occupation_code` WHERE UPPER(`details`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_occupation_code` WHERE `details` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("OCCUPATION_CODE QUERY (update): $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    //error_log("OCCUPATION_CODE FOUND (update): '$original' -> '{$result['code']}'");
                    return $result['code'];
                }
            }
            
            error_log("OCCUPATION_CODE NOT FOUND (update) for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get holding nature code from mtb_client_holding
         * Table: code, details
         */
        $getHoldingNatureCode = function($holdingNatureDisplay) {
            if (empty($holdingNatureDisplay) || trim($holdingNatureDisplay) === ' ') {
                error_log("HOLDING_NATURE: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($holdingNatureDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("HOLDING_NATURE lookup (update): Looking for '$original'");
            
            $queries = [
                "SELECT `code` FROM `mtb_client_holding` WHERE `details` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_client_holding` WHERE UPPER(`details`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_client_holding` WHERE `details` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("HOLDING_NATURE QUERY (update): $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    //error_log("HOLDING_NATURE FOUND (update): '$original' -> '{$result['code']}'");
                    return $result['code'];
                }
            }
            
            error_log("HOLDING_NATURE NOT FOUND (update) for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get authentication code from mtb_nominee_auth
         * Table: id, code, description
         */
        $getAuthenticationCode = function($authDisplay) {
            if (empty($authDisplay) || trim($authDisplay) === ' ') {
                error_log("AUTHENTICATION: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($authDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("AUTHENTICATION lookup (update): Looking for '$original'");
            
            $queries = [
                "SELECT `code` FROM `mtb_nominee_auth` WHERE `description` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_auth` WHERE UPPER(`description`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_auth` WHERE `code` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_auth` WHERE UPPER(`code`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_auth` WHERE `description` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("AUTHENTICATION QUERY (update): $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    //error_log("AUTHENTICATION FOUND (update): '$original' -> '{$result['code']}'");
                    return $result['code'];
                }
            }
            
            error_log("AUTHENTICATION NOT FOUND (update) for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get nominee identity type code from mtb_nominee_identity
         * Table: identity_type_code, identity_type_name
         */
        $getNomineeIdentityCode = function($identityDisplay) {
            if (empty($identityDisplay) || trim($identityDisplay) === ' ') {
                error_log("NOMINEE_IDENTITY: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($identityDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("NOMINEE_IDENTITY lookup (update): Looking for '$original'");
            
            $queries = [
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_name` = '$escaped' LIMIT 1",
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE UPPER(`identity_type_name`) = UPPER('$escaped') LIMIT 1",
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_code` = '$escaped' LIMIT 1",
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE UPPER(`identity_type_code`) = UPPER('$escaped') LIMIT 1",
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_name` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("NOMINEE_IDENTITY QUERY (update): $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['identity_type_code']) && !empty($result['identity_type_code'])) {
                    //error_log("NOMINEE_IDENTITY FOUND (update): '$original' -> '{$result['identity_type_code']}'");
                    return $result['identity_type_code'];
                }
            }
            
            error_log("NOMINEE_IDENTITY NOT FOUND (update) for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get bank code from mtb_billdeskbank
         */
        $getBankCode = function($bankName) {
            if (empty($bankName) || trim($bankName) === ' ') return '';
            
            $original = trim($bankName);
            $escaped = $this->safeEscape($original);
            
            //error_log("BANK lookup (update): Looking for '$original'");
            
            $queries = [
                "SELECT `BD_BANK_CODE` FROM `mtb_billdeskbank` WHERE `BD_BANK_NAME` = '$escaped' AND `BANK_CATEGORY` = 'Net Banking' LIMIT 1",
                "SELECT `BD_BANK_CODE` FROM `mtb_billdeskbank` WHERE UPPER(`BD_BANK_NAME`) = UPPER('$escaped') AND `BANK_CATEGORY` = 'Net Banking' LIMIT 1",
                "SELECT `BD_BANK_CODE` FROM `mtb_billdeskbank` WHERE `BD_BANK_NAME` LIKE '$escaped%' AND `BANK_CATEGORY` = 'Net Banking' LIMIT 1",
                "SELECT `BD_BANK_CODE` FROM `mtb_billdeskbank` WHERE `BD_BANK_NAME` LIKE '%$escaped%' AND `BANK_CATEGORY` = 'Net Banking' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("BANK QUERY (update): $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['BD_BANK_CODE']) && !empty($result['BD_BANK_CODE'])) {
                    //error_log("BANK FOUND (update): '$original' -> '{$result['BD_BANK_CODE']}'");
                    return $result['BD_BANK_CODE'];
                }
            }
            
            error_log("BANK NOT FOUND (update) for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get account type from mtb_bank_acc_type
         */
        $getAccountTypeCode = function($accountTypeDisplay) {
            if (empty($accountTypeDisplay) || trim($accountTypeDisplay) === ' ') return '';
            
            $original = trim($accountTypeDisplay);
            $escaped = $this->safeEscape($original);
            
            $queries = [
                "SELECT `code` FROM `mtb_bank_acc_type` WHERE `details` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_bank_acc_type` WHERE UPPER(`details`) = UPPER('$escaped') LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    return $result['code'];
                }
            }
            
            if (in_array(strtoupper($accountTypeDisplay), ['SB', 'CA', 'NRE', 'NRO', 'CB'])) {
                return strtoupper($accountTypeDisplay);
            }
            
            return $accountTypeDisplay;
        };
        
        /**
         * Get nominee relation code from mtb_nominee_relation
         */
        $getNomineeRelationCode = function($relationDisplay) {
            if (empty($relationDisplay) || trim($relationDisplay) === ' ') return '';
            
            $escaped = $this->safeEscape(trim($relationDisplay));
            
            $queries = [
                "SELECT `code` FROM `mtb_nominee_relation` WHERE `value` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_relation` WHERE UPPER(`value`) = UPPER('$escaped') LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    return $result['code'];
                }
            }
            
            return $relationDisplay;
        };
        
        /**
         * Get country name from mtb_country
         */
        $getCountryCode = function($countryDisplay) {
            if (empty($countryDisplay) || trim($countryDisplay) === ' ') return '';
            
            $escaped = $this->safeEscape(trim($countryDisplay));
            
            $queries = [
                "SELECT `country_name` FROM `mtb_country` WHERE `country_code` = '$escaped' LIMIT 1",
                "SELECT `country_name` FROM `mtb_country` WHERE UPPER(`country_code`) = UPPER('$escaped') LIMIT 1",
                "SELECT `country_name` FROM `mtb_country` WHERE `country_name` = '$escaped' LIMIT 1",
                "SELECT `country_name` FROM `mtb_country` WHERE UPPER(`country_name`) = UPPER('$escaped') LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                $result = $this->selectSingle($sql);
                if ($result && isset($result['country_name']) && !empty($result['country_name'])) {
                    return $result['country_name'];
                }
            }
            
            return $countryDisplay;
        };
        
        /**
         * Get state code from mtb_state
         */
        $getStateCode = function($stateDisplay) {
            if (empty($stateDisplay) || trim($stateDisplay) === ' ') return '';
            
            $escaped = $this->safeEscape(trim($stateDisplay));
            if (strtoupper($escaped) === 'BR' || strtoupper($escaped) === 'BIHAR') {
                return 'BH';
            }
            
            $queries = [
                "SELECT `state_code` FROM `mtb_state` WHERE `state_name` = '$escaped' LIMIT 1",
                "SELECT `state_code` FROM `mtb_state` WHERE UPPER(`state_name`) = UPPER('$escaped') LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                $result = $this->selectSingle($sql);
                if ($result && isset($result['state_code']) && !empty($result['state_code'])) {
                    $code = strtoupper($result['state_code']);
                    return ($code === 'BR') ? 'BH' : $code;
                }
            }
            
            return (strtoupper($stateDisplay) === 'BR') ? 'BH' : $stateDisplay;
        };
        
        // ================================================================
// ================================================================
// GET RESOLVED CODES FROM MASTER TABLES - USING IMPROVED MATCHING
// ================================================================

// Get raw values from API
$rawTaxStatus = $apiData['tax_status'] ?? '';
$rawOccupation = $apiData['occupation_code'] ?? '';
$rawHoldingNature = $apiData['holding_nature'] ?? '';
$rawAuthMode = $apiData['nomination_authentication_mode'] ?? '';
$rawNominee1Identity = $apiData['nominee_1_identity_type'] ?? '';
$rawNominee2Identity = $apiData['nominee_2_identity_type'] ?? '';
$rawNominee3Identity = $apiData['nominee_3_identity_type'] ?? '';

// ================================================================
// DEFINE ALL VARIABLES BEFORE USE - THIS FIXES THE UNDEFINED VARIABLE WARNINGS
// ================================================================
$bankCode = '';
$accountTypeCode = '';
$stateCode = '';
$countryCode = '';
$nominee1Relation = '';
$nominee2Relation = '';
$nominee3Relation = '';
$authenticationCode = '';
// ================================================================

// Resolve codes
$taxStatusCode = $getTaxStatusCode($rawTaxStatus);
$occupationCode = $getOccupationCode($rawOccupation);
$holdingNatureCode = $getHoldingNatureCode($rawHoldingNature);
$authenticationCode = $getAuthenticationCode($rawAuthMode);

// Get nominee identity codes with fuzzy matching
$nominee1Identity = $this->getNomineeIdentityCode(
    $apiData['nominee_1_identity_type'] ?? '',
    $apiData['nominee_1_id_number'] ?? ''
);
$nominee2Identity = $this->getNomineeIdentityCode(
    $apiData['nominee_2_identity_type'] ?? '',
    $apiData['nominee_2_id_number'] ?? ''
);
$nominee3Identity = $this->getNomineeIdentityCode(
    $apiData['nominee_3_identity_type'] ?? '',
    $apiData['nominee_3_id_number'] ?? ''
);

// Bank code from bank_name_1
$bankCode = $getBankCode($apiData['bank_name_1'] ?? '');
$accountTypeCode = $getAccountTypeCode($apiData['account_type_1'] ?? '');

// Nominee relations
$nominee1Relation = $getNomineeRelationCode($apiData['nominee_1_relationship'] ?? '');
$nominee2Relation = $getNomineeRelationCode($apiData['nominee_2_relationship'] ?? '');
$nominee3Relation = $getNomineeRelationCode($apiData['nominee_3_relationship'] ?? '');
$countryCode = $getCountryCode($apiData['country'] ?? '');
$stateCode = $getStateCode($apiData['state'] ?? '');

// Debug log
//error_log('=== RESOLVED NOMINEE IDENTITY CODES (UPDATE) ===');
//error_log('NOMINEE 1: "' . ($apiData['nominee_1_identity_type'] ?? '') . '" + "' . ($apiData['nominee_1_id_number'] ?? '') . '" -> "' . $nominee1Identity . '"');
//error_log('NOMINEE 2: "' . ($apiData['nominee_2_identity_type'] ?? '') . '" + "' . ($apiData['nominee_2_id_number'] ?? '') . '" -> "' . $nominee2Identity . '"');
//error_log('NOMINEE 3: "' . ($apiData['nominee_3_identity_type'] ?? '') . '" + "' . ($apiData['nominee_3_id_number'] ?? '') . '" -> "' . $nominee3Identity . '"');
//error_log('==================================================');
        
        // Get bank holder name
        $bankHolderName = $getValue($apiData['cheque_name'] ?? '');
        if (empty($bankHolderName)) {
            $bankHolderName = $getValue($apiData['account_holder_name'] ?? '');
        }
        if (empty($bankHolderName)) {
            $bankHolderName = $getValue($apiData['bank_holder_name'] ?? '');
        }
        if (empty($bankHolderName)) {
            $bankHolderName = $concatName(
                $apiData['primary_holder_first_name'] ?? '',
                $apiData['primary_holder_middle_name'] ?? '',
                $apiData['primary_holder_last_name'] ?? ''
            );
        }
        if (empty($bankHolderName)) {
            $bankHolderName = $getValue($apiData['primary_holder_name'] ?? '');
        }
        
        // ================================================================
        // BUILD UPDATE DATA - USING RESOLVED CODES
        // ================================================================
        
        $updateData = [
            'PRIMARY_HOLDER_NAME' => $concatName(
                $apiData['primary_holder_first_name'] ?? '',
                $apiData['primary_holder_middle_name'] ?? '',
                $apiData['primary_holder_last_name'] ?? ''
            ),
            'TAX_STATUS' => $taxStatusCode,  // FIXED: using resolved code
            'GENDER' => $formatGender($apiData['gender'] ?? ''),
            'PRIMARY_HOLDER_DOB' => $formatDate($apiData['primary_holder_dob_incorporation'] ?? ''),
            'OCCUPATION_CODE' => $occupationCode,  // FIXED: using resolved code
            'HOLDING_NATURE' => $holdingNatureCode,  // FIXED: using resolved code
            'PRIMARY_HOLDER_PAN' => $apiData['primary_holder_pan'] ?? '',
            'SECOND_HOLDER_NAME' => $concatName(
                $apiData['second_holder_first_name'] ?? '',
                $apiData['second_holder_middle_name'] ?? '',
                $apiData['second_holder_last_name'] ?? ''
            ),
            'SECOND_HOLDER_DOB' => $formatDate($apiData['second_holder_dob'] ?? ''),
            'SECOND_HOLDER_PAN' => $apiData['second_holder_pan'] ?? '',
            'SECOND_HOLDER_EMAIL' => $apiData['second_holder_email'] ?? '',
            'SECOND_HOLDER_MOBILE' => $apiData['second_holder_mobile'] ?? '',
            'THIRD_HOLDER_NAME' => $concatName(
                $apiData['third_holder_first_name'] ?? '',
                $apiData['third_holder_middle_name'] ?? '',
                $apiData['third_holder_last_name'] ?? ''
            ),
            'THIRD_HOLDER_DOB' => $formatDate($apiData['third_holder_dob'] ?? ''),
            'THIRD_HOLDER_EMAIL' => $apiData['third_holder_email'] ?? '',
            'THIRD_HOLDER_MOBILE' => $apiData['third_holder_mobile'] ?? '',
            'THIRD_HOLDER_PAN' => $apiData['third_holder_pan'] ?? '',
            'GUARDIAN_FIRST_NAME' => $concatName(
                $apiData['guardian_first_name'] ?? '',
                $apiData['guardian_middle_name'] ?? '',
                $apiData['guardian_last_name'] ?? ''
            ),
            'GUARDIAN_DOB' => $formatDate($apiData['guardian_dob'] ?? ''),
            'GUARDIAN_PAN' => $apiData['guardian_pan'] ?? '',
            'GUARDIAN_RELATIONSHIP' => $apiData['guardian_relationship'] ?? '',
            'BD_BANK_CODE_1' => $bankCode,
            'ACCOUNT_TYPE_1' => $accountTypeCode,
            'ACCOUNT_NO_1' => $apiData['account_no_1'] ?? '',
            'BANK_HOLDER_NAME_1' => $bankHolderName,
            'IFSC_CODE_1' => $apiData['ifsc_code_1'] ?? '',
            'BANK_BRANCH_1' => $apiData['bank_branch_1'] ?? '',
            'ADDRESS_1' => $apiData['address_1'] ?? '',
            'ADDRESS_2' => $apiData['address_2'] ?? '',
            'CITY' => $apiData['city'] ?? '',
            'STATE' => $stateCode,
            'PINCODE' => $apiData['pincode'] ?? '',
            'COUNTRY' => $countryCode,
            'EMAIL' => $apiData['email'] ?? '',
            'INDIAN_MOBILE_NO' => $apiData['indian_mobile_no'] ?? '',
            'FOREIGN_ADDRESS_1' => $apiData['foreign_address_1'] ?? '',
            'FOREIGN_ADDRESS_PINCODE' => $apiData['foreign_address_pincode'] ?? '',
            'FOREIGN_ADDRESS_STATE' => $apiData['foreign_address_state'] ?? '',
            'FOREIGN_ADDRESS_COUNTRY' => $apiData['foreign_address_country'] ?? '',
            'NOMINATION' => $formatNomination($apiData['nomination_opt'] ?? ''),
            'AUTHENTICATION' => $authenticationCode,  // FIXED: using resolved code
            'NOMINEE_NAMES_STATUS' => $apiData['nominee_names_registration_status_in_soa'] ?? '',
            'NOMINEE_1_NAME' => $apiData['nominee_1_name'] ?? '',
            'NOMINEE_1_RELATIONSHIP' => $nominee1Relation,
            'NOMINEE_1_APPLICABLE_PERCENT' => $apiData['nominee1_applicable'] ?? '',
            'NOMINEE_1_MINOR_FLAG' => $apiData['nominee_1_minor_flag'] ?? '',
            'NOMINEE_1_DOB' => $formatDate($apiData['nominee_1_dob'] ?? ''),
            'NOMINEE_1_GUARDIAN' => $apiData['nominee_1_guardian'] ?? '',
            'NOMINEE_1_GUARDIAN_PAN' => $apiData['nominee_1_guardian_pan'] ?? '',
            'NOMINEE_1_IDENTITY_TYPE' => $nominee1Identity,
            'NOMINEE_1_IDENTITY_NO' => $apiData['nominee_1_id_number'] ?? '',
            'NOMINEE_1_EMAIL' => $apiData['nominee_1_email'] ?? '',
            'NOMINEE_1_MOBILE' => $apiData['nominee_1_mobile'] ?? '',
            'NOMINEE_1_ADDRESS1' => $apiData['nominee_1_address1'] ?? '',
            'NOMINEE_1_CITY' => $apiData['nominee_1_city'] ?? '',
            'NOMINEE_1_PINCODE' => $apiData['nominee_1_pin'] ?? '',
            'NOMINEE_1_COUNTRY' => $getCountryCode($apiData['nominee_1_country'] ?? ''),
            'NOMINEE_2_NAME' => $apiData['nominee_2_name'] ?? '',
            'NOMINEE_2_RELATIONSHIP' => $nominee2Relation,
            'NOMINEE_2_APPLICABLE_PERCENT' => $apiData['nominee_2_applicable'] ?? '',
            'NOMINEE_2_MINOR_FLAG' => $apiData['nominee_2_minor_flag'] ?? '',
            'NOMINEE_2_DOB' => $formatDate($apiData['nominee_2_dob'] ?? ''),
            'NOMINEE_2_GUARDIAN' => $apiData['nominee_2_guardian'] ?? '',
            'NOMINEE_2_GUARDIAN_PAN' => $apiData['nominee_2_guardian_pan'] ?? '',
            'NOMINEE_2_IDENTITY_TYPE' => $nominee2Identity,
            'NOMINEE_2_IDENTITY_NO' => $apiData['nominee_2_id_number'] ?? '',
            'NOMINEE_2_EMAIL' => $apiData['nominee_2_email'] ?? '',
            'NOMINEE_2_MOBILE' => $apiData['nominee_2_mobile'] ?? '',
            'NOMINEE_2_ADDRESS1' => $apiData['nominee_2_address1'] ?? '',
            'NOMINEE_2_CITY' => $apiData['nominee_2_city'] ?? '',
            'NOMINEE_2_PINCODE' => $apiData['nominee_2_pin'] ?? '',
            'NOMINEE_2_COUNTRY' => $getCountryCode($apiData['nominee_2_country'] ?? ''),
            'NOMINEE_3_NAME' => $apiData['nominee_3_name'] ?? '',
            'NOMINEE_3_RELATIONSHIP' => $nominee3Relation,
            'NOMINEE_3_APPLICABLE_PERCENT' => $apiData['nominee_3_applicable'] ?? '',
            'NOMINEE_3_MINOR_FLAG' => $apiData['nominee_3_minor_flag'] ?? '',
            'NOMINEE_3_DOB' => $formatDate($apiData['nominee_3_dob'] ?? ''),
            'NOMINEE_3_GUARDIAN' => $apiData['nominee_3_guardian'] ?? '',
            'NOMINEE_3_GUARDIAN_PAN' => $apiData['nominee_3_guardian_pan'] ?? '',
            'NOMINEE_3_IDENTITY_TYPE' => $nominee3Identity,
            'NOMINEE_3_IDENTITY_NO' => $apiData['nominee_3_id_number'] ?? '',
            'NOMINEE_3_EMAIL' => $apiData['nominee_3_email'] ?? '',
            'NOMINEE_3_MOBILE' => $apiData['nominee_3_mobile'] ?? '',
            'NOMINEE_3_ADDRESS1' => $apiData['nominee_3_address1'] ?? '',
            'NOMINEE_3_CITY' => $apiData['nominee_3_city'] ?? '',
            'NOMINEE_3_PINCODE' => $apiData['nominee_3_pin'] ?? '',
            'NOMINEE_3_COUNTRY' => $getCountryCode($apiData['nominee_3_country'] ?? ''),
        ];

        // UCC_ACTIVATION mapping from ucc_status
        if (isset($apiData['ucc_status']) || isset($apiData['UCC_ACTIVATION'])) {
            $rawStatus = $apiData['ucc_status'] ?? $apiData['UCC_ACTIVATION'];
            $stUpper = strtoupper(trim((string)$rawStatus));
            $updateData['UCC_ACTIVATION'] = in_array($stUpper, ['ACTIVE', 'Y', 'APPROVED', 'ACTIVATED', '1'], true) ? 1 : 0;
        }

        // Filter out empty values (but keep 0 values)
        $filteredData = array_filter($updateData, function($value) {
            return $value !== '' && $value !== null;
        });

        if (empty($filteredData)) {
            throw new Exception('No data to update for client: ' . $clientCode);
        }

        // Build UPDATE query
        $updateFields = [];
        foreach ($filteredData as $key => $value) {
            $escapedValue = $this->safeEscape($value);
            $updateFields[] = "`$key` = '$escapedValue'";
        }
        $updateFields[] = "`UPDATED_AT` = NOW()";

        $sql = "UPDATE `tb_users` SET " . implode(', ', $updateFields) . " WHERE `CLIENT_CODE` = '$clientCode'";
        
        //error_log('UPDATE CLIENT MASTER SQL: ' . $sql);
        
        $result = $this->query($sql);
        
        if (!$result) {
            throw new Exception('Update failed: ' . $this->getError());
        }

        $affectedRows = $conn->affected_rows;
        //error_log('UPDATE affected rows: ' . $affectedRows);

        // Verify the update worked
        $updatedUser = $this->getClientByCode($clientCode);
        //error_log('Updated user data: ' . print_r($updatedUser, true));

        $conn->commit();
        
        return [
            'success' => true, 
            'message' => 'Client data updated successfully', 
            'action' => 'update',
            'affected_rows' => $affectedRows,
            'client_data' => $updatedUser
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('updateClientMasterData ERROR: ' . $e->getMessage());
        error_log('updateClientMasterData TRACE: ' . $e->getTraceAsString());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get a single client by client code from tb_users
 * 
 * @param string $clientCode Client code to search for
 * @return array|false Client data or false if not found
 */
public function getClientByCode($clientCode) {
    $clientCode = $this->safeEscape($clientCode);
    $sql = "SELECT * FROM `tb_users` WHERE `CLIENT_CODE` = '$clientCode' LIMIT 1";
    $result = $this->selectSingle($sql);
    //error_log('getClientByCode SQL: ' . $sql);
    //error_log('getClientByCode Result: ' . ($result ? 'FOUND' : 'NOT FOUND'));
    return $result;
}

/**
 * Save client master data from NSE API to tb_users table
 * Maps API fields to database columns using master table lookups
 * 
 * @param array $apiData Client master data from NSE API
 * @return array Result with success status and message
 */
public function saveClientMasterData($apiData)
{
    $conn = $this->getConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $clientCode = $this->safeEscape($apiData['client_code'] ?? '');
        
        if (empty($clientCode)) {
            throw new Exception('Client code is required');
        }

        // Helper function to concatenate name parts
        $concatName = function($first, $middle, $last) {
            $first = trim($first ?? '');
            $middle = trim($middle ?? '');
            $last = trim($last ?? '');
            if ($first === ' ' || $first === '') $first = '';
            if ($middle === ' ' || $middle === '') $middle = '';
            if ($last === ' ' || $last === '') $last = '';
            $parts = array_filter([$first, $middle, $last]);
            return !empty($parts) ? implode(' ', $parts) : '';
        };
        
        // Helper function to format date from DD/MM/YYYY to YYYY-MM-DD
        $formatDate = function($dateStr) {
            if (empty($dateStr) || trim($dateStr) === ' ' || trim($dateStr) === '') {
                return null;
            }
            $dateStr = trim($dateStr);
            
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $matches)) {
                return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dateStr, $matches)) {
                return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr)) {
                return $dateStr;
            }
            return $dateStr;
        };
        
        // Helper to get value or empty if null/space
        $getValue = function($val) {
            if ($val === null) return '';
            $val = trim($val);
            if ($val === ' ' || $val === '') return '';
            return $val;
        };

        // Helper to format gender to 1 character ('M', 'F', 'O')
        $formatGender = function($genderStr) {
            if (empty($genderStr)) return '';
            $g = strtoupper(trim((string)$genderStr));
            if ($g === 'MALE' || $g === 'M') return 'M';
            if ($g === 'FEMALE' || $g === 'F') return 'F';
            if ($g === 'OTHER' || $g === 'OTHERS' || $g === 'O') return 'O';
            return substr($g, 0, 1);
        };

        // Helper to format nomination to 1 character ('Y', 'N')
        $formatNomination = function($nomVal) {
            if (empty($nomVal)) return '';
            $n = strtoupper(trim((string)$nomVal));
            if ($n === 'Y' || $n === 'YES' || $n === 'OPT-IN' || $n === 'OPT_IN') return 'Y';
            if ($n === 'N' || $n === 'NO' || $n === 'OPT-OUT' || $n === 'OPT_OUT') return 'N';
            return substr($n, 0, 1);
        };
        
        // ================================================================
        // MASTER TABLE LOOKUP FUNCTIONS - FIXED WITH DEBUGGING
        // ================================================================
        
        /**
         * Get tax status code from mtb_tax_status
         * Table: code, tax_status
         */
        $getTaxStatusCode = function($taxStatusDisplay) {
            if (empty($taxStatusDisplay) || trim($taxStatusDisplay) === ' ') {
                error_log("TAX_STATUS: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($taxStatusDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("TAX_STATUS lookup: Looking for '$original'");
            
            // Try multiple approaches
            $queries = [
                "SELECT `code` FROM `mtb_tax_status` WHERE `tax_status` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_tax_status` WHERE UPPER(`tax_status`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_tax_status` WHERE `tax_status` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("TAX_STATUS QUERY: $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    //error_log("TAX_STATUS FOUND: '$original' -> '{$result['code']}'");
                    return $result['code'];
                }
            }
            
            error_log("TAX_STATUS NOT FOUND for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get occupation code from mtb_occupation_code
         * Table: code, details
         */
        $getOccupationCode = function($occupationDisplay) {
            if (empty($occupationDisplay) || trim($occupationDisplay) === ' ') {
                error_log("OCCUPATION_CODE: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($occupationDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("OCCUPATION_CODE lookup: Looking for '$original'");
            
            $queries = [
                "SELECT `code` FROM `mtb_occupation_code` WHERE `details` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_occupation_code` WHERE UPPER(`details`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_occupation_code` WHERE `details` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("OCCUPATION_CODE QUERY: $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    //error_log("OCCUPATION_CODE FOUND: '$original' -> '{$result['code']}'");
                    return $result['code'];
                }
            }
            
            error_log("OCCUPATION_CODE NOT FOUND for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get holding nature code from mtb_client_holding
         * Table: code, details
         */
        $getHoldingNatureCode = function($holdingNatureDisplay) {
            if (empty($holdingNatureDisplay) || trim($holdingNatureDisplay) === ' ') {
                error_log("HOLDING_NATURE: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($holdingNatureDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("HOLDING_NATURE lookup: Looking for '$original'");
            
            $queries = [
                "SELECT `code` FROM `mtb_client_holding` WHERE `details` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_client_holding` WHERE UPPER(`details`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_client_holding` WHERE `details` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("HOLDING_NATURE QUERY: $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    //error_log("HOLDING_NATURE FOUND: '$original' -> '{$result['code']}'");
                    return $result['code'];
                }
            }
            
            error_log("HOLDING_NATURE NOT FOUND for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get authentication code from mtb_nominee_auth
         * Table: id, code, description
         */
        $getAuthenticationCode = function($authDisplay) {
            if (empty($authDisplay) || trim($authDisplay) === ' ') {
                error_log("AUTHENTICATION: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($authDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("AUTHENTICATION lookup: Looking for '$original'");
            
            $queries = [
                "SELECT `code` FROM `mtb_nominee_auth` WHERE `description` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_auth` WHERE UPPER(`description`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_auth` WHERE `code` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_auth` WHERE UPPER(`code`) = UPPER('$escaped') LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_auth` WHERE `description` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("AUTHENTICATION QUERY: $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    //error_log("AUTHENTICATION FOUND: '$original' -> '{$result['code']}'");
                    return $result['code'];
                }
            }
            
            error_log("AUTHENTICATION NOT FOUND for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get nominee identity type code from mtb_nominee_identity
         * Table: identity_type_code, identity_type_name
         */
        $getNomineeIdentityCode = function($identityDisplay) {
            if (empty($identityDisplay) || trim($identityDisplay) === ' ') {
                error_log("NOMINEE_IDENTITY: Empty input, returning empty string");
                return '';
            }
            
            $original = trim($identityDisplay);
            $escaped = $this->safeEscape($original);
            
            //error_log("NOMINEE_IDENTITY lookup: Looking for '$original'");
            
            $queries = [
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_name` = '$escaped' LIMIT 1",
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE UPPER(`identity_type_name`) = UPPER('$escaped') LIMIT 1",
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_code` = '$escaped' LIMIT 1",
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE UPPER(`identity_type_code`) = UPPER('$escaped') LIMIT 1",
                "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_name` LIKE '%$escaped%' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("NOMINEE_IDENTITY QUERY: $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['identity_type_code']) && !empty($result['identity_type_code'])) {
                    //error_log("NOMINEE_IDENTITY FOUND: '$original' -> '{$result['identity_type_code']}'");
                    return $result['identity_type_code'];
                }
            }
            
            error_log("NOMINEE_IDENTITY NOT FOUND for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get bank code from mtb_billdeskbank
         * Table: BD_BANK_CODE, BD_BANK_NAME
         */
        $getBankCode = function($bankName) {
            if (empty($bankName) || trim($bankName) === ' ') return '';
            
            $original = trim($bankName);
            $escaped = $this->safeEscape($original);
            
            //error_log("BANK lookup: Looking for '$original'");
            
            $queries = [
                "SELECT `BD_BANK_CODE` FROM `mtb_billdeskbank` WHERE `BD_BANK_NAME` = '$escaped' AND `BANK_CATEGORY` = 'Net Banking' LIMIT 1",
                "SELECT `BD_BANK_CODE` FROM `mtb_billdeskbank` WHERE UPPER(`BD_BANK_NAME`) = UPPER('$escaped') AND `BANK_CATEGORY` = 'Net Banking' LIMIT 1",
                "SELECT `BD_BANK_CODE` FROM `mtb_billdeskbank` WHERE `BD_BANK_NAME` LIKE '$escaped%' AND `BANK_CATEGORY` = 'Net Banking' LIMIT 1",
                "SELECT `BD_BANK_CODE` FROM `mtb_billdeskbank` WHERE `BD_BANK_NAME` LIKE '%$escaped%' AND `BANK_CATEGORY` = 'Net Banking' LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                //error_log("BANK QUERY: $sql");
                $result = $this->selectSingle($sql);
                if ($result && isset($result['BD_BANK_CODE']) && !empty($result['BD_BANK_CODE'])) {
                    //error_log("BANK FOUND: '$original' -> '{$result['BD_BANK_CODE']}'");
                    return $result['BD_BANK_CODE'];
                }
            }
            
            error_log("BANK NOT FOUND for: '$original' - returning empty string");
            return '';
        };
        
        /**
         * Get account type from mtb_bank_acc_type
         */
        $getAccountTypeCode = function($accountTypeDisplay) {
            if (empty($accountTypeDisplay) || trim($accountTypeDisplay) === ' ') return '';
            
            $original = trim($accountTypeDisplay);
            $escaped = $this->safeEscape($original);
            
            $queries = [
                "SELECT `code` FROM `mtb_bank_acc_type` WHERE `details` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_bank_acc_type` WHERE UPPER(`details`) = UPPER('$escaped') LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    return $result['code'];
                }
            }
            
            if (in_array(strtoupper($accountTypeDisplay), ['SB', 'CA', 'NRE', 'NRO', 'CB'])) {
                return strtoupper($accountTypeDisplay);
            }
            
            return $accountTypeDisplay;
        };
        
        /**
         * Get nominee relation code from mtb_nominee_relation
         */
        $getNomineeRelationCode = function($relationDisplay) {
            if (empty($relationDisplay) || trim($relationDisplay) === ' ') return '';
            
            $escaped = $this->safeEscape(trim($relationDisplay));
            
            $queries = [
                "SELECT `code` FROM `mtb_nominee_relation` WHERE `value` = '$escaped' LIMIT 1",
                "SELECT `code` FROM `mtb_nominee_relation` WHERE UPPER(`value`) = UPPER('$escaped') LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                $result = $this->selectSingle($sql);
                if ($result && isset($result['code']) && !empty($result['code'])) {
                    return $result['code'];
                }
            }
            
            return $relationDisplay;
        };
        
        /**
         * Get country name from mtb_country
         */
        $getCountryCode = function($countryDisplay) {
            if (empty($countryDisplay) || trim($countryDisplay) === ' ') return '';
            
            $escaped = $this->safeEscape(trim($countryDisplay));
            
            $queries = [
                "SELECT `country_name` FROM `mtb_country` WHERE `country_code` = '$escaped' LIMIT 1",
                "SELECT `country_name` FROM `mtb_country` WHERE UPPER(`country_code`) = UPPER('$escaped') LIMIT 1",
                "SELECT `country_name` FROM `mtb_country` WHERE `country_name` = '$escaped' LIMIT 1",
                "SELECT `country_name` FROM `mtb_country` WHERE UPPER(`country_name`) = UPPER('$escaped') LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                $result = $this->selectSingle($sql);
                if ($result && isset($result['country_name']) && !empty($result['country_name'])) {
                    return $result['country_name'];
                }
            }
            
            return $countryDisplay;
        };
        
        /**
         * Get state code from mtb_state
         */
        $getStateCode = function($stateDisplay) {
            if (empty($stateDisplay) || trim($stateDisplay) === ' ') return '';
            
            $escaped = $this->safeEscape(trim($stateDisplay));
            if (strtoupper($escaped) === 'BR' || strtoupper($escaped) === 'BIHAR') {
                return 'BH';
            }
            
            $queries = [
                "SELECT `state_code` FROM `mtb_state` WHERE `state_name` = '$escaped' LIMIT 1",
                "SELECT `state_code` FROM `mtb_state` WHERE UPPER(`state_name`) = UPPER('$escaped') LIMIT 1"
            ];
            
            foreach ($queries as $sql) {
                $result = $this->selectSingle($sql);
                if ($result && isset($result['state_code']) && !empty($result['state_code'])) {
                    $code = strtoupper($result['state_code']);
                    return ($code === 'BR') ? 'BH' : $code;
                }
            }
            
            return (strtoupper($stateDisplay) === 'BR') ? 'BH' : $stateDisplay;
        };
        
       // ================================================================
// ================================================================
// GET RESOLVED CODES FROM MASTER TABLES - USING IMPROVED MATCHING
// ================================================================

// Get raw values from API
$rawTaxStatus = $apiData['tax_status'] ?? '';
$rawOccupation = $apiData['occupation_code'] ?? '';
$rawHoldingNature = $apiData['holding_nature'] ?? '';
$rawAuthMode = $apiData['nomination_authentication_mode'] ?? '';
$rawNominee1Identity = $apiData['nominee_1_identity_type'] ?? '';
$rawNominee2Identity = $apiData['nominee_2_identity_type'] ?? '';
$rawNominee3Identity = $apiData['nominee_3_identity_type'] ?? '';

// ================================================================
// DEFINE ALL VARIABLES BEFORE USE - THIS FIXES THE UNDEFINED VARIABLE WARNINGS
// ================================================================
$bankCode = '';
$accountTypeCode = '';
$stateCode = '';
$countryCode = '';
$nominee1Relation = '';
$nominee2Relation = '';
$nominee3Relation = '';
$authenticationCode = '';
// ================================================================

// Resolve codes
$taxStatusCode = $getTaxStatusCode($rawTaxStatus);
$occupationCode = $getOccupationCode($rawOccupation);
$holdingNatureCode = $getHoldingNatureCode($rawHoldingNature);
$authenticationCode = $getAuthenticationCode($rawAuthMode);

// Get nominee identity codes with fuzzy matching
$nominee1Identity = $this->getNomineeIdentityCode(
    $apiData['nominee_1_identity_type'] ?? '',
    $apiData['nominee_1_id_number'] ?? ''
);
$nominee2Identity = $this->getNomineeIdentityCode(
    $apiData['nominee_2_identity_type'] ?? '',
    $apiData['nominee_2_id_number'] ?? ''
);
$nominee3Identity = $this->getNomineeIdentityCode(
    $apiData['nominee_3_identity_type'] ?? '',
    $apiData['nominee_3_id_number'] ?? ''
);

// Bank code from bank_name_1
$bankCode = $getBankCode($apiData['bank_name_1'] ?? '');
$accountTypeCode = $getAccountTypeCode($apiData['account_type_1'] ?? '');

// Nominee relations
$nominee1Relation = $getNomineeRelationCode($apiData['nominee_1_relationship'] ?? '');
$nominee2Relation = $getNomineeRelationCode($apiData['nominee_2_relationship'] ?? '');
$nominee3Relation = $getNomineeRelationCode($apiData['nominee_3_relationship'] ?? '');
$countryCode = $getCountryCode($apiData['country'] ?? '');
$stateCode = $getStateCode($apiData['state'] ?? '');

// Debug log
//error_log('=== RESOLVED NOMINEE IDENTITY CODES ===');
//error_log('NOMINEE 1: "' . ($apiData['nominee_1_identity_type'] ?? '') . '" + "' . ($apiData['nominee_1_id_number'] ?? '') . '" -> "' . $nominee1Identity . '"');
//error_log('NOMINEE 2: "' . ($apiData['nominee_2_identity_type'] ?? '') . '" + "' . ($apiData['nominee_2_id_number'] ?? '') . '" -> "' . $nominee2Identity . '"');
//error_log('NOMINEE 3: "' . ($apiData['nominee_3_identity_type'] ?? '') . '" + "' . ($apiData['nominee_3_id_number'] ?? '') . '" -> "' . $nominee3Identity . '"');
//error_log('========================================');
        
        // ================================================================
        // BUILD USER DATA ARRAY
        // ================================================================
        
        // Check if user already exists
        $existingUser = $this->getClientByCode($clientCode);
        $isUpdate = ($existingUser !== false);
        
        // Get bank holder name
        $bankHolderName = $getValue($apiData['cheque_name'] ?? '');
        if (empty($bankHolderName)) {
            $bankHolderName = $getValue($apiData['account_holder_name'] ?? '');
        }
        if (empty($bankHolderName)) {
            $bankHolderName = $getValue($apiData['bank_holder_name'] ?? '');
        }
        if (empty($bankHolderName)) {
            $bankHolderName = $concatName(
                $apiData['primary_holder_first_name'] ?? '',
                $apiData['primary_holder_middle_name'] ?? '',
                $apiData['primary_holder_last_name'] ?? ''
            );
        }
        if (empty($bankHolderName)) {
            $bankHolderName = $getValue($apiData['primary_holder_name'] ?? '');
        }
        
        // Build the data array for database - ONLY include fields that have values
        $userData = [
            'CLIENT_CODE' => $clientCode,
        ];
        
        // Primary Holder - only add if has value
        $primaryName = $concatName(
            $apiData['primary_holder_first_name'] ?? '',
            $apiData['primary_holder_middle_name'] ?? '',
            $apiData['primary_holder_last_name'] ?? ''
        );
        if (!empty($primaryName)) $userData['PRIMARY_HOLDER_NAME'] = $primaryName;
        if (!empty($taxStatusCode)) $userData['TAX_STATUS'] = $taxStatusCode;
        if (!empty($apiData['gender'] ?? '')) $userData['GENDER'] = $formatGender($apiData['gender'] ?? '');
        if (!empty($apiData['primary_holder_dob_incorporation'] ?? '')) $userData['PRIMARY_HOLDER_DOB'] = $formatDate($apiData['primary_holder_dob_incorporation'] ?? '');
        if (!empty($occupationCode)) $userData['OCCUPATION_CODE'] = $occupationCode;
        if (!empty($holdingNatureCode)) $userData['HOLDING_NATURE'] = $holdingNatureCode;
        if (!empty($apiData['primary_holder_pan'] ?? '')) $userData['PRIMARY_HOLDER_PAN'] = $getValue($apiData['primary_holder_pan'] ?? '');
        
        // Second Holder
        $secondName = $concatName(
            $apiData['second_holder_first_name'] ?? '',
            $apiData['second_holder_middle_name'] ?? '',
            $apiData['second_holder_last_name'] ?? ''
        );
        if (!empty($secondName)) $userData['SECOND_HOLDER_NAME'] = $secondName;
        if (!empty($apiData['second_holder_dob'] ?? '')) $userData['SECOND_HOLDER_DOB'] = $formatDate($apiData['second_holder_dob'] ?? '');
        if (!empty($apiData['second_holder_pan'] ?? '')) $userData['SECOND_HOLDER_PAN'] = $getValue($apiData['second_holder_pan'] ?? '');
        if (!empty($apiData['second_holder_email'] ?? '')) $userData['SECOND_HOLDER_EMAIL'] = $getValue($apiData['second_holder_email'] ?? '');
        if (!empty($apiData['second_holder_mobile'] ?? '')) $userData['SECOND_HOLDER_MOBILE'] = $getValue($apiData['second_holder_mobile'] ?? '');
        
        // Third Holder
        $thirdName = $concatName(
            $apiData['third_holder_first_name'] ?? '',
            $apiData['third_holder_middle_name'] ?? '',
            $apiData['third_holder_last_name'] ?? ''
        );
        if (!empty($thirdName)) $userData['THIRD_HOLDER_NAME'] = $thirdName;
        if (!empty($apiData['third_holder_dob'] ?? '')) $userData['THIRD_HOLDER_DOB'] = $formatDate($apiData['third_holder_dob'] ?? '');
        if (!empty($apiData['third_holder_email'] ?? '')) $userData['THIRD_HOLDER_EMAIL'] = $getValue($apiData['third_holder_email'] ?? '');
        if (!empty($apiData['third_holder_mobile'] ?? '')) $userData['THIRD_HOLDER_MOBILE'] = $getValue($apiData['third_holder_mobile'] ?? '');
        if (!empty($apiData['third_holder_pan'] ?? '')) $userData['THIRD_HOLDER_PAN'] = $getValue($apiData['third_holder_pan'] ?? '');
        
        // Guardian
        $guardianName = $concatName(
            $apiData['guardian_first_name'] ?? '',
            $apiData['guardian_middle_name'] ?? '',
            $apiData['guardian_last_name'] ?? ''
        );
        if (!empty($guardianName)) $userData['GUARDIAN_FIRST_NAME'] = $guardianName;
        if (!empty($apiData['guardian_dob'] ?? '')) $userData['GUARDIAN_DOB'] = $formatDate($apiData['guardian_dob'] ?? '');
        if (!empty($apiData['guardian_pan'] ?? '')) $userData['GUARDIAN_PAN'] = $getValue($apiData['guardian_pan'] ?? '');
        if (!empty($apiData['guardian_relationship'] ?? '')) $userData['GUARDIAN_RELATIONSHIP'] = $getValue($apiData['guardian_relationship'] ?? '');
        
        // Bank Details - Account 1
        if (!empty($bankCode)) $userData['BD_BANK_CODE_1'] = $bankCode;
        if (!empty($accountTypeCode)) $userData['ACCOUNT_TYPE_1'] = $accountTypeCode;
        if (!empty($apiData['account_no_1'] ?? '')) $userData['ACCOUNT_NO_1'] = $getValue($apiData['account_no_1'] ?? '');
        if (!empty($bankHolderName)) $userData['BANK_HOLDER_NAME_1'] = $bankHolderName;
        if (!empty($apiData['ifsc_code_1'] ?? '')) $userData['IFSC_CODE_1'] = $getValue($apiData['ifsc_code_1'] ?? '');
        if (!empty($apiData['bank_branch_1'] ?? '')) $userData['BANK_BRANCH_1'] = $getValue($apiData['bank_branch_1'] ?? '');
        
        // Address
        if (!empty($apiData['address_1'] ?? '')) $userData['ADDRESS_1'] = $getValue($apiData['address_1'] ?? '');
        if (!empty($apiData['address_2'] ?? '')) $userData['ADDRESS_2'] = $getValue($apiData['address_2'] ?? '');
        if (!empty($apiData['city'] ?? '')) $userData['CITY'] = $getValue($apiData['city'] ?? '');
        if (!empty($stateCode)) $userData['STATE'] = $stateCode;
        if (!empty($apiData['pincode'] ?? '')) $userData['PINCODE'] = $getValue($apiData['pincode'] ?? '');
        if (!empty($countryCode)) $userData['COUNTRY'] = $countryCode;
        if (!empty($apiData['email'] ?? '')) $userData['EMAIL'] = $getValue($apiData['email'] ?? '');
        if (!empty($apiData['indian_mobile_no'] ?? '')) $userData['INDIAN_MOBILE_NO'] = $getValue($apiData['indian_mobile_no'] ?? '');
        
        // Foreign Address
        if (!empty($apiData['foreign_address_1'] ?? '')) $userData['FOREIGN_ADDRESS_1'] = $getValue($apiData['foreign_address_1'] ?? '');
        if (!empty($apiData['foreign_address_pincode'] ?? '')) $userData['FOREIGN_ADDRESS_PINCODE'] = $getValue($apiData['foreign_address_pincode'] ?? '');
        if (!empty($apiData['foreign_address_state'] ?? '')) $userData['FOREIGN_ADDRESS_STATE'] = $getValue($apiData['foreign_address_state'] ?? '');
        if (!empty($apiData['foreign_address_country'] ?? '')) $userData['FOREIGN_ADDRESS_COUNTRY'] = $getValue($apiData['foreign_address_country'] ?? '');
        
        // Nomination - FIXED: using authentication code
        if (!empty($apiData['nomination_opt'] ?? '')) $userData['NOMINATION'] = $formatNomination($apiData['nomination_opt'] ?? '');
        if (!empty($authenticationCode)) $userData['AUTHENTICATION'] = $authenticationCode;  // FIXED
        if (!empty($apiData['nominee_names_registration_status_in_soa'] ?? '')) $userData['NOMINEE_NAMES_STATUS'] = $getValue($apiData['nominee_names_registration_status_in_soa'] ?? '');
        
        // Nominee 1
        if (!empty($apiData['nominee_1_name'] ?? '')) $userData['NOMINEE_1_NAME'] = $getValue($apiData['nominee_1_name'] ?? '');
        if (!empty($nominee1Relation)) $userData['NOMINEE_1_RELATIONSHIP'] = $nominee1Relation;
        if (!empty($apiData['nominee1_applicable'] ?? $apiData['nominee_1_applicable'] ?? '')) $userData['NOMINEE_1_APPLICABLE_PERCENT'] = $getValue($apiData['nominee1_applicable'] ?? $apiData['nominee_1_applicable'] ?? '');
        if (!empty($apiData['nominee_1_minor_flag'] ?? '')) $userData['NOMINEE_1_MINOR_FLAG'] = $getValue($apiData['nominee_1_minor_flag'] ?? '');
        if (!empty($apiData['nominee_1_dob'] ?? '')) $userData['NOMINEE_1_DOB'] = $formatDate($apiData['nominee_1_dob'] ?? '');
        if (!empty($apiData['nominee_1_guardian'] ?? '')) $userData['NOMINEE_1_GUARDIAN'] = $getValue($apiData['nominee_1_guardian'] ?? '');
        if (!empty($apiData['nominee_1_guardian_pan'] ?? '')) $userData['NOMINEE_1_GUARDIAN_PAN'] = $getValue($apiData['nominee_1_guardian_pan'] ?? '');
       $userData['NOMINEE_1_IDENTITY_TYPE'] = $nominee1Identity;
        $userData['NOMINEE_1_IDENTITY_NO'] = $getValue($apiData['nominee_1_id_number'] ?? '');

        if (!empty($apiData['nominee_1_email'] ?? '')) $userData['NOMINEE_1_EMAIL'] = $getValue($apiData['nominee_1_email'] ?? '');
        if (!empty($apiData['nominee_1_mobile'] ?? '')) $userData['NOMINEE_1_MOBILE'] = $getValue($apiData['nominee_1_mobile'] ?? '');
        if (!empty($apiData['nominee_1_address1'] ?? '')) $userData['NOMINEE_1_ADDRESS1'] = $getValue($apiData['nominee_1_address1'] ?? '');
        if (!empty($apiData['nominee_1_city'] ?? '')) $userData['NOMINEE_1_CITY'] = $getValue($apiData['nominee_1_city'] ?? '');
        if (!empty($apiData['nominee1_pin'] ?? $apiData['nominee_1_pin'] ?? '')) $userData['NOMINEE_1_PINCODE'] = $getValue($apiData['nominee1_pin'] ?? $apiData['nominee_1_pin'] ?? '');
        $nominee1Country = $getCountryCode($apiData['nominee_1_country'] ?? '');
        if (!empty($nominee1Country)) $userData['NOMINEE_1_COUNTRY'] = $nominee1Country;
        
        // Nominee 2
        if (!empty($apiData['nominee_2_name'] ?? '')) $userData['NOMINEE_2_NAME'] = $getValue($apiData['nominee_2_name'] ?? '');
        if (!empty($nominee2Relation)) $userData['NOMINEE_2_RELATIONSHIP'] = $nominee2Relation;
        if (!empty($apiData['nominee_2_applicable'] ?? '')) $userData['NOMINEE_2_APPLICABLE_PERCENT'] = $getValue($apiData['nominee_2_applicable'] ?? '');
        if (!empty($apiData['nominee2_minor_flag'] ?? $apiData['nominee_2_minor_flag'] ?? '')) $userData['NOMINEE_2_MINOR_FLAG'] = $getValue($apiData['nominee2_minor_flag'] ?? $apiData['nominee_2_minor_flag'] ?? '');
        if (!empty($apiData['nominee_2_dob'] ?? '')) $userData['NOMINEE_2_DOB'] = $formatDate($apiData['nominee_2_dob'] ?? '');
        if (!empty($apiData['nominee2_guardian'] ?? $apiData['nominee_2_guardian'] ?? '')) $userData['NOMINEE_2_GUARDIAN'] = $getValue($apiData['nominee2_guardian'] ?? $apiData['nominee_2_guardian'] ?? '');
        if (!empty($apiData['nominee_2_guardian_pan'] ?? '')) $userData['NOMINEE_2_GUARDIAN_PAN'] = $getValue($apiData['nominee_2_guardian_pan'] ?? '');
        $userData['NOMINEE_2_IDENTITY_TYPE'] = $nominee2Identity;
        $userData['NOMINEE_2_IDENTITY_NO'] = $getValue($apiData['nominee_2_id_number'] ?? '');
        if (!empty($apiData['nominee_2_email'] ?? '')) $userData['NOMINEE_2_EMAIL'] = $getValue($apiData['nominee_2_email'] ?? '');
        if (!empty($apiData['nominee_2_mobile'] ?? '')) $userData['NOMINEE_2_MOBILE'] = $getValue($apiData['nominee_2_mobile'] ?? '');
        if (!empty($apiData['nominee_2_address1'] ?? '')) $userData['NOMINEE_2_ADDRESS1'] = $getValue($apiData['nominee_2_address1'] ?? '');
        if (!empty($apiData['nominee_2_city'] ?? '')) $userData['NOMINEE_2_CITY'] = $getValue($apiData['nominee_2_city'] ?? '');
        if (!empty($apiData['nominee_2_pin'] ?? '')) $userData['NOMINEE_2_PINCODE'] = $getValue($apiData['nominee_2_pin'] ?? '');
        $nominee2Country = $getCountryCode($apiData['nominee_2_country'] ?? '');
        if (!empty($nominee2Country)) $userData['NOMINEE_2_COUNTRY'] = $nominee2Country;
        
        // Nominee 3
        if (!empty($apiData['nominee_3_name'] ?? '')) $userData['NOMINEE_3_NAME'] = $getValue($apiData['nominee_3_name'] ?? '');
        if (!empty($nominee3Relation)) $userData['NOMINEE_3_RELATIONSHIP'] = $nominee3Relation;
        if (!empty($apiData['nominee_3_applicable'] ?? '')) $userData['NOMINEE_3_APPLICABLE_PERCENT'] = $getValue($apiData['nominee_3_applicable'] ?? '');
        if (!empty($apiData['nominee_3_minor_flag'] ?? '')) $userData['NOMINEE_3_MINOR_FLAG'] = $getValue($apiData['nominee_3_minor_flag'] ?? '');
        if (!empty($apiData['nominee_3_dob'] ?? '')) $userData['NOMINEE_3_DOB'] = $formatDate($apiData['nominee_3_dob'] ?? '');
        if (!empty($apiData['nominee_3_guardian'] ?? '')) $userData['NOMINEE_3_GUARDIAN'] = $getValue($apiData['nominee_3_guardian'] ?? '');
        if (!empty($apiData['nominee_3_guardian_pan'] ?? '')) $userData['NOMINEE_3_GUARDIAN_PAN'] = $getValue($apiData['nominee_3_guardian_pan'] ?? '');
        $userData['NOMINEE_3_IDENTITY_TYPE'] = $nominee3Identity;
        $userData['NOMINEE_3_IDENTITY_NO'] = $getValue($apiData['nominee_3_id_number'] ?? '');
        if (!empty($apiData['nominee_3_email'] ?? '')) $userData['NOMINEE_3_EMAIL'] = $getValue($apiData['nominee_3_email'] ?? '');
        if (!empty($apiData['nominee_3_mobile'] ?? '')) $userData['NOMINEE_3_MOBILE'] = $getValue($apiData['nominee_3_mobile'] ?? '');
        if (!empty($apiData['nominee_3_address1'] ?? '')) $userData['NOMINEE_3_ADDRESS1'] = $getValue($apiData['nominee_3_address1'] ?? '');
        if (!empty($apiData['nominee_3_city'] ?? '')) $userData['NOMINEE_3_CITY'] = $getValue($apiData['nominee_3_city'] ?? '');
        if (!empty($apiData['nominee_3_pin'] ?? '')) $userData['NOMINEE_3_PINCODE'] = $getValue($apiData['nominee_3_pin'] ?? '');
        $nominee3Country = $getCountryCode($apiData['nominee_3_country'] ?? '');
        if (!empty($nominee3Country)) $userData['NOMINEE_3_COUNTRY'] = $nominee3Country;
        
        // UCC_ACTIVATION mapping from ucc_status
        if (isset($apiData['ucc_status']) || isset($apiData['UCC_ACTIVATION'])) {
            $rawStatus = $apiData['ucc_status'] ?? $apiData['UCC_ACTIVATION'];
            $stUpper = strtoupper(trim((string)$rawStatus));
            $userData['UCC_ACTIVATION'] = in_array($stUpper, ['ACTIVE', 'Y', 'APPROVED', 'ACTIVATED', '1'], true) ? 1 : 0;
        }

        // Debug: Log what we're about to save
        //error_log('=== USER DATA TO SAVE (FIELDS WITH VALUES) ===');
        foreach ($userData as $key => $value) {
            if ($key !== 'CLIENT_CODE') {
                //error_log("$key: '$value'");
            }
        }
        //error_log('===========================================');
        
        if ($isUpdate) {
            // UPDATE existing user
            $updateFields = [];
            foreach ($userData as $key => $value) {
                if ($key !== 'CLIENT_CODE') {
                    $escapedValue = $this->safeEscape($value);
                    $updateFields[] = "`$key` = '$escapedValue'";
                }
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "`UPDATED_AT` = NOW()";
                $sql = "UPDATE `tb_users` SET " . implode(', ', $updateFields) . " WHERE `CLIENT_CODE` = '$clientCode'";
                //error_log('UPDATE SQL: ' . $sql);
                
                $result = $this->query($sql);
                //error_log('UPDATE Result: ' . ($result ? 'SUCCESS' : 'FAILED'));
                //error_log('UPDATE Affected Rows: ' . $conn->affected_rows);
                
                if (!$result) {
                    throw new Exception('Update failed: ' . $this->getError());
                }
                
                // Verify the update
                $updatedUser = $this->getClientByCode($clientCode);
                //error_log('Updated user data: ' . print_r($updatedUser, true));
            } else {
                error_log('No fields to update for client: ' . $clientCode);
            }
            
            $conn->commit();
            return ['success' => true, 'message' => 'Client data updated successfully', 'action' => 'update', 'affected_rows' => $conn->affected_rows];
            
        } else {
            // INSERT new user
            $columns = array_keys($userData);
            $values = array_map(function($v) {
                return "'" . $this->safeEscape($v) . "'";
            }, array_values($userData));
            
            $columns[] = 'CREATED_AT';
            $values[] = 'NOW()';
            $columns[] = 'UPDATED_AT';
            $values[] = 'NOW()';
            
            $sql = "INSERT INTO `tb_users` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ")";
            //error_log('INSERT SQL: ' . $sql);
            
            $result = $this->query($sql);
            //error_log('INSERT Result: ' . ($result ? 'SUCCESS' : 'FAILED'));
            //error_log('INSERT ID: ' . $conn->insert_id);
            
            if (!$result) {
                throw new Exception('Insert failed: ' . $this->getError());
            }
            
            // Sync client_code_counter so last_number updates on every new client insertion
            if (preg_match('/^(\d{4})(\d+)$/', $clientCode, $m)) {
                $cYear = (int)$m[1];
                $cSeq = (int)$m[2];
                $this->query("INSERT INTO `client_code_counter` (`year`, `last_number`) VALUES ($cYear, $cSeq) ON DUPLICATE KEY UPDATE `last_number` = GREATEST(`last_number`, $cSeq)");
            }
            
            $conn->commit();
            return ['success' => true, 'message' => 'Client data saved successfully', 'action' => 'insert'];
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('saveClientMasterData ERROR: ' . $e->getMessage());
        error_log('saveClientMasterData TRACE: ' . $e->getTraceAsString());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get nominee identity type code from mtb_nominee_identity table
 * Uses fuzzy matching to handle typos and variations
 * 
 * @param string $identityType The identity type name (e.g., "PAN", "AADHAAR") 
 * @param string $idNumber The nominee ID number - used to derive type if identityType is empty
 * @return string The identity_type_code (single character) or empty string
 */
public function getNomineeIdentityCode($identityType, $idNumber = '')
{
    // First try to use the provided identity type
    if (!empty($identityType) && trim($identityType) !== '') {
        $original = trim($identityType);
        $escaped = $this->safeEscape($original);
        
        // ================================================================
        // STEP 1: TRY EXACT MATCH FIRST
        // ================================================================
        $queries = [
            "SELECT `identity_type_code`, `identity_type_name` FROM `mtb_nominee_identity` WHERE `identity_type_name` = '$escaped' LIMIT 1",
            "SELECT `identity_type_code`, `identity_type_name` FROM `mtb_nominee_identity` WHERE UPPER(`identity_type_name`) = UPPER('$escaped') LIMIT 1",
            "SELECT `identity_type_code`, `identity_type_name` FROM `mtb_nominee_identity` WHERE `identity_type_code` = '$escaped' LIMIT 1",
            "SELECT `identity_type_code`, `identity_type_name` FROM `mtb_nominee_identity` WHERE UPPER(`identity_type_code`) = UPPER('$escaped') LIMIT 1",
        ];
        
        foreach ($queries as $sql) {
            $result = $this->selectSingle($sql);
            if ($result && isset($result['identity_type_code']) && !empty($result['identity_type_code'])) {
                //error_log("NOMINEE_IDENTITY FOUND (exact): '$original' -> '{$result['identity_type_code']}'");
                return $result['identity_type_code'];
            }
        }
        
        // ================================================================
        // STEP 2: GET ALL IDENTITY TYPES FROM MASTER TABLE
        // ================================================================
        $allTypes = $this->select("SELECT `identity_type_code`, `identity_type_name` FROM `mtb_nominee_identity` WHERE `is_active` = 1 OR `is_active` IS NULL");
        
        if ($allTypes && count($allTypes) > 0) {
            $bestMatch = null;
            $bestScore = 0;
            
            foreach ($allTypes as $type) {
                $masterName = $type['identity_type_name'];
                $masterCode = $type['identity_type_code'];
                
                // ================================================================
                // STEP 2a: CASE-INSENSITIVE COMPARISON
                // ================================================================
                if (strtoupper(trim($masterName)) === strtoupper($original)) {
                    //error_log("NOMINEE_IDENTITY FOUND (case-insensitive): '$original' -> '$masterCode'");
                    return $masterCode;
                }
                
                // ================================================================
                // STEP 2b: REMOVE SPACES AND COMPARE
                // ================================================================
                $masterNoSpace = preg_replace('/\s+/', '', strtoupper($masterName));
                $originalNoSpace = preg_replace('/\s+/', '', strtoupper($original));
                
                if ($masterNoSpace === $originalNoSpace) {
                    //error_log("NOMINEE_IDENTITY FOUND (no spaces): '$original' -> '$masterCode'");
                    return $masterCode;
                }
                
                // ================================================================
                // STEP 2c: LEVENSHTEIN DISTANCE (Fuzzy Matching)
                // ================================================================
                $levenshteinDist = levenshtein(strtoupper($masterName), strtoupper($original));
                $maxLen = max(strlen($masterName), strlen($original));
                if ($maxLen > 0) {
                    $similarity = 1 - ($levenshteinDist / $maxLen);
                    
                    // If similarity is high enough (70% or more)
                    if ($similarity >= 0.7 && $similarity > $bestScore) {
                        $bestScore = $similarity;
                        $bestMatch = $masterCode;
                        //error_log("NOMINEE_IDENTITY fuzzy match: '$original' vs '$masterName' = " . round($similarity * 100) . "% -> '$masterCode'");
                    }
                }
                
                // ================================================================
                // STEP 2d: SOUNDEX MATCHING (for phonetic similarity)
                // ================================================================
                if (function_exists('soundex')) {
                    $masterSoundex = soundex($masterName);
                    $originalSoundex = soundex($original);
                    
                    if ($masterSoundex === $originalSoundex && $masterSoundex !== '') {
                        //error_log("NOMINEE_IDENTITY FOUND (soundex): '$original' -> '$masterCode'");
                        return $masterCode;
                    }
                }
                
                // ================================================================
                // STEP 2e: METAPHONE MATCHING (for phonetic similarity)
                // ================================================================
                if (function_exists('metaphone')) {
                    $masterMetaphone = metaphone($masterName);
                    $originalMetaphone = metaphone($original);
                    
                    if ($masterMetaphone === $originalMetaphone && $masterMetaphone !== '') {
                        //error_log("NOMINEE_IDENTITY FOUND (metaphone): '$original' -> '$masterCode'");
                        return $masterCode;
                    }
                }
                
                // ================================================================
                // STEP 2f: STARTS WITH MATCHING
                // ================================================================
                if (stripos($masterName, $original) !== false || stripos($original, $masterName) !== false) {
                    //error_log("NOMINEE_IDENTITY FOUND (contains): '$original' -> '$masterCode'");
                    return $masterCode;
                }
            }
            
            // If we have a fuzzy match above 80%
            if ($bestMatch && $bestScore >= 0.8) {
                //error_log("NOMINEE_IDENTITY FOUND (fuzzy best): '$original' -> '$bestMatch' (score: $bestScore)");
                return $bestMatch;
            }
        }
        
        // ================================================================
        // STEP 3: COMMON TYPO/VARIATION MAPPING
        // ================================================================
        $typoMap = [
            'PAN' => 'P',
            'PANCARD' => 'P',
            'PAN CARD' => 'P',
            'PAN-CARD' => 'P',
            'AADHAAR' => 'A',
            'AADHAR' => 'A',
            'AADHAAR CARD' => 'A',
            'AADHAR CARD' => 'A',
            'AADHARCARD' => 'A',
            'UID' => 'A',
            'VOTER ID' => 'V',
            'VOTERID' => 'V',
            'VOTER' => 'V',
            'VOTERS ID' => 'V',
            'PASSPORT' => 'PS',
            'PASSPORT NO' => 'PS',
            'DRIVING LICENSE' => 'D',
            'DRIVING LICENCE' => 'D',
            'DRIVING' => 'D',
            'DL' => 'D',
            'MOBILE' => 'M',
            'MOBILE NO' => 'M',
            'MOBILE NUMBER' => 'M',
            'BUSINESS PAN' => 'BP',
            'B-PAN' => 'BP',
            'BPAN' => 'BP',
        ];
        
        $upper = strtoupper(trim($original));
        
        // Check exact match in typo map
        if (isset($typoMap[$upper])) {
            $code = $typoMap[$upper];
            
            // Verify the code exists in master table
            $verifySql = "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_code` = '$code' LIMIT 1";
            $verifyResult = $this->selectSingle($verifySql);
            if ($verifyResult && isset($verifyResult['identity_type_code'])) {
                //error_log("NOMINEE_IDENTITY FOUND (typo map): '$original' -> '$code'");
                return $code;
            }
        }
        
        // Try partial matches in typo map
        foreach ($typoMap as $key => $code) {
            if (stripos($upper, $key) !== false || stripos($key, $upper) !== false) {
                // Verify the code exists
                $verifySql = "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_code` = '$code' LIMIT 1";
                $verifyResult = $this->selectSingle($verifySql);
                if ($verifyResult && isset($verifyResult['identity_type_code'])) {
                    //error_log("NOMINEE_IDENTITY FOUND (typo map partial): '$original' -> '$code'");
                    return $code;
                }
            }
        }
        
        // ================================================================
        // STEP 4: If it's already a single character, return it
        // ================================================================
        if (strlen($original) === 1) {
            $verifySql = "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_code` = '$original' LIMIT 1";
            $verifyResult = $this->selectSingle($verifySql);
            if ($verifyResult && isset($verifyResult['identity_type_code'])) {
                //error_log("NOMINEE_IDENTITY FOUND (single char): '$original' -> '$original'");
                return $original;
            }
        }
    }
    
    // ================================================================
    // STEP 5: Derive from ID number if identity type is empty or not found
    // ================================================================
    if (!empty($idNumber)) {
        $idNumber = trim($idNumber);
        
        // Try to derive from ID number format
        $derivedCode = null;
        
        // PAN: ABCDE1234F
        if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $idNumber)) {
            $derivedCode = 'P';
        }
        // AADHAAR: 12 digits
        elseif (preg_match('/^[0-9]{12}$/', $idNumber)) {
            $derivedCode = 'A';
        }
        // AADHAAR: last 4 digits (common in some systems)
        elseif (preg_match('/^[0-9]{4}$/', $idNumber)) {
            $derivedCode = 'A';
        }
        // VOTER ID: ABC1234567
        elseif (preg_match('/^[A-Z]{3}[0-9]{7}$/', $idNumber)) {
            $derivedCode = 'V';
        }
        // PASSPORT: A1234567
        elseif (preg_match('/^[A-Z][0-9]{7}$/', $idNumber)) {
            $derivedCode = 'PS';
        }
        // DRIVING LICENSE: ABC12345678901 (varies by state)
        elseif (preg_match('/^[A-Z]{2}[0-9]{13}$/', $idNumber)) {
            $derivedCode = 'D';
        }
        // MOBILE: 10 digits
        elseif (preg_match('/^[0-9]{10}$/', $idNumber)) {
            $derivedCode = 'M';
        }
        
        if ($derivedCode) {
            // Verify the derived code exists in master table
            $verifySql = "SELECT `identity_type_code` FROM `mtb_nominee_identity` WHERE `identity_type_code` = '$derivedCode' LIMIT 1";
            $verifyResult = $this->selectSingle($verifySql);
            if ($verifyResult && isset($verifyResult['identity_type_code'])) {
                //error_log("NOMINEE_IDENTITY FOUND (derived from ID): '$idNumber' -> '$derivedCode'");
                return $derivedCode;
            }
        }
    }
    
    error_log("NOMINEE_IDENTITY NOT FOUND for: identityType='$identityType', idNumber='$idNumber'");
    return '';
}

/**
 * Save live FATCA report item to the local database (fatca_details table)
 * Handles cleaning up key whitespaces, maps user_id using pan_rp against tb_users,
 * formats and validates numbers/dates, and inserts/updates the records securely.
 * 
 * @param array $apiItem Individual FATCA record from live API
 * @return bool True on success, false on failure
 */
public function saveFetchedFatcaDetails(array $apiItem): bool
{
    try {
        // Clean trailing whitespaces in keys
        $item = [];
        foreach ($apiItem as $key => $val) {
            $item[trim($key)] = $val;
        }

        $getVal = function($key, $default = '') use ($item) {
            $val = $item[$key] ?? $default;
            return is_string($val) ? trim($val) : $val;
        };

        $pan = $getVal('pan_rp');
        if (empty($pan)) {
            error_log("saveFetchedFatcaDetails: Skipping item because pan_rp is empty.");
            return false;
        }

        // 1. Resolve user_id from tb_users using PAN
        $userId = 0;
        $escapedPan = $this->safeEscape($pan);
        $userSql = "SELECT `id` FROM `tb_users` WHERE `PRIMARY_HOLDER_PAN` = '{$escapedPan}' LIMIT 1";
        $userRow = $this->selectSingle($userSql);
        if ($userRow && isset($userRow['id'])) {
            $userId = intval($userRow['id']);
        }

        // Helper to format date inputs from DD/MM/YYYY to YYYY-MM-DD
        $formatDateVal = function($dateStr) {
            if (empty($dateStr) || trim($dateStr) === '') {
                return null;
            }
            $cleanDate = trim($dateStr);
            // Check if matches DD/MM/YYYY
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $cleanDate)) {
                $parts = explode('/', $cleanDate);
                return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
            // Check if matches DD-MM-YYYY
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $cleanDate)) {
                $parts = explode('-', $cleanDate);
                return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
            $parsed = strtotime($cleanDate);
            return $parsed ? date('Y-m-d', $parsed) : null;
        };

        // Date conversions
        $dob = $formatDateVal($getVal('dob'));
        $nw_date = $formatDateVal($getVal('nw_date'));
        $ubo_dob = $formatDateVal($getVal('ubo_dob'));

        // Prepare raw query parameters safely
        $fields = [
            'user_id'       => $userId,
            'pan_rp'        => $pan,
            'pekrn'         => $getVal('pekrn'),
            'inv_name'      => $getVal('inv_name'),
            'dob'           => $dob,
            'fr_name'       => $getVal('fr_name'),
            'sp_name'       => $getVal('sp_name'),
            'tax_status_us' => $getVal('tax_status'),
            'data_src'      => $getVal('data_src'),
            'addr_type'     => $getVal('addr_type'),
            'po_bir_inc'    => $getVal('po_bir_inc'),
            'co_bir_inc'    => $getVal('co_bir_inc'),
            'tax_res1'      => $getVal('tax_res1'),
            'tpin1'         => $getVal('tpin1'),
            'id1_type'      => $getVal('id1_type'),
            'tax_res2'      => $getVal('tax_res2'),
            'tpin2'         => $getVal('tpin2'),
            'id2_type'      => $getVal('id2_type'),
            'tax_res3'      => $getVal('tax_res3'),
            'tpin3'         => $getVal('tpin3'),
            'id3_type'      => $getVal('id3_type'),
            'tax_res4'      => $getVal('tax_res4'),
            'tpin4'         => $getVal('tpin4'),
            'id4_type'      => $getVal('id4_type'),
            'srce_wealt'    => $getVal('srce_wealt'),
            'corp_servs'    => $getVal('corp_servs'),
            'inc_slab'      => $getVal('inc_slab'),
            'net_worth'     => is_numeric($getVal('net_worth')) ? floatval($getVal('net_worth')) : 0.00,
            'nw_date'       => $nw_date,
            'pep_flag'      => $getVal('pep_flag'),
            'occ_code'      => $getVal('occ_code'),
            'occ_type'      => $getVal('occ_type'),
            'exemp_code'    => $getVal('exemp_code'),
            'ffi_drnfe'     => $getVal('ffi_drnfe'),
            'giin_no'       => $getVal('giin_no'),
            'spr_entity'    => $getVal('spr_entity'),
            'giin_na'        => $getVal('giin_na'),
            'giin_exemc'    => $getVal('giin_exemc'),
            'nffe_catg'     => $getVal('nffe_catg'),
            'act_nfe_sc'    => $getVal('act_nfe_sc'),
            'nature_bus'    => $getVal('nature_bus'),
            'rel_listed'    => $getVal('rel_listed'),
            'exch_name'     => $getVal('exch_name'),
            'ubo_appl'      => $getVal('ubo_appl'),
            'ubo_count'     => $getVal('ubo_count'),
            'ubo_name'      => $getVal('ubo_name'),
            'ubo_pan'       => $getVal('ubo_pan'),
            'ubo_nation'    => $getVal('ubo_nation'),
            'ubo_add1'      => $getVal('ubo_add1'),
            'ubo_add2'      => $getVal('ubo_add2'),
            'ubo_add3'      => $getVal('ubo_add3'),
            'ubo_city'      => $getVal('ubo_city'),
            'ubo_pin'       => $getVal('ubo_pin'),
            'ubo_state'     => $getVal('ubo_state'),
            'ubo_cntry'     => $getVal('ubo_cntry'),
            'ubo_add_type'  => $getVal('ubo_add_ty'),
            'ubo_ctr'       => $getVal('ubo_ctr'),
            'ubo_tin'       => $getVal('ubo_tin'),
            'ubo_id_ty'     => $getVal('ubo_id_ty'),
            'ubo_cob'       => $getVal('ubo_cob'),
            'ubo_dob'       => $ubo_dob,
            'ubo_gender'    => $getVal('ubo_gender'),
            'ubo_fr_nam'    => $getVal('ubo_fr_nam'),
            'ubo_occ'       => $getVal('ubo_occ'),
            'ubo_occ_ty'    => $getVal('ubo_occ_ty'),
            'ubo_tel'       => $getVal('ubo_tel'),
            'ubo_mobile'    => $getVal('ubo_mobile'),
            'ubo_code'      => $getVal('ubo_code'),
            'ubo_hol_pc'    => $getVal('ubo_hol_pc'),
            'sdf_flag'      => $getVal('sdf_flag'),
            'ubo_df'        => $getVal('ubo_df'),
            'aadhaar_rp'    => $getVal('aadhaar_rp'),
            'new_cha'       => $getVal('new_change'),
            'log_name'      => $getVal('log_name'),
            'ubo_exch'      => $getVal('ubo_exch'),
            'ubo_isin'      => $getVal('ubo_isin'),
            'ubo_rel_li'    => $getVal('ubo_rel_li'),
            'ubo_categ'     => $getVal('ubo_categ'),
            'ubo_pep_fl'    => $getVal('ubo_pep_fl'),
            'ubo_email'     => $getVal('ubo_email'),
            'ubo_smo_de'    => $getVal('ubo_smo_de'),
            'npo_form'      => $getVal('npo_form'),
            'npo_dcl'       => $getVal('npo_dcl'),
            'npo_rgno'      => $getVal('npo_rgno'),
            'filler1'       => $getVal('filler1'),
            'filler2'       => $getVal('filler2')
        ];

        // SQL building
        $columns = [];
        $values = [];
        $updates = [];

        foreach ($fields as $col => $val) {
            $columns[] = "`{$col}`";
            if ($val === null) {
                $values[] = "NULL";
                $updates[] = "`{$col}` = NULL";
            } else {
                $escaped = $this->safeEscape(strval($val));
                $values[] = "'{$escaped}'";
                $updates[] = "`{$col}` = '{$escaped}'";
            }
        }

        // Add timestamps
        $columns[] = "`updated_at`";
        $values[] = "NOW()";

        $sql = "INSERT INTO `fatca_details` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ") ";
        $sql .= "ON DUPLICATE KEY UPDATE " . implode(', ', $updates) . ", `updated_at` = NOW()";

        $result = $this->query($sql);
        if (!$result) {
            error_log("saveFetchedFatcaDetails SQL Error: " . $this->getError() . " | SQL: " . $sql);
            return false;
        }

        return true;

    } catch (Exception $e) {
        error_log("saveFetchedFatcaDetails Exception: " . $e->getMessage());
        return false;
    }
}

    //==========================================================================
    // BOND MASTER MANAGEMENT METHODS
    //==========================================================================

    public function getAllBonds() {
        return $this->select("SELECT * FROM mtb_other_investment ORDER BY investment_name ASC");
    }

    public function getBondByName($investment_name) {
        $conn = $this->getConnection();
        return $this->selectSingle("SELECT slno FROM mtb_other_investment WHERE investment_name = '" . $conn->real_escape_string($investment_name) . "'");
    }

    public function insertBond($asset_class, $investment_name, $face_value_val, $nse_code, $bse_code, $interest_rate_val, $payout_frequency, $redemption_date, $int_amt_per_payout_val, $interest_pay_date) {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("INSERT INTO mtb_other_investment (asset_class, investment_name, face_value, nse_code, bse_code, interest_rate, payout_frequency, redemption_date, int_amt_per_payout, interest_pay_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ssissdssds", $asset_class, $investment_name, $face_value_val, $nse_code, $bse_code, $interest_rate_val, $payout_frequency, $redemption_date, $int_amt_per_payout_val, $interest_pay_date);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function updateBond($slno, $asset_class, $investment_name, $face_value_val, $nse_code, $bse_code, $interest_rate_val, $payout_frequency, $redemption_date, $int_amt_per_payout_val, $interest_pay_date) {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("UPDATE mtb_other_investment SET asset_class = ?, investment_name = ?, face_value = ?, nse_code = ?, bse_code = ?, interest_rate = ?, payout_frequency = ?, redemption_date = ?, int_amt_per_payout = ?, interest_pay_date = ? WHERE slno = ?");
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ssissdssdsi", $asset_class, $investment_name, $face_value_val, $nse_code, $bse_code, $interest_rate_val, $payout_frequency, $redemption_date, $int_amt_per_payout_val, $interest_pay_date, $slno);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function deleteBond($slno) {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("DELETE FROM mtb_other_investment WHERE slno = ?");
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $slno);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
}

}

?>