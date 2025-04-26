<?php
/**
 * 插件管理界面类
 *
 * @package MultiCloud Image Bridge
 */

class MCIB_Admin {
    /**
     * 核心插件实例
     *
     * @var MCIB_Core
     */
    protected $core;

    /**
     * 初始化管理界面
     *
     * @param MCIB_Core $core 核心插件实例
     */
    public function __construct($core) {
        $this->core = $core;
    }

    /**
     * 设置钩子和初始化管理界面
     */
    public function init() {
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 注册设置
        add_action('admin_init', array($this, 'register_settings'));
        
        // 添加设置链接
        add_filter('plugin_action_links_multicloud-image-bridge/multicloud-image-bridge.php', array($this, 'add_settings_link'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_options_page(
            '多云图床桥接设置', // 页面标题
            '多云图床桥接', // 菜单标题
            'manage_options', // 权限
            'multicloud-image-bridge', // 菜单slug
            array($this, 'display_settings_page') // 回调函数
        );
    }

    /**
     * 注册插件设置
     */
    public function register_settings() {
        register_setting(
            'mcib_settings_group', // 选项组
            'mcib_settings', // 选项名称
            array($this, 'sanitize_settings') // 验证回调
        );

        // 添加设置区域 - 常规设置
        add_settings_section(
            'mcib_general_section', // ID
            '常规设置', // 标题
            array($this, 'general_section_callback'), // 回调
            'multicloud-image-bridge' // 页面
        );

        // 添加设置字段 - 默认存储
        add_settings_field(
            'mcib_default_storage', // ID
            '默认存储服务', // 标题
            array($this, 'default_storage_callback'), // 回调
            'multicloud-image-bridge', // 页面
            'mcib_general_section' // 区域
        );

        // 添加设置字段 - 启用的存储服务
        add_settings_field(
            'mcib_enabled_storages', // ID
            '启用的存储服务', // 标题
            array($this, 'enabled_storages_callback'), // 回调
            'multicloud-image-bridge', // 页面
            'mcib_general_section' // 区域
        );

        // 添加设置字段 - 文件名规则
        add_settings_field(
            'mcib_filename_rule',
            '文件名生成规则',
            array($this, 'filename_rule_callback'),
            'multicloud-image-bridge',
            'mcib_general_section'
        );

        // 添加设置字段 - 目录结构
        add_settings_field(
            'mcib_directory_structure',
            '存储目录结构',
            array($this, 'directory_structure_callback'),
            'multicloud-image-bridge',
            'mcib_general_section'
        );

        // 添加各个存储服务的设置区域
        $this->register_storage_settings();
    }

    /**
     * 注册各个存储服务的设置
     */
    private function register_storage_settings() {
        // 阿里云OSS设置
        add_settings_section(
            'mcib_aliyun_oss_section', // ID
            '阿里云OSS设置', // 标题
            array($this, 'aliyun_oss_section_callback'), // 回调
            'multicloud-image-bridge' // 页面
        );

        // 阿里云OSS字段
        $aliyun_fields = array(
            'access_key' => 'Access Key',
            'access_secret' => 'Access Secret',
            'bucket' => 'Bucket名称',
            'endpoint' => 'Endpoint（地域节点）',
            'url_prefix' => 'URL前缀（可选）'
        );

        foreach ($aliyun_fields as $key => $label) {
            add_settings_field(
                'mcib_aliyun_oss_' . $key,
                $label,
                array($this, 'storage_field_callback'),
                'multicloud-image-bridge',
                'mcib_aliyun_oss_section',
                array('storage' => 'aliyun_oss', 'field' => $key, 'type' => ($key === 'access_secret' ? 'password' : 'text'))
            );
        }

        // AWS S3设置
        add_settings_section(
            'mcib_aws_s3_section',
            'AWS S3设置',
            array($this, 'aws_s3_section_callback'),
            'multicloud-image-bridge'
        );

        // AWS S3字段
        $aws_fields = array(
            'access_key' => 'Access Key',
            'access_secret' => 'Access Secret',
            'bucket' => 'Bucket名称',
            'region' => '区域（Region）',
            'url_prefix' => 'URL前缀（可选）'
        );

        foreach ($aws_fields as $key => $label) {
            add_settings_field(
                'mcib_aws_s3_' . $key,
                $label,
                array($this, 'storage_field_callback'),
                'multicloud-image-bridge',
                'mcib_aws_s3_section',
                array('storage' => 'aws_s3', 'field' => $key, 'type' => ($key === 'access_secret' ? 'password' : 'text'))
            );
        }

        // Cloudflare R2设置
        add_settings_section(
            'mcib_cloudflare_r2_section',
            'Cloudflare R2设置',
            array($this, 'cloudflare_r2_section_callback'),
            'multicloud-image-bridge'
        );

        // Cloudflare R2字段
        $r2_fields = array(
            'account_id' => 'Account ID',
            'access_key' => 'Access Key',
            'access_secret' => 'Access Secret',
            'bucket' => 'Bucket名称',
            'url_prefix' => 'URL前缀（可选）'
        );

        foreach ($r2_fields as $key => $label) {
            add_settings_field(
                'mcib_cloudflare_r2_' . $key,
                $label,
                array($this, 'storage_field_callback'),
                'multicloud-image-bridge',
                'mcib_cloudflare_r2_section',
                array('storage' => 'cloudflare_r2', 'field' => $key, 'type' => ($key === 'access_secret' ? 'password' : 'text'))
            );
        }

        // GitHub+jsDelivr设置
        add_settings_section(
            'mcib_github_jsdelivr_section',
            'GitHub+jsDelivr设置',
            array($this, 'github_jsdelivr_section_callback'),
            'multicloud-image-bridge'
        );

        // GitHub+jsDelivr字段
        $github_fields = array(
            'token' => 'GitHub Personal Access Token',
            'repo' => '仓库名称（格式：用户名/仓库名）',
            'branch' => '分支名称',
            'path' => '图片存储路径',
            'url_prefix' => 'URL前缀（可选，留空则使用jsDelivr CDN）'
        );

        foreach ($github_fields as $key => $label) {
            add_settings_field(
                'mcib_github_jsdelivr_' . $key,
                $label,
                array($this, 'storage_field_callback'),
                'multicloud-image-bridge',
                'mcib_github_jsdelivr_section',
                array('storage' => 'github_jsdelivr', 'field' => $key, 'type' => ($key === 'token' ? 'password' : 'text'))
            );
        }

        // Imgur设置
        add_settings_section(
            'mcib_imgur_section',
            'Imgur设置',
            array($this, 'imgur_section_callback'),
            'multicloud-image-bridge'
        );

        // Imgur字段
        $imgur_fields = array(
            'client_id' => 'Client ID',
            'access_token' => 'Access Token',
            'url_prefix' => 'URL前缀'
        );
        
        $imgur_descriptions = array(
            'access_token' => '可选。如果您需要上传到自己的Imgur账户，请提供Access Token。',
            'url_prefix' => '可选。如果您使用自定义域名访问Imgur图片，请在此处填写（例如：https://images.example.com）。'
        );

        foreach ($imgur_fields as $key => $label) {
            $args = array(
                'storage' => 'imgur', 
                'field' => $key, 
                'type' => ($key === 'access_token' ? 'password' : 'text')
            );
            
            // 添加描述信息（如果有）
            if (isset($imgur_descriptions[$key])) {
                $args['description'] = $imgur_descriptions[$key];
            }
            
            add_settings_field(
                'mcib_imgur_' . $key,
                $label,
                array($this, 'storage_field_callback'),
                'multicloud-image-bridge',
                'mcib_imgur_section',
                $args
            );
        }
    }

    /**
     * 常规设置区域回调
     */
    public function general_section_callback() {
        echo '<p>配置多云图床桥接的基本设置。</p>';
    }

    /**
     * 文件名规则回调函数
     */
    public function filename_rule_callback() {
        $settings = $this->core->get_settings();
        $current_rule = isset($settings['filename_rule']) ? $settings['filename_rule'] : 'original';

        $options = array(
            'original' => '保留原始文件名',
            'timestamp' => '时间戳+随机数（36进制）',
            'md5' => 'MD5哈希',
            'sha1' => 'SHA1哈希',
            'uuid' => 'UUID v4'
        );

        echo '<select name="mcib_settings[filename_rule]" id="mcib_filename_rule">';
        foreach ($options as $value => $label) {
            $selected = $value === $current_rule ? 'selected' : '';
            echo sprintf('<option value="%s" %s>%s</option>', 
                esc_attr($value), 
                $selected,
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">选择生成上传文件名的规则（注意：修改规则不会影响已存在的文件）</p>';
    }

    /**
     * 目录结构回调函数
     */
    public function directory_structure_callback() {
        $settings = $this->core->get_settings();
        $current_structure = isset($settings['directory_structure']) ? $settings['directory_structure'] : 'img/Y/m/d';

        echo '<input type="text" name="mcib_settings[directory_structure]" 
              value="' . esc_attr($current_structure) . '"
              class="regular-text code"
              placeholder="例如：img/Y/m/d">';
        echo '<p class="description">可用变量：Y=年，m=月，d=日，H=时，i=分，s=秒</p>';
        echo '<p class="description">建议格式：img/Y/m（按年月分类存储）</p>';
    }

    /**
     * 阿里云OSS设置区域回调
     */
    public function aliyun_oss_section_callback() {
        echo '<p>配置阿里云对象存储服务（OSS）的设置。</p>';
    }

    /**
     * AWS S3设置区域回调
     */
    public function aws_s3_section_callback() {
        echo '<p>配置亚马逊AWS S3对象存储的设置。</p>';
    }

    /**
     * Cloudflare R2设置区域回调
     */
    public function cloudflare_r2_section_callback() {
        echo '<p>配置Cloudflare R2对象存储的设置。</p>';
    }

    /**
     * GitHub+jsDelivr设置区域回调
     */
    public function github_jsdelivr_section_callback() {
        echo '<p>配置GitHub+jsDelivr作为图床的设置。</p>';
    }
    
    /**
     * Imgur设置区域回调
     */
    public function imgur_section_callback() {
        echo '<p>配置Imgur存储服务的参数。您需要在<a href="https://api.imgur.com/oauth2/addclient" target="_blank">Imgur开发者平台</a>创建应用并获取Client ID。</p>';
    }

    /**
     * 默认存储回调
     */
    public function default_storage_callback() {
        $settings = $this->core->get_settings();
        $default_storage = isset($settings['default_storage']) ? $settings['default_storage'] : 'wordpress';
        $available_storages = $this->core->get_available_storages();

        echo '<select name="mcib_settings[default_storage]" id="mcib_default_storage">';
        foreach ($available_storages as $storage => $label) {
            $selected = ($storage === $default_storage) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($storage) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">选择上传图片时默认使用的存储服务。</p>';
    }

    /**
     * 启用的存储服务回调
     */
    public function enabled_storages_callback() {
        $settings = $this->core->get_settings();
        $enabled_storages = isset($settings['enabled_storages']) ? $settings['enabled_storages'] : array('wordpress');
        $available_storages = $this->core->get_available_storages();

        foreach ($available_storages as $storage => $label) {
            $checked = in_array($storage, $enabled_storages) ? 'checked="checked"' : '';
            echo '<label><input type="checkbox" name="mcib_settings[enabled_storages][]" value="' . esc_attr($storage) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
        }
        echo '<p class="description">选择要启用的存储服务。</p>';
    }

    /**
     * 存储服务字段回调
     *
     * @param array $args 字段参数
     */
    public function storage_field_callback($args) {
        $settings = $this->core->get_settings();
        $storage = $args['storage'];
        $field = $args['field'];
        $type = isset($args['type']) ? $args['type'] : 'text';
        $value = isset($settings[$storage][$field]) ? $settings[$storage][$field] : '';

        echo '<input type="' . esc_attr($type) . '" name="mcib_settings[' . esc_attr($storage) . '][' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text">';
        
        // 如果有描述信息，则显示
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * 验证设置
     *
     * @param array $input 用户输入的设置
     * @return array 验证后的设置
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // 验证文件名规则
        $sanitized['filename_rule'] = isset($input['filename_rule']) 
            ? sanitize_text_field($input['filename_rule'])
            : 'original';

        // 验证目录结构
        $sanitized['directory_structure'] = isset($input['directory_structure'])
            ? sanitize_text_field($input['directory_structure'])
            : 'img/Y/m/d';

        // 验证默认存储
        if (isset($input['default_storage'])) {
            $sanitized['default_storage'] = sanitize_text_field($input['default_storage']);
        } else {
            $sanitized['default_storage'] = 'wordpress';
        }

        // 验证启用的存储服务
        if (isset($input['enabled_storages']) && is_array($input['enabled_storages'])) {
            $sanitized['enabled_storages'] = array_map('sanitize_text_field', $input['enabled_storages']);
        } else {
            $sanitized['enabled_storages'] = array('wordpress');
        }

        // 确保WordPress本地存储始终启用
        if (!in_array('wordpress', $sanitized['enabled_storages'])) {
            $sanitized['enabled_storages'][] = 'wordpress';
        }

        // 验证各个存储服务的设置
        $storage_services = array('aliyun_oss', 'aws_s3', 'cloudflare_r2', 'github_jsdelivr', 'imgur');
        
        foreach ($storage_services as $storage) {
            if (isset($input[$storage]) && is_array($input[$storage])) {
                $sanitized[$storage] = array();
                
                foreach ($input[$storage] as $key => $value) {
                    $sanitized[$storage][$key] = sanitize_text_field($value);
                }
            }
        }

        return $sanitized;
    }

    /**
     * 显示设置页面
     */
    public function display_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // 保存设置消息
        if (isset($_GET['settings-updated'])) {
            add_settings_error('mcib_messages', 'mcib_message', '设置已保存', 'updated');
        }

        // 显示设置错误/更新消息
        settings_errors('mcib_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('mcib_settings_group');
                do_settings_sections('multicloud-image-bridge');
                submit_button('保存设置');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * 添加设置链接到插件页面
     *
     * @param array $links 插件链接数组
     * @return array 修改后的链接数组
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=multicloud-image-bridge">设置</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}