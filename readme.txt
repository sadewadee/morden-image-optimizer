=== Morden Image Optimizer ===
Contributors: mordenteam
Donate link: https://mordenhost.com/donate
Tags: image optimization, compress images, webp, bulk optimize, performance
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A modern, user-friendly image optimizer with bulk optimization, backups, and auto-updates. Reduce image file sizes by up to 60% automatically.

== Description ==

**Morden Image Optimizer** is a powerful yet easy-to-use WordPress plugin that automatically optimizes your images to improve website performance while maintaining visual quality.

= Key Features =

* **Automatic optimization** on image upload
* **Bulk optimization** with real-time progress tracking
* **Multiple optimization methods**: reSmush.it API, GD Library, Imagick, TinyPNG
* **Smart file routing**: Large files automatically use cloud processing
* **Original image backup** with one-click restore
* **WebP support** for modern browsers
* **Media Library integration** with optimization status
* **Auto-update system** from GitHub repository

= Optimization Methods =

The plugin intelligently selects the best optimization method:

1. **reSmush.it API** - Free, unlimited, cloud-based optimization
2. **GD Library** - Built-in PHP image processing
3. **Imagick** - Advanced local optimization (with resource limits)
4. **TinyPNG** - Premium API with excellent compression

= Performance Benefits =

* **30-60% file size reduction** on average
* **Faster page load times** and better SEO
* **Reduced bandwidth usage** and storage costs
* **Improved user experience** on mobile devices

= Safety Features =

* **Original image backup** before optimization
* **One-click restore** to original images
* **Automatic cleanup** of old backups
* **Resource limits** to prevent server overload
* **Error recovery** and fallback methods

= Admin Interface =

* **Modern tabbed interface** for easy navigation
* **Real-time statistics** and optimization history
* **System compatibility checks** with recommendations
* **Bulk operations** with pause/resume functionality
* **Detailed logging** for troubleshooting

The plugin is designed to be lightweight, server-friendly, and requires minimal configuration. It works out of the box with sensible defaults while offering advanced options for power users.

== Installation ==

= Automatic Installation =

1. Go to your WordPress admin dashboard
2. Navigate to **Plugins > Add New**
3. Search for "Morden Image Optimizer"
4. Click **Install Now** and then **Activate**

= Manual Installation =

1. Download the plugin zip file
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the downloaded zip file and click **Install Now**
4. Activate the plugin

= After Installation =

1. Go to **Settings > Morden Optimizer**
2. Configure your optimization preferences
3. Enable backup if desired (recommended)
4. Use **Media > Bulk Optimize** for existing images

== Frequently Asked Questions ==

= Is this plugin free? =

Yes, the plugin is completely free. It uses the free reSmush.it API by default, which has no limits or restrictions.

= Will it work on shared hosting? =

Absolutely! The plugin is designed to be server-friendly with resource limits and smart file routing to prevent server overload.

= Can I restore original images? =

Yes, if you enable the backup option, you can restore any optimized image to its original state with one click.

= What image formats are supported? =

The plugin supports JPEG, PNG, GIF, and WebP formats. It automatically detects the format and applies appropriate optimization.

= Does it optimize existing images? =

Yes, use the **Bulk Optimize** feature in **Media > Bulk Optimize** to optimize all existing images in your media library.

= Will it slow down my website? =

No, optimization happens in the background and doesn't affect your website's frontend performance. Large files are processed via cloud API to reduce server load.

= Can I use my own TinyPNG API key? =

Yes, you can configure your TinyPNG API key in the settings for premium compression quality.

= Is it compatible with other plugins? =

Yes, the plugin is designed to work alongside other WordPress plugins and themes without conflicts.

== Screenshots ==

1. Main settings page with tabbed interface for easy navigation
2. Bulk optimization page with real-time progress tracking
3. Media library integration showing optimization status
4. Dashboard with statistics and recent activity
5. System information and compatibility checks

== Changelog ==

= 1.1.0 =
* Added: Smart file size routing (>5MB files use API)
* Added: Enhanced Imagick processing with resource limits
* Added: Configurable logging levels and controls
* Fixed: 503 errors with large image processing
* Fixed: Database table creation issues on activation
* Fixed: JavaScript AJAX errors in admin interface
* Improved: Memory management and garbage collection
* Improved: Error handling and recovery mechanisms

= 1.0.0 =
* Initial release
* Auto-optimization on image upload
* Bulk optimization with progress tracking
* Original image backup and restore
* Multiple optimization methods (reSmush.it, GD, Imagick, TinyPNG)
* Settings interface with validation
* Media Library integration
* Auto-update system from GitHub
* Comprehensive logging and error handling

== Upgrade Notice ==

= 1.1.0 =
Major stability improvements! This version fixes 503 server errors and adds smart file routing for better performance. Recommended for all users.

= 1.0.0 =
Initial release of Morden Image Optimizer. Install now to start optimizing your images automatically!

== Advanced Features ==

= Developer-Friendly =

* **PSR-4 autoloading** for modern code organization
* **Comprehensive hooks** for customization
* **Detailed logging** with configurable levels
* **Performance monitoring** with memory tracking
* **Security layer** with nonce validation

= Technical Specifications =

* **Minimum PHP**: 7.4
* **Minimum WordPress**: 5.8
* **Memory usage**: Optimized for shared hosting
* **Processing speed**: 1-3 seconds per image
* **Batch size**: 3-5 images per batch to prevent timeouts

= Support =

For support, feature requests, or bug reports, please visit our [GitHub repository](https://github.com/sadewadee/morden-image-optimizer) or contact us through our [website](https://mordenhost.com).