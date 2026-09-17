<?php require_once __DIR__ . "/config.php"; ?>
<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// 简单的鉴权防护
$admin_user = isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : '';
if (empty($admin_user) && isset($_POST['admin_user'])) {
    $admin_user = trim($_POST['admin_user']);
}

$action = isset($_GET['action']) ? trim($_GET['action']) : (isset($_POST['action']) ? trim($_POST['action']) : 'list');

function get_db_connection() {
    $db_host = DB_HOST;
    $db_port = DB_PORT;
    $db_name = DB_NAME;
    $db_user = DB_NAME;
    $db_pass = 'GkvoHmEZ1692OFhcdtrcBZNuInMu';

    try {
        $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (Exception $e) {
        return null;
    }
}

// 1. 获取已存在的邮箱列表 (action=list)
if ($action === 'list') {
    $pdo = get_db_connection();
    if (!$pdo) {
        echo json_encode(['status' => false, 'message' => '数据库连接失败', 'mailboxes' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->query("SELECT username as email, domain, active, quota FROM mailbox ORDER BY created DESC");
        $mailboxes = $stmt->fetchAll();
        $formatted = [];
        foreach ($mailboxes as $row) {
            $quota_gb = round($row['quota'] / (1024 * 1024 * 1024), 1);
            $quota_str = ($quota_gb > 0 ? $quota_gb : '10.0') . ' GB';
            $formatted[] = [
                'email' => $row['email'],
                'domain' => $row['domain'],
                'status' => intval($row['active']) === 1 ? 'normal' : 'readonly',
                'quota' => $quota_str
            ];
        }
        echo json_encode(['status' => true, 'mailboxes' => $formatted], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['status' => false, 'message' => '查询失败: ' . $e->getMessage(), 'mailboxes' => []], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 2. 新增邮箱账号 (action=create)
if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => false, 'message' => '无效的请求方式'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $prefix = isset($_POST['prefix']) ? trim($_POST['prefix']) : '';
    $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $quota_raw = isset($_POST['quota']) ? trim($_POST['quota']) : '5.0 GB';

    if (empty($prefix) || empty($domain) || empty($password)) {
        echo json_encode(['status' => false, 'message' => '请填写完整的邮箱前缀、域名和初始密码！'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $full_email = strtolower($prefix . '@' . $domain);

    $pdo = get_db_connection();
    if (!$pdo) {
        echo json_encode(['status' => false, 'message' => '连接底层 Mailcow 数据库失败！'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 检查域名是否存在
    $stmt = $pdo->prepare("SELECT domain FROM domain WHERE domain = ?");
    $stmt->execute([$domain]);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => false, 'message' => "域名 {$domain} 未在底层 Mailcow 激活！"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 检查账号是否已存在
    $stmt = $pdo->prepare("SELECT username FROM mailbox WHERE username = ?");
    $stmt->execute([$full_email]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => false, 'message' => "邮箱账号 {$full_email} 已在底层 Mailcow 中存在，无需重复创建！"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 计算密码 Hash (BLF-CRYPT)
    $hash = '{BLF-CRYPT}' . password_hash($password, PASSWORD_BCRYPT);

    // 解析容量配额 (字节 B)
    $quota_bytes = 5368709120; // 5GB default
    if (strpos($quota_raw, '10') !== false) {
        $quota_bytes = 10737418240;
    } elseif (strpos($quota_raw, '2') !== false) {
        $quota_bytes = 2147483648;
    }

    $attributes = json_encode([
        "force_pw_update" => "0",
        "force_tfa" => "0",
        "tls_enforce_in" => "0",
        "tls_enforce_out" => "0",
        "sogo_access" => "1",
        "imap_access" => "1",
        "pop3_access" => "1",
        "smtp_access" => "1",
        "sieve_access" => "1",
        "eas_access" => "1",
        "dav_access" => "1",
        "relayhost" => "0",
        "passwd_update" => date('Y-m-d H:i:s'),
        "mailbox_format" => "maildir:",
        "quarantine_notification" => "hourly",
        "quarantine_category" => "reject",
        "attribute_hash" => ""
    ], JSON_UNESCAPED_SLASHES);

    try {
        $sql = "INSERT INTO mailbox (username, password, name, description, mailbox_path_prefix, quota, local_part, domain, attributes, custom_attributes, kind, multiple_bookings, authsource, active, created) 
                VALUES (?, ?, ?, ?, '/var/vmail/', ?, ?, ?, ?, '{}', '', -1, 'mailcow', 1, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $full_email,
            $hash,
            $prefix,
            "中心服务邮局管理台创建",
            $quota_bytes,
            $prefix,
            $domain,
            $attributes
        ]);

        // 补全 alias 映射记录，确保 Postfix 发信鉴权 (Sender ACL) 正常通过
        $stmt_alias = $pdo->prepare("INSERT IGNORE INTO alias (address, goto, domain, created, active) VALUES (?, ?, ?, NOW(), 1)");
        $stmt_alias->execute([$full_email, $full_email, $domain]);

        echo json_encode([
            'status' => true,
            'message' => "账号 {$full_email} 已成功在底层 Mailcow 数据库中自动创建并开启 IMAP/SMTP 鉴权！",
            'item' => [
                'email' => $full_email,
                'domain' => $domain,
                'status' => 'normal',
                'quota' => $quota_raw
            ]
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['status' => false, 'message' => '写入 Mailcow 失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 3. 切换状态 (action=toggle)
if ($action === 'toggle') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (empty($email)) {
        echo json_encode(['status' => false, 'message' => '邮箱地址不能为空'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = get_db_connection();
    if (!$pdo) {
        echo json_encode(['status' => false, 'message' => '数据库连接失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT active FROM mailbox WHERE username = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $new_active = intval($row['active']) === 1 ? 0 : 1;
            $u_stmt = $pdo->prepare("UPDATE mailbox SET active = ? WHERE username = ?");
            $u_stmt->execute([$new_active, $email]);
            echo json_encode([
                'status' => true,
                'message' => '状态修改成功',
                'new_status' => $new_active === 1 ? 'normal' : 'readonly'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => false, 'message' => '账号不存在'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 4. 真实彻底删除邮箱账号 (action=delete)
if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => false, 'message' => '无效的请求方式'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $emails_raw = isset($_POST['emails']) ? trim($_POST['emails']) : '';
    if (empty($emails_raw)) {
        echo json_encode(['status' => false, 'message' => '请选择需要删除的邮箱账号！'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $emails = array_filter(array_map('trim', explode(',', $emails_raw)));
    if (empty($emails)) {
        echo json_encode(['status' => false, 'message' => '未传入有效的邮箱账号'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = get_db_connection();
    if (!$pdo) {
        echo json_encode(['status' => false, 'message' => '数据库连接失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $in_clause = implode(',', array_fill(0, count($emails), '?'));
        $stmt = $pdo->prepare("DELETE FROM mailbox WHERE username IN ({$in_clause})");
        $stmt->execute(array_values($emails));
        $deleted_count = $stmt->rowCount();

        // 同步清理 alias 表关联记录
        $stmt_alias = $pdo->prepare("DELETE FROM alias WHERE address IN ({$in_clause})");
        $stmt_alias->execute(array_values($emails));

        // 方案3：服务端主动销毁已被删除邮箱的 PHP Session 文件
        $session_path = session_save_path() ?: sys_get_temp_dir();
        if ($session_path && is_dir($session_path)) {
            $session_files = glob($session_path . '/sess_*');
            if ($session_files) {
                foreach ($session_files as $file) {
                    $content = @file_get_contents($file);
                    if ($content) {
                        foreach ($emails as $email) {
                            if (stripos($content, $email) !== false) {
                                @unlink($file);
                                break;
                            }
                        }
                    }
                }
            }
        }

        echo json_encode([
            'status' => true,
            'message' => "已成功从底层 Mailcow 数据库中真实物理删除 {$deleted_count} 个邮箱账号！",
            'deleted_count' => $deleted_count
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['status' => false, 'message' => '删除失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
