import { readOrder } from '../../lib/vercel/orders.js';
import { jsonResponse, publicOrder } from '../../lib/vercel/http.js';

const ORDER_PATTERN = /^[A-Za-z0-9]+$/;

export async function GET(request) {
  try {
    const orderNo = new URL(request.url).searchParams.get('order_no')?.trim() || '';
    if (!orderNo) return jsonResponse(400, '订单号不能为空');
    if (!ORDER_PATTERN.test(orderNo)) return jsonResponse(400, '订单号格式不正确');

    const stored = await readOrder(orderNo);
    if (!stored) return jsonResponse(404, '订单不存在');

    return jsonResponse(200, '查询成功', publicOrder(stored.order));
  } catch (error) {
    console.error('Query order failed', error);
    return jsonResponse(500, '系统错误，请稍后重试');
  }
}
