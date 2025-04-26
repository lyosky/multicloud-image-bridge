<?php
/**
 * 插件核心类
 *
 * @package MultiCloud Image Bridge
 */

class MCIB_Core {
    /**
     * 存储适配器实例的数组
     *
     * @var array
     */
    protected $storage_adapters = array();

    /**
     * 当前选择的存储适配器
     *
     * @var string
     */
    protected $current_storage = 'wordpress';

    /**
     * 插件设置
     *
     * @var array
     */
    protected $settings;

    /**
     * 初始化插件
     */
    public function __construct() {
        $this->settings = get_option('mcib_settings', array());
        $this->current_storage = isset($this->settings['default_storage']) ? $this->settings['default_storage'] : 'wordpress';
        
        // 初始化存储适配器
        $this->init_storage_adapters();
    }

    /**
     * 运行插件，设置钩子
     */
    public function run() {
        // 初始化管理界面
        $admin = new MCIB_Admin($this);
        $admin->init();

        // 添加附件钩子
        add_action('add_attachment', array($this, 'handle_attachment_creation'), 10, 1);
        add_action('delete_attachment', array($this, 'handle_attachment_deletion'), 10, 1); // 新增删除钩子
        add_filter('wp_get_attachment_url', array($this, 'get_attachment_url'), 10, 2);
        add_filter('wp_update_attachment_metadata', array($this, 'update_attachment_metadata'), 10, 2);
        
        // 添加媒体库界面的存储选择器
        add_action('post-upload-ui', array($this, 'add_storage_selector'));
    }

    /**
     * 初始化所有启用的存储适配器
     */
    private function init_storage_adapters() {
        // 添加WordPress默认存储
        $this->storage_adapters['wordpress'] = null; // WordPress默认不需要适配器

        // 检查并初始化启用的存储适配器
        $enabled_storages = isset($this->settings['enabled_storages']) ? $this->settings['enabled_storages'] : array('wordpress');

        if (in_array('aliyun_oss', $enabled_storages) && !empty($this->settings['aliyun_oss']['access_key'])) {
            $this->storage_adapters['aliyun_oss'] = new MCIB_Aliyun_OSS($this->settings['aliyun_oss']);
        }

        if (in_array('aws_s3', $enabled_storages) && !empty($this->settings['aws_s3']['access_key'])) {
            $this->storage_adapters['aws_s3'] = new MCIB_AWS_S3($this->settings['aws_s3']);
        }

        if (in_array('cloudflare_r2', $enabled_storages) && !empty($this->settings['cloudflare_r2']['access_key'])) {
            $this->storage_adapters['cloudflare_r2'] = new MCIB_Cloudflare_R2($this->settings['cloudflare_r2']);
        }

        if (in_array('github_jsdelivr', $enabled_storages) && !empty($this->settings['github_jsdelivr']['token'])) {
            $this->storage_adapters['github_jsdelivr'] = new MCIB_GitHub_jsDelivr($this->settings['github_jsdelivr']);
        }

        if (in_array('imgur', $enabled_storages) && !empty($this->settings['imgur']['client_id'])) {
            $this->storage_adapters['imgur'] = new MCIB_Imgur($this->settings['imgur']);
        }
    }

    /**
     * 处理文件上传
     *
     * @param array $upload 上传文件信息
     * @param string $context 上传上下文
     * @return array 修改后的上传信息
     */
    /**
     * 处理附件创建后的云存储上传
     */
    public function handle_attachment_creation($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        $upload = array(
            'file' => $file_path,
            'url' => wp_get_attachment_url($attachment_id),
            'type' => get_post_mime_type($attachment_id),
            'attachment_id' => $attachment_id
        );
        $this->handle_upload($upload, 'upload');
    }

    public function handle_upload($upload, $context) {
        $storage = isset($_POST['mcib_storage']) ? sanitize_text_field($_POST['mcib_storage']) : $this->current_storage;

        if ($storage === 'wordpress' || !isset($this->storage_adapters[$storage])) {
            return $upload;
        }

        // 获取WordPress生成的相对路径
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'].'/', '', $upload['file']); // 新增代码

        $adapter = $this->storage_adapters[$storage];

        // 在调用upload_file前添加
        error_log("尝试上传到云OSS，本地文件路径: " . $upload['file']);
        error_log("相对路径参数: " . $relative_path);
        error_log("存储适配器实例: " . print_r($adapter, true));

        $result = $adapter->upload_file($upload['file'], $relative_path); // 修改此处参数

        if ($result && isset($result['url'])) {
            // 添加错误日志记录
            error_log("云存储上传成功，URL: " . $result['url']);
            // 保存原始文件路径和云存储信息到附件元数据
            add_post_meta($upload['attachment_id'], '_mcib_storage', $storage, true);
            add_post_meta($upload['attachment_id'], '_mcib_original_file', $upload['file'], true);
            add_post_meta($upload['attachment_id'], '_mcib_cloud_url', $result['url'], true);

            // 更新URL
            $upload['url'] = $result['url'];
        }
        else {
            error_log("云存储上传失败，响应数据: " . print_r($result, true));
        }

        return $upload;
    }

