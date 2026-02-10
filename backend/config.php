<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'ser172416977057'); // 使用提供的数据库账号作为数据库名
define('DB_USER', 'ser172416977057'); // 数据库账号
define('DB_PASS', '替换真实密码'); // 数据库密码 - 请替换为真实密码

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