# 快速开始指南

## 3 步快速部署

### 第 1 步：修改配置文件

编辑 `config/config.php`，修改以下内容：

```php
'epay' => [
    'pid' => 'YOUR_PID',              // ← 填入您的 Client ID
    'key' => 'YOUR_SECRET_KEY',       // ← 填入您的 Client Secret
    'notify_url' => 'http://your-domain.com/api/notify.php',  // ← 修改域名
    'return_url' => 'http://your-domain.com/success.html',     // ← 修改域名
],
```

**获取 PID 和 KEY**:
1. 访问 https://credit.linux.do
2. 登录并进入控制台
3. 创建新应用或查看现有应用
4. 复制 Client ID（pid）和 Client Secret（key）

### 第 2 步：配置回调地址

在 Linux.do Credit 后台的应用设置中配置：

- **异步回调地址**: `http://your-domain.com/api/notify.php`
- **同步返回地址**: `http://your-domain.com/success.html`

⚠️ **重要**: 域名必须替换为您的实际域名！

### 第 3 步：设置权限并访问

```bash
# 设置日志目录权限
chmod 755 logs
chmod 755 logs/orders

# 在浏览器访问
http://your-domain.com/index.html
```

## 测试流程

1. 访问 `index.html`
2. 输入测试金额 `0.01` 元
3. 填写测试留言（可选）
4. 点击"立即支持"
5. 在 Linux.do Credit 页面完成认证
6. 查看支付结果

## 常见配置错误

### ❌ 错误 1：notify_url 使用了 localhost

```php
// 错误示例
'notify_url' => 'http://localhost/api/notify.php',  // ❌ 外网无法访问

// 正确示例
'notify_url' => 'http://yourdomain.com/api/notify.php',  // ✅ 外网可访问
```

### ❌ 错误 2：未修改 pid 和 key

```php
// 错误示例
'pid' => 'YOUR_PID',  // ❌ 未修改占位符

// 正确示例
'pid' => '10001',     // ✅ 实际的 Client ID
```

### ❌ 错误 3：目录权限不足

```bash
# 如果出现写入错误，执行：
chmod -R 755 logs/
```

## 检查清单

部署前请确认：

- [ ] 已修改 `config.php` 中的 pid 和 key
- [ ] 已修改 notify_url 和 return_url 为实际域名
- [ ] 已在 Linux.do Credit 后台配置回调地址
- [ ] notify_url 可以从外网访问
- [ ] logs 目录有写入权限
- [ ] PHP 已安装 cURL 扩展

## 验证配置是否正确

### 1. 检查 PHP 环境

```bash
# 检查 PHP 版本（需要 >= 7.0）
php -v

# 检查 cURL 扩展
php -m | grep curl
```

### 2. 测试 API 接口

在浏览器访问：

```
http://your-domain.com/api/create_order.php
```

应该返回类似：
```json
{"code":400,"message":"..."}
```

如果返回 404 或其他错误，检查文件路径和服务器配置。

### 3. 查看日志

完成一次测试支付后，检查日志：

```bash
# 查看当天日志
cat logs/2025-01-01.log

# 查看订单文件
ls -la logs/orders/
```

## 需要帮助？

如遇问题：
1. 查看 `README.md` 完整文档
2. 检查 `logs/` 目录的日志文件
3. 确认所有配置项都已正确修改

---

**祝部署顺利！** 🚀
