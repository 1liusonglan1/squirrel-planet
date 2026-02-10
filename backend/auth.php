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
    
    if ($mode === 'register') {
        // 注册逻辑
        registerUser($pdo, $input);
    } elseif ($mode === 'login') {
        // 登录逻辑
        loginUser($pdo, $input);
    } elseif ($mode === 'forgot_password') {
        // 忘记密码 - 发送验证码
        forgotPassword($pdo, $input);
    } elseif ($mode === 'verify_code') {
        // 验证验证码
        verifyCode($pdo, $input);
    } elseif ($mode === 'reset_password') {
        // 重置密码（通过验证码验证后）
        resetPassword($pdo, $input);
    } else {
        echo json_encode(['success' => false, 'message' => '无效的操作模式']);
    }
} catch (Exception $e) {
    error_log("认证错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器内部错误']);
}

function registerUser($pdo, $input) {
    $username = trim($input['name'] ?? '');
    $password = $input['pass'] ?? '';
    $email = trim($input['email'] ?? '');
    
    // 验证输入
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
        return;
    }

    // 验证用户名格式（只允许字母、数字、下划线，长度3-20）
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        echo json_encode(['success' => false, 'message' => '用户名格式不正确，应为3-20位字母、数字或下划线']);
        return;
    }

    // 验证密码格式（SHA-256哈希应该是64位十六进制字符）
    if (!preg_match('/^[a-f0-9]{64}$/', $password)) {
        echo json_encode(['success' => false, 'message' => '密码格式不正确']);
        return;
    }
    
    // 验证邮箱格式（如果提供了邮箱）
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        return;
    }

    // 检查用户名是否已存在
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => '用户名已存在']);
        return;
    }
    
    // 检查邮箱是否已存在（如果提供了邮箱）
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => '邮箱已被注册']);
            return;
        }
    }
    
    // 插入新用户
    if (!empty($email)) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, created_at) VALUES (?, ?, ?, NOW())");
        $result = $stmt->execute([$username, $password, $email]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, created_at) VALUES (?, ?, NOW())");
        $result = $stmt->execute([$username, $password]);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '注册成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '注册失败']);
    }
}

function loginUser($pdo, $input) {
    $username = trim($input['name'] ?? '');
    $password = $input['pass'] ?? '';
    
    // 验证输入
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
        return;
    }

    // 验证用户名格式（只允许字母、数字、下划线，长度3-20）
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        echo json_encode(['success' => false, 'message' => '用户名格式不正确']);
        return;
    }

    // 验证密码格式（SHA-256哈希应该是64位十六进制字符）
    if (!preg_match('/^[a-f0-9]{64}$/', $password)) {
        echo json_encode(['success' => false, 'message' => '密码格式不正确']);
        return;
    }
    
    // 查找用户
    $stmt = $pdo->prepare("SELECT id, username, password, email FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户名不存在']);
        return;
    }
    
    // 验证密码（由于前端使用SHA256加密，这里直接比较）
    if (hash_equals($user['password'], $password)) { // 使用hash_equals防止时序攻击
        // 登录成功，可以设置会话或其他认证信息
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        echo json_encode([
            'success' => true, 
            'message' => '登录成功',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '密码错误']);
    }
}

function forgotPassword($pdo, $input) {
    $email = trim($input['email'] ?? '');
    
    // 验证邮箱格式
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        return;
    }
    
    // 检查用户是否存在
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // 为了安全，不直接告知邮箱是否存在
        echo json_encode(['success' => true, 'message' => '如果该邮箱存在，验证码已发送至您的邮箱']);
        return;
    }
    
    // 生成6位数字验证码
    $verification_code = sprintf("%06d", rand(0, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes')); // 10分钟后过期
    
    // 存储验证码到数据库
    // 首先检查是否已存在记录，如果存在则更新，否则插入新记录
    $checkStmt = $pdo->prepare("SELECT id FROM password_resets WHERE email = ?");
    $checkStmt->execute([$email]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        $updateStmt = $pdo->prepare("UPDATE password_resets SET token = ?, created_at = ?, expires_at = ? WHERE email = ?");
        $result = $updateStmt->execute([$verification_code, date('Y-m-d H:i:s'), $expires_at, $email]);
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
        $result = $insertStmt->execute([$email, $verification_code, $expires_at]);
    }
    
    if ($result) {
        // 发送验证码（这里使用模拟方式，实际应用中应使用邮件服务）
        // 模拟发送邮件
        echo json_encode([
            'success' => true, 
            'message' => '如果该邮箱存在，验证码已发送至您的邮箱',
            'verification_code' => $verification_code // 仅用于演示，实际不应返回验证码
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '验证码发送失败']);
    }
}

function verifyCode($pdo, $input) {
    $email = trim($input['email'] ?? '');
    $code = trim($input['code'] ?? '');
    
    // 验证输入
    if (empty($email) || empty($code)) {
        echo json_encode(['success' => false, 'message' => '邮箱和验证码不能为空']);
        return;
    }
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        return;
    }
    
    // 检查验证码是否正确且未过期
    $stmt = $pdo->prepare("SELECT id, email, token, expires_at FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()");
    $stmt->execute([$email, $code]);
    $verification = $stmt->fetch();
    
    if (!$verification) {
        echo json_encode(['success' => false, 'message' => '验证码错误或已过期']);
        return;
    }
    
    // 验证成功，返回临时令牌用于重置密码
    $temp_token = bin2hex(random_bytes(32)); // 生成临时令牌
    
    // 存储临时令牌到用户表（或专门的表）
    // 这里我们更新用户表中的临时令牌字段
    $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    $result = $updateStmt->execute([$temp_token, date('Y-m-d H:i:s', strtotime('+30 minutes')), $email]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => '验证码验证成功',
            'temp_token' => $temp_token
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '验证过程中出现错误']);
    }
}

function resetPassword($pdo, $input) {
    $temp_token = trim($input['temp_token'] ?? '');
    $newPassword = $input['new_password'] ?? '';
    
    // 验证输入
    if (empty($temp_token) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => '临时令牌和新密码不能为空']);
        return;
    }
    
    // 验证新密码格式（SHA-256哈希应该是64位十六进制字符）
    if (!preg_match('/^[a-f0-9]{64}$/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => '新密码格式不正确']);
        return;
    }
    
    // 检查临时令牌是否有效且未过期
    $stmt = $pdo->prepare("SELECT id, email, reset_token_expires FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$temp_token]);
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