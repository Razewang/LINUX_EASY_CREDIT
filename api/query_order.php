<?php
/**
 * 查询订单状态接口
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    // 获取订单号
    $orderNo = isset($_GET['order_no']) ? trim($_GET['order_no']) : '';

    if (empty($orderNo)) {
        $helper->jsonResponse(400, '订单号不能为空');
    }

    // 安全验证：订单号只允许字母数字，防止路径遍历攻击
    if (!preg_match('/^[A-Za-z0-9]+$/', $orderNo)) {
        $helper->jsonResponse(400, '订单号格式不正确');
    }

    // 读取本地订单信息
    $orderFile = __DIR__ . '/../logs/orders/' . $orderNo . '.json';

    if (!file_exists($orderFile)) {
        $helper->jsonResponse(404, '订单不存在');
    }

    $orderData = json_decode(file_get_contents($orderFile), true);

    // 支付状态以已验签的异步回调为准。不要调用易支付 GET 查询接口：
    // 该兼容接口要求将 Client Secret 作为 URL 查询参数传递，容易进入代理或访问日志。
    $helper->jsonResponse(200, '查询成功', [
        'order_no' => $orderData['out_trade_no'],
        'amount' => $orderData['amount'],
        'message' => $orderData['message'],
        'status' => $orderData['status'],
        'status_text' => $orderData['status'] == 1 ? '已支付' : '未支付',
        'pay_time' => isset($orderData['pay_time']) ? $orderData['pay_time'] : null
    ]);

} catch (Exception $e) {
    $helper->log("查询订单失败: " . $e->getMessage(), 'error');
    $helper->jsonResponse(500, '系统错误：' . $e->getMessage());
}
