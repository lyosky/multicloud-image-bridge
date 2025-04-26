# MultiCloud Image Bridge

## 插件介绍

MultiCloud Image Bridge 是一个WordPress插件，允许用户在上传图片时选择不同的云存储服务，包括：

- 阿里云OSS
- AWS S3
- Cloudflare R2
- GitHub+jsDelivr
- Imgur
- WordPress本地存储

通过使用云存储服务，您可以减轻WordPress服务器的存储负担，提高图片加载速度，并获得更好的可扩展性和可靠性。

## 功能特点

- 支持多种云存储服务
- 在媒体上传界面提供存储选择器
- 完整的管理界面，方便配置各种存储服务
- 支持为每个存储服务设置自定义URL前缀
- 自动处理图片缩略图的云存储同步
- 无缝集成到WordPress媒体库

## 安装方法

1. 下载插件压缩包
2. 在WordPress管理后台，进入"插件 > 安装插件"页面
3. 点击"上传插件"按钮，选择下载的压缩包
4. 安装完成后，激活插件

## 配置指南

1. 在WordPress管理后台，进入"设置 > 多云图床桥接"页面
2. 在常规设置中，选择默认存储服务和启用需要的存储服务
3. 为每个启用的存储服务配置相应的参数：
   - 阿里云OSS：填写Access Key、Access Secret、Bucket名称和Endpoint
   - AWS S3：填写Access Key、Access Secret、Bucket名称和区域
   - Cloudflare R2：填写Account ID、Access Key、Access Secret和Bucket名称
   - GitHub+jsDelivr：填写GitHub Token、仓库名称、分支名称和存储路径
   - Imgur：填写Client ID和Access Token（可选）
4. 保存设置后，在媒体上传界面就可以选择使用哪种存储服务了

## 常见问题

**Q: 上传图片失败怎么办？**

A: 请检查云存储服务的配置信息是否正确，特别是Access Key和Secret是否有效，以及Bucket是否存在。

**Q: 如何使用自定义域名访问图片？**

A: 在存储服务配置中填写URL前缀字段，输入您的自定义域名（例如：https://images.example.com）。

**Q: 插件会自动处理缩略图吗？**

A: 是的，插件会自动将WordPress生成的缩略图也上传到选定的云存储服务中。

**Q: 如何迁移已有的图片到云存储？**

A: 当前版本不支持自动迁移已有图片，仅支持新上传的图片使用云存储。

## 许可证

本插件基于GPL-2.0+许可证发布。