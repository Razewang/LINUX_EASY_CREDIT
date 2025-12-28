/**
 * Vercel Serverless Function - 创建支付订单
 */

const crypto = require('crypto');

// 从环境变量获取配置
const config = {
    pid: process.env.EPAY_PID || '',
    key: process.env.EPAY_KEY || '',
    gateway: process.env.EPAY_GATEWAY || 'https://credit.linux.do/epay',
    minAmount: parseFloat(process.env.MIN_AMOUNT || '0.01'),
    maxAmount: parseFloat(process.env.MAX_AMOUNT || '9999.99'),
};

/**
 * 生成签名
 */
function createSign(params, key) {
    // 过滤空值和sign字段
    const filtered = {};
    for (const k in params) {
        if (params[k] !== '' && params[k] !== null && k !== 'sign' && k !== 'sign_type') {
            filtered[k] = params[k];
        }
    }

    // ASCII排序
    const sorted = Object.keys(filtered).sort();

    // 拼接参数
    const str = sorted.map(k => `${k}=${filtered[k]}`).join('&') + key;

    // MD5加密
    return crypto.createHash('md5').update(str).digest('hex');
}

/**
 * 生成订单号
 */
function generateOrderNo() {
    const now = new Date();
    const dateStr = now.getFullYear().toString() +
        String(now.getMonth() + 1).padStart(2, '0') +
        String(now.getDate()).padStart(2, '0') +
        String(now.getHours()).padStart(2, '0') +
        String(now.getMinutes()).padStart(2, '0') +
        String(now.getSeconds()).padStart(2, '0');
    const rand = Math.floor(1000 + Math.random() * 9000);
    return 'RW' + dateStr + rand;
}

module.exports = async (req, res) => {
    // CORS headers
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    // Handle OPTIONS
    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }

    if (req.method !== 'POST') {
        return res.status(405).json({ code: 405, message: '方法不允许' });
    }

    try {
        // 检查配置
        if (!config.pid || !config.key) {
            return res.status(500).json({
                code: 500,
                message: '服务器配置错误：请设置 EPAY_PID 和 EPAY_KEY 环境变量'
            });
        }

        const { amount: rawAmount, message = '' } = req.body || {};
        const amount = parseFloat(rawAmount) || 0;

        // 验证金额
        if (amount < config.minAmount) {
            return res.status(400).json({
                code: 400,
                message: `打赏金额不能小于 ${config.minAmount} 元`
            });
        }

        if (amount > config.maxAmount) {
            return res.status(400).json({
                code: 400,
                message: `打赏金额不能大于 ${config.maxAmount} 元`
            });
        }

        // 金额小数位数检查
        const amountStr = amount.toString();
        if (amountStr.includes('.')) {
            const decimals = amountStr.split('.')[1].length;
            if (decimals > 2) {
                return res.status(400).json({
                    code: 400,
                    message: '金额小数位数不能超过2位'
                });
            }
        }

        // 生成订单号
        const outTradeNo = generateOrderNo();

        // 构建支付参数
        const payParams = {
            pid: config.pid,
            type: 'epay',
            out_trade_no: outTradeNo,
            name: '打赏支持' + (message ? '：' + message.substring(0, 20) : ''),
            money: amount.toFixed(2), // 格式化为两位小数
        };

        // 生成签名
        payParams.sign = createSign(payParams, config.key);
        payParams.sign_type = 'MD5';

        // 构建支付URL
        const payUrl = config.gateway + '/pay/submit.php';

        // 返回结果
        return res.status(200).json({
            code: 200,
            message: '订单创建成功',
            data: {
                order_no: outTradeNo,
                amount: amount,
                pay_url: payUrl,
                pay_params: payParams,
                redirect_url: payUrl + '?' + new URLSearchParams(payParams).toString()
            }
        });

    } catch (error) {
        console.error('创建订单失败:', error);
        return res.status(500).json({
            code: 500,
            message: '系统错误：' + error.message
        });
    }
};
