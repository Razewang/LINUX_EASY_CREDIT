<?php
/**
 * 清理未支付且超过指定时长的订单文件，以及过期的限速状态文件。
 * 已支付（status == 1）的订单永不删除；无法解析的订单文件会被跳过。
 *
 * 用法（仅 CLI）：
 *   php scripts/cleanup_orders.php [--max-age-hours=24] [--dir=/path/to/logs] [--dry-run]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../api/InputGuard.php';

/**
 * @return array ['deleted' => string[], 'kept' => int]
 */
function lec_cleanup_orders($ordersDir, $maxAgeSeconds, $now = null, $dryRun = false)
{
    $now = $now ?? time();
    $deleted = [];
    $kept = 0;

    foreach (glob(rtrim($ordersDir, '/') . '/*.json') ?: [] as $file) {
        $order = json_decode((string) @file_get_contents($file), true);
        if (!is_array($order) || (int) ($order['status'] ?? 0) === 1) {
            $kept++;
            continue;
        }

        $created = isset($order['create_time']) ? strtotime((string) $order['create_time']) : false;
        if ($created === false) {
            $created = @filemtime($file);
        }
        if ($created === false || $now - $created < $maxAgeSeconds) {
            $kept++;
            continue;
        }

        if ($dryRun || @unlink($file)) {
            $deleted[] = basename($file);
        } else {
            $kept++;
        }
    }

    return ['deleted' => $deleted, 'kept' => $kept];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $options = getopt('', ['max-age-hours::', 'dir::', 'dry-run']);
    $logsDir = rtrim($options['dir'] ?? (__DIR__ . '/../logs'), '/');
    $hours = isset($options['max-age-hours']) ? (float) $options['max-age-hours'] : 24.0;
    if ($hours <= 0) {
        fwrite(STDERR, "--max-age-hours must be positive\n");
        exit(2);
    }
    $dryRun = array_key_exists('dry-run', $options);

    $result = lec_cleanup_orders($logsDir . '/orders', (int) ($hours * 3600), null, $dryRun);
    $rateRemoved = $dryRun ? 0 : lec_rate_limit_cleanup($logsDir . '/ratelimit', 3600);

    printf(
        "[%s] %s %d expired unpaid order(s), kept %d, removed %d rate-limit file(s)\n",
        date('Y-m-d H:i:s'),
        $dryRun ? 'would delete' : 'deleted',
        count($result['deleted']),
        $result['kept'],
        $rateRemoved
    );
}
