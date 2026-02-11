<?php
// 数据库配置
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_41121223_user_password_list'); // 数据库名
define('DB_USER', 'if0_41121223'); // 数据库账号
define('DB_PASS', '1qaz2wsxsongshu'); // 数据库密码

// 创建数据库连接
function getConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, 
            DB_USER, 
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("数据库连接失败: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '数据库连接失败']);
        exit();
    }
}
?>