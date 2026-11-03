<?php
/**
 * 创建支付订单接口
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/EpayHelper.php';
require_once __DIR__ . '/InputGuard.php';

// 加载配置
$config = require __DIR__ . '/../config/config.php';
$helper = new EpayHelper($config['epay']);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $helper->jsonResponse(405, '请求方法不支持');
    }

    // 简单的按 IP 限速（Docker 部署另有 Nginx limit_req）
    $rateLimit = $config['rate_limit'] ?? [];
    if (($rateLimit['enabled'] ?? true)
        && !lec_rate_limit_allow(
            __DIR__ . '/../logs/ratelimit',
            'create:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            (int) ($rateLimit['create_per_minute'] ?? 10),
            60
        )) {
        header('Retry-After: 60');
        $helper->jsonResponse(429, '请求过于频繁，请稍后重试');
    }

    // 获取请求参数：有请求体时必须是合法 JSON 对象，否则回退到表单字段
    $rawBody = file_get_contents('php://input');
    if (is_string($rawBody) && trim($rawBody) !== '' && $_POST === []) {
        $input = json_decode($rawBody, true);
        if (!is_array($input)) {
            $helper->jsonResponse(400, '请求格式不正确');
        }
    } else {
        $input = $_POST;
    }

    $validated = lec_validate_create_input($input, $config['reward']);
    if ($validated['error'] !== null) {
        $helper->jsonResponse(400, $validated['error']);
    }
    $amount = (float) $validated['amount'];
    $message = $validated['message'];

    // 生成订单号
    $outTradeNo = $helper->generateOrderNo();

    // 构建支付参数
    // 注意：根据官方文档，notify_url 和 return_url 不参与请求
    // 这些 URL 在控制台配置，不需要在请求中传递
    // 重要：money 必须格式化为固定两位小数的字符串，否则签名会失败
    $payParams = [
        'pid' => $config['epay']['pid'],
        'type' => 'epay',
        'out_trade_no' => $outTradeNo,
        'name' => '打赏支持' . ($message ? '：' . mb_substr($message, 0, 20) : ''),
        'money' => $validated['amount'],  // 已格式化为两位小数: 3 → "3.00"
        // notify_url 和 return_url 已在控制台配置，不在此传递
    ];

    // 生成签名
    $payParams['sign'] = $helper->createSign($payParams);
    $payParams['sign_type'] = 'MD5';

    // 调试日志：输出签名信息
    $helper->log("签名调试 - 订单号: {$outTradeNo}");
    $helper->log("签名调试 - 参数: " . json_encode($payParams, JSON_UNESCAPED_UNICODE));

    // 重新生成签名字符串用于日志（不包含sign和sign_type）
    $signParams = $payParams;
    unset($signParams['sign']);
    unset($signParams['sign_type']);
    ksort($signParams);
    $signString = '';
    foreach ($signParams as $k => $v) {
        $signString .= $k . '=' . $v . '&';
    }
    $signString = rtrim($signString, '&');
    $helper->log("签名调试 - 待签名字符串: {$signString}[KEY_HIDDEN]");
    $helper->log("签名调试 - 生成的签名: {$payParams['sign']}");

    // 构建支付URL
    $payUrl = $config['epay']['gateway'] . '/pay/submit.php';

    // 记录日志
    $helper->log("创建订单: {$outTradeNo}, 金额: {$amount}, 留言: {$message}");

    // 保存订单信息到临时文件（用于后续查询）
    $orderData = [
        'out_trade_no' => $outTradeNo,
        'amount' => $amount,
        'message' => $message,
        'create_time' => date('Y-m-d H:i:s'),
        'status' => 0, // 0-未支付
    ];

    $orderFile = __DIR__ . '/../logs/orders/' . $outTradeNo . '.json';
    $helper->saveOrder($orderFile, $orderData);

    // 返回支付URL和订单信息
    $helper->jsonResponse(200, '订单创建成功', [
        'order_no' => $outTradeNo,
        'amount' => $amount,
        'pay_url' => $payUrl,
        'pay_params' => $payParams,
        'redirect_url' => $payUrl . '?' . http_build_query($payParams)
    ]);

} catch (Exception $e) {
    $helper->log("创建订单失败: " . $e->getMessage(), 'error');
    $helper->jsonResponse(500, '系统错误：' . $e->getMessage());
}
