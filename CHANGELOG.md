# Changelog

All notable changes to the Press Releases Manager plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2025-09-22

### 🚀 Major SEO Improvements

#### Added
- **🔄 301 Redirects** - Individual press release pages automatically redirect to main press releases page
- **🗺️ Sitemap Exclusion** - Press releases excluded from WordPress core sitemaps and Yoast SEO sitemaps
- **⚙️ Settings Page** - New admin settings page for configuring redirect destinations
- **🔍 Auto-Detection** - Automatically finds pages containing `[press_releases]` shortcode for redirects
- **🔔 Admin Notice** - Informative notice about SEO improvements with dismiss functionality
- **🎯 Smart Fallbacks** - Robust redirect destination detection with multiple fallback options

#### Changed
- **🔍 Search Bar Default** - Search bar now disabled by default in shortcode (previously enabled)
- **📊 SEO Optimization** - All individual press release URLs now consolidate SEO value to main page

#### SEO Benefits
- **✅ Eliminates duplicate content** issues completely
- **✅ Consolidates link equity** to one authoritative page
- **✅ Improves crawl efficiency** for search engines
- **✅ Concentrates page authority** instead of spreading it thin
- **✅ Better user experience** with direct navigation to functional pages

#### Technical Implementation
- Added `template_redirect` hook for seamless 301 redirects
- Added `wp_sitemaps_post_types` filter for WordPress core sitemap exclusion
- Added `wpseo_sitemap_exclude_post_type` filter for Yoast SEO sitemap exclusion
- New admin settings interface under **Press Releases → Settings**
- Intelligent redirect destination detection with database queries

### 🔧 Backward Compatibility
- **No Breaking Changes** - All existing functionality preserved
- **Optional Configuration** - Auto-detection works out of the box
- **User Control** - Manual redirect URL override available in settings

## [1.3.0] - 2025-09-21

### Added
- **🎯 Beginner-Friendly Press Release Interface** - Complete redesign of "Add New Press Release"
- **📊 Live Statistics Dashboard** - Real-time URL count and status updates
- **🗂️ Tabbed Interface** - Three easy methods: Individual URLs, Bulk Import, Manage URLs
- **✅ URL Validation System** - Test URLs before adding with instant feedback
- **👁️ Live Preview** - See URLs as you add them with working links
- **⚙️ URL Management** - Edit, delete, and organize existing URLs visually
- **📋 Enhanced Bulk Import** - Preview before import with error detection
- **🎨 Visual URL Cards** - Organized display with titles and actions
- **📚 Complete Beginner Guide** - Step-by-step tutorial for new users

### Improved
- **User Experience** - No technical knowledge required for press release creation
- **Error Prevention** - Validation and preview before saving
- **URL Organization** - Title-based organization instead of "Untitled URL #47"
- **Visual Feedback** - Color-coded new vs existing URLs
- **Save System** - Enhanced to handle both individual and bulk URL data

### Fixed
- Better error handling for invalid URLs
- Improved data sanitization for security
- Enhanced form submission handling

## [1.2.0] - 2025-09-21

### Added
- **🚀 Shortcode Builder Interface** - Visual shortcode generator in WordPress admin
- **📋 Enhanced Shortcode Options** - 12 new customization parameters
- **🔍 Search Functionality** - Optional search box for press releases
- **🎯 Advanced Filtering** - Show/hide specific press releases by ID
- **📖 Complete Shortcode Guide** - Beginner-friendly documentation
- **⚙️ Display Customization** - Control dates, counts, descriptions, title tags
- **✂️ Excerpt Control** - Limit description length
- **🎨 Flexible Layout Options** - Multiple display configurations

### Changed
- Updated plugin author to "Inbound Interactive"
- Enhanced shortcode system with backward compatibility
- Improved admin interface with dedicated shortcode builder page

### Fixed
- Better parameter handling in shortcode display
- Enhanced security with proper escaping for new attributes

## [1.1.0] - 2025-09-21

### Added
- Version control system with auto-updater functionality
- GitHub-based automatic updates
- Plugin update notifications in WordPress admin
- Proper semantic versioning implementation

### Changed
- Updated plugin version to 1.1.0
- Enhanced update mechanism configuration

### Fixed
- Auto-updater configuration for GitHub integration

## [1.0.0] - 2025-09-15

### Added
- Initial release of Press Releases Manager
- Custom post type for press releases
- AJAX-powered accordion interface
- Bulk URL import functionality
- Mobile-responsive design
- Copy URL functionality (individual and bulk)
- WordPress shortcode support
- Custom database table for URL management
- Security features (nonce verification, input sanitization)