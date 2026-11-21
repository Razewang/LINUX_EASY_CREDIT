<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../api/EpayHelper.php';

function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectException($class, callable $callback)
{
    try {
        $callback();
    } catch (Throwable $error) {
        expect($error instanceof $class, 'Unexpected exception: ' . get_class($error));
        return;
    }
    throw new RuntimeException('Expected ' . $class);
}

function check($name, callable $callback)
{
    $callback();
    echo "PASS: {$name}\n";
}

// A local stream wrapper makes disk failures deterministic on Windows and Linux.
class OrderFailureStream
{
    public $context;
    public static $files = [];
    public static $failOpen = false;
    public static $writeLimit = PHP_INT_MAX;
    public static $failRename = false;
    private $path;
    private $position = 0;

    public function stream_open($path, $mode, $options, &$openedPath)
    {
        if (self::$failOpen) {
            return false;
        }
        $this->path = $path;
        self::$files[$path] = '';
        return true;
    }

    public function stream_write($data)
    {
        $length = min(strlen($data), max(0, self::$writeLimit - $this->position));
        self::$files[$this->path] .= substr($data, 0, $length);
        $this->position += $length;
        return $length;
    }

    public function url_stat($path, $flags)
    {
        if ($path === 'orderfailure://orders') {
            return ['mode' => 0040777, 'size' => 0];
        }
        if (array_key_exists($path, self::$files)) {
            return ['mode' => 0100666, 'size' => strlen(self::$files[$path])];
        }
        return false;
    }

    public function unlink($path)
    {
        unset(self::$files[$path]);
        return true;
    }

    public function rename($from, $to)
    {
        if (self::$failRename) {
            return false;
        }
        self::$files[$to] = self::$files[$from];
        unset(self::$files[$from]);
        return true;
    }
}

function createEndpointFixture($root, $name, $endpoint, $failSave)
{
    $dir = $root . '/' . $name;
    mkdir($dir . '/api', 0755, true);
    mkdir($dir . '/config');
    mkdir($dir . '/logs/orders', 0755, true);
    copy(__DIR__ . '/../../api/' . $endpoint . '.php', $dir . '/api/' . $endpoint . '.php');
    copy(__DIR__ . '/../../api/EpayHelper.php', $dir . '/api/EpayHelper.php');
    copy(__DIR__ . '/../../api/InputGuard.php', $dir . '/api/InputGuard.php');
    copy(__DIR__ . '/../../api/OrderLock.php', $dir . '/api/OrderLock.php');

    if ($failSave) {
        // Exercise the unchanged endpoint with a helper that rejects storage.
        // The real helper's failed and partial writes are tested separately.
        file_put_contents($dir . '/api/EpayHelper.php', <<<'PHP'
<?php
class EpayHelper
{
    public function __construct($config) {}
    public function verifySign($params, $sign) { return true; }
    public function generateOrderNo() { return 'RWTESTCREATE'; }
    public function createSign($params) { return str_repeat('a', 32); }
    public function log($message, $type = 'info') {}
    public function saveOrder($file, array $order)
    {
        throw new RuntimeException('Simulated storage failure');
    }
    public function jsonResponse($code, $message, $data = null)
    {
        http_response_code($code);
        echo json_encode(['code' => $code, 'message' => $message, 'data' => $data]);
        exit;
    }
}
PHP
        );
    }

    file_put_contents($dir . '/api/IntegrationHelper.php', <<<'PHP'
<?php
class IntegrationHelper
{
    public function __construct($config, $helper) {}
    public function sendToIntegrations($order)
    {
        $file = __DIR__ . '/../logs/orders/' . $order['out_trade_no'] . '.json';
        $saved = json_decode(file_get_contents($file), true);
        if ($saved['status'] !== 1) {
            throw new RuntimeException('Integration called before persistence');
        }
        file_put_contents(__DIR__ . '/../integration-calls', 'called', FILE_APPEND);
        return ['notion' => ['enabled' => false], 'webhook' => ['enabled' => false]];
    }
}
PHP
    );

    $config = [
        'epay' => ['pid' => 'test-pid', 'key' => 'test-secret', 'gateway' => 'https://example.invalid/epay'],
        'reward' => ['min_amount' => 0.01, 'max_amount' => 9999.99],
    ];
    file_put_contents($dir . '/config/config.php', '<?php return ' . var_export($config, true) . ';');
    $pending = ['out_trade_no' => 'RWTESTNOTIFY', 'amount' => 10, 'message' => '', 'status' => 0];
    file_put_contents($dir . '/logs/orders/RWTESTNOTIFY.json', json_encode($pending));
    $params = [
        'pid' => 'test-pid', 'out_trade_no' => 'RWTESTNOTIFY', 'trade_no' => 'TESTTRADE',
        'money' => '10.00', 'trade_status' => 'TRADE_SUCCESS',
    ];
    $params['sign'] = (new EpayHelper($config['epay']))->createSign($params);
    $runner = '<?php' . "\n"
        . '$_SERVER["REQUEST_METHOD"] = ' . var_export($endpoint === 'notify' ? 'GET' : 'POST', true) . ';' . "\n"
        . '$_GET = ' . var_export($params, true) . ';' . "\n"
        . '$_POST = ["amount" => 10, "message" => ""];' . "\n"
        . 'register_shutdown_function(function () { file_put_contents(__DIR__ . "/http-status", (string) (http_response_code() ?: 200)); });' . "\n"
        . 'require __DIR__ . "/api/' . $endpoint . '.php";';
    file_put_contents($dir . '/run.php', $runner);
    return $dir;
}

