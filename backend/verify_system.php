<?php
// 验证后端文件是否都存在
$required_files = [
    'config.php',
    'auth.php',
    'create_table.sql',
    'hash_password.php',
    'test_db.php'
];

echo "<h2>松鼠星球 - 登录/注册系统验证报告</h2>\n";
echo "<p><strong>时间:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

echo "<h3>1. 文件完整性检查</h3>\n";
echo "<ul>\n";
$all_files_exist = true;
foreach ($required_files as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path)) {
        echo "<li style='color: #00FF9D;'>✓ $file - 存在</li>\n";
    } else {
        echo "<li style='color: #EC4899;'>✗ $file - 缺失</li>\n";
        $all_files_exist = false;
    }
}
echo "</ul>\n";

if ($all_files_exist) {
    echo "<p style='color: #00FF9D;'><strong>文件完整性检查: 通过</strong></p>\n";
} else {
    echo "<p style='color: #EC4899;'><strong>文件完整性检查: 失败</strong></p>\n";
}

echo "<h3>2. 数据库配置验证</h3>\n";
if (file_exists('config.php')) {
    require_once 'config.php';
    
    echo "<ul>\n";
    echo "<li>数据库主机: " . DB_HOST . "</li>\n";
    echo "<li>数据库端口: " . DB_PORT . "</li>\n";
    echo "<li>数据库名称: " . DB_NAME . "</li>\n";
    echo "<li>数据库用户: " . DB_USER . "</li>\n";
    echo "<li>数据库密码: " . (defined('DB_PASS') && DB_PASS ? '已设置' : '未设置') . "</li>\n";
    echo "</ul>\n";
    
    echo "<h3>3. 数据库连接测试</h3>\n";
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
        
        // 测试查询
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        if ($result) {
            echo "<p style='color: #00FF9D;'><strong>数据库连接: 成功</strong></p>\n";
            
            // 检查用户表是否存在
            echo "<h3>4. 数据库表结构验证</h3>\n";
            try {
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'");
                if ($tableCheck->rowCount() > 0) {
                    echo "<p style='color: #00FF9D;'><strong>用户表 (users): 存在</strong></p>\n";
                    
                    // 检查表结构
                    $columns = $pdo->query("DESCRIBE users");
                    echo "<h4>用户表结构:</h4>\n<ul>\n";
                    while ($column = $columns->fetch()) {
                        echo "<li>{$column['Field']} - {$column['Type']}, {$column['Null']}, {$column['Key']}, {$column['Extra']}</li>\n";
                    }
                    echo "</ul>\n";
                } else {
                    echo "<p style='color: #EC4899;'><strong>用户表 (users): 不存在</strong></p>\n";
                    echo "<p>请运行 create_table.sql 文件创建表结构</p>\n";
                }
            } catch (Exception $e) {
                echo "<p style='color: #EC4899;'>检查表结构时出错: " . $e->getMessage() . "</p>\n";
            }
        } else {
            echo "<p style='color: #EC4899;'><strong>数据库连接: 失败 - 无法执行查询</strong></p>\n";
        }
    } catch (PDOException $e) {
        echo "<p style='color: #EC4899;'><strong>数据库连接: 失败 - " . $e->getMessage() . "</strong></p>\n";
    }
} else {
    echo "<p style='color: #EC4899;'>配置文件不存在，无法进行数据库验证</p>\n";
}

echo "<h3>5. 前端集成验证</h3>\n";
$index_file = __DIR__ . '/../index.html';
if (file_exists($index_file)) {
    $index_content = file_get_contents($index_file);
    
    $checks = [
        '登录按钮' => strpos($index_content, 'id="loginBtn"') !== false,
        '注册按钮' => strpos($index_content, 'id="registerBtn"') !== false,
        '认证模态框' => strpos($index_content, 'id="authModal"') !== false,
        '认证JavaScript' => strpos($index_content, 'setupAuthModal()') !== false,
        '密码哈希功能' => strpos($index_content, 'hashPassword(') !== false
    ];
    
    echo "<ul>\n";
    $frontend_ok = true;
    foreach ($checks as $name => $exists) {
        if ($exists) {
            echo "<li style='color: #00FF9D;'>✓ $name - 已集成</li>\n";
        } else {
            echo "<li style='color: #EC4899;'>✗ $name - 未找到</li>\n";
            $frontend_ok = false;
        }
    }
    echo "</ul>\n";
    
    if ($frontend_ok) {
        echo "<p style='color: #00FF9D;'><strong>前端集成: 完成</strong></p>\n";
    } else {
        echo "<p style='color: #EC4899;'><strong>前端集成: 不完整</strong></p>\n";
    }
} else {
    echo "<p style='color: #EC4899;'>index.html 文件不存在</p>\n";
}

echo "<h3>6. 系统总结</h3>\n";
echo "<ul>\n";
echo "<li>前端: 登录/注册界面已添加到 index.html</li>\n";
echo "<li>后端: PHP 脚本已创建在 backend/ 目录</li>\n";
echo "<li>数据库: 配置和表结构已定义</li>\n";
echo "<li>安全: 密码通过 SHA-256 前端加密传输</li>\n";
echo "<li>API: 提供了登录/注册接口 (backend/auth.php)</li>\n";
echo "</ul>\n";

echo "<h3>7. 部署说明</h3>\n";
echo "<ol>\n";
echo "<li>将整个项目上传到支持PHP的服务器</li>\n";
echo "<li>在服务器上运行 create_table.sql 创建数据库表</li>\n";
echo "<li>更新 backend/config.php 中的数据库密码为真实密码</li>\n";
echo "<li>确保PHP版本为7.4或更高</li>\n";
echo "<li>确保启用了PDO和MySQL扩展</li>\n";
echo "</ol>\n";
?>

<style>
    body {
        font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #0D1117;
        color: #F0F6FC;
        padding: 40px;
        line-height: 1.6;
    }
    
    h2 {
        background: linear-gradient(135deg, #FFA500, #FF8C00);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 10px;
    }
    
    h3 {
        color: #FFB347;
        margin-top: 25px;
        margin-bottom: 15px;
    }
    
    h4 {
        color: #FFA500;
        margin: 15px 0 10px 0;
    }
    
    ul {
        margin: 10px 0 10px 20px;
    }
    
    li {
        margin: 5px 0;
    }
    
    ol {
        margin: 10px 0 10px 20px;
    }
</style>