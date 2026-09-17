<?php require_once __DIR__ . "/config.php"; ?>
<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$smtp_user = isset($_SESSION['webmail_user']) ? $_SESSION['webmail_user'] : '';
$smtp_pass = isset($_SESSION['webmail_pass']) ? $_SESSION['webmail_pass'] : '';

if (empty($smtp_user) && isset($_POST['email'])) {
    $smtp_user = trim($_POST['email']);
    $smtp_pass = isset($_POST['password']) ? trim($_POST['password']) : '';
}

if (empty($smtp_user) || empty($smtp_pass)) {
    echo json_encode(['status' => false, 'message' => '未授权：请先登录邮箱！'], JSON_UNESCAPED_UNICODE);
    exit;
}

$msg_nums_raw = isset($_POST['msg_nums']) ? trim($_POST['msg_nums']) : '';
$msg_num = isset($_POST['msg_num']) ? intval($_POST['msg_num']) : 0;
$folder = isset($_POST['folder']) ? trim($_POST['folder']) : 'INBOX';
$action = isset($_POST['action']) ? trim($_POST['action']) : 'delete'; // delete / expunge / restore

$msg_nums = [];
if (!empty($msg_nums_raw)) {
    $parts = explode(',', $msg_nums_raw);
    foreach ($parts as $p) {
        $v = intval(trim($p));
        if ($v > 0) $msg_nums[] = $v;
    }
} elseif ($msg_num > 0) {
    $msg_nums[] = $msg_num;
}

$folder_map = [
    'inbox'  => 'INBOX',
    'sent'   => 'Sent',
    'drafts' => 'Drafts',
    'junk'   => 'Junk',
    'trash'  => 'Trash'
];
$target_folder = isset($folder_map[strtolower($folder)]) ? $folder_map[strtolower($folder)] : 'INBOX';

function delete_imap_mail($user, $pass, $folder_name, $msg_nums, $action) {
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ]);
    $socket = @stream_socket_client("" . "tcp://" . IMAP_HOST . ":" . IMAP_PORT . "", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['status' => false, 'message' => '无法连接 IMAP 服务器'];
    }

    fgets($socket, 512);

    fwrite($socket, "A0 STARTTLS\r\n");
    $res = fgets($socket, 512);
    if (strpos($res, 'A0 OK') === false) {
        fclose($socket);
        return ['status' => false, 'message' => 'STARTTLS 协商失败'];
    }

    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);

    $safe_user = addslashes($user);
    $safe_pass = addslashes($pass);
    fwrite($socket, "A1 LOGIN \"{$safe_user}\" \"{$safe_pass}\"\r\n");
    $login_res = fgets($socket, 512);
    if (strpos($login_res, 'A1 OK') === false) {
        fclose($socket);
        return ['status' => false, 'message' => 'IMAP 鉴权失败'];
    }

    fwrite($socket, "A2 SELECT \"{$folder_name}\"\r\n");
    while ($line = fgets($socket, 512)) {
        if (strpos($line, 'A2 ') === 0) break;
    }

    if (!empty($msg_nums)) {
        $nums_str = implode(',', $msg_nums);
        if ($action === 'restore') {
            // 恢复邮件：从当前箱 (如 Trash) COPY 到 INBOX 并标记 \Deleted 后 EXPUNGE
            fwrite($socket, "A3 COPY {$nums_str} \"INBOX\"\r\n");
            while ($line = fgets($socket, 512)) {
                if (strpos($line, 'A3 ') === 0) break;
            }
            fwrite($socket, "A4 STORE {$nums_str} +FLAGS (\\Deleted)\r\n");
            while ($line = fgets($socket, 512)) {
                if (strpos($line, 'A4 ') === 0) break;
            }
            fwrite($socket, "A5 EXPUNGE\r\n");
            while ($line = fgets($socket, 512)) {
                if (strpos($line, 'A5 ') === 0) break;
            }
        } elseif ($action === 'expunge') {
            // 批量彻底物理删除：标记 Deleted 并 EXPUNGE
            fwrite($socket, "A3 STORE {$nums_str} +FLAGS (\\Deleted)\r\n");
            while ($line = fgets($socket, 512)) {
                if (strpos($line, 'A3 ') === 0) break;
            }
            fwrite($socket, "A4 EXPUNGE\r\n");
            while ($line = fgets($socket, 512)) {
                if (strpos($line, 'A4 ') === 0) break;
            }
        } else {
            // 批量移至已删除邮箱: COPY 到 Trash 并标记当前为 Deleted
            if (strtolower($folder_name) !== 'trash') {
                fwrite($socket, "A3 COPY {$nums_str} \"Trash\"\r\n");
                while ($line = fgets($socket, 512)) {
                    if (strpos($line, 'A3 ') === 0) break;
                }
                fwrite($socket, "A4 STORE {$nums_str} +FLAGS (\\Deleted)\r\n");
                while ($line = fgets($socket, 512)) {
                    if (strpos($line, 'A4 ') === 0) break;
                }
                fwrite($socket, "A5 EXPUNGE\r\n");
                while ($line = fgets($socket, 512)) {
                    if (strpos($line, 'A5 ') === 0) break;
                }
            }
        }
    }

    fwrite($socket, "A6 LOGOUT\r\n");
    fclose($socket);

    return ['status' => true, 'message' => '底层 IMAP 邮局同步批量删除成功'];
}

$res = delete_imap_mail($smtp_user, $smtp_pass, $target_folder, $msg_nums, $action);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
