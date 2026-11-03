<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../api/InputGuard.php';
require_once __DIR__ . '/../../scripts/cleanup_orders.php';

function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function check($name, callable $callback)
{
    $callback();
    echo "PASS: {$name}\n";
}

function removeTree($dir)
{
    $resolved = realpath($dir);
    expect(
        $resolved !== false
            && dirname($resolved) === realpath(sys_get_temp_dir())
            && str_starts_with(basename($resolved), 'lec-guard-test-'),
        'Refusing to clean a directory outside this test fixture'
    );
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($resolved);
}

/** 用 PHP 内置服务器（加载 mbstring 等扩展）跑真实的 create_order.php，覆盖 php://input、状态码与限速。 */
function startServer($docroot)
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $process = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $docroot],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes
    );
    for ($i = 0; $i < 50; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($probe) {
            fclose($probe);
            return [$process, $port];
        }
        usleep(100000);
    }
    proc_terminate($process);
    throw new RuntimeException('PHP built-in server did not start');
}

function postJson($port, $body)
{
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $body,
        'ignore_errors' => true,
        'timeout' => 5,
    ]]);
    $response = file_get_contents("http://127.0.0.1:{$port}/api/create_order.php", false, $context);
    preg_match('#^HTTP/\S+\s+(\d+)#', $http_response_header[0] ?? '', $match);
    return ['status' => (int) ($match[1] ?? 0), 'body' => json_decode((string) $response, true)];
}

$root = sys_get_temp_dir() . '/lec-guard-test-' . bin2hex(random_bytes(8));
mkdir($root, 0755, true);
$reward = ['min_amount' => 0.01, 'max_amount' => 9999.99];
$exitCode = 0;
$server = null;

