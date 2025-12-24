<?php
/**
 * Linux.do Credit 支付配置文件模板
 *
 * 使用说明：
 * 1. 复制本文件并重命名为 config.php
 * 2. 填写您的实际配置信息
 * 3. 确保 config.php 已在 .gitignore 中排除，不要提交到 Git
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
];
