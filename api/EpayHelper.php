<?php
/**
 * 易支付工具类
 */

class EpayHelper
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 生成签名
     * @param array $params 参数数组
     * @return string 签名字符串
     */
    public function createSign($params)
    {
        // 1. 过滤空值和sign字段
        $params = array_filter($params, function ($value) {
            return $value !== '' && $value !== null;
        });
        unset($params['sign']);
        unset($params['sign_type']);

        // 2. ASCII排序
        ksort($params);

        // 3. 拼接参数
        $string = '';
        foreach ($params as $key => $value) {
            $string .= $key . '=' . $value . '&';
        }
        $string = rtrim($string, '&');

        // 4. 追加密钥
        $string .= $this->config['key'];

        // 5. MD5加密
        return md5($string);
    }

    /**
     * 验证签名
     * @param array $params 参数数组
     * @param string $sign 签名
     * @return bool
     */
    public function verifySign($params, $sign)
    {
        return $this->createSign($params) === $sign;
    }

    /**
     * 生成订单号
     * @return string
     */
    public function generateOrderNo()
    {
        return 'RW' . date('YmdHis') . rand(1000, 9999);
    }

    /**
     * 发送HTTP POST请求
     * @param string $url 请求地址
     * @param array $data 请求数据
     * @return mixed
     */
    public function httpPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'response' => $response
        ];
    }

    /**
     * 发送HTTP GET请求
     * @param string $url 请求地址
     * @return mixed
     */
    public function httpGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'response' => $response
        ];
    }

    /**
     * 记录日志
     * @param string $message 日志内容
     * @param string $type 日志类型
     */
    public function log($message, $type = 'info')
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/' . date('Y-m-d') . '.log';
        $time = date('Y-m-d H:i:s');
        $content = "[{$time}] [{$type}] {$message}\n";
        file_put_contents($logFile, $content, FILE_APPEND);
    }

    /**
     * 返回JSON响应
     * @param int $code 状态码
     * @param string $message 消息
     * @param mixed $data 数据
     */
    public function jsonResponse($code, $message, $data = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        echo json_encode([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
