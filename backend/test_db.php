<?php
header('Content-Type: application/json');

// 引入数据库配置
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // 尝试执行一个简单的查询来验证连接
    $stmt = $pdo->query("SELECT 1");
    $result = $stmt->fetch();
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => '数据库连接成功！'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => '数据库连接失败：无法执行查询'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => '数据库连接失败：' . $e->getMessage()
    ]);
}
?>