function startPhpFixture($file)
{
    $process = proc_open(
        [PHP_BINARY, '-n', '-d', 'display_errors=stderr', $file],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    expect(is_resource($process), 'Could not start endpoint fixture');
    stream_set_timeout($pipes[1], 5);
    stream_set_timeout($pipes[2], 5);
    return [$process, $pipes];
}

function finishPhpFixture($process, $pipes)
{
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    expect($exitCode === 0 && $errors === '', 'Endpoint fixture failed: ' . $errors);
    return $body;
}

function runEndpointFixture($dir)
{
    [$process, $pipes] = startPhpFixture($dir . '/run.php');
    $body = finishPhpFixture($process, $pipes);
    return ['status' => (int) file_get_contents($dir . '/http-status'), 'body' => $body];
}

function removeFixtureDirectory($dir)
{
    $resolved = realpath($dir);
    expect(
        $resolved !== false
            && dirname($resolved) === realpath(sys_get_temp_dir())
            && str_starts_with(basename($resolved), 'lec-order-test-'),
        'Refusing to clean a directory outside this test fixture'
    );
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($resolved);
}

$root = sys_get_temp_dir() . '/lec-order-test-' . bin2hex(random_bytes(8));
mkdir($root, 0755, true);
$helper = new EpayHelper(['key' => 'test-secret']);
$pending = ['out_trade_no' => 'RWTEST', 'amount' => 10, 'message' => '', 'status' => 0];
$paid = array_merge($pending, ['status' => 1, 'trade_no' => 'TESTTRADE']);
$exitCode = 0;

try {
    check('create and update an order using complete JSON files', function () use ($helper, $root, $pending, $paid) {
        $file = $root . '/store/orders/RWTEST.json';
        $helper->saveOrder($file, $pending);
        expect(json_decode(file_get_contents($file), true) === $pending, 'Pending order was not saved');
        $helper->saveOrder($file, $paid);
        expect(json_decode(file_get_contents($file), true) === $paid, 'Paid order was not saved');
        expect(glob($file . '.*.tmp') === [], 'Temporary file left after success');
    });

    check('JSON encoding failure preserves the previous order', function () use ($helper, $root, $paid) {
        $file = $root . '/store/orders/RWTEST.json';
        $before = file_get_contents($file);
        expectException(JsonException::class, function () use ($helper, $file, $paid) {
            $helper->saveOrder($file, array_merge($paid, ['message' => "\xB1\x31"]));
        });
        expect(file_get_contents($file) === $before, 'Encoding failure changed the order');
        expect(glob($file . '.*.tmp') === [], 'Encoding failure left a temporary file');
    });

    check('directory creation failure rejects an unsaved order', function () use ($helper, $root, $pending) {
        file_put_contents($root . '/blocked', 'keep');
        expectException(RuntimeException::class, function () use ($helper, $root, $pending) {
            $helper->saveOrder($root . '/blocked/RWTEST.json', $pending);
        });
        expect(file_get_contents($root . '/blocked') === 'keep', 'Existing file was changed');
    });

    stream_wrapper_register('orderfailure', OrderFailureStream::class);
    foreach (['open', 'partial', 'rename'] as $failure) {
        check($failure . ' failure preserves the pending order and permits retry', function () use ($helper, $pending, $paid, $failure) {
            $file = 'orderfailure://orders/RWTEST.json';
            $original = json_encode($pending);
            OrderFailureStream::$files = [$file => $original];
            OrderFailureStream::$failOpen = $failure === 'open';
            OrderFailureStream::$writeLimit = $failure === 'partial' ? 5 : PHP_INT_MAX;
            OrderFailureStream::$failRename = $failure === 'rename';
            clearstatcache();
            expectException(RuntimeException::class, function () use ($helper, $file, $paid) {
                $helper->saveOrder($file, $paid);
            });
            expect(OrderFailureStream::$files === [$file => $original], 'Failure changed the order or left a temporary file');
            OrderFailureStream::$failOpen = false;
            OrderFailureStream::$writeLimit = PHP_INT_MAX;
            OrderFailureStream::$failRename = false;
            clearstatcache();
            $helper->saveOrder($file, $paid);
            expect(count(OrderFailureStream::$files) === 1, 'Retry left temporary data');
            expect(json_decode(OrderFailureStream::$files[$file], true) === $paid, 'Retry did not save the paid order');
        });
    }

    check('callback storage failure returns HTTP 500 / fail without integrations', function () use ($root) {
        $dir = createEndpointFixture($root, 'notify-failure', 'notify', true);
        $result = runEndpointFixture($dir);
        expect($result === ['status' => 500, 'body' => 'fail'], 'Failed callback was acknowledged: ' . json_encode($result));
        expect(!file_exists($dir . '/integration-calls'), 'Failed callback triggered integrations');
        expect(json_decode(file_get_contents($dir . '/logs/orders/RWTESTNOTIFY.json'), true)['status'] === 0, 'Failed callback changed the order');
    });

    check('successful callback saves before integrations and accepts a replay', function () use ($root) {
        $dir = createEndpointFixture($root, 'notify-success', 'notify', false);
        expect(runEndpointFixture($dir) === ['status' => 200, 'body' => 'success'], 'Successful callback was rejected');
        $file = $dir . '/logs/orders/RWTESTNOTIFY.json';
        $saved = json_decode(file_get_contents($file), true);
        expect($saved['status'] === 1 && $saved['trade_no'] === 'TESTTRADE', 'Payment was not persisted');
        expect(!empty($saved['pay_time']), 'Payment time missing');
        expect(file_get_contents($dir . '/integration-calls') === 'called', 'Integration did not run after saving');
        expect(runEndpointFixture($dir) === ['status' => 200, 'body' => 'success'], 'Replay was rejected');
        expect(file_get_contents($dir . '/integration-calls') === 'called', 'Sequential replay repeated integration');
    });

    check('concurrent duplicate callbacks send integrations exactly once', function () use ($root) {
        $dir = createEndpointFixture($root, 'notify-duplicate-race', 'notify', false);
        $file = $dir . '/logs/orders/RWTESTNOTIFY.json';
        $helper = file_get_contents($dir . '/api/EpayHelper.php');
        $helper = str_replace('class EpayHelper', 'class PersistingEpayHelper', $helper);
        file_put_contents($dir . '/api/EpayHelper.php', $helper . <<<'PHP'

class EpayHelper extends PersistingEpayHelper
{
    public function verifySign($params, $sign)
    {
        $valid = parent::verifySign($params, $sign);
        echo "verified\n";
        flush();
        return $valid;
    }

    public function saveOrder($file, array $order)
    {
        echo "saving\n";
        flush();
        if (fgets(STDIN) !== "save\n") {
            throw new RuntimeException('Save barrier was not released');
        }
        parent::saveOrder($file, $order);
    }
}
PHP
        );

        $first = $second = null;
        $firstPipes = $secondPipes = [];
        try {
            [$first, $firstPipes] = startPhpFixture($dir . '/run.php');
            expect(fgets($firstPipes[1]) === "verified\n", 'First callback did not verify its signature');
            expect(fgets($firstPipes[1]) === "saving\n", 'First callback did not reach the save barrier');
            [$second, $secondPipes] = startPhpFixture($dir . '/run.php');
            expect(fgets($secondPipes[1]) === "verified\n", 'Second callback did not verify its signature');
            fwrite($secondPipes[0], "save\n");

            $read = [$secondPipes[1]];
            $write = $except = null;
            expect(stream_select($read, $write, $except, 0, 200000) === 0, 'Duplicate callback bypassed the lock before the first callback saved');
            expect(json_decode(file_get_contents($file), true)['status'] === 0, 'Paused callback already changed the order');
            expect(!file_exists($dir . '/integration-calls'), 'Integration ran before the first callback saved');

            fwrite($firstPipes[0], "save\n");
            expect(finishPhpFixture($first, $firstPipes) === 'success', 'First callback failed');
            expect(finishPhpFixture($second, $secondPipes) === 'success', 'Duplicate callback failed');
        } finally {
            foreach ([$first, $second] as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            foreach (array_merge($firstPipes, $secondPipes) as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }

        expect(json_decode(file_get_contents($file), true)['status'] === 1, 'Concurrent callbacks did not persist the paid order');
        expect(file_get_contents($dir . '/integration-calls') === 'called', 'Concurrent duplicate callbacks repeated integration');
    });

    check('cleanup waits for an in-flight callback and retains the paid order', function () use ($root) {
        $dir = createEndpointFixture($root, 'notify-cleanup-race', 'notify', false);
        $file = $dir . '/logs/orders/RWTESTNOTIFY.json';
        $pending = json_decode(file_get_contents($file), true);
        $pending['create_time'] = '2000-01-01 00:00:00';
        file_put_contents($file, json_encode($pending));

        // Pause the real callback just before its atomic save, while the order
        // still looks expired and unpaid to an uncoordinated cleaner.
        $helper = file_get_contents($dir . '/api/EpayHelper.php');
        $helper = str_replace('class EpayHelper', 'class PersistingEpayHelper', $helper);
        file_put_contents($dir . '/api/EpayHelper.php', $helper . <<<'PHP'

class EpayHelper extends PersistingEpayHelper
{
    public function saveOrder($file, array $order)
    {
        echo "saving\n";
        flush();
        if (fgets(STDIN) !== "save\n") {
            throw new RuntimeException('Save barrier was not released');
        }
        parent::saveOrder($file, $order);
    }
}
PHP
        );
        $cleanupScript = '<?php require ' . var_export(__DIR__ . '/../../scripts/cleanup_orders.php', true) . ';'
            . 'echo "ready\n"; flush();'
            . 'echo json_encode(lec_cleanup_orders(__DIR__ . "/logs/orders", 24 * 3600));';
        file_put_contents($dir . '/cleanup.php', $cleanupScript);

        $callback = $cleaner = null;
        $callbackPipes = $cleanerPipes = [];
        try {
            [$callback, $callbackPipes] = startPhpFixture($dir . '/run.php');
            expect(fgets($callbackPipes[1]) === "saving\n", 'Callback did not reach the save barrier');
            [$cleaner, $cleanerPipes] = startPhpFixture($dir . '/cleanup.php');
            expect(fgets($cleanerPipes[1]) === "ready\n", 'Cleaner did not start');

            $read = [$cleanerPipes[1]];
            $write = $except = null;
            expect(stream_select($read, $write, $except, 0, 200000) === 0, 'Cleanup finished before the callback saved');
            expect(is_file($file), 'Cleanup deleted the in-flight order');

            fwrite($callbackPipes[0], "save\n");
            expect(finishPhpFixture($callback, $callbackPipes) === 'success', 'Concurrent callback failed');
            $result = json_decode(finishPhpFixture($cleaner, $cleanerPipes), true);
            expect($result === ['deleted' => [], 'kept' => 1], 'Cleanup did not re-read the paid order');
        } finally {
            foreach ([$callback, $cleaner] as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            foreach (array_merge($callbackPipes, $cleanerPipes) as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }

        expect(json_decode(file_get_contents($file), true)['status'] === 1, 'Paid order was removed or reverted');
        expect(runEndpointFixture($dir) === ['status' => 200, 'body' => 'success'], 'Callback replay could not find the paid order');
        expect(file_get_contents($dir . '/integration-calls') === 'called', 'Integration was lost or repeated');
    });

    check('creation storage failure returns HTTP 500 without payment details', function () use ($root) {
        $dir = createEndpointFixture($root, 'create-failure', 'create_order', true);
        $result = runEndpointFixture($dir);
        $body = json_decode($result['body'], true);
        expect($result['status'] === 500 && $body['code'] === 500, 'Unsaved order was accepted');
        expect($body['data'] === null, 'Unsaved order exposed payment details');
        expect(!file_exists($dir . '/logs/orders/RWTESTCREATE.json'), 'Failure unexpectedly created an order');
    });

    check('successful creation persists the order before returning payment details', function () use ($root) {
        $dir = createEndpointFixture($root, 'create-success', 'create_order', false);
        $result = runEndpointFixture($dir);
        $body = json_decode($result['body'], true);
        expect($result['status'] === 200 && $body['code'] === 200, 'Order creation failed');
        $file = $dir . '/logs/orders/' . $body['data']['order_no'] . '.json';
        $saved = json_decode(file_get_contents($file), true);
        expect($saved['status'] === 0 && $saved['amount'] === 10, 'Returned order was not saved');
        expect($body['data']['pay_params']['out_trade_no'] === $saved['out_trade_no'], 'Payment refers to another order');
    });
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    removeFixtureDirectory($root);
}

exit($exitCode);
