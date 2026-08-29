import assert from 'node:assert/strict';
import test from 'node:test';
import {
  createSign,
  generateOrderNo,
  normalizeAmount,
  verifySign,
} from '../lib/vercel/epay.js';
import { POST as createOrderPost } from '../api/vercel/create-order.js';
import { GET as queryOrderGet } from '../api/vercel/query-order.js';
import { GET as notifyGet } from '../api/vercel/notify.js';

test('createSign follows EasyPay field filtering and ASCII ordering', () => {
  const params = {
    type: 'epay',
    pid: '001',
    money: '10.00',
    sign_type: 'MD5',
    empty: '',
  };

  assert.equal(
    createSign(params, 'secret'),
    createSign({ money: '10.00', pid: '001', type: 'epay' }, 'secret'),
  );
});

test('verifySign accepts a valid signature and rejects malformed values', () => {
  const params = { pid: '001', out_trade_no: 'RW123', money: '1.00' };
  const sign = createSign(params, 'secret');

  assert.equal(verifySign({ ...params, sign }, sign, 'secret'), true);
  assert.equal(verifySign(params, 'not-a-signature', 'secret'), false);
});

test('normalizeAmount accepts at most two decimal places', () => {
  assert.equal(normalizeAmount('1'), '1.00');
  assert.equal(normalizeAmount('1.20'), '1.20');
  assert.equal(normalizeAmount('1.234'), null);
  assert.equal(normalizeAmount('-1'), null);
});

test('generateOrderNo returns an alphanumeric order number', () => {
  assert.match(generateOrderNo(), /^RW[A-Z0-9]+$/);
});

test('create-order rejects malformed amounts before writing storage', async () => {
  process.env.EPAY_PID = '001';
  process.env.EPAY_KEY = 'secret';

  const response = await createOrderPost(new Request('https://example.test/api/create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ amount: '1.234', message: '' }),
  }));
  const body = await response.json();

  assert.equal(response.status, 400);
  assert.match(body.message, /金额格式/);
});

test('create-order rejects amounts below minimum limit', async () => {
  process.env.EPAY_PID = '001';
  process.env.EPAY_KEY = 'secret';
  process.env.MIN_AMOUNT = '1.00';

  const response = await createOrderPost(new Request('https://example.test/api/create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ amount: '0.50', message: '' }),
  }));
  const body = await response.json();

  assert.equal(response.status, 400);
  assert.match(body.message, /打赏积分不能小于 1 LDC/);
});

test('query-order rejects invalid order numbers before reading storage', async () => {
  const response = await queryOrderGet(
    new Request('https://example.test/api/query?order_no=../config'),
  );

  assert.equal(response.status, 400);
});

test('notify rejects callbacks with invalid signatures', async () => {
  process.env.EPAY_PID = '001';
  process.env.EPAY_KEY = 'secret';

  const response = await notifyGet(
    new Request('https://example.test/api/notify?out_trade_no=RW123&sign=invalid'),
  );

  assert.equal(response.status, 400);
  assert.equal(await response.text(), 'fail');
});