    /**
     * 获取附件URL
     *
     * @param string $url 原始URL
     * @param int $attachment_id 附件ID
     * @return string 可能修改后的URL
     */
    public function get_attachment_url($url, $attachment_id) {
        $storage = get_post_meta($attachment_id, '_mcib_storage', true);
        $cloud_url = get_post_meta($attachment_id, '_mcib_cloud_url', true);

        if ($storage && $storage !== 'wordpress' && $cloud_url) {
            return $cloud_url;
        }

        return $url;
    }

    /**
     * 更新附件元数据时处理缩略图等
     *
     * @param array $metadata 附件元数据
     * @param int $attachment_id 附件ID
     * @return array 可能修改后的元数据
     */
    public function update_attachment_metadata($metadata, $attachment_id) {
        $storage = get_post_meta($attachment_id, '_mcib_storage', true);

        // 如果不是云存储，则不做处理
        if (!$storage || $storage === 'wordpress' || !isset($this->storage_adapters[$storage])) {
            return $metadata;
        }

        $adapter = $this->storage_adapters[$storage];
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        // 处理缩略图
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $file_dir = dirname($metadata['file']);
            
            foreach ($metadata['sizes'] as $size => $size_info) {
                $file_path = $base_dir . '/' . $file_dir . '/' . $size_info['file'];
                
                if (file_exists($file_path)) {
                    // 上传缩略图到云存储
                    $result = $adapter->upload_file($file_path, $file_dir . '/' . $size_info['file']);
                    
                    if ($result && isset($result['url'])) {
                        // 保存云存储URL到元数据
                        $metadata['sizes'][$size]['cloud_url'] = $result['url'];
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * 在媒体上传界面添加存储选择器
     */
    public function add_storage_selector() {
        // 获取启用的存储服务
        $enabled_storages = isset($this->settings['enabled_storages']) ? $this->settings['enabled_storages'] : array('wordpress');
        
        // 如果只有WordPress默认存储，则不显示选择器
        if (count($enabled_storages) <= 1 && in_array('wordpress', $enabled_storages)) {
            return;
        }

        // 存储服务名称映射
        $storage_names = array(
            'wordpress' => '本地WordPress',
            'aliyun_oss' => '阿里云OSS',
            'aws_s3' => 'AWS S3',
            'cloudflare_r2' => 'Cloudflare R2',
            'github_jsdelivr' => 'GitHub+jsDelivr',
            'imgur' => 'Imgur'
        );

        echo '<p class="mcib-storage-selector">';
        echo '<label for="mcib-storage">选择图片存储服务：</label>';
        echo '<select name="mcib_storage" id="mcib-storage">';
        
        foreach ($enabled_storages as $storage) {
            if (isset($storage_names[$storage])) {
                $selected = ($storage === $this->current_storage) ? 'selected="selected"' : '';
                echo '<option value="' . esc_attr($storage) . '" ' . $selected . '>' . esc_html($storage_names[$storage]) . '</option>';
            }
        }
        
        echo '</select>';
        echo '</p>';

        // 添加一些CSS样式
        echo '<style>
            .mcib-storage-selector {
                margin: 10px 0;
                padding: 5px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            .mcib-storage-selector label {
                display: inline-block;
                margin-right: 10px;
                font-weight: bold;
            }
        </style>';
    }

    /**
     * 获取插件设置
     *
     * @return array 插件设置
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * 更新插件设置
     *
     * @param array $settings 新的设置
     * @return bool 是否更新成功
     */
    public function update_settings($settings) {
        $this->settings = $settings;
        return update_option('mcib_settings', $settings);
    }

    /**
     * 获取所有可用的存储适配器
     *
     * @return array 存储适配器列表
     */
    public function get_available_storages() {
        return array(
            'wordpress' => '本地WordPress',
            'aliyun_oss' => '阿里云OSS',
            'aws_s3' => 'AWS S3',
            'cloudflare_r2' => 'Cloudflare R2',
            'github_jsdelivr' => 'GitHub+jsDelivr',
            'imgur' => 'Imgur'
        );
    }

    /**
     * 处理附件删除
     */
    public function handle_attachment_deletion($attachment_id) {
        $storage = get_post_meta($attachment_id, '_mcib_storage', true);
        $original_file = get_post_meta($attachment_id, '_mcib_original_file', true);
        $metadata = wp_get_attachment_metadata($attachment_id);

        if ($storage && $storage !== 'wordpress' && isset($this->storage_adapters[$storage])) {
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'];
            
            // 删除原始文件
            $relative_path = str_replace($base_dir.'/', '', $original_file);
            $this->storage_adapters[$storage]->delete_file($relative_path);
            
            // 删除缩略图
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                $file_dir = dirname($relative_path);
                
                foreach ($metadata['sizes'] as $size_info) {
                    $thumbnail_path = $file_dir . '/' . $size_info['file'];
                    $this->storage_adapters[$storage]->delete_file($thumbnail_path);
                    error_log("已删除缩略图: " . $thumbnail_path);
                }
            }
        }
    }
}