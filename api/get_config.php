<?php
/**
 * 获取公开配置接口
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 加载配置
$config_path = __DIR__ . '/../config/config.php';
$example_path = __DIR__ . '/../config/config.example.php';

if (file_exists($config_path)) {
    $config = require $config_path;
} else {
    $config = require $example_path;
}

$public_config = [
    'title' => $config['reward']['title'] ?? '打赏支持',
    'description' => $config['reward']['description'] ?? '感谢您的支持，每一份打赏都是对创作的鼓励',
    'min_amount' => $config['reward']['min_amount'] ?? 0.01,
    'max_amount' => $config['reward']['max_amount'] ?? 9999.99,
    'preset_amounts' => $config['reward']['preset_amounts'] ?? [1, 5, 10, 20, 50, 100],
];

echo json_encode([
    'code' => 200,
    'data' => $public_config
], JSON_UNESCAPED_UNICODE);
