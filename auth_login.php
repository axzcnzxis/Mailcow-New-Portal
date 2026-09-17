<?php require_once __DIR__ . "/config.php"; ?>
<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$action = isset($_GET['action']) ? trim($_GET['action']) : 'login';

// 1. 检查 Session 会话状态接口 (action=check)
if ($action === 'check') {
    if (isset($_SESSION['webmail_user']) && !empty($_SESSION['webmail_user']) && isset($_SESSION['webmail_pass'])) {
        // 校验底层 IMAP 凭据是否依然有效（防御账号已被删除或禁用）
        $auth_check = verify_imap_credentials($_SESSION['webmail_user'], $_SESSION['webmail_pass']);
        if ($auth_check['status']) {
            echo json_encode([
                'status' => true,
                'user' => [
                    'email' => $_SESSION['webmail_user']
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            // 凭据无效（如账号已被超级管理员彻底删除），清空 Session
            unset($_SESSION['webmail_user']);
            unset($_SESSION['webmail_pass']);
            session_destroy();
            echo json_encode(['status' => false, 'message' => '账号不存在或无法完成鉴权（已被删除或禁用）'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode(['status' => false, 'message' => '未登录或会话已过期'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 2. 注销 Logout 接口 (action=logout)
if ($action === 'logout') {
    unset($_SESSION['webmail_user']);
    unset($_SESSION['webmail_pass']);
    session_destroy();
    echo json_encode(['status' => true, 'message' => '已成功安全退出 Session'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. 登录鉴权 (POST 方式)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => '无效的请求方式']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if (empty($email) || empty($password)) {
    echo json_encode(['status' => false, 'message' => '请填写完整的邮箱地址与访问密码！']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => false, 'message' => '请输入格式正确的邮箱地址！']);
    exit;
}

/**
 * 检查账号在底层 Mailcow 数据库中是否存在
 */
function check_mailbox_exists($user) {
    try {
        $pdo = new PDO("mysql:host=127.0.0.1;port=13306;dbname=mailcow;charset=utf8mb4", 'mailcow', 'GkvoHmEZ1692OFhcdtrcBZNuInMu', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mailbox WHERE username = ?");
        $stmt->execute([$user]);
        return intval($stmt->fetchColumn()) > 0;
    } catch (Exception $e) {
        return true; // 连接失败时默认返回 true，回退到 IMAP 校验
    }
}

/**
 * 通过 IMAP 协议 (端口 143/993) 连接底层 Mailcow 邮局验证账号密码
 */
function verify_imap_credentials($user, $pass) {
    // 先检查邮箱账号是否存在
    if (!check_mailbox_exists($user)) {
        return ['status' => false, 'message' => '邮箱账号不存在，请检查后重试！'];
    }

    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ]);
    
    // 优先通过 143 端口 + STARTTLS 握手鉴权
    $socket = @stream_socket_client("" . "tcp://" . IMAP_HOST . ":" . IMAP_PORT . "", $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['status' => false, 'message' => '无法连接底层 IMAP 邮局服务器 (端口 143)'];
    }

    // 读取 Welcome 协议 Banner
    $banner = fgets($socket, 512);

    // 发起 STARTTLS 加密握手
    fwrite($socket, "A0 STARTTLS\r\n");
    $res = fgets($socket, 512);
    if (strpos($res, 'A0 OK') === false) {
        fclose($socket);
        return ['status' => false, 'message' => 'STARTTLS 安全握手失败'];
    }

    // 启用 TLS 加密套接字
    @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);

    // 发送 IMAP 登录鉴权指令
    $safe_user = addslashes($user);
    $safe_pass = addslashes($pass);
    fwrite($socket, "A1 LOGIN \"{$safe_user}\" \"{$safe_pass}\"\r\n");
    
    $login_res = fgets($socket, 512);
    fwrite($socket, "A2 LOGOUT\r\n");
    fclose($socket);

    if (strpos($login_res, 'A1 OK') !== false) {
        return ['status' => true, 'message' => '底层 Mailcow 邮局鉴权成功'];
    } else {
        return ['status' => false, 'message' => '访问密码错误，请重新输入！'];
    }
}

$auth_res = verify_imap_credentials($email, $password);

if ($auth_res['status']) {
    // 鉴权成功，将用户凭据存入服务器 Session
    $_SESSION['webmail_user'] = $email;
    $_SESSION['webmail_pass'] = $password;
    
    echo json_encode([
        'status' => true, 
        'message' => '登录成功，正在加载邮箱工作区...',
        'user' => [
            'email' => $email
        ]
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode($auth_res, JSON_UNESCAPED_UNICODE);
}
