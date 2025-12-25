# 第三方集成指南

本文档说明如何配置 Notion 和 Webhook 集成，实现支付成功后自动发送订单数据到第三方平台。

---

## 🎯 功能概述

支付成功后，系统可以自动将订单信息发送到：

1. **Notion 数据库** - 在 Notion 中自动创建订单记录
2. **Webhook URL** - 发送 HTTP 请求到任意 API 接口

---

## 📝 Notion 集成配置

### 第 1 步：创建 Notion Integration

1. 访问 https://www.notion.so/my-integrations
2. 点击 **"+ New integration"**
3. 填写信息：
   - **Name**: 打赏网站集成
   - **Associated workspace**: 选择你的工作区
   - **Type**: Internal
4. 点击 **"Submit"**
5. 复制 **"Internal Integration Token"**（以 `secret_` 开头）

### 第 2 步：创建 Notion 数据库

在 Notion 中创建一个数据库，包含以下属性（列）：

| 属性名 | 类型 | 说明 |
|--------|------|------|
| 订单号 | Title | 标题字段（必须） |
| 金额 | Number | 打赏金额 |
| 留言 | Text | 用户留言 |
| 状态 | Select | 支付状态（已支付/未支付） |
| 支付时间 | Date | 支付完成时间 |

**注意**：属性名必须与配置文件中的名称完全一致！

### 第 3 步：获取数据库 ID

1. 打开你创建的 Notion 数据库
2. 点击右上角 **"..."** → **"Copy link"**
3. 链接格式：`https://www.notion.so/{workspace}/{database_id}?v=...`
4. 提取其中的 `database_id`（32位字符串，去掉连字符）

示例：
```
链接：https://www.notion.so/myworkspace/a1b2c3d4e5f67890a1b2c3d4e5f67890?v=...
Database ID: a1b2c3d4e5f67890a1b2c3d4e5f67890
```

### 第 4 步：连接 Integration 到数据库

1. 打开 Notion 数据库页面
2. 点击右上角 **"..."** → **"Connect to"**
3. 搜索并选择你创建的 Integration

### 第 5 步：配置项目

编辑 `config/config.php`：

```php
'notion' => [
    'enabled' => true,  // 启用 Notion 集成
    'api_key' => 'secret_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',  // 你的 API Key
    'database_id' => 'a1b2c3d4e5f67890a1b2c3d4e5f67890',     // 数据库 ID
    'api_version' => '2022-06-28',
    'properties' => [
        'title_field' => '订单号',      // 必须与 Notion 中的属性名一致
        'amount_field' => '金额',
        'message_field' => '留言',
        'status_field' => '状态',
        'pay_time_field' => '支付时间',
    ],
],
```

---

## 🔗 Webhook 集成配置

Webhook 允许你将支付数据发送到任意 HTTP 接口。

### 配置示例

编辑 `config/config.php`：

```php
'webhook' => [
    'enabled' => true,  // 启用 Webhook
    'url' => 'https://your-api.com/webhook/payment',  // 接收通知的 URL
    'method' => 'POST',  // 请求方法（POST 或 GET）
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer YOUR_API_TOKEN',  // 可选：认证头
        'X-Custom-Header' => 'value',                // 可选：自定义头
    ],
    'timeout' => 10,   // 超时时间（秒）
    'retry' => 3,      // 失败重试次数
],
```

### Webhook 数据格式

系统会发送以下 JSON 数据：

```json
{
    "event": "payment_success",
    "order_no": "RW20250125143000001",
    "amount": 10.00,
    "message": "感谢分享，继续加油！",
    "status": 1,
    "pay_time": "2025-01-25 14:35:20",
    "trade_no": "20250125001",
    "create_time": "2025-01-25 14:30:00",
    "timestamp": 1706164520
}
```

### 接收 Webhook 的服务器要求

1. 返回 HTTP 200 状态码表示成功
2. 响应时间应在 10 秒内（可配置）
3. 支持 HTTPS（推荐）

---

## 🧪 测试集成

### 测试 Notion 集成

1. 完成配置后，创建一笔测试订单
2. 完成支付
3. 检查 Notion 数据库是否出现新记录
4. 查看日志：`tail -f logs/$(date +%Y-%m-%d).log | grep Notion`

### 测试 Webhook

1. 使用在线工具创建测试 Webhook：
   - https://webhook.site （免费，立即可用）
   - https://requestbin.com
2. 复制生成的 URL 到 `config.php` 的 `webhook.url`
3. 创建测试订单并完成支付
4. 在 Webhook 工具页面查看接收到的数据
5. 查看日志：`tail -f logs/$(date +%Y-%m-d).log | grep Webhook`

---

## 📊 数据流程

```
用户支付成功
    ↓
Linux.do Credit 回调 notify.php
    ↓
验证签名 & 更新订单状态
    ↓
触发第三方集成
    ├─→ Notion: 创建数据库记录
    └─→ Webhook: 发送 HTTP 请求
    ↓
返回 success 给 Linux.do Credit
```

---

## ❓ 常见问题

### Q: Notion 集成失败，日志显示 "unauthorized"

**A:** 检查以下项：
1. API Key 是否正确（以 `secret_` 开头）
2. Integration 是否已连接到数据库（数据库设置 → Connect to）
3. 数据库 ID 是否正确（32位字符串）

### Q: Notion 显示 "body failed validation"

**A:** 检查以下项：
1. `properties` 配置中的字段名是否与 Notion 数据库完全一致
2. 字段类型是否匹配（标题、数字、文本、日期、选择）
3. 如果 "状态" 字段是 Select 类型，确保有 "已支付" 和 "未支付" 选项

### Q: Webhook 一直失败

**A:** 检查以下项：
1. URL 是否可从服务器访问（使用 `curl` 测试）
2. 服务器是否返回 200 状态码
3. 超时时间是否足够（默认 10 秒）
4. 查看详细错误日志：`tail -f logs/$(date +%Y-%m-%d).log`

### Q: 集成失败会影响支付吗？

**A:** 不会。集成失败只会记录日志，不影响支付流程。订单仍然会被标记为"已支付"。

---

## 🔧 高级配置

### 自定义 Notion 属性映射

如果你的 Notion 数据库字段名不同，只需修改 `properties` 映射：

```php
'properties' => [
    'title_field' => 'Order Number',    // 英文字段名
    'amount_field' => 'Amount',
    'message_field' => 'Comment',
    'status_field' => 'Payment Status',
    'pay_time_field' => 'Paid At',
],
```

### 自定义 Webhook 数据

编辑 `api/IntegrationHelper.php` 的 `sendToWebhook()` 方法，修改 `$webhookData` 数组。

### 添加更多集成

在 `IntegrationHelper.php` 中添加新方法：

```php
private function sendToCustomService($orderData)
{
    // 你的自定义集成逻辑
}
```

然后在 `sendToIntegrations()` 中调用。

---

## 📚 相关文档

- **Notion API 文档**: https://developers.notion.com
- **README.md** - 项目快速开始
- **API.md** - 完整 API 接口文档

---

## 🆘 技术支持

- **GitHub Issues**: https://github.com/Razewang/LINUX_EASY_CREDIT/issues
- **Linux.do 社区**: https://linux.do

---

更新时间：2025-12-25
