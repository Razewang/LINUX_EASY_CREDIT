import { getRewardConfig } from '../../lib/vercel/config.js';
import { jsonResponse } from '../../lib/vercel/http.js';

export function GET() {
  try {
    const reward = getRewardConfig();

    return jsonResponse(200, '配置获取成功', {
      min_amount: reward.minAmount,
      max_amount: reward.maxAmount,
    });
  } catch (error) {
    console.error('Get public config failed', error);
    return jsonResponse(500, '系统配置错误');
  }
}

export function POST() {
  return jsonResponse(405, '请求方法不支持');
}
