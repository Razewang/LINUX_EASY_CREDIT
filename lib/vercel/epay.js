import { createHash, randomBytes, timingSafeEqual } from 'node:crypto';

export function createSign(params, secret) {
  const payload = Object.entries(params)
    .filter(([key, value]) => (
      key !== 'sign'
      && key !== 'sign_type'
      && value !== ''
      && value !== null
      && value !== undefined
    ))
    .sort(([left], [right]) => (left < right ? -1 : left > right ? 1 : 0))
    .map(([key, value]) => `${key}=${value}`)
    .join('&');

  return createHash('md5').update(`${payload}${secret}`, 'utf8').digest('hex');
}

export function verifySign(params, receivedSign, secret) {
  if (!/^[a-fA-F0-9]{32}$/.test(receivedSign || '')) return false;

  const localSign = createSign(params, secret);
  return timingSafeEqual(
    Buffer.from(localSign, 'ascii'),
    Buffer.from(receivedSign.toLowerCase(), 'ascii'),
  );
}

export function generateOrderNo(now = new Date()) {
  const timestamp = now.toISOString()
    .replace(/\D/g, '')
    .slice(0, 14);
  return `RW${timestamp}${randomBytes(8).toString('hex').toUpperCase()}`;
}

export function normalizeAmount(value) {
  if (typeof value !== 'number' && typeof value !== 'string') return null;
  const raw = String(value).trim();
  if (!/^\d+(?:\.\d{1,2})?$/.test(raw)) return null;

  const amount = Number(raw);
  if (!Number.isFinite(amount)) return null;
  return amount.toFixed(2);
}
