<?php require_once __DIR__ . "/config.php"; ?>
<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$custom_html = isset($_POST['custom_html']) ? trim($_POST['custom_html']) : '';

if (!$email) {
    echo json_encode(['status' => false, 'message' => '请输入有效的邮箱地址！']);
    exit;
}

// 动态使用 Session 中的发件人凭据；未登录发信时降级为默认的官方 no-reply 验证码服务
$smtp_user = isset($_SESSION['webmail_user']) && !empty($_SESSION['webmail_user']) ? $_SESSION['webmail_user'] : 'no-reply@example.com';
$smtp_pass = isset($_SESSION['webmail_pass']) && !empty($_SESSION['webmail_pass']) ? $_SESSION['webmail_pass'] : 'admin';

// 允许通过 POST 参数显式指定发件人账号
if (isset($_POST['from_email']) && !empty($_POST['from_email'])) {
    $smtp_user = trim($_POST['from_email']);
}

// 校验账号是否被管理员停用发信 (active=0)
try {
    $pdo_chk = new PDO("mysql:host=127.0.0.1;port=13306;dbname=mailcow;charset=utf8mb4", "mailcow", "GkvoHmEZ1692OFhcdtrcBZNuInMu");
    $stmt_chk = $pdo_chk->prepare("SELECT active FROM mailbox WHERE username = ?");
    $stmt_chk->execute([$smtp_user]);
    $user_row = $stmt_chk->fetch(PDO::FETCH_ASSOC);
    if ($user_row && intval($user_row['active']) === 0) {
        echo json_encode(['status' => false, 'message' => "发信拒绝：账号 {$smtp_user} 已被管理员封禁或设为仅收信状态！"], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    // 降级继续
}

$to = $email;

// 如果用户通过 Webmail 在线富文本/HTML 编辑器自定义了正文
if (!empty($custom_html)) {
    $mail_subject = !empty($subject) ? $subject : '自建邮局系统连通性测试邮件';
    $html_body = $custom_html;
} else {
    // 默认的验证码样式
    $code = sprintf("%06d", mt_rand(0, 999999));
    $_SESSION['verify_code'] = $code;
    $_SESSION['verify_email'] = $email;
    $mail_subject = '【中心服务邮局】您的企业域名入驻验证码';
    $html_body = "
<html>
<head><meta charset='utf-8'></head>
<body style='font-family: Arial, sans-serif; color: #1a1f2e; padding: 20px;'>
  <div style='max-width: 600px; margin: 0 auto; background: #fbfbfc; border: 1px solid #e8eaed; border-radius: 8px; padding: 32px;'>
    <h2 style='color: #ec652b; margin-bottom: 16px;'>中心服务邮局 - 身份验证</h2>
    <p>您好，</p>
    <p>您正在申请入驻中心服务邮局（email.example.com）。您的 6 位 HTML 邮件安全验证码为：</p>
    <div style='font-size: 28px; font-weight: bold; color: #ec652b; background: #f0f2f5; padding: 16px; text-align: center; border-radius: 6px; margin: 24px 0; letter-spacing: 4px;'>
      {$code}
    </div>
    <p style='color: #5f6368; font-size: 13px;'>验证码在 10 分钟内有效。发信账号：{$smtp_user}。如非本人操作，请忽略此邮件。</p>
    <hr style='border: none; border-top: 1px solid #e8eaed; margin: 24px 0;'>
    <p style='color: #80868b; font-size: 12px;'>中心服务邮局 · 私有化部署的安全堡垒，企业级通信的管理中枢。</p>
  </div>
</body>
</html>";
}

$domain_host = explode('@', $smtp_user)[1] ?? 'example.com';

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=utf-8\r\n";
$headers .= "From: 中心服务邮局 <{$smtp_user}>\r\n";
$headers .= "To: <{$to}>\r\n";
$headers .= "Subject: =?UTF-8?B?" . base64_encode($mail_subject) . "?=\r\n";
$headers .= "Date: " . date('r') . "\r\n";
$headers .= "Message-ID: <" . md5(uniqid(microtime(), true)) . "@{$domain_host}>\r\n";

$full_mime = $headers . "\r\n" . $html_body;

function send_smtp($to, $user, $pass, $mime) {
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ]);
    $socket = @stream_socket_client("" . "tcp://" . SMTP_HOST . ":" . SMTP_PORT . "", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        $socket = @stream_socket_client("" . "tcp://" . SMTP_HOST . ":25"", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    }
    if (!$socket) return false;

    fgets($socket, 512);

    fwrite($socket, "EHLO mail.learnflow.edu.kg\r\n");
    while ($line = fgets($socket, 512)) {
        if (substr($line, 3, 1) == ' ') break;
    }

    fwrite($socket, "STARTTLS\r\n");
    $starttls_res = fgets($socket, 512);
    if (strpos($starttls_res, '220') !== false) {
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        fwrite($socket, "EHLO mail.learnflow.edu.kg\r\n");
        while ($line = fgets($socket, 512)) {
            if (substr($line, 3, 1) == ' ') break;
        }
    }

    fwrite($socket, "AUTH LOGIN\r\n");
    fgets($socket, 512);

    fwrite($socket, base64_encode($user) . "\r\n");
    fgets($socket, 512);

    fwrite($socket, base64_encode($pass) . "\r\n");
    $auth_res = fgets($socket, 512);
    if (strpos($auth_res, '235') === false) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "MAIL FROM:<{$user}>\r\n");
    fgets($socket, 512);

    fwrite($socket, "RCPT TO:<{$to}>\r\n");
    fgets($socket, 512);

    fwrite($socket, "DATA\r\n");
    fgets($socket, 512);

    fwrite($socket, $mime . "\r\n.\r\n");
    $data_res = fgets($socket, 512);

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return strpos($data_res, '250') !== false;
}

function sync_imap_sent($user, $pass, $mime) {
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ]);
    $socket = @stream_socket_client("" . "tcp://" . IMAP_HOST . ":" . IMAP_PORT . "", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) return false;

    fgets($socket, 512);

    $safe_user = addslashes($user);
    $safe_pass = addslashes($pass);
    fwrite($socket, "A1 LOGIN \"{$safe_user}\" \"{$safe_pass}\"\r\n");
    $login_res = fgets($socket, 512);
    if (strpos($login_res, 'A1 OK') === false) {
        fclose($socket);
        return false;
    }

    $len = strlen($mime);
    fwrite($socket, "A2 APPEND \"Sent\" (\\Seen) {{$len}}\r\n");
    $append_res = fgets($socket, 512);
    if (strpos($append_res, '+') !== false || strpos($append_res, 'CONTINUE') !== false) {
        fwrite($socket, $mime . "\r\n");
        fgets($socket, 512);
    }

    fwrite($socket, "A3 LOGOUT\r\n");
    fclose($socket);
    return true;
}

$sent_ok = send_smtp($to, $smtp_user, $smtp_pass, $full_mime);

if ($sent_ok) {
    sync_imap_sent($smtp_user, $smtp_pass, $full_mime);
    echo json_encode(['status' => true, 'message' => "邮件已通过 {$smtp_user} 成功发送并存储至该账号发件箱！"]);
} else {
    echo json_encode(['status' => false, 'message' => "发信失败：使用账号 {$smtp_user} 连接 SMTP 服务失败，请检查账密。"]);
}
