<?php
/**
 * 阿里云OSS存储适配器
 *
 * @package MultiCloud Image Bridge
 */

class MCIB_Aliyun_OSS implements MCIB_Storage_Interface {
    /**
     * 阿里云OSS配置
     *
     * @var array
     */
    private $config;

    /**
     * 初始化阿里云OSS适配器
     *
     * @param array $config 配置参数
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * 上传文件到阿里云OSS
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
            // 如果没有安装阿里云OSS SDK，则尝试使用WordPress HTTP API
            $file_content = file_get_contents($file_path);
            if ($file_content === false) {
                return false;
            }

            // 构建OSS API请求
            $date = gmdate('D, d M Y H:i:s \G\M\T');
            $content_md5 = base64_encode(md5($file_content, true));
            $content_type = mime_content_type($file_path);
            $string_to_sign = "PUT\n{$content_md5}\n{$content_type}\n{$date}\n/{$this->config['bucket']}/{$remote_path}";
            $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->config['access_secret'], true));

            // 构建请求URL和头信息
            $url = "https://{$this->config['bucket']}.{$this->config['endpoint']}/{$remote_path}";
            $headers = array(
                'Host' => "{$this->config['bucket']}.{$this->config['endpoint']}",
                'Date' => $date,
                'Content-Type' => $content_type,
                'Content-MD5' => $content_md5,
                'Authorization' => "OSS {$this->config['access_key']}:{$signature}",
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
     * 删除阿里云OSS中的文件
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
            // 构建OSS API请求
            $date = gmdate('D, d M Y H:i:s \G\M\T');
            $string_to_sign = "DELETE\n\n\n{$date}\n/{$this->config['bucket']}/{$remote_path}";
            $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->config['access_secret'], true));

            // 构建请求URL和头信息
            $url = "https://{$this->config['bucket']}.{$this->config['endpoint']}/{$remote_path}";
            $headers = array(
                'Host' => "{$this->config['bucket']}.{$this->config['endpoint']}",
                'Date' => $date,
                'Authorization' => "OSS {$this->config['access_key']}:{$signature}"
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

        // 否则使用默认的OSS URL
        return "https://{$this->config['bucket']}.{$this->config['endpoint']}/{$remote_path}";
    }

    /**
     * 测试阿里云OSS连接是否有效
     *
     * @return bool 连接是否有效
     */
    public function test_connection() {
        // 检查配置是否完整
        if (!$this->validate_config()) {
            return false;
        }

        try {
            // 构建OSS API请求
            $date = gmdate('D, d M Y H:i:s \G\M\T');
            $string_to_sign = "GET\n\n\n{$date}\n/{$this->config['bucket']}/";
            $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->config['access_secret'], true));

            // 构建请求URL和头信息
            $url = "https://{$this->config['bucket']}.{$this->config['endpoint']}/";
            $headers = array(
                'Host' => "{$this->config['bucket']}.{$this->config['endpoint']}",
                'Date' => $date,
                'Authorization' => "OSS {$this->config['access_key']}:{$signature}"
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
            !empty($this->config['endpoint'])
        );
    }
}