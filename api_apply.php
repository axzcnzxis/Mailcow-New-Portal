<?php require_once __DIR__ . "/config.php"; ?>
<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$data_file = __DIR__ . '/.aiproject/apply_queue.json';

// 初始化存储目录与默认申请数据
function load_applications($data_file) {
    if (!file_exists(dirname($data_file))) {
        @mkdir(dirname($data_file), 0755, true);
    }
    if (!file_exists($data_file)) {
        $default_data = [
            [
                "id" => "app_" . time() . "_1",
                "name" => "张工程师",
                "company" => "极客云科技有限公司",
                "email" => "zhang@qq.com",
                "domain" => "learnflow.edu.kg",
                "prefix" => "tech_lead",
                "full_email" => "tech_lead@learnflow.edu.kg",
                "remarks" => "需要 5GB 空间，用于团队项目收发信件",
                "status" => "pending", // pending / approved / rejected
                "created_at" => date('Y-m-d H:i:s', time() - 3600)
            ],
            [
                "id" => "app_" . time() . "_2",
                "name" => "李经理",
                "company" => "前沿网络安全",
                "email" => "li@163.com",
                "domain" => "example.com",
                "prefix" => "security_op",
                "full_email" => "security_op@example.com",
                "remarks" => "申请部署安全通信账号",
                "status" => "pending",
                "created_at" => date('Y-m-d H:i:s', time() - 1800)
            ]
        ];
        file_put_contents($data_file, json_encode($default_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $default_data;
    }

    $content = file_get_contents($data_file);
    return json_decode($content, true) ?: [];
}

function save_applications($data_file, $data) {
    file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$action = isset($_GET['action']) ? trim($_GET['action']) : (isset($_POST['action']) ? trim($_POST['action']) : 'list');

// 1. 提交新申请 (POST)
if ($action === 'submit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => false, 'message' => 'Invalid Method']);
        exit;
    }

    $name = isset($_POST['name']) ? trim($_POST['name']) : '申请人';
    $company = isset($_POST['company']) ? trim($_POST['company']) : '个人用户';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $domain = isset($_POST['domain']) ? trim($_POST['domain']) : 'example.com';
    $prefix = isset($_POST['prefix']) ? trim($_POST['prefix']) : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    if (empty($email) || empty($prefix)) {
        echo json_encode(['status' => false, 'message' => '请填写完整的验证邮箱与期望的前缀账号！'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $full_email = strtolower($prefix . '@' . $domain);
    $apps = load_applications($data_file);

    // 检查重复全称
    foreach ($apps as $item) {
        if ($item['full_email'] === $full_email && $item['status'] === 'pending') {
            echo json_encode(['status' => false, 'message' => "账号 {$full_email} 已在审核队列中，请等待管理员批复！"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $new_item = [
        "id" => "app_" . uniqid(),
        "name" => $name,
        "company" => $company,
        "email" => $email,
        "domain" => $domain,
        "prefix" => $prefix,
        "full_email" => $full_email,
        "remarks" => $remarks,
        "status" => "pending",
        "created_at" => date('Y-m-d H:i:s')
    ];

    array_unshift($apps, $new_item);
    save_applications($data_file, $apps);

    echo json_encode([
        'status' => true,
        'message' => "您的企邮入驻申请 ({$full_email}) 已成功提交至超级管理员审核队列！",
        'item' => $new_item
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. 获取申请队列列表 (action=list)
if ($action === 'list') {
    $apps = load_applications($data_file);
    echo json_encode(['status' => true, 'applications' => $apps], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. 审核申请 (action=approve / action=reject)
if ($action === 'approve' || $action === 'reject') {
    $id = isset($_POST['id']) ? trim($_POST['id']) : '';
    if (empty($id)) {
        echo json_encode(['status' => false, 'message' => '缺失申请单 ID'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $apps = load_applications($data_file);
    $found = false;
    $target_item = null;

    foreach ($apps as &$item) {
        if ($item['id'] === $id) {
            $found = true;
            $item['status'] = ($action === 'approve') ? 'approved' : 'rejected';
            $item['processed_at'] = date('Y-m-d H:i:s');
            $target_item = $item;
            break;
        }
    }

    if (!$found) {
        echo json_encode(['status' => false, 'message' => '未找到对应的申请记录'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 如果审核通过 (approve)，自动联动 Mailcow 在 DB 中建号！
    if ($action === 'approve') {
        $db_host = DB_HOST;
        $db_port = DB_PORT;
        $db_name = DB_NAME;
        $db_user = DB_NAME;
        $db_pass = 'GkvoHmEZ1692OFhcdtrcBZNuInMu';

        try {
            $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $full_email = $target_item['full_email'];
            $prefix = $target_item['prefix'];
            $domain = $target_item['domain'];
            $default_pass = 'admin'; // 默认统一开通初始密码

            // 检查 mailbox 表是否已存在
            $stmt = $pdo->prepare("SELECT username FROM mailbox WHERE username = ?");
            $stmt->execute([$full_email]);
            if (!$stmt->fetch()) {
                $hash = '{BLF-CRYPT}' . password_hash($default_pass, PASSWORD_BCRYPT);
                $attributes = json_encode([
                    "force_pw_update" => "0", "force_tfa" => "0", "tls_enforce_in" => "0", "tls_enforce_out" => "0",
                    "sogo_access" => "1", "imap_access" => "1", "pop3_access" => "1", "smtp_access" => "1",
                    "sieve_access" => "1", "eas_access" => "1", "dav_access" => "1", "relayhost" => "0",
                    "passwd_update" => date('Y-m-d H:i:s'), "mailbox_format" => "maildir:", "quarantine_notification" => "hourly",
                    "quarantine_category" => "reject", "attribute_hash" => ""
                ], JSON_UNESCAPED_SLASHES);

                $sql = "INSERT INTO mailbox (username, password, name, description, mailbox_path_prefix, quota, local_part, domain, attributes, custom_attributes, kind, multiple_bookings, authsource, active, created) 
                        VALUES (?, ?, ?, ?, '/var/vmail/', 5368709120, ?, ?, ?, '{}', '', -1, 'mailcow', 1, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_email, $hash, $prefix, "中心服务邮局申请队列通过自动创建", $prefix, $domain, $attributes]);

                // 补全 alias 映射记录，确保 Postfix 发信鉴权 (Sender ACL) 正常通过
                $stmt_alias = $pdo->prepare("INSERT IGNORE INTO alias (address, goto, domain, created, active) VALUES (?, ?, ?, NOW(), 1)");
                $stmt_alias->execute([$full_email, $full_email, $domain]);
            }

            save_applications($data_file, $apps);
            echo json_encode([
                'status' => true,
                'message' => "已批准申请！底层 Mailcow 已成功自动创建账号 {$full_email} (初始密码: {$default_pass}) 并联动生效 IMAP/SMTP 鉴权！",
                'full_email' => $full_email
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Exception $e) {
            echo json_encode(['status' => false, 'message' => '联动 Mailcow 创建账号失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        save_applications($data_file, $apps);
        echo json_encode(['status' => true, 'message' => '已拒绝该入驻申请'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 4. 批量删除 / 清空申请记录 (action=delete)
if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => false, 'message' => 'Invalid Method']);
        exit;
    }

    $ids_raw = isset($_POST['ids']) ? trim($_POST['ids']) : '';
    $clear_all = isset($_POST['clear_all']) ? intval($_POST['clear_all']) : 0;

    if ($clear_all === 1) {
        save_applications($data_file, []);
        echo json_encode(['status' => true, 'message' => '申请队列已成功清空！'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($ids_raw)) {
        echo json_encode(['status' => false, 'message' => '未选择要删除的申请记录'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $target_ids = array_filter(array_map('trim', explode(',', $ids_raw)));
    $apps = load_applications($data_file);

    $remaining = array_values(array_filter($apps, function($item) use ($target_ids) {
        return !in_array($item['id'], $target_ids);
    }));

    save_applications($data_file, $remaining);
    $count = count($apps) - count($remaining);

    echo json_encode([
        'status' => true,
        'message' => "已成功删除 {$count} 条申请记录！",
        'deleted_count' => $count
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
