import assert from 'node:assert/strict';
import test from 'node:test';
import { GET as getPublicConfig } from '../api/vercel/config.js';
import {
  DEFAULT_AMOUNT_LIMITS,
  applyAmountLimits,
  loadAmountLimits,
} from '../assets/js/amount-config.js';

const withAmountEnv = async (values, callback) => {
  const previous = {
    MIN_AMOUNT: process.env.MIN_AMOUNT,
    MAX_AMOUNT: process.env.MAX_AMOUNT,
    EPAY_PID: process.env.EPAY_PID,
    EPAY_KEY: process.env.EPAY_KEY,
  };

  Object.assign(process.env, values);

  try {
    await callback();
  } finally {
    for (const [name, value] of Object.entries(previous)) {
      if (value === undefined) {
        delete process.env[name];
      } else {
        process.env[name] = value;
      }
    }
  }
};

test('public config returns amount limits without exposing Vercel secrets', async () => {
  await withAmountEnv({
    MIN_AMOUNT: '2.5',
    MAX_AMOUNT: '250',
    EPAY_PID: 'private-client-id',
    EPAY_KEY: 'private-client-secret',
  }, async () => {
    const response = getPublicConfig();
    const body = await response.json();

    assert.equal(response.status, 200);
    assert.deepEqual(body.data, {
      min_amount: 2.5,
      max_amount: 250,
    });
    assert.doesNotMatch(JSON.stringify(body), /private-client/);
  });
});

test('public config does not require merchant credentials', async () => {
  await withAmountEnv({
    MIN_AMOUNT: '1',
    MAX_AMOUNT: '100',
  }, async () => {
    delete process.env.EPAY_PID;
    delete process.env.EPAY_KEY;

    const response = getPublicConfig();

    assert.equal(response.status, 200);
  });
});

test('homepage amount config uses valid public limits and applies input bounds', async () => {
  const limits = await loadAmountLimits(async (url) => {
    assert.equal(url, './api/config');
    return new Response(JSON.stringify({
      data: {
        min_amount: 3,
        max_amount: 300,
      },
    }));
  });
  const input = {};

  applyAmountLimits(input, limits);

  assert.deepEqual(limits, { minAmount: 3, maxAmount: 300 });
  assert.deepEqual(input, { min: '3', max: '300' });
});

test('homepage amount config preserves PHP/Docker defaults when endpoint is unavailable', async () => {
  const limits = await loadAmountLimits(async () => new Response('', { status: 404 }));

  assert.deepEqual(limits, DEFAULT_AMOUNT_LIMITS);
});
