# API 接口文档

本文档详细说明项目的 API 接口、签名算法和技术细节。

---

## 📡 API 接口列表

### 1. 创建订单

创建支付订单并返回支付 URL。

**接口**: `POST /api/create_order.php`

**Content-Type**: `application/json`

**请求参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| amount | Number | 是 | 打赏金额，最多 2 位小数 |
| message | String | 否 | 打赏留言，最多 200 字符 |

**请求示例**:
```json
{
    "amount": 10.00,
    "message": "感谢分享，继续加油！"
}
```

**成功响应**:
```json
{
    "code": 200,
    "message": "订单创建成功",
    "data": {
        "order_no": "RW20250125143000001",
        "amount": 10.00,
        "pay_url": "https://credit.linux.do/epay/pay/submit.php",
        "pay_params": {
            "pid": "...",
            "type": "epay",
            "out_trade_no": "RW20250125143000001",
            "name": "打赏支持：感谢分享，继续加油！",
            "money": 10.00,
            "sign": "...",
            "sign_type": "MD5"
        },
        "redirect_url": "https://credit.linux.do/epay/pay/submit.php?pid=..."
    }
}
```

**错误响应**:
```json
{
    "code": 400,
    "message": "打赏金额不能小于 0.01 元",
    "data": null
}
```

---

### 2. 查询订单

查询订单支付状态。

**接口**: `GET /api/query_order.php`

**请求参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_no | String | 是 | 订单号 |

**请求示例**:
```
GET /api/query_order.php?order_no=RW20250125143000001
```

**成功响应**:
```json
{
    "code": 200,
    "message": "查询成功",
    "data": {
        "order_no": "RW20250125143000001",
        "amount": 10.00,
        "message": "感谢分享，继续加油！",
        "status": 1,
        "status_text": "已支付",
        "pay_time": "2025-01-25 14:35:20"
    }
}
```

**状态说明**:
- `status: 0` - 未支付
- `status: 1` - 已支付

---

### 3. 支付回调

Linux.do Credit 支付成功后调用此接口通知服务器。

**接口**: `GET /api/notify.php`

**调用方**: Linux.do Credit 服务器

**请求参数**:

| 参数 | 类型 | 说明 |
|------|------|------|
| pid | String | 商户ID |
| trade_no | String | 平台订单号 |
| out_trade_no | String | 商户订单号 |
| type | String | 固定值 `epay` |
| name | String | 订单标题 |
| money | String | 订单金额 |
| trade_status | String | 交易状态，成功为 `TRADE_SUCCESS` |
| sign_type | String | 签名类型 `MD5` |
| sign | String | 签名字符串 |

**请求示例**:
```
GET /api/notify.php?pid=001&trade_no=20250125001&out_trade_no=RW20250125143000001&type=epay&name=打赏支持&money=10.00&trade_status=TRADE_SUCCESS&sign=xxx&sign_type=MD5
```

**响应要求**:
- **成功**: 返回 HTTP 200，响应体为 `success`（大小写不敏感）
- **失败**: 返回其他内容，Linux.do Credit 会重试（最多 5 次）

---

## 🔐 签名算法详解

### 签名生成步骤

1. **筛选参数**: 取所有非空参数，排除 `sign` 和 `sign_type`
2. **ASCII 排序**: 按参数名 ASCII 码升序排列
3. **拼接字符串**: 格式为 `key1=value1&key2=value2`
4. **追加密钥**: 字符串末尾直接拼接商户密钥
5. **MD5 加密**: 对整个字符串进行 MD5，取 32 位小写

### PHP 实现

```php
function createSign($params, $secret) {
    // 1. 过滤空值和 sign 字段
    $params = array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    });
    unset($params['sign']);
    unset($params['sign_type']);

    // 2. ASCII 排序
    ksort($params);

    // 3. 拼接参数
    $string = '';
    foreach ($params as $key => $value) {
        $string .= $key . '=' . $value . '&';
    }
    $string = rtrim($string, '&');

    // 4. 追加密钥
    $string .= $secret;

    // 5. MD5 加密
    return md5($string);
}
```

