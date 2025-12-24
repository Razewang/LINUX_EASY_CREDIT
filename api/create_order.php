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

// 加载配置
$config = require __DIR__ . '/../config/config.php';
$helper = new EpayHelper($config['epay']);

try {
    // 获取请求参数
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // 验证必填参数
    $amount = isset($input['amount']) ? floatval($input['amount']) : 0;
    $message = isset($input['message']) ? trim($input['message']) : '';

    // 验证金额
    if ($amount < $config['reward']['min_amount']) {
        $helper->jsonResponse(400, '打赏金额不能小于 ' . $config['reward']['min_amount'] . ' 元');
    }

    if ($amount > $config['reward']['max_amount']) {
        $helper->jsonResponse(400, '打赏金额不能大于 ' . $config['reward']['max_amount'] . ' 元');
    }

    // 金额小数位数检查
    if (strpos(strval($amount), '.') !== false) {
        $decimals = strlen(substr(strrchr(strval($amount), '.'), 1));
        if ($decimals > 2) {
            $helper->jsonResponse(400, '金额小数位数不能超过2位');
        }
    }

    // 生成订单号
    $outTradeNo = $helper->generateOrderNo();

    // 构建支付参数
    $payParams = [
        'pid' => $config['epay']['pid'],
        'type' => 'epay',
        'out_trade_no' => $outTradeNo,
        'name' => '打赏支持' . ($message ? '：' . mb_substr($message, 0, 20) : ''),
        'money' => $amount,
        'notify_url' => $config['epay']['notify_url'],
        'return_url' => $config['epay']['return_url'],
    ];

    // 生成签名
    $payParams['sign'] = $helper->createSign($payParams);
    $payParams['sign_type'] = 'MD5';

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
    $orderDir = dirname($orderFile);
    if (!is_dir($orderDir)) {
        mkdir($orderDir, 0755, true);
    }
    file_put_contents($orderFile, json_encode($orderData, JSON_UNESCAPED_UNICODE));

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
