import { getConfig } from '../../lib/vercel/config.js';
import { createSign, generateOrderNo, normalizeAmount } from '../../lib/vercel/epay.js';
import { createOrder } from '../../lib/vercel/orders.js';
import { jsonResponse } from '../../lib/vercel/http.js';

export async function POST(request) {
  try {
    const config = getConfig();
    const input = await request.json();

    const amount = normalizeAmount(input?.amount);
    if (amount === null) {
      return jsonResponse(400, '金额格式不正确，最多支持两位小数');
    }

    const numericAmount = Number(amount);
    if (numericAmount < config.reward.minAmount) {
      return jsonResponse(400, `打赏金额不能小于 ${config.reward.minAmount} 元`);
    }
    if (numericAmount > config.reward.maxAmount) {
      return jsonResponse(400, `打赏金额不能大于 ${config.reward.maxAmount} 元`);
    }

    if (input?.message !== undefined && typeof input.message !== 'string') {
      return jsonResponse(400, '留言必须是字符串');
    }
    const message = (input?.message || '').trim();
    if ([...message].length > 200) {
      return jsonResponse(400, '留言不能超过 200 个字符');
    }

    const outTradeNo = generateOrderNo();
    const payParams = {
      pid: config.epay.pid,
      type: 'epay',
      out_trade_no: outTradeNo,
      name: `积分流转${message ? `：${[...message].slice(0, 20).join('')}` : ''}`,
      money: amount,
    };
    payParams.sign = createSign(payParams, config.epay.key);
    payParams.sign_type = 'MD5';

    await createOrder({
      out_trade_no: outTradeNo,
      amount: numericAmount,
      message,
      create_time: new Date().toISOString(),
      status: 0,
    });

    const payUrl = `${config.epay.gateway}/pay/submit.php`;
    return jsonResponse(200, '订单创建成功', {
      order_no: outTradeNo,
      amount: numericAmount,
      pay_url: payUrl,
      pay_params: payParams,
      redirect_url: `${payUrl}?${new URLSearchParams(payParams)}`,
    });
  } catch (error) {
    console.error('Create order failed', error);
    return jsonResponse(500, '系统错误，请稍后重试');
  }
}

export function GET() {
  return jsonResponse(405, '请求方法不支持');
}
