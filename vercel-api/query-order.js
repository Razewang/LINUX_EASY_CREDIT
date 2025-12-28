/**
 * Vercel Serverless Function - 查询订单状态
 */

const crypto = require('crypto');
const https = require('https');

const config = {
    pid: process.env.EPAY_PID || '',
    key: process.env.EPAY_KEY || '',
    gateway: process.env.EPAY_GATEWAY || 'https://credit.linux.do/epay',
};

/**
 * 发送 HTTPS GET 请求
 */
function httpGet(url) {
    return new Promise((resolve, reject) => {
        https.get(url, (res) => {
            let data = '';
            res.on('data', (chunk) => data += chunk);
            res.on('end', () => resolve({ code: res.statusCode, response: data }));
        }).on('error', reject);
    });
}

module.exports = async (req, res) => {
    // CORS headers
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }

    try {
        const orderNo = req.query.order_no || '';

        if (!orderNo) {
            return res.status(400).json({ code: 400, message: '订单号不能为空' });
        }

        // 安全验证：订单号只允许字母数字
        if (!/^[A-Za-z0-9]+$/.test(orderNo)) {
            return res.status(400).json({ code: 400, message: '订单号格式不正确' });
        }

        // 查询 Linux.do Credit 的订单状态
        const queryUrl = `${config.gateway}/api.php?` + new URLSearchParams({
            act: 'order',
            pid: config.pid,
            key: config.key,
            out_trade_no: orderNo
        }).toString();

        const result = await httpGet(queryUrl);

        if (result.code === 200) {
            try {
                const response = JSON.parse(result.response);

                if (response && response.code === 1 && response.status === 1) {
                    // 订单已支付
                    return res.status(200).json({
                        code: 200,
                        message: '查询成功',
                        data: {
                            order_no: orderNo,
                            amount: response.money || 0,
                            status: 1,
                            status_text: '已支付',
                            trade_no: response.trade_no || '',
                            pay_time: response.endtime || null
                        }
                    });
                } else {
                    // 订单未支付或不存在
                    return res.status(200).json({
                        code: 200,
                        message: '查询成功',
                        data: {
                            order_no: orderNo,
                            status: 0,
                            status_text: '未支付',
                            pay_time: null
                        }
                    });
                }
            } catch (parseError) {
                console.error('解析响应失败:', parseError);
            }
        }

        // 查询失败
        return res.status(200).json({
            code: 200,
            message: '查询成功',
            data: {
                order_no: orderNo,
                status: 0,
                status_text: '未支付',
                pay_time: null
            }
        });

    } catch (error) {
        console.error('查询订单失败:', error);
        return res.status(500).json({
            code: 500,
            message: '系统错误：' + error.message
        });
    }
};
