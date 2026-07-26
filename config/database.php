<?php
/**
 * config/database.php
 * إعدادات الاتصال بقاعدة البيانات باستخدام PDO
 */

// إعدادات الاتصال - غيّرها حسب بيئتك
define('DB_HOST', 'localhost');
define('DB_NAME', 'marketplace_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Class Database
 * يقوم بإنشاء اتصال PDO آمن باستخدام Prepared Statements
 */
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // في بيئة التطوير اظهر الخطأ، وفي الإنتاج سجلها في ملف logs
                die('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }
}