### JavaScript 实现

```javascript
async function createSign(params, secret) {
    // 1. 过滤并排序
    const filtered = Object.keys(params)
        .filter(k => params[k] && k !== 'sign' && k !== 'sign_type')
        .sort();

    // 2. 拼接参数
    const string = filtered
        .map(k => `${k}=${params[k]}`)
        .join('&') + secret;

    // 3. MD5 加密
    const encoder = new TextEncoder();
    const data = encoder.encode(string);
    const hashBuffer = await crypto.subtle.digest('MD5', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}
```

### 签名示例

**参数**:
```
pid: 001
type: epay
out_trade_no: ORDER001
name: Test
money: 10
```

**密钥**: `secret123`

**处理过程**:
```
1. 排序: money, name, out_trade_no, pid, type
2. 拼接: money=10&name=Test&out_trade_no=ORDER001&pid=001&type=epay
3. 加密: md5("money=10&name=Test&out_trade_no=ORDER001&pid=001&type=epaysecret123")
4. 结果: d290f1ee6c544b0190e18d43d4f18bf6
```

---

## 📂 订单数据存储

### 存储方式

订单信息存储在文件系统中（可扩展为数据库）：

**文件路径**: `logs/orders/{订单号}.json`

**数据结构**:
```json
{
    "out_trade_no": "RW20250125143000001",
    "amount": 10.00,
    "message": "感谢分享",
    "create_time": "2025-01-25 14:30:00",
    "status": 1,
    "trade_no": "20250125001",
    "pay_time": "2025-01-25 14:35:20"
}
```

### 查看订单文件

```bash
# 查看所有订单
ls -la logs/orders/

# 查看特定订单
cat logs/orders/RW20250125143000001.json
```

---

## 🔍 故障排查

### 问题 1：创建订单失败

**错误信息**: `{"code":400,"message":"..."}`

**可能原因**:
- 金额格式不正确
- 金额超出限制
- PHP 缺少 cURL 扩展

**解决方法**:
```bash
# 检查 PHP cURL 扩展
php -m | grep curl

# 检查配置文件
cat config/config.php
```

---

### 问题 2：回调未收到

**现象**: 支付成功但订单状态未更新

**可能原因**:
- notify_url 无法从外网访问
- 防火墙阻止
- 签名验证失败

**解决方法**:

1. **测试回调地址可达性**:
   ```bash
   # 从外部访问测试
   curl http://your-domain.com/api/notify.php
   ```

2. **查看回调日志**:
   ```bash
   tail -f logs/$(date +%Y-%m-%d).log | grep "收到回调"
   ```

3. **检查防火墙**:
   ```bash
   # 确保 80 端口开放
   sudo ufw status
   sudo ufw allow 80
   ```

---

### 问题 3：签名验证失败

**错误信息**: 日志中显示"签名验证失败"

**可能原因**:
- config.php 中的 key 配置错误
- 字符编码问题
- 参数被修改

**解决方法**:
1. 确认 `config.php` 中的 `key` 与 Linux.do Credit 后台一致
2. 确保所有文件编码为 UTF-8
3. 检查日志中的签名对比信息

---

## 🔧 高级配置

### 开机自启动

创建 systemd 服务文件：

```bash
sudo nano /etc/systemd/system/reward-site.service
```

内容：
```ini
[Unit]
Description=Reward Website PHP Server
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/home/linuxcredit
ExecStart=/usr/bin/php -S 0.0.0.0:80
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

启动服务：
```bash
sudo systemctl daemon-reload
sudo systemctl enable reward-site
sudo systemctl start reward-site

# 查看状态
sudo systemctl status reward-site
```

---

### 使用数据库存储

如需使用数据库存储订单（替代文件存储）：

#### 1. 创建数据库表

```sql
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `out_trade_no` varchar(64) NOT NULL,
  `trade_no` varchar(64) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `message` text,
  `status` tinyint(1) DEFAULT 0,
  `create_time` datetime NOT NULL,
  `pay_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `out_trade_no` (`out_trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 2. 配置数据库连接

