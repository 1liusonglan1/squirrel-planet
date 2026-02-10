<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问，生产环境应限制特定域名
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 引入数据库配置
require_once 'config.php';

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => '无效的请求数据']);
    exit();
}

$mode = $input['mode'] ?? '';

try {
    $pdo = getConnection();
    
    if ($mode === 'forgot_password') {
        // 忘记密码逻辑
        forgotPassword($pdo, $input);
    } elseif ($mode === 'reset_password') {
        // 重置密码逻辑
        resetPassword($pdo, $input);
    } else {
        echo json_encode(['success' => false, 'message' => '无效的操作模式']);
    }
} catch (Exception $e) {
    error_log("密码重置错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器内部错误']);
}

function forgotPassword($pdo, $input) {
    $email = trim($input['email'] ?? '');
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        return;
    }
    
    // 检查用户是否存在
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // 为了安全，不直接告知邮箱是否存在
        echo json_encode(['success' => true, 'message' => '如果该邮箱存在，重置链接已发送至您的邮箱']);
        return;
    }
    
    // 生成重置令牌
    $token = bin2hex(random_bytes(32)); // 生成64位的随机字符串
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1小时后过期
    
    // 存储重置令牌到数据库
    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    $result = $stmt->execute([$token, $expires, $email]);
    
    if ($result) {
        // 发送重置邮件（这里使用模拟方式，实际应用中应使用邮件服务）
        // 模拟发送邮件
        $resetLink = 'https://1liusonglan1.github.io/login_or_signup/reset_password.html?token=' . $token;
        
        // 注意：在实际应用中，这里应该使用邮件服务发送重置链接
        // 比如使用PHPMailer或其他邮件服务
        // mail($email, '密码重置', "点击以下链接重置密码: $resetLink");
        
        // 为了演示，我们返回重置链接
        echo json_encode([
            'success' => true, 
            'message' => '如果该邮箱存在，重置链接已发送至您的邮箱',
            'reset_link' => $resetLink // 仅用于演示，实际不应该返回重置链接
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '重置令牌生成失败']);
    }
}

function resetPassword($pdo, $input) {
    $token = trim($input['token'] ?? '');
    $newPassword = $input['new_password'] ?? '';
    
    // 验证输入
    if (empty($token) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => '重置令牌和新密码不能为空']);
        return;
    }
    
    // 验证新密码格式（SHA-256哈希应该是64位十六进制字符）
    if (!preg_match('/^[a-f0-9]{64}$/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => '新密码格式不正确']);
        return;
    }
    
    // 检查令牌是否有效且未过期
    $stmt = $pdo->prepare("SELECT id, email, reset_token_expires FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '重置令牌无效或已过期']);
        return;
    }
    
    // 更新用户密码并清除重置令牌
    $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
    $result = $stmt->execute([$newPassword, $user['id']]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '密码重置成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '密码重置失败']);
    }
}
?>