<?php
/**
 * Linux.do Credit 支付配置文件模板
 *
 * ========================================
 * 🚀 最简启动流程（3 步）
 * ========================================
 *
 * 第 1 步：配置文件
 * -----------------
 * cp config/config.example.php config/config.php
 * nano config/config.php
 *
 * 填写必填项：
 * - pid: 您的 Client ID（从 Linux.do Credit 获取）
 * - key: 您的 Client Secret
 * - notify_url: http://您的域名/api/notify.php
 * - return_url: http://您的域名/success.html
 *
 * 第 2 步：启动服务器
 * -------------------
 * cd /path/to/project
 * php -S 0.0.0.0:80
 *
 * 或后台运行：
 * nohup php -S 0.0.0.0:80 > logs/server.log 2>&1 &
 *
 * 第 3 步：访问网站
 * -----------------
 * http://您的域名/index.html
 *
 * ========================================
 * 📝 配置说明
 * ========================================
 *
 * 使用说明：
 * 1. 复制本文件并重命名为 config.php
 * 2. 填写您的实际配置信息
 * 3. 确保 config.php 已在 .gitignore 中排除，不要提交到 Git
 *
 * 域名配置示例：
 * - 主域名: http://yourdomain.com/api/notify.php
 * - 子域名: http://tip.yourdomain.com/api/notify.php
 * - 带端口: http://yourdomain.com:8080/api/notify.php
 */

return [
    // Linux.do Credit API 配置
    'epay' => [
        // 商户ID (Client ID)
        // 获取方式：登录 https://credit.linux.do -> 控制台 -> 应用管理
        'pid' => 'YOUR_PID',

        // 商户密钥 (Client Secret)
        // ⚠️ 重要：请妥善保管，不要泄露或提交到代码仓库
        'key' => 'YOUR_SECRET_KEY',

        // 支付网关地址
        // 默认为 Linux.do Credit 的网关，一般不需要修改
        'gateway' => 'https://credit.linux.do/epay',

        // 异步回调地址
        // 必须是外网可访问的完整 URL
        // Linux.do Credit 支付成功后会调用此地址通知您的服务器
        // 示例：http://your-domain.com/api/notify.php
        'notify_url' => 'http://YOUR_DOMAIN/api/notify.php',

        // 同步返回地址
        // 用户支付完成后跳转的页面
        // 示例：http://your-domain.com/success.html
        'return_url' => 'http://YOUR_DOMAIN/success.html',
    ],

    // 打赏配置
    'reward' => [
        // 网站标题
        'title' => '支持作者',

        // 描述信息
        'description' => '感谢您的支持！',

        // 最小打赏金额（元）
        'min_amount' => 0.01,

        // 最大打赏金额（元）
        'max_amount' => 9999.99,

        // 预设金额选项（元）
        // 这些金额会在页面上显示为快捷按钮
        'preset_amounts' => [1, 5, 10, 20, 50, 100],
    ],

    // 数据库配置（可选）
    // 如果您需要将打赏记录保存到数据库，请配置此项
    'database' => [
        // 是否启用数据库
        'enabled' => false,

        // 数据库主机
        'host' => 'localhost',

        // 数据库端口
        'port' => 3306,

        // 数据库名
        'database' => 'reward',

        // 数据库用户名
        'username' => 'root',

        // 数据库密码
        'password' => '',

        // 字符集
        'charset' => 'utf8mb4',
    ],

    // Notion 集成配置（可选）
    // 支付成功后自动创建 Notion 数据库记录
    'notion' => [
        // 是否启用 Notion 集成
        'enabled' => false,

        // Notion API 密钥
        // 获取方式：https://www.notion.so/my-integrations
        // 创建 Internal Integration，复制 API Key
        'api_key' => '',

        // Notion 数据库 ID
        // 获取方式：打开 Notion 数据库，点击右上角"..."-> "Copy link"
        // 链接格式：https://www.notion.so/{workspace}/{database_id}?v=...
        // 提取其中的 database_id（32位字符串）
        'database_id' => '',

        // Notion API 版本
        'api_version' => '2022-06-28',

        // 数据库属性映射（根据你的 Notion 数据库结构配置）
        // 键名必须与 Notion 数据库中的属性名完全一致
        'properties' => [
            'title_field' => '订单号',      // Notion 中的标题字段名
            'amount_field' => '金额',        // 金额字段名（Number类型）
            'message_field' => '留言',       // 留言字段名（Rich Text类型）
            'status_field' => '状态',        // 状态字段名（Select类型）
            'pay_time_field' => '支付时间',  // 支付时间字段名（Date类型）
        ],
    ],

    // Webhook 集成配置（可选）
    // 支付成功后发送 POST 请求到指定 URL
    'webhook' => [
        // 是否启用 Webhook
        'enabled' => false,

        // Webhook URL（接收支付通知的完整 URL）
        'url' => '',

        // 请求方法（POST 或 GET）
        'method' => 'POST',

        // 自定义请求头（可选）
        'headers' => [
            'Content-Type' => 'application/json',
            // 可以添加自定义认证头
            // 'Authorization' => 'Bearer YOUR_TOKEN',
            // 'X-Custom-Header' => 'value',
        ],

        // 请求超时时间（秒）
        'timeout' => 10,

        // 失败重试次数
        'retry' => 3,
    ],
];
