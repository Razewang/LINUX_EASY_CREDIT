<?php
/**
 * 签名测试脚本
 * 用于验证签名生成是否正确
 */

require_once __DIR__ . '/api/EpayHelper.php';

// 加载配置
$config = require __DIR__ . '/config/config.php';
$helper = new EpayHelper($config['epay']);

echo "==========================================\n";
echo "签名测试工具\n";
echo "==========================================\n\n";

// 测试参数
$testParams = [
    'pid' => $config['epay']['pid'],
    'type' => 'epay',
    'out_trade_no' => 'TEST' . date('YmdHis'),
    'name' => '测试订单',
    'money' => 0.01,
    'notify_url' => $config['epay']['notify_url'],
    'return_url' => $config['epay']['return_url'],
];

echo "1. 配置信息\n";
echo "-------------------------------------------\n";
echo "PID: {$config['epay']['pid']}\n";
echo "Key: " . substr($config['epay']['key'], 0, 10) . "..." . substr($config['epay']['key'], -10) . "\n";
echo "Gateway: {$config['epay']['gateway']}\n";
echo "Notify URL: {$config['epay']['notify_url']}\n";
echo "Return URL: {$config['epay']['return_url']}\n\n";

echo "2. 测试参数\n";
echo "-------------------------------------------\n";
foreach ($testParams as $key => $value) {
    echo "{$key} = {$value}\n";
}
echo "\n";

// 生成签名
$sign = $helper->createSign($testParams);

echo "3. 签名过程\n";
echo "-------------------------------------------\n";

// 重现签名过程
$signParams = array_filter($testParams, function ($value) {
    return $value !== '' && $value !== null;
});
ksort($signParams);

$signString = '';
foreach ($signParams as $key => $value) {
    $signString .= $key . '=' . $value . '&';
}
$signString = rtrim($signString, '&');

echo "待签名字符串：\n{$signString}\n\n";
echo "追加密钥后：\n{$signString}{$config['epay']['key']}\n\n";
echo "MD5签名：{$sign}\n\n";

echo "4. 完整请求参数\n";
echo "-------------------------------------------\n";
$testParams['sign'] = $sign;
$testParams['sign_type'] = 'MD5';

foreach ($testParams as $key => $value) {
    echo "{$key} = {$value}\n";
}

echo "\n5. 完整POST URL\n";
echo "-------------------------------------------\n";
echo $config['epay']['gateway'] . '/pay/submit.php' . '?' . http_build_query($testParams) . "\n\n";

echo "==========================================\n";
echo "测试完成\n";
echo "==========================================\n";
