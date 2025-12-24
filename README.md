# 打赏网站项目

一个基于 Linux.do Credit 支付接口的简洁打赏网站，支持自定义金额、预设金额快捷按钮和留言功能。

## 功能特性

- ✨ 简洁美观的用户界面
- 💰 支持自定义金额和预设金额选择
- 💬 支持打赏留言功能
- 🔄 实时支付状态查询
- 📱 完全响应式设计，支持移动端
- 🔒 安全的签名验证机制
- 📝 完整的日志记录

## 项目结构

```
reward-website/
├── index.html              # 主页面（打赏页面）
├── success.html           # 支付成功页面
├── api/                   # 后端接口目录
│   ├── EpayHelper.php    # 易支付工具类
│   ├── create_order.php  # 创建订单接口
│   ├── query_order.php   # 查询订单接口
│   └── notify.php        # 支付回调接口
├── assets/               # 静态资源目录
│   ├── css/
│   │   └── style.css    # 样式文件
│   └── js/
│       └── main.js      # JavaScript逻辑
├── config/              # 配置目录
│   └── config.php      # 配置文件
└── logs/               # 日志目录（自动创建）
    └── orders/         # 订单文件存储
```

## 部署指南

### 环境要求

- PHP 7.0 或更高版本
- 启用 cURL 扩展
- Web 服务器（Apache/Nginx）
- HTTPS 支持（生产环境强烈推荐）

### 部署步骤

#### 1. 上传文件

将整个 `reward-website` 目录上传到您的 Web 服务器。

#### 2. 配置权限

确保以下目录有写入权限：

```bash
chmod 755 reward-website/logs
chmod 755 reward-website/logs/orders
```

#### 3. 配置 Linux.do Credit

编辑 `config/config.php` 文件，填入您的 Linux.do Credit 配置信息：

```php
'epay' => [
    'pid' => 'YOUR_PID',              // 您的 Client ID
    'key' => 'YOUR_SECRET_KEY',       // 您的 Client Secret
    'gateway' => 'https://credit.linux.do/epay',
    'notify_url' => 'http://your-domain.com/api/notify.php',  // 修改为您的域名
    'return_url' => 'http://your-domain.com/success.html',     // 修改为您的域名
],
```

**重要提示：**
- 将 `YOUR_PID` 替换为您在 Linux.do Credit 获取的 Client ID
- 将 `YOUR_SECRET_KEY` 替换为您的 Client Secret
- 将 `your-domain.com` 替换为您的实际域名
- `notify_url` 必须是外网可访问的地址（用于接收支付回调）

#### 4. 在 Linux.do Credit 后台配置回调地址

登录 Linux.do Credit 管理后台，在应用设置中配置：

- **异步回调地址**: `http://your-domain.com/api/notify.php`
- **同步返回地址**: `http://your-domain.com/success.html`

#### 5. 访问网站

在浏览器中访问：`http://your-domain.com/index.html`

## 配置说明

### config/config.php

```php
return [
    // Linux.do Credit API 配置
    'epay' => [
        'pid' => 'YOUR_PID',              // Client ID
        'key' => 'YOUR_SECRET_KEY',       // Client Secret
        'gateway' => 'https://credit.linux.do/epay',
        'notify_url' => '...',
        'return_url' => '...',
    ],

    // 打赏配置
    'reward' => [
        'title' => '支持作者',             // 网站标题
        'description' => '感谢您的支持！',
        'min_amount' => 0.01,             // 最小打赏金额
        'max_amount' => 9999.99,          // 最大打赏金额
        'preset_amounts' => [1, 5, 10, 20, 50, 100],  // 预设金额
    ],
];
```

可以根据需要调整：
- 最小/最大打赏金额
- 预设金额选项
- 网站标题和描述

## API 接口说明

### 1. 创建订单

**接口**: `POST /api/create_order.php`

**请求参数**:
```json
{
    "amount": 10.00,
    "message": "感谢分享，继续加油！"
}
```

**返回示例**:
```json
{
    "code": 200,
    "message": "订单创建成功",
    "data": {
        "order_no": "RW20250101120000001",
        "amount": 10.00,
        "redirect_url": "https://credit.linux.do/epay/pay/submit.php?..."
    }
}
```

### 2. 查询订单

**接口**: `GET /api/query_order.php?order_no=xxx`

