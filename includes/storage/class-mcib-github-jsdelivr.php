<?php
/**
 * GitHub+jsDelivr存储适配器
 *
 * @package MultiCloud Image Bridge
 */

class MCIB_GitHub_jsDelivr implements MCIB_Storage_Interface {
    /**
     * GitHub+jsDelivr配置
     *
     * @var array
     */
    private $config;

    /**
     * 初始化GitHub+jsDelivr适配器
     *
     * @param array $config 配置参数
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * 上传文件到GitHub
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
            // 读取文件内容并进行Base64编码
            $file_content = file_get_contents($file_path);
            if ($file_content === false) {
                return false;
            }
            $content_base64 = base64_encode($file_content);
            
            // 构建GitHub API请求URL
            $api_url = "https://api.github.com/repos/{$this->config['repo']}/contents/{$this->config['path']}/{$remote_path}";
            
            // 构建请求数据
            $data = array(
                'message' => 'Upload image via MultiCloud Image Bridge',
                'content' => $content_base64,
                'branch' => $this->config['branch']
            );
            
            // 构建请求头
            $headers = array(
                'Authorization' => "token {$this->config['token']}",
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/MultiCloud-Image-Bridge'
            );
            
            // 发送请求
            $response = wp_remote_post($api_url, array(
                'headers' => $headers,
                'body' => json_encode($data),
                'timeout' => 30
            ));
            
            // 检查响应
            if (is_wp_error($response)) {
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 201) {
                return false;
            }
            
            // 解析响应
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['content']['sha'])) {
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
     * 删除GitHub中的文件
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
            // 首先获取文件的SHA
            $api_url = "https://api.github.com/repos/{$this->config['repo']}/contents/{$this->config['path']}/{$remote_path}";
            
            // 构建请求头
            $headers = array(
                'Authorization' => "token {$this->config['token']}",
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/MultiCloud-Image-Bridge'
            );
            
            // 发送请求获取文件信息
            $response = wp_remote_get($api_url, array(
                'headers' => $headers,
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
            
            // 解析响应获取SHA
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['sha'])) {
                return false;
            }
            
            $sha = $data['sha'];
            
            // 构建删除请求数据
            $delete_data = array(
                'message' => 'Delete image via MultiCloud Image Bridge',
                'sha' => $sha,
                'branch' => $this->config['branch']
            );
            
            // 发送删除请求
            $delete_response = wp_remote_request($api_url, array(
                'method' => 'DELETE',
                'headers' => $headers,
                'body' => json_encode($delete_data),
                'timeout' => 30
            ));
            
            // 检查响应
            if (is_wp_error($delete_response)) {
                return false;
            }
            
            $delete_response_code = wp_remote_retrieve_response_code($delete_response);
            return ($delete_response_code === 200);
            
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

        // 否则使用jsDelivr CDN URL
        return "https://cdn.jsdelivr.net/gh/{$this->config['repo']}@{$this->config['branch']}/{$this->config['path']}/{$remote_path}";
    }

    /**
     * 测试GitHub连接是否有效
     *
     * @return bool 连接是否有效
     */
    public function test_connection() {
        // 检查配置是否完整
        if (!$this->validate_config()) {
            return false;
        }

        try {
            // 构建GitHub API请求URL
            $api_url = "https://api.github.com/repos/{$this->config['repo']}";
            
            // 构建请求头
            $headers = array(
                'Authorization' => "token {$this->config['token']}",
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/MultiCloud-Image-Bridge'
            );
            
            // 发送请求
            $response = wp_remote_get($api_url, array(
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
            !empty($this->config['token']) &&
            !empty($this->config['repo']) &&
            !empty($this->config['branch']) &&
            !empty($this->config['path'])
        );
    }
}