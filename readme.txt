=== Morden Image Optimizer ===
Contributors: (your-wordpress-org-username)
Tags: image, optimization, performance, bulk, backup, webp
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later

# Morden Image Optimizer

A modern, user-friendly WordPress image optimizer with advanced features including bulk optimization, backups, and auto-updates.

## 🚀 Features

- **Automatic Optimization**: Images are optimized upon upload using the best available method
- **Multiple Optimization Methods**: Supports Imagick, GD Library, reSmush.it API, and TinyPNG API
- **Bulk Optimization**: Optimize all existing images with a single click
- **Original Image Backup**: Keep original images safe with built-in backup functionality
- **Auto-Updates**: Seamless updates directly from GitHub
- **Media Library Integration**: View optimization status and savings directly in Media Library
- **Comprehensive Logging**: Detailed logging for debugging and monitoring

## 📦 Installation

### Method 1: Download from Releases
1. Go to [Releases](https://github.com/sadewadee/morden-image-optimizer/releases)
2. Download the latest `morden-image-optimizer.zip`
3. Upload to WordPress via Plugins > Add New > Upload Plugin
4. Activate the plugin

### Method 2: Manual Installation
1. Clone this repository
2. Upload the `morden-image-optimizer` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

## ⚙️ Configuration

1. Go to **Settings > Morden Optimizer**
2. Configure your optimization preferences:
   - Compression quality (recommended: 75-85)
   - API service selection
   - Enable/disable original image backup
3. For existing images, use **Media > Bulk Optimize**

## 🔧 Development

### Requirements
- PHP 7.4+
- WordPress 5.8+
- Composer (for development dependencies)

### Setup Development Environment
