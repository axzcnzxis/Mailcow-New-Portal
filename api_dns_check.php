<?php
header('Content-Type: application/json; charset=utf-8');

$domain = isset($_GET['domain']) ? trim($_GET['domain']) : '';
if (empty($domain)) {
    echo json_encode(['status' => false, 'message' => '域名不能为空']);
    exit;
}

// 获取服务器 IP
$server_ip = trim(@file_get_contents('https://api.ipify.org')) ?: gethostbyname(gethostname());

// 1. 检测 MX 记录
$mx_records = [];
$mx_ok = false;
if (checkdnsrr($domain, "MX")) {
    getmxrr($domain, $mx_hosts, $mx_weights);
    if (!empty($mx_hosts)) {
        $mx_ok = true;
        foreach ($mx_hosts as $idx => $host) {
            $mx_records[] = [
                'host' => $host,
                'priority' => $mx_weights[$idx] ?? 10
            ];
        }
    }
}

// 2. 检测 TXT / SPF 记录
$spf_ok = false;
$spf_record = '';
$txt_records = @dns_get_record($domain, DNS_TXT);
if ($txt_records) {
    foreach ($txt_records as $rec) {
        $txt = $rec['txt'] ?? '';
        if (strpos($txt, 'v=spf1') !== false) {
            $spf_ok = true;
            $spf_record = $txt;
            break;
        }
    }
}

// 3. 检测 DKIM 记录 (dkim._domainkey.domain 或 dkim._domainkey)
$dkim_ok = false;
$dkim_record = '';
$dkim_domain = "dkim._domainkey." . $domain;
$dkim_txts = @dns_get_record($dkim_domain, DNS_TXT);
if ($dkim_txts) {
    foreach ($dkim_txts as $rec) {
        $txt = $rec['txt'] ?? '';
        if (strpos($txt, 'v=DKIM1') !== false || strpos($txt, 'k=rsa') !== false) {
            $dkim_ok = true;
            $dkim_record = substr($txt, 0, 80) . '...';
            break;
        }
    }
}

// 4. 检测 DMARC 记录 (_dmarc.domain)
$dmarc_ok = false;
$dmarc_record = '';
$dmarc_domain = "_dmarc." . $domain;
$dmarc_txts = @dns_get_record($dmarc_domain, DNS_TXT);
if ($dmarc_txts) {
    foreach ($dmarc_txts as $rec) {
        $txt = $rec['txt'] ?? '';
        if (strpos($txt, 'v=DMARC1') !== false) {
            $dmarc_ok = true;
            $dmarc_record = $txt;
            break;
        }
    }
}

// 建议配置规范
$specs = [
    'MX' => [
        'type' => 'MX',
        'host' => '@',
        'value' => 'mail.learnflow.edu.kg',
        'priority' => 10,
        'status' => $mx_ok,
        'current' => $mx_ok ? implode(', ', array_map(function($m){ return $m['host']; }, $mx_records)) : '未检测到有效 MX'
    ],
    'SPF' => [
        'type' => 'TXT',
        'host' => '@',
        'value' => 'v=spf1 mx a ip4:' . $server_ip . ' ~all',
        'status' => $spf_ok,
        'current' => $spf_ok ? $spf_record : '未检测到 SPF'
    ],
    'DKIM' => [
        'type' => 'TXT',
        'host' => 'dkim._domainkey',
        'value' => 'v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC...',
        'status' => $dkim_ok,
        'current' => $dkim_ok ? $dkim_record : '未配置 DKIM 签名'
    ],
    'DMARC' => [
        'type' => 'TXT',
        'host' => '_dmarc',
        'value' => 'v=DMARC1; p=none; pct=100; rua=mailto:postmaster@' . $domain,
        'status' => $dmarc_ok,
        'current' => $dmarc_ok ? $dmarc_record : '未设置 DMARC 保护'
    ]
];

echo json_encode([
    'status' => true,
    'domain' => $domain,
    'server_ip' => $server_ip,
    'specs' => $specs
], JSON_UNESCAPED_UNICODE);
