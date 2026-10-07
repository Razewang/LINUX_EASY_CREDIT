<?php
/**
 * 创建订单的输入校验与简单的按 IP 文件限速（与 Node 端 api/vercel/create-order.js 对齐）
 */

const LEC_MESSAGE_MAX_LENGTH = 200;

/**
 * 校验创建订单的输入。
 * @return array ['error' => string|null, 'amount' => string|null（两位小数）, 'message' => string]
 */
function lec_validate_create_input($input, array $reward)
{
    $fail = function ($error) {
        return ['error' => $error, 'amount' => null, 'message' => ''];
    };

    if (!is_array($input)) {
        return $fail('请求格式不正确');
    }

    $rawAmount = $input['amount'] ?? null;
    if (is_int($rawAmount) || is_float($rawAmount)) {
        $rawAmount = is_finite((float) $rawAmount) ? (string) $rawAmount : '';
    }
    if (!is_string($rawAmount) || !preg_match('/^\d+(?:\.\d{1,2})?$/', trim($rawAmount))) {
        return $fail('金额格式不正确，最多支持两位小数');
    }
    $amount = number_format((float) trim($rawAmount), 2, '.', '');
    $numericAmount = (float) $amount;

    if ($numericAmount < $reward['min_amount']) {
        return $fail('打赏积分不能小于 ' . $reward['min_amount'] . ' LDC');
    }
    if ($numericAmount > $reward['max_amount']) {
        return $fail('打赏积分不能大于 ' . $reward['max_amount'] . ' LDC');
    }

    if (array_key_exists('message', $input) && $input['message'] !== null && !is_string($input['message'])) {
        return $fail('留言必须是字符串');
    }
    $message = trim((string) ($input['message'] ?? ''));
    // 按 Unicode 码点计数，等价于 mb_strlen($message, 'UTF-8')，与 Node 端 [...message].length 一致；
    // 用 PCRE 实现以免依赖 mbstring。非法 UTF-8 时 preg_match_all 返回 false。
    $length = preg_match_all('/./us', $message);
    if ($length === false) {
        return $fail('留言必须是字符串');
    }
    if ($length > LEC_MESSAGE_MAX_LENGTH) {
        return $fail('留言不能超过 ' . LEC_MESSAGE_MAX_LENGTH . ' 个字符');
    }

    return ['error' => null, 'amount' => $amount, 'message' => $message];
}

/**
 * 固定窗口限速：每个 key 在 $window 秒内最多 $limit 次。
 * 状态文件放在 $dir 下，过期文件会被顺带清理（也由 scripts/cleanup_orders.php 清理）。
 * 存储异常时放行，避免限速故障导致整站不可用。
 */
function lec_rate_limit_allow($dir, $key, $limit, $window, $now = null)
{
    $now = $now ?? time();
    if ($limit <= 0) {
        return true;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return true;
    }

    $file = $dir . '/' . hash('sha256', (string) $key) . '.json';
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return true;
    }

    try {
        flock($handle, LOCK_EX);
        $state = json_decode(stream_get_contents($handle), true);
        if (!is_array($state) || !isset($state['start'], $state['count']) || $now - $state['start'] >= $window) {
            $state = ['start' => $now, 'count' => 0];
        }
        $allowed = $state['count'] < $limit;
        if ($allowed) {
            $state['count']++;
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state));
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    // 约 1% 的请求顺带清理过期限速文件
    if (random_int(1, 100) === 1) {
        lec_rate_limit_cleanup($dir, $window, $now);
    }

    return $allowed;
}

/**
 * 删除超过 $window 秒未更新的限速文件，返回删除数量。
 */
function lec_rate_limit_cleanup($dir, $window, $now = null)
{
    $now = $now ?? time();
    $removed = 0;
    foreach (glob($dir . '/*.json') ?: [] as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && $now - $mtime >= $window && @unlink($file)) {
            $removed++;
        }
    }
    return $removed;
}
