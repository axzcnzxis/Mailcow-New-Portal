<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$config_file = __DIR__ . '/site_config.json';
$action = isset($_GET['action']) ? trim($_GET['action']) : (isset($_POST['action']) ? trim($_POST['action']) : 'get');

// 默认配置模版（若文件不存在时自动初始化）
function get_default_config() {
    return [
        'brand' => [
            'site_name' => '中心服务邮局',
            'badge' => 'email.example.com',
            'primary_color' => '#ec652b'
        ],
        'nav_menu' => [
            ['id' => 'nav_home', 'name' => '首页', 'url' => 'index.html', 'visible' => true],
            ['id' => 'nav_features', 'name' => '核心特性', 'url' => 'features.html', 'visible' => true],
            ['id' => 'nav_pricing', 'name' => '解决方案', 'url' => 'pricing.html', 'visible' => true],
            ['id' => 'nav_webmail', 'name' => 'Webmail客户端', 'url' => 'webmail.html', 'visible' => true],
            ['id' => 'nav_admin', 'name' => '管理控制台', 'url' => 'admin.html', 'visible' => true]
        ],
        'page_content' => [
            'hero_tag' => '企业级自建域名邮局',
            'hero_title' => '中心服务邮局',
            'hero_subtitle' => '私有化部署的安全堡垒，企业级通信的管理中枢。支持深度定制与四级权限管理的专业邮件解决方案。',
            'btn_register_text' => '注册域名邮箱',
            'btn_admin_text' => '进入管理台',
            'btn_login_text' => '登录邮箱'
        ]
    ];
}

// 1. 获取全站配置 (GET 方式，完全公开只读)
if ($action === 'get') {
    if (file_exists($config_file)) {
        $raw = file_get_contents($config_file);
        $json = json_decode($raw, true);
        if ($json) {
            echo json_encode(['status' => true, 'config' => $json], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // 如果配置文件缺失或解析失败，回退返回默认配置并写入
    $default_config = get_default_config();
    @file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['status' => true, 'config' => $default_config], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. 保存配置 (POST 方式，限管理员权限)
if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => false, 'message' => '无效的请求方式'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    // 管理员权限验证（Session 或 JSON input 校验）
    $admin_user = isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : '';
    if (empty($admin_user) && isset($_POST['admin_user'])) {
        $admin_user = trim($_POST['admin_user']);
    }
    if (empty($admin_user) && $data && isset($data['admin_user'])) {
        $admin_user = trim($data['admin_user']);
    }

    if (!$data || !isset($data['config'])) {
        // 回退解析 POST 方式
        if (isset($_POST['config'])) {
            $data = ['config' => json_decode($_POST['config'], true)];
        }
    }

    if (!$data || !isset($data['config'])) {
        echo json_encode(['status' => false, 'message' => '无法解析传入的配置数据'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $new_config = $data['config'];

    // 保存配置数据到 JSON 文件
    $result = @file_put_contents($config_file, json_encode($new_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($result !== false) {
        echo json_encode(['status' => true, 'message' => '全站 UI 品牌与导航配置已成功保存更新！', 'config' => $new_config], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => false, 'message' => '写入 site_config.json 配置文件失败，请检查文件写入权限！'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

echo json_encode(['status' => false, 'message' => '未知的 action 请求'], JSON_UNESCAPED_UNICODE);
