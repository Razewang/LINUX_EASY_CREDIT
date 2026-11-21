<?php
/**
 * Serialize callback updates and cleanup using a stable directory lock.
 * Order files are atomically replaced, so locking an order's inode is insufficient.
 */
function lec_with_orders_lock($ordersDir, callable $callback)
{
    // Keep this file in place so every process locks the same inode.
    $lockFile = rtrim($ordersDir, '/') . '/.orders.lock';
    $handle = @fopen($lockFile, 'c');
    if ($handle === false) {
        // Docker cleanup runs as root; flock needs no writes to an existing lock.
        $handle = @fopen($lockFile, 'r');
    }
    if ($handle === false) {
        throw new RuntimeException('无法打开订单锁');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('无法获取订单锁');
        }
        return $callback();
    } finally {
        // Closing also releases the lock when the callback throws.
        fclose($handle);
    }
}
