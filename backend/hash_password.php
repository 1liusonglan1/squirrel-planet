<?php
/**
 * 密码加密工具 - 用于生成SHA-256哈希值
 * 注意：在实际应用中，应使用更安全的哈希算法如password_hash()和password_verify()
 * 但为了与前端SHA-256保持一致，这里提供SHA-256功能
 */

if (php_sapi_name() !== 'cli') {
    die('此脚本只能在命令行中运行');
}

if ($argc < 2) {
    echo "用法: php hash_password.php <password>\n";
    echo "示例: php hash_password.php mypassword\n";
    exit(1);
}

$password = $argv[1];
$hashedPassword = hash('sha256', $password);

echo "原始密码: $password\n";
echo "SHA-256哈希: $hashedPassword\n";
?>