try {
    check('validation accepts numeric and string amounts with up to two decimals', function () use ($reward) {
        foreach ([[10, '10.00'], [1.5, '1.50'], ['0.01', '0.01'], [' 3.20 ', '3.20']] as [$in, $out]) {
            $result = lec_validate_create_input(['amount' => $in, 'message' => ' hi '], $reward);
            expect($result['error'] === null && $result['amount'] === $out, 'Rejected valid amount ' . var_export($in, true));
            expect($result['message'] === 'hi', 'Message was not trimmed');
        }
        expect(lec_validate_create_input(['amount' => '5'], $reward)['message'] === '', 'Missing message should be empty');
    });

    check('validation rejects malformed amounts and non-string fields', function () use ($reward) {
        $amountError = '金额格式不正确，最多支持两位小数';
        foreach (['1.234', '-1', '1e2', 'abc', '', ['1'], true, null, 1.005, INF] as $bad) {
            $result = lec_validate_create_input(['amount' => $bad], $reward);
            expect($result['error'] === $amountError, 'Accepted amount ' . var_export($bad, true));
        }
        expect(lec_validate_create_input([], $reward)['error'] === $amountError, 'Missing amount accepted');
        expect(lec_validate_create_input('x', $reward)['error'] === '请求格式不正确', 'Non-array input accepted');
        foreach ([['a'], 123, false, ['k' => 'v']] as $bad) {
            $result = lec_validate_create_input(['amount' => '1', 'message' => $bad], $reward);
            expect($result['error'] === '留言必须是字符串', 'Accepted message ' . var_export($bad, true));
        }
        $result = lec_validate_create_input(['amount' => '1', 'message' => "\xB1\x31"], $reward);
        expect($result['error'] === '留言必须是字符串', 'Accepted invalid UTF-8 message');
    });

    check('validation keeps min/max limits and the 200 character message limit', function () use ($reward) {
        expect(lec_validate_create_input(['amount' => '0.00'], $reward)['error'] === '打赏积分不能小于 0.01 LDC', 'Below min accepted');
        expect(lec_validate_create_input(['amount' => '10000'], $reward)['error'] === '打赏积分不能大于 9999.99 LDC', 'Above max accepted');
        expect(lec_validate_create_input(['amount' => '9999.99'], $reward)['error'] === null, 'Max rejected');
        $ok = str_repeat('积', 200);
        expect(lec_validate_create_input(['amount' => '1', 'message' => $ok], $reward)['error'] === null, '200 chars rejected');
        $long = lec_validate_create_input(['amount' => '1', 'message' => $ok . 'a'], $reward);
        expect($long['error'] === '留言不能超过 200 个字符', '201 chars accepted');
    });

    check('rate limiter allows the limit per window, then resets and cleans up', function () use ($root) {
        $dir = $root . '/ratelimit';
        $now = 1_000_000;
        for ($i = 0; $i < 3; $i++) {
            expect(lec_rate_limit_allow($dir, 'ip-a', 3, 60, $now), 'Request within limit was blocked');
        }
        expect(!lec_rate_limit_allow($dir, 'ip-a', 3, 60, $now + 10), 'Request over limit was allowed');
        expect(lec_rate_limit_allow($dir, 'ip-b', 3, 60, $now + 10), 'Limit leaked across keys');
        expect(lec_rate_limit_allow($dir, 'ip-a', 3, 60, $now + 60), 'Window did not reset');
        expect(count(glob($dir . '/*.json')) === 2, 'Unexpected rate-limit files');
        foreach (glob($dir . '/*.json') as $file) {
            touch($file, time() - 7200);
        }
        expect(lec_rate_limit_cleanup($dir, 3600) === 2 && glob($dir . '/*.json') === [], 'Stale rate-limit files kept');
    });

    check('cleanup deletes only unpaid orders older than the max age', function () use ($root) {
        $dir = $root . '/logs/orders';
        mkdir($dir, 0755, true);
        $now = time();
        $orders = [
            'RWOLDUNPAID' => ['status' => 0, 'create_time' => date('Y-m-d H:i:s', $now - 25 * 3600)],
            'RWNEWUNPAID' => ['status' => 0, 'create_time' => date('Y-m-d H:i:s', $now - 3600)],
            'RWOLDPAID' => ['status' => 1, 'create_time' => date('Y-m-d H:i:s', $now - 72 * 3600)],
            'RWOLDNOTIME' => ['status' => 0],
        ];
        foreach ($orders as $no => $order) {
            file_put_contents("{$dir}/{$no}.json", json_encode(['out_trade_no' => $no] + $order));
        }
        touch("{$dir}/RWOLDNOTIME.json", $now - 30 * 3600);
        file_put_contents("{$dir}/RWBROKEN.json", '{not json');
        touch("{$dir}/RWBROKEN.json", $now - 30 * 3600);

        $dry = lec_cleanup_orders($dir, 24 * 3600, $now, true);
        expect(count($dry['deleted']) === 2 && count(glob("{$dir}/*.json")) === 5, 'Dry run changed files');

        $result = lec_cleanup_orders($dir, 24 * 3600, $now);
        sort($result['deleted']);
        expect($result['deleted'] === ['RWOLDNOTIME.json', 'RWOLDUNPAID.json'], 'Wrong orders deleted: ' . json_encode($result));
        foreach (['RWNEWUNPAID', 'RWOLDPAID', 'RWBROKEN'] as $no) {
            expect(is_file("{$dir}/{$no}.json"), "{$no} should be kept");
        }
    });

    check('cleanup script runs from the CLI', function () use ($root) {
        $dir = $root . '/cli-logs';
        mkdir($dir . '/orders', 0755, true);
        file_put_contents("{$dir}/orders/RWCLIOLD.json", json_encode(['status' => 0, 'create_time' => '2000-01-01 00:00:00']));
        file_put_contents("{$dir}/orders/RWCLIPAID.json", json_encode(['status' => 1, 'create_time' => '2000-01-01 00:00:00']));
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg(__DIR__ . '/../../scripts/cleanup_orders.php')
            . ' --dir=' . escapeshellarg($dir) . ' 2>&1', $output, $code);
        expect($code === 0, 'CLI cleanup failed: ' . implode("\n", $output));
        expect(str_contains(implode("\n", $output), 'deleted 1 expired unpaid order(s)'), 'Unexpected CLI output: ' . implode("\n", $output));
        expect(!is_file("{$dir}/orders/RWCLIOLD.json") && is_file("{$dir}/orders/RWCLIPAID.json"), 'CLI cleanup removed the wrong files');
    });

    check('create_order endpoint returns 400 for bad bodies and 429 when rate limited', function () use ($root, &$server) {
        $site = $root . '/site';
        mkdir($site . '/api', 0755, true);
        mkdir($site . '/config');
        mkdir($site . '/logs/orders', 0755, true);
        foreach (['create_order.php', 'EpayHelper.php', 'InputGuard.php'] as $file) {
            copy(__DIR__ . '/../../api/' . $file, $site . '/api/' . $file);
        }
        $config = [
            'epay' => ['pid' => 'test-pid', 'key' => 'test-secret', 'gateway' => 'https://example.invalid/epay'],
            'reward' => ['min_amount' => 0.01, 'max_amount' => 9999.99],
            'rate_limit' => ['create_per_minute' => 5],
        ];
        file_put_contents($site . '/config/config.php', '<?php return ' . var_export($config, true) . ';');
        [$server, $port] = startServer($site);

        $cases = [
            ['{not json', '请求格式不正确'],
            ['"just a string"', '请求格式不正确'],
            ['{"amount":"1","message":["a"]}', '留言必须是字符串'],
            ['{"amount":"1.234"}', '金额格式不正确，最多支持两位小数'],
        ];
        foreach ($cases as [$body, $message]) {
            $result = postJson($port, $body);
            expect($result['status'] === 400 && $result['body']['message'] === $message, "Unexpected response for {$body}: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        $ok = postJson($port, '{"amount":2,"message":"ok"}');
        expect($ok['status'] === 200 && $ok['body']['data']['pay_params']['money'] === '2.00', 'Valid order failed: ' . json_encode($ok, JSON_UNESCAPED_UNICODE));
        expect(count(glob($site . '/logs/orders/*.json')) === 1, 'Rejected requests created orders');

        $limited = postJson($port, '{"amount":2}');
        expect($limited['status'] === 429 && $limited['body']['code'] === 429, 'Sixth request was not rate limited: ' . json_encode($limited, JSON_UNESCAPED_UNICODE));
    });
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    removeTree($root);
}

exit($exitCode);
