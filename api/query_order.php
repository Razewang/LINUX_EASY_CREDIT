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

    // 读取本地订单信息
    $orderFile = __DIR__ . '/../logs/orders/' . $orderNo . '.json';

    if (!file_exists($orderFile)) {
        $helper->jsonResponse(404, '订单不存在');
    }

    $orderData = json_decode(file_get_contents($orderFile), true);

    // 如果订单已经是已支付状态，直接返回
    if ($orderData['status'] == 1) {
        $helper->jsonResponse(200, '查询成功', [
            'order_no' => $orderData['out_trade_no'],
            'amount' => $orderData['amount'],
            'message' => $orderData['message'],
            'status' => 1,
            'status_text' => '已支付',
            'pay_time' => isset($orderData['pay_time']) ? $orderData['pay_time'] : null
        ]);
    }

    // 如果订单未支付，查询 Linux.do Credit 的订单状态
    $queryUrl = $config['epay']['gateway'] . '/api.php?' . http_build_query([
        'act' => 'order',
        'pid' => $config['epay']['pid'],
        'key' => $config['epay']['key'],
        'out_trade_no' => $orderNo
    ]);

    $result = $helper->httpGet($queryUrl);
    $helper->log("查询订单 {$orderNo}: HTTP {$result['code']}, 响应: {$result['response']}");

    if ($result['code'] == 200) {
        $response = json_decode($result['response'], true);

        if ($response && isset($response['code'])) {
            if ($response['code'] == 1 && $response['status'] == 1) {
                // 订单已支付，更新本地状态
                $orderData['status'] = 1;
                $orderData['pay_time'] = date('Y-m-d H:i:s');
                $orderData['trade_no'] = isset($response['trade_no']) ? $response['trade_no'] : '';
                file_put_contents($orderFile, json_encode($orderData, JSON_UNESCAPED_UNICODE));

                $helper->jsonResponse(200, '查询成功', [
                    'order_no' => $orderData['out_trade_no'],
                    'amount' => $orderData['amount'],
                    'message' => $orderData['message'],
                    'status' => 1,
                    'status_text' => '已支付',
                    'pay_time' => $orderData['pay_time']
                ]);
            } else {
                // 订单未支付
                $helper->jsonResponse(200, '查询成功', [
                    'order_no' => $orderData['out_trade_no'],
                    'amount' => $orderData['amount'],
                    'message' => $orderData['message'],
                    'status' => 0,
                    'status_text' => '未支付',
                    'pay_time' => null
                ]);
            }
        }
    }

    // 查询失败，返回本地状态
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
