<?php require_once __DIR__ . "/config.php"; ?>
<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// 从 Session 中动态获取当前登录用户的凭据
$smtp_user = isset($_SESSION['webmail_user']) ? $_SESSION['webmail_user'] : '';
$smtp_pass = isset($_SESSION['webmail_pass']) ? $_SESSION['webmail_pass'] : '';

// 支持通过 POST/Header 或临时凭据补充
if (empty($smtp_user) && isset($_POST['email'])) {
    $smtp_user = trim($_POST['email']);
    $smtp_pass = isset($_POST['password']) ? trim($_POST['password']) : '';
}

if (empty($smtp_user) || empty($smtp_pass)) {
    echo json_encode(['status' => false, 'message' => '未授权：请先登录邮箱！', 'mails' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$folder = isset($_GET['folder']) ? trim($_GET['folder']) : 'INBOX';

$folder_map = [
    'inbox'  => 'INBOX',
    'sent'   => 'Sent',
    'drafts' => 'Drafts',
    'junk'   => 'Junk',
    'trash'  => 'Trash'
];
$target_folder = isset($folder_map[strtolower($folder)]) ? $folder_map[strtolower($folder)] : 'INBOX';

function decode_mime_header($str) {
    if (empty($str)) return '';
    if (function_exists('mb_decode_mimeheader')) {
        return mb_decode_mimeheader($str);
    }
    return iconv_mime_decode($str, 0, "UTF-8");
}

function parse_raw_mime($raw_mime, $default_user) {
    // 拆分 Header 和 Body
    $parts = explode("\r\n\r\n", $raw_mime, 2);
    $headers_raw = $parts[0];
    $body_raw = isset($parts[1]) ? $parts[1] : '';

    // 解析 Header 字段 (处理折行 Header)
    $headers_raw = preg_replace("/\r\n[ \t]+/", " ", $headers_raw);
    $header_lines = explode("\r\n", $headers_raw);
    $headers = [];
    foreach ($header_lines as $line) {
        if (strpos($line, ':') !== false) {
            list($k, $v) = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }

    $subject = isset($headers['subject']) ? decode_mime_header($headers['subject']) : '无主题';
    $sender = isset($headers['from']) ? decode_mime_header($headers['from']) : '未知发件人';
    $to = isset($headers['to']) ? decode_mime_header($headers['to']) : $default_user;

    $date = date('Y-m-d H:i');
    if (isset($headers['date'])) {
        $timestamp = strtotime($headers['date']);
        if ($timestamp !== false) {
            $date = date('Y-m-d H:i', $timestamp);
        }
    }

    // 解析 Body（处理 multipart/alternative / boundary / Base64 / Quoted-Printable）
    $body_html = '';
    $body_plain = '';

    if (isset($headers['content-type']) && preg_match('/boundary="?([^";]+)"?/i', $headers['content-type'], $bm)) {
        $boundary = $bm[1];
        $sub_parts = explode('--' . $boundary, $body_raw);
        foreach ($sub_parts as $sub_part) {
            if (empty(trim($sub_part)) || trim($sub_part) === '--') continue;

            $sub_split = explode("\r\n\r\n", ltrim($sub_part, "\r\n"), 2);
            $sub_header_raw = $sub_split[0];
            $sub_content = isset($sub_split[1]) ? $sub_split[1] : '';

            // 清理末尾 boundary 标识
            $sub_content = preg_replace('/--$/', '', trim($sub_content));

            $is_html = stripos($sub_header_raw, 'text/html') !== false;
            $is_base64 = stripos($sub_header_raw, 'base64') !== false;
            $is_qp = stripos($sub_header_raw, 'quoted-printable') !== false;

            if ($is_base64) {
                $decoded = base64_decode($sub_content);
            } elseif ($is_qp) {
                $decoded = quoted_printable_decode($sub_content);
            } else {
                $decoded = $sub_content;
            }

            if ($is_html) {
                $body_html = $decoded;
            } else {
                $body_plain = $decoded;
            }
        }
    } else {
        // 单一部分邮件正文
        $is_base64 = isset($headers['content-transfer-encoding']) && stripos($headers['content-transfer-encoding'], 'base64') !== false;
        $is_qp = isset($headers['content-transfer-encoding']) && stripos($headers['content-transfer-encoding'], 'quoted-printable') !== false;
        $is_html = isset($headers['content-type']) && stripos($headers['content-type'], 'text/html') !== false;

        if ($is_base64) {
            $decoded = base64_decode(trim($body_raw));
        } elseif ($is_qp) {
            $decoded = quoted_printable_decode(trim($body_raw));
        } else {
            $decoded = $body_raw;
        }

        if ($is_html) {
            $body_html = $decoded;
        } else {
            $body_plain = $decoded;
        }
    }

    $final_body = !empty($body_html) ? $body_html : '<div style="white-space: pre-wrap; font-family: sans-serif;">' . htmlspecialchars($body_plain) . '</div>';

    return [
        'id' => md5($sender . $date . $subject),
        'msg_num' => 0, // 动态赋予
        'sender' => $sender,
        'to' => $to,
        'date' => $date,
        'subject' => $subject,
        'body' => $final_body
    ];
}

function fetch_imap_mails($user, $pass, $folder_name) {
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ]);
    $socket = @stream_socket_client("" . "tcp://" . IMAP_HOST . ":" . IMAP_PORT . "", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['status' => false, 'message' => '无法连接 IMAP 服务器', 'mails' => []];
    }

    fgets($socket, 512);

    fwrite($socket, "A0 STARTTLS\r\n");
    $res = fgets($socket, 512);
    if (strpos($res, 'A0 OK') === false) {
        fclose($socket);
        return ['status' => false, 'message' => 'STARTTLS 协商失败', 'mails' => []];
    }

    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);

    $safe_user = addslashes($user);
    $safe_pass = addslashes($pass);
    fwrite($socket, "A1 LOGIN \"{$safe_user}\" \"{$safe_pass}\"\r\n");
    $login_res = fgets($socket, 512);
    if (strpos($login_res, 'A1 OK') === false) {
        fclose($socket);
        // 清理当前 Session，标记未授权
        unset($_SESSION['webmail_user']);
        unset($_SESSION['webmail_pass']);
        session_destroy();
        return ['status' => false, 'code' => 'AUTH_FAILED', 'message' => 'IMAP 鉴权失败：账号不存在或密码错误', 'mails' => []];
    }

    fwrite($socket, "A2 SELECT \"{$folder_name}\"\r\n");
    $exists = 0;
    while ($line = fgets($socket, 512)) {
        if (preg_match('/^\*\s+(\d+)\s+EXISTS/i', $line, $matches)) {
            $exists = intval($matches[1]);
        }
        if (strpos($line, 'A2 ') === 0) break;
    }

    $mails = [];
    if ($exists > 0) {
        $start = max(1, $exists - 19);
        for ($i = $exists; $i >= $start; $i--) {
            fwrite($socket, "A3 FETCH {$i} (BODY.PEEK[])\r\n");
            $raw_mail = '';
            while ($line = fgets($socket, 2048)) {
                if (strpos($line, 'A3 OK') === 0 || strpos($line, 'A3 BAD') === 0 || strpos($line, 'A3 NO') === 0) {
                    break;
                }
                $raw_mail .= $line;
            }
            // 剥离首行 FETCH 标签
            $raw_mail = preg_replace('/^\*\s+\d+\s+FETCH\s+\(BODY\[\]\s+\{\d+\}\r\n/i', '', $raw_mail);
            $raw_mail = preg_replace('/\)\r\n$/', '', $raw_mail);

            if (!empty(trim($raw_mail))) {
                $parsed = parse_raw_mime($raw_mail, $user);
                $parsed['msg_num'] = $i;
                $mails[] = $parsed;
            }
        }
    }

    fwrite($socket, "A4 LOGOUT\r\n");
    fclose($socket);

    return ['status' => true, 'mails' => $mails];
}

$res = fetch_imap_mails($smtp_user, $smtp_pass, $target_folder);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
