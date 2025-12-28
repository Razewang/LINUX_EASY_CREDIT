# 打赏网站项目

基于 Linux.do Credit 的简洁打赏网站，3 步即可启动。

## ✨ 功能特性

- 💰 自定义金额 + 预设金额快捷按钮
- 💬 支持打赏留言
- 🎨 Linux.do Credit 官方暗色主题
- 📱 完美支持移动端
- 🔒 安全的签名验证

---

## 🚀 快速开始（3 步启动）

### 第 1 步：在 Linux.do Credit 创建应用

1. 访问 https://credit.linux.do → 登录
2. 进入 **控制台** → **集市中心** → 点击 **创建应用**
3. 填写应用信息（假设你的域名是 `donate.momb.top`）：

| 字段 | 填写内容 |
|------|---------|
| **应用名称** | 打赏网站（或自定义名称） |
| **应用主页** | `https://donate.momb.top` |
| **通知地址** | `https://donate.momb.top/api/notify.php` |
| **回调地址** | `https://donate.momb.top/success.html` |

4. 创建成功后，记录 **Client ID** 和 **Client Secret**

⚠️ **重要说明**：
- 通知地址和回调地址必须是**外网可访问**的完整 URL
- 不能使用 `localhost` 或 `127.0.0.1`
- 推荐使用 HTTPS（生产环境）

---

### 第 2 步：配置项目文件

```bash
# 复制配置模板
cp config/config.example.php config/config.php

# 编辑配置文件
nano config/config.php
```

**填写以下配置**（必须与第 1 步创建应用时填写的信息一致）：

| 配置项 | 说明 | 示例值 |
|--------|------|--------|
| `pid` | Client ID（第 1 步获取） | `10001` |
| `key` | Client Secret（第 1 步获取） | `sk_xxxxx...` |
| `notify_url` | 通知地址（与第 1 步**完全一致**） | `https://donate.momb.top/api/notify.php` |
| `return_url` | 回调地址（与第 1 步**完全一致**） | `https://donate.momb.top/success.html` |

```php
'epay' => [
    'pid' => '10001',  // ← 你的 Client ID
    'key' => 'sk_xxxxx...',  // ← 你的 Client Secret
    'notify_url' => 'https://donate.momb.top/api/notify.php',  // ← 与创建应用时一致
    'return_url' => 'https://donate.momb.top/success.html',     // ← 与创建应用时一致
],
```

💡 **提示**：`notify_url` 和 `return_url` 仅用于签名验证，Linux.do Credit 实际使用的是创建应用时填写的地址。

---

### 第 3 步：启动服务

#### 方式 A：Docker 部署（推荐）

```bash
# 启动容器
docker compose up -d

# 查看日志
docker compose logs -f
```

访问：`https://donate.momb.top/index.html`

**详细文档**：查看 [DOCKER.md](DOCKER.md) 了解完整的 Docker 部署指南。

#### 方式 B：PHP 内置服务器（测试环境）

```bash
# 启动服务器
php -S 0.0.0.0:8000

# 后台运行（推荐）
nohup php -S 0.0.0.0:8000 > logs/server.log 2>&1 &
```

访问：`http://your-server-ip:8000/index.html`

**停止服务器**：
```bash
pkill -f "php -S"
```

**生产环境部署**：建议使用 Nginx 反向代理 + HTTPS，详见 [DEPLOYMENT.md](DEPLOYMENT.md)

---

## ✅ 测试支付流程

1. 访问网站：`https://donate.momb.top/index.html`
2. 选择或输入金额（建议先用 **0.01** 测试）
3. 填写留言（可选）
4. 点击"下一步"
5. 在 Linux.do Credit 完成支付认证
6. 自动返回查看结果

---

## 🌐 配置检查清单

部署前请确认：

- [ ] 已在 Linux.do Credit **创建应用**并填写通知地址、回调地址
- [ ] config.php 中的地址与创建应用时填写的**完全一致**
- [ ] 地址使用外网可访问的域名（不能用 localhost）
- [ ] 通知地址格式：`https://你的域名/api/notify.php`
- [ ] 回调地址格式：`https://你的域名/success.html`
- [ ] 已正确填写 Client ID 和 Client Secret
- [ ] 已启动服务并能正常访问

**常见地址格式**：

| 域名类型 | 通知地址示例 | 回调地址示例 |
|---------|-------------|-------------|
| 主域名 | `https://example.com/api/notify.php` | `https://example.com/success.html` |
| 子域名 | `https://donate.example.com/api/notify.php` | `https://donate.example.com/success.html` |
| 带端口 | `http://example.com:8000/api/notify.php` | `http://example.com:8000/success.html` |

---

## ⚙️ 自定义配置

### 修改预设金额

编辑 `config/config.php`：

```php
'preset_amounts' => [2, 6, 18, 66, 188],  // 修改为您需要的金额
```

### 修改金额限制

```php
'min_amount' => 1,      // 最小 1 元
'max_amount' => 500,    // 最大 500 元
```

### 修改页面文案

```php
'title' => '请我喝咖啡',
'description' => '您的支持是创作的动力',
```

### 修改 UI 主题

查看 `THEME.md` 了解如何自定义颜色和样式。

---

## 📁 项目结构

```
reward-website/
├── index.html              # 打赏页面
├── success.html           # 支付成功页面
├── api/                   # 后端接口
│   ├── create_order.php   # 创建订单
│   ├── query_order.php    # 查询订单
│   └── notify.php         # 支付回调
├── config/
│   ├── config.php         # 配置文件（需创建）
│   └── config.example.php # 配置模板
└── assets/                # CSS/JS 资源
```

---

## ❓ 常见问题

### Q: 如何获取 Client ID 和 Secret？
访问 https://credit.linux.do → 控制台 → 应用管理 → 创建应用

### Q: notify_url 配置错误会怎样？
支付会成功，但您的服务器收不到通知。可以通过成功页面的轮询查询获取状态。

### Q: 80 端口被占用怎么办？
使用其他端口：`php -S 0.0.0.0:8080`，域名配置也要加端口号。

### Q: 如何查看日志？
```bash
tail -f logs/server.log           # 服务器日志
tail -f logs/$(date +%Y-%m-%d).log  # 应用日志
```

### Q: 支持 HTTPS 吗？
PHP 内置服务器不支持 HTTPS。生产环境建议使用 Nginx/Apache + Let's Encrypt。

---

## 📚 更多文档

- **DOCKER.md** - 🐳 Docker 部署指南（推荐生产环境）
- **DEPLOYMENT.md** - 完整部署文档（Nginx、1Panel、Systemd）
- **QUICKSTART.md** - 快速开始指南
- **THEME.md** - UI 主题自定义
- **API.md** - 详细接口文档（查看完整 API 说明）

---

## 📧 技术支持

- **Linux.do Credit 文档**: https://credit.linux.do/docs
- **GitHub 仓库**: https://github.com/Razewang/LINUX_EASY_CREDIT

---

**祝您使用愉快！** 🎉
