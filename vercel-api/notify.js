/**
 * Vercel Serverless Function - 支付回调通知
 */

const crypto = require('crypto');

const config = {
    key: process.env.EPAY_KEY || '',
};

/**
 * 生成签名
 */
function createSign(params, key) {
    const filtered = {};
    for (const k in params) {
        if (params[k] !== '' && params[k] !== null && k !== 'sign' && k !== 'sign_type') {
            filtered[k] = params[k];
        }
    }
    const sorted = Object.keys(filtered).sort();
    const str = sorted.map(k => `${k}=${filtered[k]}`).join('&') + key;
    return crypto.createHash('md5').update(str).digest('hex');
}

module.exports = async (req, res) => {
    try {
        // 获取回调参数 (GET)
        const params = { ...req.query };

        console.log('收到回调:', JSON.stringify(params));

        // 提取签名
        const receiveSign = params.sign || '';

        if (!receiveSign) {
            console.log('回调失败: 签名为空');
            return res.status(200).send('fail');
        }

        // 验证签名
        const signParams = { ...params };
        delete signParams.sign;
        delete signParams.sign_type;

        const localSign = createSign(signParams, config.key);

        if (localSign !== receiveSign) {
            console.log('回调失败: 签名验证失败');
            return res.status(200).send('fail');
        }

        // 验证签名通过，处理业务逻辑
        const outTradeNo = params.out_trade_no || '';
        const tradeNo = params.trade_no || '';
        const money = params.money || 0;
        const tradeStatus = params.trade_status || '';

        if (!outTradeNo) {
            console.log('回调失败: 订单号为空');
            return res.status(200).send('fail');
        }

        // 安全验证：订单号只允许字母数字
        if (!/^[A-Za-z0-9]+$/.test(outTradeNo)) {
            console.log('回调失败: 订单号格式不正确');
            return res.status(200).send('fail');
        }

        // 检查交易状态
        if (tradeStatus === 'TRADE_SUCCESS') {
            console.log(`订单支付成功: ${outTradeNo}, 金额: ${money}, 流水号: ${tradeNo}`);

            // 注意：Vercel 是无状态的，无法直接存储订单状态
            // 如需持久化存储，请配置 Vercel KV 或外部数据库
            // 这里只返回成功，实际业务逻辑需要根据需求扩展

            return res.status(200).send('success');
        } else {
            console.log(`回调失败: 交易状态异常 - ${tradeStatus}`);
            return res.status(200).send('fail');
        }

    } catch (error) {
        console.error('回调处理异常:', error);
        return res.status(200).send('fail');
    }
};
