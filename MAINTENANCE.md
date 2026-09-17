# 🛠️ Mailcow-New-Portal 项目维护与二次开发指南

本指南旨在帮助开发者、运维人员及社区贡献者理解项目的代码结构、微服务集成机制以及常见故障的排查与维护方法。

---

## 📌 1. 免责声明与维护说明

- **AI 辅助开发声明**：本项目由个人借助 AI 编码助手设计与开发。
- **长期维护说明**：原作者**不承诺提供长期的版本迭代、功能维护或一对一的技术支持**。
- **社区与自主维护**：欢迎使用者根据实际需求自由 Fork 本仓库自行维护、修复 Bug 或进行二次开发。

---

## 🏗️ 2. 项目目录结构与模块说明

`	ext
email-portal-repo/
├── Dockerfile                  # Alpine PHP 8.2 + Nginx 镜像构建文件
├── nginx.conf                  # 容器内 Nginx 虚拟主机路由配置
├── config.php                  # 核心配置文件 (读取 Docker 环境变量)
├── site_config.json            # 全站 UI 与品牌配置数据 (JSON 格式)
│
├── index.html                  # 网站 Portal 首页 (产品展示与入口)
├── admin.html                  # 四级权限管理控制台界面
├── webmail.html                # Webmail 极简邮件客户端界面
├── features.html / pricing.html# 特性与服务展示页面
├── style.css / site_config.js  # 全局 CSS 样式与 UI 动态加载 JS
│
├── auth_login.php              # 管理员鉴权与 Session 处理接口
├── api_mailbox.php             # 邮箱账号列表拉取与创建接口
├── api_dns_check.php           # 域名 DNS (MX/SPF/DKIM/DMARC) 检测接口
├── api_site_config.php         # 动态 UI 品牌配置读取与保存接口
├── fetch_mails.php             # IMAP 协议拉取收件箱邮件接口
├── send_code.php               # SMTP 协议自定义 HTML 邮件发送接口
└── delete_mail.php             # IMAP 协议删除邮件接口
`

---

## ⚙️ 3. 核心集成机制与数据流

本Portal作为 Mailcow 的扩展子容器，直接与 Mailcow 底层组件交互：

1. **数据库鉴权与存储**：
   - 依赖 config.php 中的 PDO 连接 mysql-mailcow:3306。
   - 读取与操作 mailcow 数据库下的 domain、mailbox、lias 等核心表。

2. **IMAP 邮件收取 (etch_mails.php / delete_mail.php)**：
   - 使用 PHP 原生 imap_open() 或 socket / fsockopen 协议连接 dovecot-mailcow:143。
   - 验证用户账号密码后拉取 INBOX 邮件结构并解析 HTML/Text 正文。

3. **SMTP 邮件发送 (send_code.php)**：
   - 通过 Socket / fsockopen 直连 postfix-mailcow:587 或 25 端口。
   - 构造标准的 MIME 多部分 HTML 邮件报文进行发送。

---

## 🛠️ 4. 常见运维与排查步骤

### A. 检查 Portal 容器运行状态与日志
`ash
# 查看容器日志
docker logs -f email-service-portal

# 进入容器内部排查
docker exec -it email-service-portal sh
`

### B. 数据库连接排查
若页面提示数据库错误，可在容器内通过 CLI 测试 PDO 连接：
`ash
docker exec -it email-service-portal php -r "require '/www/wwwroot/email_service_portal/config.php'; \ = new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME, DB_USER, DB_PASS); echo 'OK\n';"
`

### C. 恢复默认全站配置
全站 UI 品牌配置保存在 site_config.json 中。若由于配置错误导致前端渲染异常，可恢复初始 JSON：
`json
{
  "site_title": "中心服务邮局",
  "hero_title": "中心服务邮局",
  "hero_desc": "私有化部署的安全堡垒与高品质原生 Webmail 控制台体系",
  "theme_color": "#e05621",
  "badge_text": "email.example.com",
  "nav_items": [
    {"label": "首页", "link": "index.html"},
    {"label": "Webmail客户端", "link": "webmail.html"},
    {"label": "管理控制台", "link": "admin.html"}
  ]
}
`

---

## 🤝 5. 如何参与贡献与提交代码

1. Fork 本仓库。
2. 在本地完成修改并测试无误。
3. 提交 Pull Request (PR)，并附带具体的修改说明与测试截图。
