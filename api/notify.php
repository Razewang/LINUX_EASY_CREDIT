<?php
/**
 * Linux.do Credit 异步回调接口
 */

require_once __DIR__ . '/EpayHelper.php';

// 加载配置
$config = require __DIR__ . '/../config/config.php';
$helper = new EpayHelper($config['epay']);

try {
    // 获取回调参数
    $params = $_GET;

    $helper->log("收到回调: " . json_encode($params, JSON_UNESCAPED_UNICODE));

    // 提取签名
    $receiveSign = isset($params['sign']) ? $params['sign'] : '';

    if (empty($receiveSign)) {
        $helper->log("回调失败: 签名为空", 'error');
        echo 'fail';
        exit;
    }

    // 验证签名
    $sign = $params['sign'];
    unset($params['sign']);
    unset($params['sign_type']);

    $localSign = $helper->createSign($params);

    if ($localSign !== $receiveSign) {
        $helper->log("回调失败: 签名验证失败", 'error');
        echo 'fail';
        exit;
    }

    // 验证签名通过，处理业务逻辑
    $outTradeNo = isset($params['out_trade_no']) ? $params['out_trade_no'] : '';
    $tradeNo = isset($params['trade_no']) ? $params['trade_no'] : '';
    $money = isset($params['money']) ? $params['money'] : 0;
    $tradeStatus = isset($params['trade_status']) ? $params['trade_status'] : '';

    if (empty($outTradeNo)) {
        $helper->log("回调失败: 订单号为空", 'error');
        echo 'fail';
        exit;
    }

    // 读取订单信息
    $orderFile = __DIR__ . '/../logs/orders/' . $outTradeNo . '.json';

    if (!file_exists($orderFile)) {
        $helper->log("回调失败: 订单不存在 - {$outTradeNo}", 'error');
        echo 'fail';
        exit;
    }

    $orderData = json_decode(file_get_contents($orderFile), true);

    // 检查订单是否已处理
    if ($orderData['status'] == 1) {
        $helper->log("订单已处理: {$outTradeNo}");
        echo 'success';
        exit;
    }

    // 验证金额
    if (floatval($money) != floatval($orderData['amount'])) {
        $helper->log("回调失败: 金额不匹配 - 期望: {$orderData['amount']}, 实际: {$money}", 'error');
        echo 'fail';
        exit;
    }

    // 检查交易状态
    if ($tradeStatus === 'TRADE_SUCCESS') {
        // 更新订单状态
        $orderData['status'] = 1;
        $orderData['trade_no'] = $tradeNo;
        $orderData['pay_time'] = date('Y-m-d H:i:s');
        file_put_contents($orderFile, json_encode($orderData, JSON_UNESCAPED_UNICODE));

        $helper->log("订单支付成功: {$outTradeNo}, 金额: {$money}");

        // 这里可以添加额外的业务逻辑
        // 例如：发送邮件通知、更新数据库等

        echo 'success';
    } else {
        $helper->log("回调失败: 交易状态异常 - {$tradeStatus}", 'error');
        echo 'fail';
    }

} catch (Exception $e) {
    $helper->log("回调处理异常: " . $e->getMessage(), 'error');
    echo 'fail';
}
