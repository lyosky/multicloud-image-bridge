<?php
/**
 * 存储适配器接口
 *
 * @package MultiCloud Image Bridge
 */

interface MCIB_Storage_Interface {
    /**
     * 上传文件到云存储
     *
     * @param string $file_path 本地文件路径
     * @param string $remote_path 远程存储路径
     * @return array|bool 成功返回包含url的数组，失败返回false
     */
    public function upload_file($file_path, $remote_path);
    
    /**
     * 删除云存储中的文件
     *
     * @param string $remote_path 远程存储路径
     * @return bool 是否删除成功
     */
    public function delete_file($remote_path);
    
    /**
     * 获取文件的URL
     *
     * @param string $remote_path 远程存储路径
     * @return string 文件URL
     */
    public function get_file_url($remote_path);
    
    /**
     * 测试存储配置是否有效
     *
     * @return bool 配置是否有效
     */
    public function test_connection();
}