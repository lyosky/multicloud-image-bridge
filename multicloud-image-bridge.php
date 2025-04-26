<?php
/**
 * Plugin Name: MultiCloud Image Bridge
 * Plugin URI: https://liyongs.com/multicloud-image-bridge
 * Description: 一个WordPress插件，允许用户在上传图片时选择不同的云存储服务（阿里云OSS、AWS S3、Cloudflare R2、GitHub+jsDelivr、Imgur等）。
 * Version: 1.0.0
 * Author: lyosky
 * Author URI: https://liyongs.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: multicloud-image-bridge
 * Domain Path: /languages
 */

// 如果直接访问此文件，则中止执行
if (!defined('WPINC')) {
    die;
}

// 定义插件版本
define('MCIB_VERSION', '1.0.0');

// 定义插件目录路径
define('MCIB_PLUGIN_DIR', plugin_dir_path(__FILE__));

// 定义插件URL
define('MCIB_PLUGIN_URL', plugin_dir_url(__FILE__));

// 加载必要的文件
require_once MCIB_PLUGIN_DIR . 'includes/class-mcib-core.php';
require_once MCIB_PLUGIN_DIR . 'includes/class-mcib-admin.php';
require_once MCIB_PLUGIN_DIR . 'includes/storage/class-mcib-storage-interface.php';
require_once MCIB_PLUGIN_DIR . 'includes/storage/class-mcib-aliyun-oss.php';
require_once MCIB_PLUGIN_DIR . 'includes/storage/class-mcib-aws-s3.php';
require_once MCIB_PLUGIN_DIR . 'includes/storage/class-mcib-cloudflare-r2.php';
require_once MCIB_PLUGIN_DIR . 'includes/storage/class-mcib-github-jsdelivr.php';
require_once MCIB_PLUGIN_DIR . 'includes/storage/class-mcib-imgur.php';

/**
 * 插件激活时执行的函数
 */
function activate_multicloud_image_bridge() {
    // 创建必要的数据库表和选项
    add_option('mcib_settings', array(
        'default_storage' => 'wordpress', // 默认使用WordPress本地存储
        'enabled_storages' => array('wordpress'),
        'aliyun_oss' => array(
            'access_key' => '',
            'access_secret' => '',
            'bucket' => '',
            'endpoint' => '',
            'url_prefix' => ''
        ),
        'aws_s3' => array(
            'access_key' => '',
            'access_secret' => '',
            'bucket' => '',
            'region' => '',
            'url_prefix' => ''
        ),
        'cloudflare_r2' => array(
            'account_id' => '',
            'access_key' => '',
            'access_secret' => '',
            'bucket' => '',
            'url_prefix' => ''
        ),
        'github_jsdelivr' => array(
            'token' => '',
            'repo' => '',
            'branch' => 'main',
            'path' => 'images',
            'url_prefix' => ''
        ),
        'imgur' => array(
            'client_id' => '',
            'access_token' => '',
            'url_prefix' => ''
        )
    ));
}

/**
 * 插件停用时执行的函数
 */
function deactivate_multicloud_image_bridge() {
    // 清理临时文件等
}

/**
 * 插件卸载时执行的函数
 */
function uninstall_multicloud_image_bridge() {
    // 删除插件创建的选项和数据
    delete_option('mcib_settings');
}

// 注册激活、停用和卸载钩子
register_activation_hook(__FILE__, 'activate_multicloud_image_bridge');
register_deactivation_hook(__FILE__, 'deactivate_multicloud_image_bridge');
register_uninstall_hook(__FILE__, 'uninstall_multicloud_image_bridge');

/**
 * 启动插件
 */
function run_multicloud_image_bridge() {
    // 初始化核心类
    $plugin = new MCIB_Core();
    $plugin->run();
}

// 运行插件
run_multicloud_image_bridge();