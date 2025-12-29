# 打赏网站项目

基于 Linux.do Credit 的简洁打赏网站。

## ✨ 功能特性

- 💰 自定义金额 + 预设金额快捷按钮
- 💬 支持打赏留言
- 🎨 Linux.do Credit 官方暗色主题
- 📱 完美支持移动端
- 🔒 安全的签名验证

---

## 🚀 选择部署方式

| 方式 | 难度 | 适用场景 | 需要服务器 |
|-----|------|---------|-----------|
| **[Vercel 一键部署](#-vercel-一键部署推荐)** | ⭐ 最简单 | 快速上线、无服务器 | ❌ 不需要 |
| **[Docker 部署](#-docker-部署)** | ⭐⭐ 简单 | 自托管、完整功能 | ✅ 需要 |
| **[PHP 部署](#-php-手动部署)** | ⭐⭐⭐ 中等 | 传统服务器 | ✅ 需要 |

---

## ☁️ Vercel 一键部署（推荐）

**无需服务器，3 分钟完成部署！**

### 步骤 1：获取 API 密钥

1. 访问 [credit.linux.do](https://credit.linux.do) → 登录
2. 进入 **控制台** → **集市中心** → **创建应用**
3. 记录 **Client ID** 和 **Client Secret**

### 步骤 2：一键部署

点击下方按钮，自动部署到 Vercel：

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https://github.com/Razewang/LINUX_EASY_CREDIT&env=EPAY_PID,EPAY_KEY&envDescription=Linux.do%20Credit%20API%20配置&envLink=https://credit.linux.do&project-name=reward-website&repository-name=reward-website)

部署时填写环境变量：

| 变量名 | 必填 | 说明 |
|--------|-----|------|
| `EPAY_PID` | ✅ | 你的 Client ID |
| `EPAY_KEY` | ✅ | 你的 Client Secret |
| `EPAY_GATEWAY` | ❌ | 支付网关（默认 `https://credit.linux.do/epay`） |
| `MIN_AMOUNT` | ❌ | 最小金额（默认 `0.01`） |
| `MAX_AMOUNT` | ❌ | 最大金额（默认 `9999.99`） |

### 步骤 3：配置回调地址

部署完成后，Vercel 会分配一个域名（如 `reward-website-xxx.vercel.app`）。

回到 [Linux.do Credit 控制台](https://credit.linux.do)，更新你的应用：

| 字段 | 填写内容 |
|------|---------|
| **应用主页** | `https://your-app.vercel.app` |
| **通知地址** | `https://your-app.vercel.app/api/notify.php` |
| **回调地址** | `https://your-app.vercel.app/success.html` |

**完成！** 访问 `https://your-app.vercel.app` 即可使用。

---

## 🐳 Docker 部署

适合有服务器的用户，支持完整功能（订单持久化存储）。

### 步骤 1：获取 API 密钥

同上，在 [credit.linux.do](https://credit.linux.do) 创建应用并记录密钥。

### 步骤 2：配置文件

```bash
# 克隆项目
git clone https://github.com/Razewang/LINUX_EASY_CREDIT.git
cd LINUX_EASY_CREDIT

# 创建配置文件
cp config/config.example.php config/config.php
nano config/config.php
```

填写配置：

```php
'epay' => [
    'pid' => '你的 Client ID',
    'key' => '你的 Client Secret',
    'notify_url' => 'https://你的域名/api/notify.php',
    'return_url' => 'https://你的域名/success.html',
],
```

### 步骤 3：启动容器

```bash
docker compose up -d
```

**详细文档**：[DOCKER.md](DOCKER.md)

---

## 🔧 PHP 手动部署

适合传统 PHP 环境（Apache/Nginx + PHP）。

### 快速启动（测试）

```bash
# 克隆并配置
git clone https://github.com/Razewang/LINUX_EASY_CREDIT.git
cd LINUX_EASY_CREDIT
cp config/config.example.php config/config.php
nano config/config.php  # 填写配置

# 启动服务器
php -S 0.0.0.0:8000
```

访问：`http://your-ip:8000`

**生产环境**：建议使用 Nginx + PHP-FPM，详见 [DEPLOYMENT.md](DEPLOYMENT.md)

---

## ✅ 测试支付流程

1. 访问你的网站
2. 选择或输入金额（建议先用 **0.01** 测试）
3. 填写留言（可选）
4. 点击"下一步"
5. 在 Linux.do Credit 完成支付
6. 自动返回查看结果

---

## 🌐 配置检查清单

部署前请确认：

- [ ] 已在 Linux.do Credit **创建应用**
- [ ] 已正确填写 Client ID 和 Client Secret
- [ ] 通知地址格式：`https://你的域名/api/notify.php`
- [ ] 回调地址格式：`https://你的域名/success.html`
- [ ] 地址必须是外网可访问的（不能用 localhost）

---

## ⚙️ 自定义配置

### Docker/PHP 部署

编辑 `config/config.php`：

```php
'preset_amounts' => [2, 6, 18, 66, 188],  // 预设金额
'min_amount' => 1,      // 最小金额
'max_amount' => 500,    // 最大金额
'title' => '请我喝咖啡',
'description' => '您的支持是创作的动力',
```

### Vercel 部署

在 Vercel 控制台 → Settings → Environment Variables 中修改。

---

## 📝 自定义页面文案（WebUI）

直接改静态页面/前端脚本即可：

- 首页文案：`reward-website/index.html`（标题、副标题、区块标题、按钮文字、placeholder 等）
- 成功/等待页文案：`reward-website/success.html`
- 前端校验/错误提示：`reward-website/assets/js/main.js`
- 主题切换提示文案：`reward-website/assets/js/theme.js`

修改后生效方式：

- **Docker 部署**：`git pull` 后执行 `docker compose up -d --build`
- **PHP 直跑**：刷新页面即可（必要时清缓存）
- **Vercel**：推送到仓库后触发重新部署（或手动 Redeploy）

---

## 📁 项目结构

```
reward-website/
├── index.html              # 打赏页面
├── success.html            # 支付成功页面
├── vercel.json             # Vercel 配置
├── api/
│   ├── create_order.php    # PHP 版 API
│   ├── create-order.js     # Vercel 版 API
│   └── ...
├── config/
│   └── config.example.php  # 配置模板
└── assets/                 # CSS/JS 资源
```

---

## ❓ 常见问题

### Q: Vercel 部署后订单数据会丢失吗？
Vercel 是无状态的，订单数据不会持久化保存。如需保存，可配置外部数据库或使用 Docker 部署。

### Q: 如何获取 Client ID 和 Secret？
访问 https://credit.linux.do → 控制台 → 集市中心 → 创建应用

### Q: 签名验证失败怎么办？
检查 Client ID 和 Secret 是否正确，确保没有多余空格。

### Q: 如何查看日志？
- **Vercel**: 控制台 → Functions → Logs
- **Docker**: `docker compose logs -f`
- **PHP**: `tail -f logs/*.log`

---

## 📚 更多文档

- [DOCKER.md](DOCKER.md) - Docker 部署指南
- [DEPLOYMENT.md](DEPLOYMENT.md) - 完整部署文档
- [THEME.md](THEME.md) - UI 主题自定义
- [API.md](API.md) - 接口文档

---

## 📧 支持

- **Linux.do Credit 文档**: https://credit.linux.do/docs
- **GitHub Issues**: https://github.com/Razewang/LINUX_EASY_CREDIT/issues

---

**祝您使用愉快！** 🎉
