<?php
/**
 * 第三方集成帮助类
 * 处理 Notion 和 Webhook 集成
 */

class IntegrationHelper
{
    private $config;
    private $logHelper;

    public function __construct($config, $logHelper = null)
    {
        $this->config = $config;
        $this->logHelper = $logHelper;
    }

    /**
     * 发送订单数据到所有启用的集成
     *
     * @param array $orderData 订单数据
     * @return array 各个集成的执行结果
     */
    public function sendToIntegrations($orderData)
    {
        $results = [
            'notion' => ['enabled' => false, 'success' => false, 'message' => ''],
            'webhook' => ['enabled' => false, 'success' => false, 'message' => ''],
        ];

        // 发送到 Notion
        if ($this->isNotionEnabled()) {
            $results['notion']['enabled'] = true;
            try {
                $this->sendToNotion($orderData);
                $results['notion']['success'] = true;
                $results['notion']['message'] = 'Notion 记录创建成功';
                $this->log('Notion 集成成功');
            } catch (Exception $e) {
                $results['notion']['message'] = 'Notion 集成失败: ' . $e->getMessage();
                $this->log('Notion 集成失败: ' . $e->getMessage(), 'error');
            }
        }

        // 发送到 Webhook
        if ($this->isWebhookEnabled()) {
            $results['webhook']['enabled'] = true;
            try {
                $this->sendToWebhook($orderData);
                $results['webhook']['success'] = true;
                $results['webhook']['message'] = 'Webhook 发送成功';
                $this->log('Webhook 发送成功');
            } catch (Exception $e) {
                $results['webhook']['message'] = 'Webhook 发送失败: ' . $e->getMessage();
                $this->log('Webhook 发送失败: ' . $e->getMessage(), 'error');
            }
        }

        return $results;
    }

