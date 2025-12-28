# 快速开始指南

参考 Linux.do Credit 官方 API 集成流程，3 步快速部署打赏网站。

---

## 第 1 步：在 Linux.do Credit 创建应用

1. 访问 https://credit.linux.do → 登录
2. 进入 **控制台** → **集市中心** → 点击 **创建应用**
3. 填写应用信息：

| 字段 | 说明 | 填写示例 |
|------|------|---------|
| **应用名称** | 自定义应用名称 | `我的打赏网站` |
| **应用主页** | 你的网站域名 | `https://donate.momb.top` |
| **通知地址** | 异步回调通知（支付成功后调用） | `https://donate.momb.top/api/notify.php` |
| **回调地址** | 同步返回页面（用户支付后跳转） | `https://donate.momb.top/success.html` |

4. 创建成功后，复制 **Client ID** 和 **Client Secret** 备用

⚠️ **重要**：
- 通知地址和回调地址必须是**外网可访问**的完整 URL
- 不能使用 `localhost`、`127.0.0.1` 或内网地址
- 推荐使用 HTTPS（生产环境必须）

---

## 第 2 步：配置项目文件

### 2.1 复制配置模板

```bash
cd /home/paygo/reward-website
cp config/config.example.php config/config.php
```

### 2.2 编辑配置文件

```bash
nano config/config.php
```

### 2.3 填写必要配置

**必须与第 1 步创建应用时填写的信息完全一致：**

```php
'epay' => [
    'pid' => 'YOUR_CLIENT_ID',              // ← 第 1 步获取的 Client ID
    'key' => 'YOUR_CLIENT_SECRET',          // ← 第 1 步获取的 Client Secret
    'notify_url' => 'https://donate.momb.top/api/notify.php',  // ← 与创建应用时一致
    'return_url' => 'https://donate.momb.top/success.html',     // ← 与创建应用时一致
],
```

**配置说明**：

| 参数 | 作用 | 来源 |
|------|------|------|
| `pid` | 商户 ID | Linux.do Credit 应用的 Client ID |
| `key` | 商户密钥 | Linux.do Credit 应用的 Client Secret |
| `notify_url` | 通知地址（仅用于签名） | 必须与创建应用时填写的一致 |
| `return_url` | 回调地址（仅用于签名） | 必须与创建应用时填写的一致 |

💡 **注意**：
- `notify_url` 和 `return_url` 在 config.php 中**仅用于签名验证**
- Linux.do Credit 实际调用的是**创建应用时填写的地址**
- 两边地址不一致会导致签名验证失败

---

## 第 3 步：启动服务并测试

### 3.1 设置权限

```bash
# 确保日志目录有写入权限
chmod 755 logs
chmod 755 logs/orders
```

### 3.2 启动服务

**方式 A：Docker 部署（推荐）**

```bash
# 启动容器
docker compose up -d

# 查看日志
docker compose logs -f
```

访问：`https://donate.momb.top/index.html`

**方式 B：PHP 内置服务器（测试环境）**

```bash
# 启动服务器（8000 端口）
php -S 0.0.0.0:8000

# 后台运行
nohup php -S 0.0.0.0:8000 > logs/server.log 2>&1 &
```

访问：`http://your-server-ip:8000/index.html`

**停止服务器**：
```bash
pkill -f "php -S"
```

### 3.3 测试支付流程

1. 访问网站首页
2. 输入测试金额 **0.01** 积分
3. 填写测试留言（可选）
4. 点击"下一步"
5. 在 Linux.do Credit 页面完成认证
6. 支付成功后自动返回查看结果

---

## ✅ 配置检查清单

部署前请确认以下所有项：

- [ ] 已在 Linux.do Credit **创建应用**并填写通知地址、回调地址
- [ ] config.php 中的地址与创建应用时填写的**完全一致**
- [ ] 地址使用外网可访问的域名（不能用 localhost）
- [ ] 已正确填写 Client ID 和 Client Secret
- [ ] logs 目录有写入权限（`chmod 755 logs`）
- [ ] PHP 已安装 cURL 扩展（`php -m | grep curl`）
- [ ] 已启动服务并能正常访问

---

## ❌ 常见配置错误

### 错误 1：创建应用时未填写回调地址

**错误表现**：支付成功但收不到通知

**原因**：创建应用时没有填写通知地址和回调地址

**解决**：
1. 在 Linux.do Credit 控制台找到应用
2. 编辑应用，填写通知地址和回调地址
3. 确保与 config.php 中的地址一致

### 错误 2：两边地址不一致

**错误表现**：签名验证失败

**示例**：
```php
// Linux.do Credit 应用配置：https://donate.momb.top/api/notify.php
// config.php 配置：http://donate.momb.top/api/notify.php  ← 协议不一致！
```

**解决**：确保两边的地址**完全一致**（包括协议、域名、路径）

### 错误 3：使用了 localhost

```php
// ❌ 错误
'notify_url' => 'http://localhost/api/notify.php',

// ✅ 正确
'notify_url' => 'https://donate.momb.top/api/notify.php',
```

### 错误 4：未修改占位符

```php
// ❌ 错误
'pid' => 'YOUR_CLIENT_ID',

// ✅ 正确
'pid' => '10001',
```

---

## 🔍 验证配置是否正确

### 1. 检查 PHP 环境

```bash
# 检查 PHP 版本（需要 >= 7.4）
php -v

# 检查 cURL 扩展
php -m | grep curl
```

### 2. 测试 API 接口

访问：`https://donate.momb.top/api/create_order.php`

应该返回类似：
```json
{"code":400,"message":"Invalid request method"}
```

如果返回 404 或其他错误，检查文件路径和服务器配置。

### 3. 查看日志

完成一次测试支付后，检查日志：

```bash
# 查看当天日志
cat logs/$(date +%Y-%m-%d).log

# 查看订单文件
ls -la logs/orders/
```

---

## 需要帮助？

如遇问题：
1. 查看 [README.md](README.md) 完整文档
2. 查看 [DEPLOYMENT.md](DEPLOYMENT.md) 生产环境部署指南
3. 检查 `logs/` 目录的日志文件
4. 确认所有配置项都已正确修改

---

**祝部署顺利！** 🚀
