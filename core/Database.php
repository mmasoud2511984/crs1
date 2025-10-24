<?php
/**
 * File: Database.php
 * Path: /core/Database.php
 * Purpose: Database connection handler with PDO
 * Dependencies: PDO extension
 */

namespace Core;

use PDO;
use PDOException;
use Exception;

/**
 * Database Class
 * يدير الاتصال بقاعدة البيانات باستخدام PDO مع دعم Transactions
 */
class Database
{
    /** @var PDO|null اتصال PDO */
    private static ?PDO $connection = null;
    
    /** @var array إعدادات الاتصال */
    private static array $config = [];
    
    /** @var bool حالة Transaction */
    private static bool $inTransaction = false;
    
    /** @var int عدد الاستعلامات المنفذة */
    private static int $queryCount = 0;
    
    /**
     * تهيئة إعدادات قاعدة البيانات
     * 
     * @param array $config إعدادات الاتصال
     * @return void
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }
    
    /**
     * الحصول على اتصال قاعدة البيانات
     * 
     * @return PDO
     * @throws Exception
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::connect();
        }
        
        return self::$connection;
    }
    
    /**
     * الاتصال بقاعدة البيانات
     * 
     * @return void
     * @throws Exception
     */
    private static function connect(): void
    {
        try {
            $host = self::$config['host'] ?? 'localhost';
            $database = self::$config['database'] ?? '';
            $username = self::$config['username'] ?? 'root';
            $password = self::$config['password'] ?? '';
            $charset = self::$config['charset'] ?? 'utf8mb4';
            $port = self::$config['port'] ?? 3306;
            
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
            ];
            
            self::$connection = new PDO($dsn, $username, $password, $options);
            
            // تسجيل الاتصال الناجح
            self::log('Database connection established successfully');
            
        } catch (PDOException $e) {
            self::logError('Database connection failed: ' . $e->getMessage());
            throw new Exception('فشل الاتصال بقاعدة البيانات');
        }
    }
    
    /**
     * تنفيذ استعلام SELECT
     * 
     * @param string $query الاستعلام
     * @param array $params المتغيرات
     * @return array
     */
    public static function select(string $query, array $params = []): array
    {
        try {
            $stmt = self::execute($query, $params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            self::logError('SELECT query failed: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تنفيذ استعلام SELECT للحصول على صف واحد
     * 
     * @param string $query الاستعلام
     * @param array $params المتغيرات
     * @return array|null
     */
    public static function selectOne(string $query, array $params = []): ?array
    {
        try {
            $stmt = self::execute($query, $params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (Exception $e) {
            self::logError('SELECT ONE query failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * تنفيذ استعلام INSERT
     * 
     * @param string $query الاستعلام
     * @param array $params المتغيرات
     * @return int|false آخر معرف مُدرج أو false
     */
    public static function insert(string $query, array $params = []): int|false
    {
        try {
            self::execute($query, $params);
            return (int)self::$connection->lastInsertId();
        } catch (Exception $e) {
            self::logError('INSERT query failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تنفيذ استعلام UPDATE
     * 
     * @param string $query الاستعلام
     * @param array $params المتغيرات
     * @return int عدد الصفوف المتأثرة
     */
    public static function update(string $query, array $params = []): int
    {
        try {
            $stmt = self::execute($query, $params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            self::logError('UPDATE query failed: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * تنفيذ استعلام DELETE
     * 
     * @param string $query الاستعلام
     * @param array $params المتغيرات
     * @return int عدد الصفوف المحذوفة
     */
    public static function delete(string $query, array $params = []): int
    {
        try {
            $stmt = self::execute($query, $params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            self::logError('DELETE query failed: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * تنفيذ استعلام عام
     * 
     * @param string $query الاستعلام
     * @param array $params المتغيرات
     * @return \PDOStatement
     * @throws Exception
     */
    private static function execute(string $query, array $params = []): \PDOStatement
    {
        try {
            $connection = self::getConnection();
            $stmt = $connection->prepare($query);
            
            // ربط المتغيرات
            foreach ($params as $key => $value) {
                if (is_int($key)) {
                    $stmt->bindValue($key + 1, $value);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            self::$queryCount++;
            
            return $stmt;
            
        } catch (PDOException $e) {
            self::logError('Query execution failed: ' . $e->getMessage() . ' | Query: ' . $query);
            throw new Exception('فشل تنفيذ الاستعلام');
        }
    }
    
    /**
     * بدء Transaction
     * 
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        try {
            if (!self::$inTransaction) {
                $connection = self::getConnection();
                $result = $connection->beginTransaction();
                self::$inTransaction = true;
                self::log('Transaction started');
                return $result;
            }
            return false;
        } catch (PDOException $e) {
            self::logError('Failed to begin transaction: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حفظ Transaction
     * 
     * @return bool
     */
    public static function commit(): bool
    {
        try {
            if (self::$inTransaction) {
                $connection = self::getConnection();
                $result = $connection->commit();
                self::$inTransaction = false;
                self::log('Transaction committed');
                return $result;
            }
            return false;
        } catch (PDOException $e) {
            self::logError('Failed to commit transaction: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * إلغاء Transaction
     * 
     * @return bool
     */
    public static function rollBack(): bool
    {
        try {
            if (self::$inTransaction) {
                $connection = self::getConnection();
                $result = $connection->rollBack();
                self::$inTransaction = false;
                self::log('Transaction rolled back');
                return $result;
            }
            return false;
        } catch (PDOException $e) {
            self::logError('Failed to rollback transaction: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * التحقق من وجود جدول
     * 
     * @param string $tableName اسم الجدول
     * @return bool
     */
    public static function tableExists(string $tableName): bool
    {
        try {
            $query = "SHOW TABLES LIKE :table";
            $result = self::selectOne($query, [':table' => $tableName]);
            return $result !== null;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * الحصول على عدد الصفوف في جدول
     * 
     * @param string $tableName اسم الجدول
     * @param string $condition شرط WHERE (اختياري)
     * @param array $params متغيرات الشرط
     * @return int
     */
    public static function count(string $tableName, string $condition = '', array $params = []): int
    {
        try {
            $query = "SELECT COUNT(*) as total FROM {$tableName}";
            if (!empty($condition)) {
                $query .= " WHERE {$condition}";
            }
            
            $result = self::selectOne($query, $params);
            return $result ? (int)$result['total'] : 0;
        } catch (Exception $e) {
            self::logError('Count query failed: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * إغلاق الاتصال
     * 
     * @return void
     */
    public static function closeConnection(): void
    {
        self::$connection = null;
        self::$inTransaction = false;
        self::log('Database connection closed');
    }
    
    /**
     * الحصول على عدد الاستعلامات المنفذة
     * 
     * @return int
     */
    public static function getQueryCount(): int
    {
        return self::$queryCount;
    }
    
    /**
     * إعادة تعيين عداد الاستعلامات
     * 
     * @return void
     */
    public static function resetQueryCount(): void
    {
        self::$queryCount = 0;
    }
    
    /**
     * تسجيل رسالة في السجل
     * 
     * @param string $message الرسالة
     * @return void
     */
    private static function log(string $message): void
    {
        if (self::$config['log_queries'] ?? false) {
            $logFile = __DIR__ . '/../storage/logs/database.log';
            $logDir = dirname($logFile);
            
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
    
    /**
     * تسجيل خطأ في السجل
     * 
     * @param string $error رسالة الخطأ
     * @return void
     */
    private static function logError(string $error): void
    {
        $logFile = __DIR__ . '/../storage/logs/database-errors.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] ERROR: {$error}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