**返回示例**:
```json
{
    "code": 200,
    "message": "查询成功",
    "data": {
        "order_no": "RW20250101120000001",
        "amount": 10.00,
        "message": "感谢分享",
        "status": 1,
        "status_text": "已支付",
        "pay_time": "2025-01-01 12:05:00"
    }
}
```

### 3. 支付回调

**接口**: `GET /api/notify.php`

由 Linux.do Credit 服务器调用，用于通知支付结果。

**回调参数**:
- `pid`: 商户ID
- `trade_no`: 平台订单号
- `out_trade_no`: 商户订单号
- `money`: 金额
- `trade_status`: 交易状态（TRADE_SUCCESS 表示成功）
- `sign`: 签名

**返回**: `success` 或 `fail`

## 使用流程

### 用户端流程

1. 用户访问打赏页面（index.html）
2. 选择或输入打赏金额
3. 可选填写留言
4. 点击"立即支持"按钮
5. 跳转到 Linux.do Credit 支付页面
6. 完成支付认证
7. 自动返回成功页面（success.html）
8. 显示支付结果和订单信息

### 系统处理流程

1. **创建订单**
   - 前端调用 `create_order.php`
   - 验证金额和参数
   - 生成订单号
   - 保存订单信息到文件
   - 返回支付URL

2. **支付处理**
   - 用户在 Linux.do Credit 完成支付
   - Linux.do Credit 回调 `notify.php`
   - 验证签名
   - 更新订单状态
   - 返回 success

3. **状态查询**
   - 成功页面轮询 `query_order.php`
   - 查询本地订单状态
   - 如未完成则查询 Linux.do Credit
   - 返回最新状态

## 安全说明

### 签名验证

所有与 Linux.do Credit 的通信都使用 MD5 签名验证：

1. 参数按 ASCII 升序排列
2. 拼接成 key=value 格式
3. 末尾追加密钥
4. 进行 MD5 加密

### 密钥保护

- 商户密钥（key）仅在后端使用
- 不要在前端代码中暴露密钥
- 定期更换密钥以提高安全性

### HTTPS

生产环境强烈建议使用 HTTPS：
- 保护用户数据传输安全
- 防止中间人攻击
- 提高用户信任度

## 日志说明

系统会自动记录日志到 `logs` 目录：

### 日志文件

- `logs/YYYY-MM-DD.log`: 每日日志文件
- `logs/orders/订单号.json`: 订单信息文件

### 日志内容

- 订单创建记录
- 支付回调记录
- 订单查询记录
- 错误信息记录

### 查看日志

```bash
# 查看今天的日志
tail -f logs/2025-01-01.log

# 查看特定订单
cat logs/orders/RW20250101120000001.json
```

## 故障排查

### 问题：创建订单失败

**可能原因**:
- API 配置错误
- 金额格式不正确
- PHP 缺少 cURL 扩展

**解决方法**:
1. 检查 `config/config.php` 配置是否正确
2. 确保金额小数位不超过 2 位
3. 运行 `php -m | grep curl` 检查 cURL 扩展

### 问题：回调未收到

**可能原因**:
- notify_url 配置错误
- 服务器防火墙阻止
- URL 无法公网访问

**解决方法**:
1. 确保 notify_url 可以从外网访问
2. 检查服务器防火墙设置
3. 在 Linux.do Credit 后台检查回调配置
4. 查看 logs 目录日志

### 问题：签名验证失败

**可能原因**:
- 密钥配置错误
- 参数顺序错误
- 字符编码问题

**解决方法**:
1. 确认 config.php 中的 key 正确
2. 检查签名算法实现
3. 确保文件编码为 UTF-8

## 自定义修改

### 修改预设金额

编辑 `config/config.php`:

```php
'preset_amounts' => [1, 5, 10, 20, 50, 100],  // 修改为您需要的金额
```

### 修改页面标题和样式

编辑 `index.html` 和 `assets/css/style.css`

### 添加数据库支持

如需保存打赏记录到数据库：

1. 创建数据库表
2. 在 `config.php` 中配置数据库信息
3. 修改 `api/notify.php` 添加数据库写入逻辑

## 技术栈

- **前端**: HTML5, CSS3, JavaScript (原生)
- **后端**: PHP 7.0+
- **支付**: Linux.do Credit API
- **存储**: 文件系统（可扩展为数据库）

## 许可证

本项目仅供学习交流使用。

## 支持与反馈

如有问题或建议，欢迎反馈。

---

**祝您使用愉快！** 🎉
