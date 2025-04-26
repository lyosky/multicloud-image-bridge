<?php
/**
 * AWS S3存储适配器
 *
 * @package MultiCloud Image Bridge
 */

class MCIB_AWS_S3 implements MCIB_Storage_Interface {
    /**
     * AWS S3配置
     *
     * @var array
     */
    private $config;

    /**
     * 初始化AWS S3适配器
     *
     * @param array $config 配置参数
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * 上传文件到AWS S3
     *
     * @param string $file_path 本地文件路径
     * @param string $remote_path 远程存储路径
     * @return array|bool 成功返回包含url的数组，失败返回false
     */
    public function upload_file($file_path, $remote_path) {
        // 检查配置是否完整
        if (!$this->validate_config()) {
            return false;
        }

        // 检查文件是否存在
        if (!file_exists($file_path)) {
            return false;
        }

        try {
            // 使用WordPress HTTP API实现S3上传
            $file_content = file_get_contents($file_path);
            if ($file_content === false) {
                return false;
            }

            // 获取文件信息
            $content_type = mime_content_type($file_path);
            $date = gmdate('Ymd\THis\Z');
            $short_date = substr($date, 0, 8);
            
            // 构建请求URL
            $host = "{$this->config['bucket']}.s3.{$this->config['region']}.amazonaws.com";
            $url = "https://{$host}/{$remote_path}";
            
            // 计算签名
            $amz_content_sha256 = hash('sha256', $file_content);
            
            // 规范请求
            $canonical_request = "PUT\n/{$remote_path}\n\nhost:{$host}\nx-amz-content-sha256:{$amz_content_sha256}\nx-amz-date:{$date}\n\nhost;x-amz-content-sha256;x-amz-date\n{$amz_content_sha256}";
            
            // 创建签名字符串
            $string_to_sign = "AWS4-HMAC-SHA256\n{$date}\n{$short_date}/{$this->config['region']}/s3/aws4_request\n" . hash('sha256', $canonical_request);
            
            // 计算签名密钥
            $k_date = hash_hmac('sha256', $short_date, "AWS4{$this->config['access_secret']}", true);
            $k_region = hash_hmac('sha256', $this->config['region'], $k_date, true);
            $k_service = hash_hmac('sha256', 's3', $k_region, true);
            $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
            
            // 计算签名
            $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
            
            // 构建授权头
            $authorization = "AWS4-HMAC-SHA256 Credential={$this->config['access_key']}/{$short_date}/{$this->config['region']}/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature={$signature}";
            
            // 构建请求头
            $headers = array(
                'Host' => $host,
                'x-amz-date' => $date,
                'x-amz-content-sha256' => $amz_content_sha256,
                'Authorization' => $authorization,
                'Content-Type' => $content_type,
                'Content-Length' => strlen($file_content)
            );
            
            // 发送请求
            $response = wp_remote_request($url, array(
                'method' => 'PUT',
                'headers' => $headers,
                'body' => $file_content,
                'timeout' => 30
            ));
            
            // 检查响应
            if (is_wp_error($response)) {
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return false;
            }
            
            // 构建返回的URL
            $file_url = $this->get_file_url($remote_path);
            return array('url' => $file_url);
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 删除AWS S3中的文件
     *
     * @param string $remote_path 远程存储路径
     * @return bool 是否删除成功
     */
    public function delete_file($remote_path) {
        // 检查配置是否完整
        if (!$this->validate_config()) {
            return false;
        }

        try {
            // 获取当前时间
            $date = gmdate('Ymd\THis\Z');
            $short_date = substr($date, 0, 8);
            
            // 构建请求URL
            $host = "{$this->config['bucket']}.s3.{$this->config['region']}.amazonaws.com";
            $url = "https://{$host}/{$remote_path}";
            
            // 计算签名
            $amz_content_sha256 = hash('sha256', '');
            
            // 规范请求
            $canonical_request = "DELETE\n/{$remote_path}\n\nhost:{$host}\nx-amz-content-sha256:{$amz_content_sha256}\nx-amz-date:{$date}\n\nhost;x-amz-content-sha256;x-amz-date\n{$amz_content_sha256}";
            
            // 创建签名字符串
            $string_to_sign = "AWS4-HMAC-SHA256\n{$date}\n{$short_date}/{$this->config['region']}/s3/aws4_request\n" . hash('sha256', $canonical_request);
            
            // 计算签名密钥
            $k_date = hash_hmac('sha256', $short_date, "AWS4{$this->config['access_secret']}", true);
            $k_region = hash_hmac('sha256', $this->config['region'], $k_date, true);
            $k_service = hash_hmac('sha256', 's3', $k_region, true);
            $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
            
            // 计算签名
            $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
            
            // 构建授权头
            $authorization = "AWS4-HMAC-SHA256 Credential={$this->config['access_key']}/{$short_date}/{$this->config['region']}/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature={$signature}";
            
            // 构建请求头
            $headers = array(
                'Host' => $host,
                'x-amz-date' => $date,
                'x-amz-content-sha256' => $amz_content_sha256,
                'Authorization' => $authorization
            );
            
            // 发送请求
            $response = wp_remote_request($url, array(
                'method' => 'DELETE',
                'headers' => $headers,
                'timeout' => 30
            ));
            
            // 检查响应
            if (is_wp_error($response)) {
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            return ($response_code === 204 || $response_code === 200);
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取文件的URL
     *
     * @param string $remote_path 远程存储路径
     * @return string 文件URL
     */
    public function get_file_url($remote_path) {
        // 如果设置了URL前缀，则使用前缀
        if (!empty($this->config['url_prefix'])) {
            return rtrim($this->config['url_prefix'], '/') . '/' . $remote_path;
        }

        // 否则使用默认的S3 URL
        return "https://{$this->config['bucket']}.s3.{$this->config['region']}.amazonaws.com/{$remote_path}";
    }

    /**
     * 测试AWS S3连接是否有效
     *
     * @return bool 连接是否有效
     */
    public function test_connection() {
        // 检查配置是否完整
        if (!$this->validate_config()) {
            return false;
        }

        try {
            // 获取当前时间
            $date = gmdate('Ymd\THis\Z');
            $short_date = substr($date, 0, 8);
            
            // 构建请求URL
            $host = "{$this->config['bucket']}.s3.{$this->config['region']}.amazonaws.com";
            $url = "https://{$host}/";
            
            // 计算签名
            $amz_content_sha256 = hash('sha256', '');
            
            // 规范请求
            $canonical_request = "GET\n/\n\nhost:{$host}\nx-amz-content-sha256:{$amz_content_sha256}\nx-amz-date:{$date}\n\nhost;x-amz-content-sha256;x-amz-date\n{$amz_content_sha256}";
            
            // 创建签名字符串
            $string_to_sign = "AWS4-HMAC-SHA256\n{$date}\n{$short_date}/{$this->config['region']}/s3/aws4_request\n" . hash('sha256', $canonical_request);
            
            // 计算签名密钥
            $k_date = hash_hmac('sha256', $short_date, "AWS4{$this->config['access_secret']}", true);
            $k_region = hash_hmac('sha256', $this->config['region'], $k_date, true);
            $k_service = hash_hmac('sha256', 's3', $k_region, true);
            $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
            
            // 计算签名
            $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
            
            // 构建授权头
            $authorization = "AWS4-HMAC-SHA256 Credential={$this->config['access_key']}/{$short_date}/{$this->config['region']}/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature={$signature}";
            
            // 构建请求头
            $headers = array(
                'Host' => $host,
                'x-amz-date' => $date,
                'x-amz-content-sha256' => $amz_content_sha256,
                'Authorization' => $authorization
            );
            
            // 发送请求
            $response = wp_remote_request($url, array(
                'method' => 'GET',
                'headers' => $headers,
                'timeout' => 30
            ));
            
            // 检查响应
            if (is_wp_error($response)) {
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            return ($response_code === 200);
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 验证配置是否完整
     *
     * @return bool 配置是否有效
     */
    private function validate_config() {
        return (
            !empty($this->config['access_key']) &&
            !empty($this->config['access_secret']) &&
            !empty($this->config['bucket']) &&
            !empty($this->config['region'])
        );
    }
}