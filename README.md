# Email Service Portal (Mailcow 前端控制台与 Webmail 门户)

轻量级、响应式、原生的 Mailcow 邮件系统定制前端门户与 UI 管理控制台。原生集成 Mailcow 引擎底层 API，支持 Webmail 邮箱收发、自定义 HTML 内容发信、管理员四级权限中枢以及全站动态 UI 品牌自定。

---

## 🌟 核心特性

- 📬 **原生 Webmail 客户端**：内置极简流畅的 Webmail 界面，支持邮件列表查看、实时接收与正文渲染。
- ✉️ **支持 HTML 自定义发信**：富文本 / 自定义 HTML 内容发信，内置自动模板与邮件源码预览能力。
- 🛡️ **四级权限管理中枢 (dmin.html)**：
  - 👑 **超级管理员** (dmin@example.com)：运维管理与 Mailcow 底层联动。
  - 🛡️ **管理员** (support@example.com)：配额与商务账号控制。
  - 🔑 **域名管理员** (security@example.com)：安全审计与域名 DNS 检查。
  - 👤 **下属管理员** (
o-reply@example.com)：自动化发信与留痕。
- 🎨 **全站 UI 在线品牌定制**：后台在线修改站点名称、Hero 标语、主题颜色与导航菜单，实时生效。
- 🐳 **Docker 极速容器化部署**：基于 Alpine PHP-FPM + Nginx，资源占用极低（< 30MB 内存）。

---

## 🏗️ 架构与数据流设计

`	ext
[ 浏览器 (Web Interface) ]
         │
         ├──> [ Nginx + PHP-FPM (email-portal 容器) ] ── (Port 8088)
                     │
                     ├───> MySQL (mysql-mailcow) ──── 账号鉴权 & 站点配置
                     ├───> Dovecot (dovecot-mailcow) ── IMAP 邮件读取
                     └───> Postfix (postfix-mailcow) ── SMTP 邮件发送
`

---

## 🛠️ 部署指南 (基于 Mailcow Docker)

### 1. 克隆本仓库到 Mailcow 目录

将代码放至 Mailcow 主目录下的 portal-web 文件夹中：

`ash
cd /opt/mailcow-dockerized
git clone https://github.com/YOUR_USERNAME/email-portal.git portal-web
`

### 2. 配置 docker-compose.override.yml

在 /opt/mailcow-dockerized 目录下创建 docker-compose.override.yml：

`yaml
version: '3'
services:
  email-portal:
    build: ./portal-web
    container_name: email-service-portal
    restart: always
    environment:
      - DB_HOST=mysql-mailcow
      - DB_PORT=3306
      - DB_NAME=mailcow
      - DB_USER=mailcow
      - DB_PASS=YOUR_MAILCOW_DB_PASSWORD
      - IMAP_HOST=dovecot-mailcow
      - IMAP_PORT=143
      - SMTP_HOST=postfix-mailcow
      - SMTP_PORT=587
    ports:
      - "8088:80"
    networks:
      - mailcow-network

networks:
  mailcow-network:
    external: true
    name: mailcowdockerized_mailcow-network
`

### 3. 启动容器

`ash
docker compose up -d email-portal
`

访问 http://<YOUR-SERVER-IP>:8088 即可使用系统。

---

## 🔐 预设初始账号与测试

- **控制台地址**：http://<YOUR-SERVER-IP>:8088/admin.html
- **默认超级管理员**：dmin@example.com
- **默认初始密码**：dmin

---

## 🎨 前端 UI 更改与定制方式

在容器运行后，更改前端界面有以下 **3 种方式**：

1. **后台在线动态配置（推荐，无需重启）**：
   登录 dmin.html 进入【全站 UI 与品牌配置】，即可在线编辑站点名称、标语与菜单，保存即生效。
2. **宿主机目录挂载（二次开发推荐）**：
   在 docker-compose.override.yml 中配置 olumes: - ./portal-web:/www/wwwroot/email_service_portal，编辑 HTML/CSS 文件保存后刷新浏览器即可实时预览。
3. **重新构建镜像（固化版本）**：
   docker compose build email-portal && docker compose up -d email-portal

---

## 📄 开源许可证

MIT License
