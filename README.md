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

### 第 1 步：配置

```bash
# 复制配置文件
cp config/config.example.php config/config.php

# 编辑配置
nano config/config.php
```

**填写 4 个必填项**（假设域名是 `tip.yourdomain.com`）：

```php
'pid' => 'YOUR_CLIENT_ID',              // 从 Linux.do Credit 控制台获取
'key' => 'YOUR_CLIENT_SECRET',          // 从 Linux.do Credit 控制台获取
'notify_url' => 'http://tip.yourdomain.com/api/notify.php',
'return_url' => 'http://tip.yourdomain.com/success.html',
```

**获取 API 密钥**：
1. 访问 https://credit.linux.do
2. 登录 → 控制台 → 应用管理
3. 创建应用，复制 Client ID 和 Client Secret

**配置回调地址**（在 Linux.do Credit 后台）：
- 异步回调：`http://tip.yourdomain.com/api/notify.php`
- 同步返回：`http://tip.yourdomain.com/success.html`

---

### 第 2 步：启动服务器

```bash
cd /path/to/project
php -S 0.0.0.0:80
```

**后台运行**（推荐）：
```bash
nohup php -S 0.0.0.0:80 > logs/server.log 2>&1 &
```

**停止服务器**：
```bash
pkill -f "php -S"
```

---

### 第 3 步：访问测试

```
http://tip.yourdomain.com/index.html
```

**测试流程**：
1. 选择或输入金额（建议先用 0.01 测试）
2. 填写留言（可选）
3. 点击"下一步"
4. 在 Linux.do Credit 完成支付认证
5. 自动返回查看结果

---

## 🌐 域名配置说明

### notify_url 和 return_url 怎么填？

**格式**：`http://您的域名/文件路径`

| 域名类型 | notify_url 示例 | return_url 示例 |
|---------|----------------|----------------|
| 主域名 | `http://example.com/api/notify.php` | `http://example.com/success.html` |
| 子域名 | `http://tip.example.com/api/notify.php` | `http://tip.example.com/success.html` |
| 带端口 | `http://example.com:8080/api/notify.php` | `http://example.com:8080/success.html` |

**重点**：
- ✅ 必须是外网可以访问的完整 URL
- ✅ 项目配置和 Linux.do Credit 后台配置必须一致
- ❌ 不能用 `localhost` 或 `127.0.0.1`

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

- **QUICKSTART.md** - 快速开始指南
- **THEME.md** - UI 主题自定义
- **API.md** - 详细接口文档（查看完整 API 说明）

---

## 📧 技术支持

- **Linux.do Credit 文档**: https://credit.linux.do/docs
- **GitHub 仓库**: https://github.com/Razewang/LINUX_EASY_CREDIT

---

**祝您使用愉快！** 🎉
