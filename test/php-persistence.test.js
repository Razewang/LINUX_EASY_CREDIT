import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

test('PHP order persistence and callback regressions', (context) => {
  const result = spawnSync(
    process.env.PHP_TEST_BINARY || 'php',
    [fileURLToPath(new URL('./php/order-persistence.test.php', import.meta.url))],
    { encoding: 'utf8', timeout: 30_000 },
  );

  if (result.error?.code === 'ENOENT' && !process.env.CI && !process.env.PHP_TEST_BINARY) {
    context.skip('PHP CLI is unavailable; set PHP_TEST_BINARY to run PHP regressions');
    return;
  }

  assert.ifError(result.error);
  assert.equal(result.status, 0, `PHP regressions failed:\n${result.stdout}${result.stderr}`);
});
