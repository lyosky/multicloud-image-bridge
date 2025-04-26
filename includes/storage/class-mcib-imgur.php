<?php
/**
 * Imgur存储适配器
 *
 * @package MultiCloud Image Bridge
 */

class MCIB_Imgur implements MCIB_Storage_Interface {
    /**
     * Imgur配置
     *
     * @var array
     */
    private $config;

    /**
     * 初始化Imgur适配器
     *
     * @param array $config 配置参数
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * 上传文件到Imgur
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
            // 读取文件内容
            $file_content = file_get_contents($file_path);
            if ($file_content === false) {
                return false;
            }
            
            // 构建Imgur API请求URL
            $api_url = 'https://api.imgur.com/3/image';
            
            // 构建请求头
            $headers = array(
                'Authorization' => "Client-ID {$this->config['client_id']}",
                'Content-Type' => 'application/x-www-form-urlencoded'
            );
            
            // 如果有access_token，则使用Bearer认证
            if (!empty($this->config['access_token'])) {
                $headers['Authorization'] = "Bearer {$this->config['access_token']}";
            }
            
            // 构建请求数据
            $data = array(
                'image' => base64_encode($file_content),
                'type' => 'base64',
                'name' => basename($file_path),
                'title' => 'Uploaded via MultiCloud Image Bridge',
                'description' => $remote_path
            );
            
            // 发送请求
            $response = wp_remote_post($api_url, array(
                'headers' => $headers,
                'body' => $data,
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
            
            // 解析响应
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['data']['link'])) {
                return false;
            }
            
            // 存储Imgur图片ID和删除哈希，用于后续删除操作
            $this->store_imgur_metadata($remote_path, $data['data']['id'], $data['data']['deletehash']);
            
            // 构建返回的URL
            $file_url = $data['data']['link'];
            if (!empty($this->config['url_prefix'])) {
                $file_url = $this->config['url_prefix'] . '/' . basename($file_url);
            }
            
            return array('url' => $file_url);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 删除Imgur中的文件
     *
     * @param string $remote_path 远程存储路径
     * @return bool 是否删除成功
     */
    public function delete_file($remote_path) {
        // 检查配置是否完整
        if (!$this->validate_config()) {
            return false;
        }
        
        // 获取Imgur元数据
        $metadata = $this->get_imgur_metadata($remote_path);
        if (!$metadata || empty($metadata['deletehash'])) {
            return false;
        }
        
        try {
            // 构建Imgur API请求URL
            $api_url = "https://api.imgur.com/3/image/{$metadata['deletehash']}";
            
            // 构建请求头
            $headers = array(
                'Authorization' => "Client-ID {$this->config['client_id']}"
            );
            
            // 如果有access_token，则使用Bearer认证
            if (!empty($this->config['access_token'])) {
                $headers['Authorization'] = "Bearer {$this->config['access_token']}";
            }
            
            // 发送请求
            $response = wp_remote_request($api_url, array(
                'method' => 'DELETE',
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
            
            // 删除元数据
            $this->delete_imgur_metadata($remote_path);
            
            return true;
            
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
        // 获取Imgur元数据
        $metadata = $this->get_imgur_metadata($remote_path);
        if (!$metadata || empty($metadata['id'])) {
            return '';
        }
        
        $file_url = "https://i.imgur.com/{$metadata['id']}.jpg";
        
        // 如果设置了URL前缀，则使用自定义域名
        if (!empty($this->config['url_prefix'])) {
            $file_url = $this->config['url_prefix'] . '/' . basename($file_url);
        }
        
        return $file_url;
    }
    
    /**
     * 测试存储配置是否有效
     *
     * @return bool 配置是否有效
     */
    public function test_connection() {
        // 检查配置是否完整
        if (!$this->validate_config()) {
            return false;
        }
        
        try {
            // 构建Imgur API请求URL
            $api_url = 'https://api.imgur.com/3/credits';
            
            // 构建请求头
            $headers = array(
                'Authorization' => "Client-ID {$this->config['client_id']}"
            );
            
            // 如果有access_token，则使用Bearer认证
            if (!empty($this->config['access_token'])) {
                $headers['Authorization'] = "Bearer {$this->config['access_token']}";
            }
            
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
            return $response_code === 200;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 验证配置是否完整
     *
     * @return bool 配置是否完整
     */
    private function validate_config() {
        return !empty($this->config['client_id']);
    }
    
    /**
     * 存储Imgur元数据
     *
     * @param string $remote_path 远程存储路径
     * @param string $id Imgur图片ID
     * @param string $deletehash Imgur删除哈希
     */
    private function store_imgur_metadata($remote_path, $id, $deletehash) {
        $metadata = get_option('mcib_imgur_metadata', array());
        $metadata[$remote_path] = array(
            'id' => $id,
            'deletehash' => $deletehash
        );
        update_option('mcib_imgur_metadata', $metadata);
    }
    
    /**
     * 获取Imgur元数据
     *
     * @param string $remote_path 远程存储路径
     * @return array|bool 元数据或false
     */
    private function get_imgur_metadata($remote_path) {
        $metadata = get_option('mcib_imgur_metadata', array());
        return isset($metadata[$remote_path]) ? $metadata[$remote_path] : false;
    }
    
    /**
     * 删除Imgur元数据
     *
     * @param string $remote_path 远程存储路径
     */
    private function delete_imgur_metadata($remote_path) {
        $metadata = get_option('mcib_imgur_metadata', array());
        if (isset($metadata[$remote_path])) {
            unset($metadata[$remote_path]);
            update_option('mcib_imgur_metadata', $metadata);
        }
    }
}