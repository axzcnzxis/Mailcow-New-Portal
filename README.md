# 🚀 Mailcow-New-Portal (Mailcow 邮局全新的界面与 UI 管理控制台)

[![License: Non-Commercial](https://img.shields.io/badge/License-Non--Commercial-red.svg)](LICENSE)
[![Maintenance Guide](https://img.shields.io/badge/Maintenance-Guide-purple.svg)](MAINTENANCE.md)
[![Commercial License Contact](https://img.shields.io/badge/Commercial%20License-Apply%20Now-green.svg)](mailto:admin@myhomeyin.help?subject=Mailcow-New-Portal%20商业授权申请)
[![Docker](https://img.shields.io/badge/Docker-Supported-blue.svg)](https://www.docker.com/)
[![Mailcow](https://img.shields.io/badge/Mailcow-Compatible-orange.svg)](https://mailcow.email/)

Mailcow-New-Portal 是一款专为 **Mailcow: dockerized** 邮件系统打造的**现代化、高颜值、轻量化前端门户与 UI 管理控制台**。它集成了原生 Webmail 邮件客户端、自定义 HTML/富文本发信引擎、四级管理员权限控制中枢以及全站 UI 动态品牌定制功能，能够帮助运维人员与企业无缝升级 Mailcow 的用户交互体验。

---

## ⛔ 开源许可、维护说明与商业授权

> **⚠️ 使用协议与版权声明：**  
> 本项目源码遵循 **[自定义非商业许可证 (Custom Non-Commercial License)](LICENSE)** 开源：
> - **个人 / 非盈利使用**：允许个人学习、研究及非商业性免费部署使用。
> - **商业使用限制**：未经原作者本人明确书面许可，**严禁**将本项目源码、打包镜像或衍生作品用于任何商业售卖、付费交付、二次打包转售或作为商业付费产品/服务的一部分进行盈利。

### 🤖 AI 开发与维护免责声明
- **本系统由个人借助 AI 编码助手开发完成**。
- **原作者不承诺提供长期持续的版本迭代、功能维护或一对一技术支持**。
- 如需深入了解系统架构、组件对接逻辑或自行修复 Bug / 二次开发，请参阅独立的 **[🛠️ 项目维护与二次开发指南 (MAINTENANCE.md)](MAINTENANCE.md)**。

### 💼 商业授权申请通道 (Commercial Authorization Application)

如果您或您的企业希望将 Mailcow-New-Portal 整合进商业产品、提供商业安装交付服务、二次开发或作为付费系统销售，请通过官方通道向原作者提交**商业授权申请**：

- 📧 **官方授权邮箱**：[dmin@myhomeyin.help](mailto:admin@myhomeyin.help)
- 📝 **邮件申请格式建议**：
  - **邮件主题**：[商业授权申请] Mailcow-New-Portal - 公司/个人名称
  - **申请主体信息**：公司全称 / 团队或个人名称 / 联系电话
  - **使用场景说明**：拟使用的商业场景（如客户系统定制、私有化部署交付、商业集成等）
  - **预计部署规模**：预计部署的服务器数量或企业用户规模

> **📫 邮件回复与垃圾箱提醒（重要提示）：**  
> 官方授权邮箱 dmin@myhomeyin.help 为**全新创建的域名邮箱**。在您提交授权申请后，原作者通常会在 **7 天内** 进行审核与回复。  
> 鉴于新域名邮箱在部分第三方邮件服务商（如 QQ 邮箱、163 邮箱、Gmail、Outlook 等）中可能会被误判，**如果您在发送申请后超过数日未在常规“收件箱”收到回信，请务必前往您发信邮箱的【垃圾箱 / 订阅邮件 / 垃圾邮件】中查找回复通知**。

---

## 📸 界面预览与系统定位

本系统以子容器的形式原生挂载到 Mailcow 的 Docker 容器网络中，与 Mailcow 共享底层的 **MySQL 数据库**、**Dovecot (IMAP)** 以及 **Postfix (SMTP)** 服务。

`	ext
                                [ 用户/管理员 浏览器 ]
                                           │
                                           ▼ (端口 8088 / 自定义)
                       ┌───────────────────────────────────────┐
                       │  email-service-portal (PHP 8.2+Nginx) │
                       └───────────────────┬───────────────────┘
                                           │ (Docker 内部网络)
           ┌───────────────────────────────┼───────────────────────────────┐
           ▼                               ▼                               ▼
 ┌───────────────────┐           ┌───────────────────┐           ┌───────────────────┐
 │   mysql-mailcow   │           │  dovecot-mailcow  │           │  postfix-mailcow  │
 │ (鉴权/配额/站点配置)│           │   (IMAP 接收邮件) │           │   (SMTP 发送邮件) │
 └───────────────────┘           └───────────────────┘           └───────────────────┘
`

---

## ✨ 完整功能特性列表

### 1. 📬 极简 Webmail 客户端 (webmail.html)
- **邮件收件箱查看**：通过底层 IMAP 协议无缝连接 Mailcow Dovecot，实现邮件列表实时拉取与阅读。
- **邮件详情与源码解析**：支持 Plain Text 与 HTML 富文本邮件渲染，智能解析收件人、发件人及时间戳。
- **邮件删除与管理**：提供单封/批量邮件删除接口，同步更新服务器端 mailbox 状态。

### 2. ✉️ 支持 HTML 自定义发信与富文本编辑器
- **自定义 HTML 内容发信**：突破传统纯文本限制，支持直接粘贴或编写 HTML 源码进行专业邮件排版。
- **实时效果预览**：内置发信预览窗口，可即时预览 HTML 标签在邮件客户端中的实际呈现样式。
- **动态变量插入**：支持自动识别模板变量，适合系统自动化通知、营销邮件及验证码发送。

### 3. 🛡️ 四级权限鉴权管理中枢 (dmin.html)
基于原生角色与权限设计的管理控制台，支持针对不同管理层级进行精准赋权：

| 角色级别 | 代表账号 (示例) | 权限说明 |
| :--- | :--- | :--- |
| 👑 **超级管理员** | dmin@example.com | IT 运维与 Mailcow 底层盘联动，拥有系统所有配置最高控制权 |
| 🛡️ **管理员** | support@example.com | 商务与多域配额管制、域名配置及用户账号配额调整 |
| 🔑 **域名管理员** | security@example.com | 安全与合规审计、域名 DNS 解析一键检测与记录审查 |
| 👤 **下属管理员** | 
o-reply@example.com | 系统自动化发信、操作日志查询与留痕审计 |

- **域名 DNS 一键检测**：自动抓取并校验 MX、SPF、DKIM 及 DMARC 记录，诊断域名解析健康度。
- **邮箱账号动态管理**：支持在后台面板快速创建新邮箱账号、修改密码、分配存储配额等。

### 4. 🎨 全站 UI 动态品牌定制系统
- **无需改动代码**：后台提供【全站 UI 与品牌配置】可视化面板，修改后**即刻生效，无需重启容器**。
- **定制范围**：
  - 全站名称（如“XX集团专属邮局”）
  - Hero 标语与描述文案
  - 顶栏与底栏导航菜单项
  - 系统主题颜色与亮暗高亮样式

---

## 🛠️ 详细安装与部署教程 (基于 Mailcow Docker)

### 前置要求
1. 服务器已成功安装并运行 **Mailcow: dockerized** 系统（安装路径默认为 /opt/mailcow-dockerized）。
2. 服务器支持 Docker 与 Docker Compose 命令。

---

### 第一步：克隆本仓库到 Mailcow 目录

登录云服务器终端，进入 Mailcow 目录，将本仓库克隆为 portal-web 文件夹：

`ash
cd /opt/mailcow-dockerized
git clone https://github.com/axzcnzxis/Mailcow-New-Portal.git portal-web
`

---

### 第二步：配置 docker-compose.override.yml

在 /opt/mailcow-dockerized 根目录下新建或修改 docker-compose.override.yml 文件：

`ash
nano /opt/mailcow-dockerized/docker-compose.override.yml
`

填入以下内容（**注意修改 DB_PASS 为您真实的 Mailcow 数据库密码**）：

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
      - DB_PASS=YOUR_REAL_MAILCOW_DB_PASSWORD # 👈 替换为您 mailcow.conf 中的 DBPASS 值
      - IMAP_HOST=dovecot-mailcow
      - IMAP_PORT=143
      - SMTP_HOST=postfix-mailcow
      - SMTP_PORT=587
    ports:
      - "8088:80" # 👈 左侧 8088 为对外访问端口，可按需更改
    networks:
      - mailcow-network

networks:
  mailcow-network:
    external: true
    name: mailcowdockerized_mailcow-network
`

> **💡 如何获取您的 Mailcow 数据库密码？**  
> 运行命令 grep DBPASS /opt/mailcow-dockerized/mailcow.conf 即可查看。

---

### 第三步：构建并启动子容器

在 /opt/mailcow-dockerized 目录下执行构建与启动命令：

`ash
docker compose build email-portal
docker compose up -d email-portal
`

启动完成后，检查容器运行状态：
`ash
docker ps | grep email-service-portal
`

---

### 第四步：访问与初始登录

在浏览器中打开：
- **Webmail / 首页地址**：http://<您的服务器IP>:8088/
- **管理控制台地址**：http://<您的服务器IP>:8088/admin.html

**预设初始登录凭据：**
- **管理员账号**：dmin@example.com （或您的 Mailcow 管理员邮箱）
- **初始默认密码**：dmin

---

## 🖌️ 如何修改和二次开发前端界面？

打包成 Docker 之后，修改网页前端共有 **3 种灵活方便的方式**：

### 方式一：管理员后台在线修改（最简单，无需修改代码/不重启）
1. 登录 dmin.html 控制台。
2. 进入【全站 UI 与品牌配置】选项卡。
3. 修改品牌名称、标语、主题色及导航菜单，点击保存**即刻生效**。

### 方式二：宿主机目录挂载（推荐！修改 HTML/CSS 实时刷新）
在 docker-compose.override.yml 中添加挂载卷配置：
`yaml
    volumes:
      - ./portal-web:/www/wwwroot/email_service_portal
`
直接编辑宿主机 ./portal-web 目录下的 index.html 或 style.css，**保存后直接刷新浏览器即可看到变化**。

### 方式三：重新构建容器镜像（适用于固化发布版本）
修改完本地源码文件后，运行以下指令：
`ash
docker compose build email-portal && docker compose up -d email-portal
`
重构过程只需 **2~3 秒**，完全不影响底层 Mailcow 邮箱服务。

---

## 📄 开源与版权许可证

本项目采用 [Custom Non-Commercial License (自定义非商业开源许可证)](LICENSE)。  
**非商业用途免费授权；商用请联系 [dmin@myhomeyin.help](mailto:admin@myhomeyin.help) 获取商业授权许可。**