    /**
     * 发送数据到 Notion 数据库
     *
     * @param array $orderData 订单数据
     * @throws Exception
     */
    private function sendToNotion($orderData)
    {
        $config = $this->config['notion'];

        // 构建 Notion API 请求数据
        $notionData = $this->buildNotionPageData($orderData, $config);

        // 发送请求到 Notion API
        $url = 'https://api.notion.com/v1/pages';
        $headers = [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
            'Notion-Version: ' . $config['api_version'],
        ];

        $response = $this->sendHttpRequest($url, 'POST', $notionData, $headers);

        if (!$response['success']) {
            throw new Exception($response['error']);
        }

        $this->log('Notion API 响应: ' . json_encode($response['data'], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 构建 Notion 页面数据
     *
     * @param array $orderData 订单数据
     * @param array $config Notion 配置
     * @return array Notion API 请求数据
     */
    private function buildNotionPageData($orderData, $config)
    {
        $props = $config['properties'];

        // 构建属性数据
        $properties = [];

        // 标题字段（Title类型）
        $titleField = $props['title_field'];
        $properties[$titleField] = [
            'title' => [
                [
                    'text' => [
                        'content' => $orderData['out_trade_no']
                    ]
                ]
            ]
        ];

        // 金额字段（Number类型）
        $amountField = $props['amount_field'];
        $properties[$amountField] = [
            'number' => floatval($orderData['amount'])
        ];

        // 留言字段（Rich Text类型）
        $messageField = $props['message_field'];
        $properties[$messageField] = [
            'rich_text' => [
                [
                    'text' => [
                        'content' => $orderData['message'] ?: '无'
                    ]
                ]
            ]
        ];

        // 状态字段（Select类型）
        $statusField = $props['status_field'];
        $properties[$statusField] = [
            'select' => [
                'name' => $orderData['status'] == 1 ? '已支付' : '未支付'
            ]
        ];

        // 支付时间字段（Date类型）
        if (!empty($orderData['pay_time'])) {
            $payTimeField = $props['pay_time_field'];
            $properties[$payTimeField] = [
                'date' => [
                    'start' => date('c', strtotime($orderData['pay_time']))
                ]
            ];
        }

        return [
            'parent' => [
                'database_id' => $config['database_id']
            ],
            'properties' => $properties
        ];
    }

    /**
     * 发送数据到 Webhook
     *
     * @param array $orderData 订单数据
     * @throws Exception
     */
    private function sendToWebhook($orderData)
    {
        $config = $this->config['webhook'];
        $url = $config['url'];
        $method = strtoupper($config['method']);

        // 构建 Webhook 数据
        $webhookData = [
            'event' => 'payment_success',
            'order_no' => $orderData['out_trade_no'],
            'amount' => floatval($orderData['amount']),
            'message' => $orderData['message'],
            'status' => $orderData['status'],
            'pay_time' => $orderData['pay_time'] ?? null,
            'trade_no' => $orderData['trade_no'] ?? null,
            'create_time' => $orderData['create_time'] ?? null,
            'timestamp' => time(),
        ];

        // 构建请求头
        $headers = [];
        if (!empty($config['headers'])) {
            foreach ($config['headers'] as $key => $value) {
                $headers[] = "$key: $value";
            }
        }

        // 发送请求（带重试机制）
        $maxRetries = $config['retry'] ?? 3;
        $lastError = null;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $response = $this->sendHttpRequest(
                    $url,
                    $method,
                    $webhookData,
                    $headers,
                    $config['timeout'] ?? 10
                );

                if ($response['success']) {
                    $this->log("Webhook 发送成功（尝试 " . ($i + 1) . " 次）");
                    return;
                }

                $lastError = $response['error'];
                $this->log("Webhook 发送失败（尝试 " . ($i + 1) . " 次）: " . $lastError, 'warning');

                // 等待后重试
                if ($i < $maxRetries - 1) {
                    sleep(1);
                }
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $this->log("Webhook 请求异常（尝试 " . ($i + 1) . " 次）: " . $lastError, 'warning');

                if ($i < $maxRetries - 1) {
                    sleep(1);
                }
            }
        }

        throw new Exception("Webhook 发送失败，已重试 {$maxRetries} 次。最后错误: {$lastError}");
    }

    /**
     * 发送 HTTP 请求
     *
     * @param string $url 请求 URL
     * @param string $method 请求方法（GET/POST）
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @param int $timeout 超时时间（秒）
     * @return array ['success' => bool, 'data' => mixed, 'error' => string]
     */
    private function sendHttpRequest($url, $method = 'POST', $data = [], $headers = [], $timeout = 30)
    {
        $ch = curl_init();

        // 设置基本选项
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // 设置请求方法和数据
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        } elseif ($method === 'GET' && !empty($data)) {
            $query = http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
        }

        // 设置请求头
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // 处理响应
        if ($error) {
            return [
                'success' => false,
                'data' => null,
                'error' => "cURL 错误: {$error}"
            ];
        }

        // 检查 HTTP 状态码
        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'success' => false,
                'data' => $response,
                'error' => "HTTP 错误 {$httpCode}: {$response}"
            ];
        }

        // 尝试解析 JSON 响应
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $responseData = $response;
        }

        return [
            'success' => true,
            'data' => $responseData,
            'error' => null
        ];
    }

    /**
     * 检查 Notion 是否启用
     *
     * @return bool
     */
    private function isNotionEnabled()
    {
        return !empty($this->config['notion']['enabled'])
            && !empty($this->config['notion']['api_key'])
            && !empty($this->config['notion']['database_id']);
    }

    /**
     * 检查 Webhook 是否启用
     *
     * @return bool
     */
    private function isWebhookEnabled()
    {
        return !empty($this->config['webhook']['enabled'])
            && !empty($this->config['webhook']['url']);
    }

    /**
     * 记录日志
     *
     * @param string $message 日志消息
     * @param string $level 日志级别
     */
    private function log($message, $level = 'info')
    {
        if ($this->logHelper && method_exists($this->logHelper, 'log')) {
            $this->logHelper->log($message, $level);
        }
    }
}