编辑 `config/config.php`：

```php
'database' => [
    'enabled' => true,
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'reward',
    'username' => 'root',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
],
```

#### 3. 修改接口代码

修改 `api/create_order.php` 和 `api/notify.php`，将文件操作改为数据库操作。

---

## 🌐 生产环境部署

### 使用 Nginx

**Nginx 配置示例**:

```nginx
server {
    listen 80;
    server_name tip.yourdomain.com;
    root /home/linuxcredit;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### SSL 证书（HTTPS）

使用 Let's Encrypt 免费证书：

```bash
# 安装 Certbot
sudo apt install certbot python3-certbot-nginx

# 获取证书
sudo certbot --nginx -d tip.yourdomain.com

# 自动续期
sudo certbot renew --dry-run
```

---

## 📊 日志说明

### 日志文件

| 文件 | 说明 |
|------|------|
| `logs/YYYY-MM-DD.log` | 每日应用日志 |
| `logs/server.log` | PHP 服务器日志（后台运行时） |
| `logs/orders/{订单号}.json` | 订单数据文件 |

### 日志内容

**应用日志** (`logs/YYYY-MM-DD.log`):
```
[2025-01-25 14:30:00] [info] 创建订单: RW20250125143000001, 金额: 10, 留言: 感谢分享
[2025-01-25 14:35:20] [info] 收到回调: {"pid":"001","trade_no":"..."}
[2025-01-25 14:35:20] [info] 订单支付成功: RW20250125143000001, 金额: 10
```

### 查看日志

```bash
# 实时查看应用日志
tail -f logs/$(date +%Y-%m-%d).log

# 实时查看服务器日志
tail -f logs/server.log

# 查看所有订单
ls -lh logs/orders/

# 查看特定订单
cat logs/orders/RW20250125143000001.json | jq .
```

---

## 🔒 安全最佳实践

### 1. 密钥保护

- ✅ 商户密钥只在后端使用
- ✅ config.php 已在 .gitignore 中排除
- ✅ 不要在前端代码中暴露密钥
- ✅ 定期更换密钥

### 2. 签名验证

- ✅ 所有回调必须验证签名
- ✅ 签名验证失败的请求直接拒绝
- ✅ 金额必须与原订单一致

### 3. HTTPS

生产环境强烈建议使用 HTTPS：
- 保护数据传输安全
- 防止中间人攻击
- 提高 SEO 排名

### 4. 访问控制

- 限制 API 访问频率
- 记录所有异常请求
- 定期检查日志

---

## 📈 性能优化

### PHP 内置服务器限制

PHP 内置服务器适用于：
- ✅ 开发测试
- ✅ 小流量项目
- ✅ 个人使用

**不适用于**:
- ❌ 高并发场景
- ❌ 生产环境（建议用 Nginx/Apache）

### 优化建议

1. **使用专业 Web 服务器**
   - Nginx + PHP-FPM
   - Apache + mod_php

2. **启用缓存**
   - 静态文件缓存
   - PHP OPcache

3. **数据库优化**
   - 使用数据库替代文件存储
   - 添加索引

---

## 🧪 测试工具

### 测试支付流程

```bash
# 1. 创建测试订单
curl -X POST http://localhost/api/create_order.php \
  -H "Content-Type: application/json" \
  -d '{"amount": 0.01, "message": "测试"}'

# 2. 查询订单状态
curl "http://localhost/api/query_order.php?order_no=RW20250125143000001"

# 3. 模拟回调（测试签名验证）
curl "http://localhost/api/notify.php?pid=001&trade_no=xxx&..."
```

---

## 📚 相关文档

- **README.md** - 快速开始指南
- **QUICKSTART.md** - 3 步部署教程
- **THEME.md** - UI 主题自定义
- **config/config.example.php** - 配置文件模板

---

## 🆘 技术支持

- **Linux.do Credit 官方文档**: https://credit.linux.do/docs
- **GitHub Issues**: https://github.com/Razewang/LINUX_EASY_CREDIT/issues
- **Linux.do 社区**: https://linux.do

---

更新时间：2025-12-25
