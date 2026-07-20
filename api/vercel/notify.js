import { getConfig } from '../../lib/vercel/config.js';
import { normalizeAmount, verifySign } from '../../lib/vercel/epay.js';
import { readOrder, updateOrder } from '../../lib/vercel/orders.js';
import { textResponse } from '../../lib/vercel/http.js';

const ORDER_PATTERN = /^[A-Za-z0-9]+$/;

export async function GET(request) {
  try {
    const config = getConfig();
    const params = Object.fromEntries(new URL(request.url).searchParams.entries());
    const receivedSign = params.sign || '';

    if (!verifySign(params, receivedSign, config.epay.key)) {
      console.warn('Callback signature verification failed');
      return textResponse('fail', 400);
    }

    const orderNo = params.out_trade_no || '';
    if (!ORDER_PATTERN.test(orderNo)) return textResponse('fail', 400);

    const stored = await readOrder(orderNo);
    if (!stored) return textResponse('fail', 404);
    if (stored.order.status === 1) return textResponse('success');

    const callbackAmount = normalizeAmount(params.money);
    const orderAmount = normalizeAmount(stored.order.amount);
    if (callbackAmount === null || callbackAmount !== orderAmount) {
      console.warn('Callback amount mismatch', { orderNo });
      return textResponse('fail', 400);
    }
    if (params.trade_status !== 'TRADE_SUCCESS') {
      return textResponse('fail', 400);
    }

    await updateOrder({
      ...stored.order,
      status: 1,
      trade_no: params.trade_no || '',
      pay_time: new Date().toISOString(),
    }, stored.etag);

    return textResponse('success');
  } catch (error) {
    console.error('Callback processing failed', error);
    return textResponse('fail', 500);
  }
}

export function POST() {
  return textResponse('fail', 405);
}
