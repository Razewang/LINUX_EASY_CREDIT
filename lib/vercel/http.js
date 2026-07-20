export const jsonResponse = (status, message, data = null) => (
  Response.json(
    { code: status, message, data },
    {
      status,
      headers: {
        'Cache-Control': 'no-store',
        'X-Content-Type-Options': 'nosniff',
      },
    },
  )
);

export const textResponse = (body, status = 200) => (
  new Response(body, {
    status,
    headers: {
      'Content-Type': 'text/plain; charset=utf-8',
      'Cache-Control': 'no-store',
    },
  })
);

export function publicOrder(order) {
  return {
    order_no: order.out_trade_no,
    amount: order.amount,
    message: order.message,
    status: order.status,
    status_text: order.status === 1 ? '已支付' : '未支付',
    pay_time: order.pay_time ?? null,
  };
}
