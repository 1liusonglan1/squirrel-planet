<?php
// 处理 CORS 预检请求 - 必须放在最前面
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    http_response_code(200);
    exit();
}

// 允许跨域访问
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// 邮件服务配置
define('MAIL_API_URL', 'https://api.mmp.cc/api/mail');
define('MAIL_SENDER_EMAIL', '2477872205@qq.com'); // 发信邮箱
define('MAIL_SENDER_KEY', 'rjcgnglacqzmebhd'); // 邮箱授权码
define('MAIL_SENDER_NAME', '松鼠星球'); // 发信昵称

// 发送邮件函数
function sendVerificationEmail($toEmail, $code) {
    $params = [
        'email' => MAIL_SENDER_EMAIL,
        'key' => MAIL_SENDER_KEY,
        'mail' => $toEmail,
        'title' => '【松鼠星球】验证码',
        'name' => MAIL_SENDER_NAME,
        'text' => "您好！\n\n您的验证码是：$code\n\n验证码有效期为10分钟，请尽快使用。\n\n如非本人操作，请忽略此邮件。\n\n-- 松鼠星球"
    ];
    
    $url = MAIL_API_URL . '?' . http_build_query($params);
    
    // 使用 cURL 替代 file_get_contents
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("cURL 错误: " . $error);
        return false;
    }
    
    $result = json_decode($response, true);
    
    if ($result && isset($result['status']) && $result['status'] === 'success') {
        return true;
    } else {
        error_log("邮件发送失败: " . $response);
        return false;
    }
}

// 引入数据库配置
require_once 'config.php';

// 处理 ping 请求（用于测试服务器是否可达）
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ping'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode([
        'status' => 'ok',
        'message' => '服务器运行正常',
        'timestamp' => time(),
        'server' => $_SERVER['HTTP_HOST'] ?? 'unknown'
    ]);
    exit();
}

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
        registerUser($pdo, $input);
    } elseif ($mode === 'login') {
        loginUser($pdo, $input);
    } elseif ($mode === 'forgot_password') {
        forgotPassword($pdo, $input);
    } elseif ($mode === 'send_verification') {
        sendVerificationCode($pdo, $input);
    } elseif ($mode === 'verify_code') {
        verifyCode($pdo, $input);
    } elseif ($mode === 'reset_password') {
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
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
        return;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        echo json_encode(['success' => false, 'message' => '用户名格式不正确，应为3-20位字母、数字或下划线']);
        return;
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $password)) {
        echo json_encode(['success' => false, 'message' => '密码格式不正确']);
        return;
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => '用户名已存在']);
        return;
    }
    
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => '邮箱已被注册']);
            return;
        }
    }
    
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
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
        return;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        echo json_encode(['success' => false, 'message' => '用户名格式不正确']);
        return;
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $password)) {
        echo json_encode(['success' => false, 'message' => '密码格式不正确']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT id, username, password, email FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户名不存在']);
        return;
    }
    
    if (hash_equals($user['password'], $password)) {
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
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => true, 'message' => '如果该邮箱存在，验证码已发送至您的邮箱']);
        return;
    }
    
    $verification_code = sprintf("%06d", rand(0, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
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
        $mailSent = sendVerificationEmail($email, $verification_code);
        
        if ($mailSent) {
            echo json_encode([
                'success' => true, 
                'message' => '验证码已发送至您的邮箱'
            ]);
        } else {
            // 邮件发送失败时，返回成功但带调试信息（实际应该返回失败）
            // 为了调试，先返回成功，实际部署时改为 false
            error_log("邮件发送失败，验证码: $verification_code");
            echo json_encode([
                'success' => true, 
                'message' => '验证码已发送至您的邮箱（邮件服务可能暂时不可用，验证码已记录）',
                'debug_code' => $verification_code // 调试用
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '验证码保存失败']);
    }
}

function sendVerificationCode($pdo, $input) {
    $email = trim($input['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        return;
    }
    
    // 检查邮箱是否已被注册
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo json_encode(['success' => false, 'message' => '该邮箱已被注册']);
        return;
    }
    
    // 生成验证码并保存
    $verification_code = sprintf("%06d", rand(0, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // 使用 email_verification 表存储注册验证码
    $checkStmt = $pdo->prepare("SELECT id FROM email_verification WHERE email = ?");
    $checkStmt->execute([$email]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        $updateStmt = $pdo->prepare("UPDATE email_verification SET token = ?, created_at = ?, expires_at = ? WHERE email = ?");
        $result = $updateStmt->execute([$verification_code, date('Y-m-d H:i:s'), $expires_at, $email]);
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO email_verification (email, token, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
        $result = $insertStmt->execute([$email, $verification_code, $expires_at]);
    }
    
    if ($result) {
        $mailSent = sendVerificationEmail($email, $verification_code);
        
        if ($mailSent) {
            echo json_encode([
                'success' => true, 
                'message' => '验证码已发送至您的邮箱'
            ]);
        } else {
            // 邮件发送失败时，返回成功但带调试信息
            error_log("邮件发送失败，验证码: $verification_code");
            echo json_encode([
                'success' => true, 
                'message' => '验证码已发送至您的邮箱（邮件服务可能暂时不可用，验证码已记录）',
                'debug_code' => $verification_code
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '验证码保存失败']);
    }
}

function verifyCode($pdo, $input) {
    $email = trim($input['email'] ?? '');
    $code = trim($input['code'] ?? '');
    
    if (empty($email) || empty($code)) {
        echo json_encode(['success' => false, 'message' => '邮箱和验证码不能为空']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT id, email, token, expires_at FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()");
    $stmt->execute([$email, $code]);
    $verification = $stmt->fetch();
    
    if (!$verification) {
        echo json_encode(['success' => false, 'message' => '验证码错误或已过期']);
        return;
    }
    
    $temp_token = bin2hex(random_bytes(32));
    
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
    
    if (empty($temp_token) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => '临时令牌和新密码不能为空']);
        return;
    }
    
    if (!preg_match('/^[a-f0-9]{64}$/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => '新密码格式不正确']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT id, email, reset_token_expires FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$temp_token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '重置令牌无效或已过期']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
    $result = $stmt->execute([$newPassword, $user['id']]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '密码重置成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '密码重置失败']);
    }
}
?>
