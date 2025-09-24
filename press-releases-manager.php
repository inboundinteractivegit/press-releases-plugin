<?php
/**
 * Plugin Name: PressStack
 * Plugin URI: https://github.com/inboundinteractivegit/press-releases-plugin
 * Description: Free press releases management with AJAX-loaded URLs, advanced security, and beginner-friendly interface. Manage hundreds of press release URLs with SEO optimization and comprehensive protection. Support our development with a donation!
 * Version: 1.5.5
 * Author: Inbound Interactive
 * Author URI: https://inboundinteractive.com
 * Text Domain: pressstack
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8.2
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Donate link: https://github.com/sponsors/inboundinteractivegit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PressStack {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('template_redirect', array($this, 'redirect_individual_press_releases'));
        add_filter('wp_sitemaps_post_types', array($this, 'exclude_from_sitemap'));
        add_filter('wpseo_sitemap_exclude_post_type', array($this, 'exclude_from_yoast_sitemap'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_load_press_release_urls', array($this, 'ajax_load_urls'));
        add_action('wp_ajax_nopriv_load_press_release_urls', array($this, 'ajax_load_urls'));
        add_shortcode('press_releases', array($this, 'shortcode_display'));

        // Enable auto-updates and admin features
        if (is_admin()) {
            require_once plugin_dir_path(__FILE__) . 'plugin-updater.php';
            new PressReleasesUpdater(__FILE__, 'inboundinteractivegit', 'press-releases-plugin');
            add_action('admin_notices', array($this, 'show_seo_update_notice'));
            add_action('wp_ajax_dismiss_seo_notice', array($this, 'dismiss_seo_notice'));

            // Donation system
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_donation_link'));
            add_action('admin_notices', array($this, 'show_donation_notice'));
            add_action('wp_ajax_dismiss_donation_notice', array($this, 'dismiss_donation_notice'));

            // Pro upgrade integration
            add_action('admin_notices', array($this, 'show_pro_upgrade_notices'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_pro_upgrade_link'));
            add_action('admin_menu', array($this, 'add_pro_upgrade_menu'));
            add_action('wp_ajax_dismiss_pro_notice', array($this, 'dismiss_pro_notice'));
        }
    }

    public function init() {
        $this->create_press_release_post_type();
        // Database table creation moved to activation hook
    }

    /**
     * Create custom post type for press releases
     */
    public function create_press_release_post_type() {
        register_post_type('press_release', array(
            'public' => true,
            'label'  => 'Press Releases',
            'labels' => array(
                'name' => 'Press Releases',
                'singular_name' => 'Press Release',
                'add_new_item' => 'Add New Press Release',
                'edit_item' => 'Edit Press Release'
            ),
            'supports' => array('title', 'editor', 'custom-fields'),
            'menu_icon' => 'dashicons-megaphone',
            'show_in_rest' => true
        ));
    }

    /**
     * Plugin activation hook
     */
    public function activate_plugin() {
        // Create database table on activation only
        $this->create_database_table();

        // Create press release post type for rewrite rules
        $this->create_press_release_post_type();

        // Flush rewrite rules to ensure pretty permalinks work
        flush_rewrite_rules();

        // Set plugin version
        update_option('pressstack_version', '1.5.5');
        update_option('pressstack_activation_time', current_time('mysql'));
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate_plugin() {
        // Flush rewrite rules to clean up
        flush_rewrite_rules();

        // Clean up transients (but keep data for reactivation)
        $this->cleanup_transients();
    }

    /**
     * Clean up transients and cache
     */
    private function cleanup_transients() {
        // Clean up rate limiting transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_rate_limit_%'
             OR option_name LIKE '_transient_timeout_rate_limit_%'"
        );
    }

    /**
     * Create database table for URLs (only on activation)
     */
    public function create_database_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'press_release_urls';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            press_release_id bigint(20) NOT NULL,
            url text NOT NULL,
            title varchar(255) DEFAULT '',
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY press_release_id (press_release_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Redirect individual press release pages to main press releases page
     */
    public function redirect_individual_press_releases() {
        if (is_singular('press_release')) {
            // Get the main press releases page URL
            $redirect_url = $this->get_press_releases_page_url();

            if ($redirect_url) {
                wp_redirect($redirect_url, 301);
                exit();
            }
        }
    }

    /**
     * Get the URL of the page containing the press releases shortcode
     */
    public function get_press_releases_page_url() {
        // First check if there's a custom option set
        $custom_url = get_option('press_releases_redirect_url');
        if ($custom_url) {
            return $custom_url;
        }

        // Ensure WordPress functions are available
        if (!function_exists('has_shortcode') || !function_exists('get_posts')) {
            return home_url('/');
        }

        // Search for a page containing the [press_releases] shortcode
        $pages = get_posts(array(
            'post_type' => array('page', 'post'),
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));

        if (!empty($pages)) {
            foreach ($pages as $page) {
                if (has_shortcode($page->post_content, 'press_releases')) {
                    return get_permalink($page->ID);
                }
            }
        }

        // Fallback: search all published pages/posts for the shortcode
        global $wpdb;
        $result = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND (post_type = 'page' OR post_type = 'post')
             AND post_content LIKE '%[press_releases%'
             LIMIT 1"
        );

        if ($result) {
            return get_permalink($result);
        }

        // Ultimate fallback: redirect to home page
        return home_url('/');
    }

    /**
     * Exclude press releases from WordPress core sitemaps
     */
    public function exclude_from_sitemap($post_types) {
        unset($post_types['press_release']);
        return $post_types;
    }

    /**
     * Exclude press releases from Yoast SEO sitemaps
     */
    public function exclude_from_yoast_sitemap($excluded, $post_type) {
        if ($post_type === 'press_release') {
            return true;
        }
        return $excluded;
    }

    /**
     * Show admin notice about SEO improvements in v1.4.0
     */
    public function show_seo_update_notice() {
        if (get_option('press_releases_seo_notice_dismissed')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['edit-press_release', 'press_release', 'press_release_page_press-releases-settings'])) {
            return;
        }

        ?>
        <div class="notice notice-success is-dismissible" id="press-releases-seo-notice">
            <h3>🚀 Press Releases v1.4.0 - Major SEO Improvements!</h3>
            <p><strong>Your press releases are now SEO-optimized:</strong></p>
            <ul style="margin-left: 20px;">
                <li>✅ <strong>301 Redirects:</strong> Individual press release pages redirect to your main page</li>
                <li>✅ <strong>Sitemap Exclusion:</strong> Search engines won't index duplicate pages</li>
                <li>✅ <strong>Search Bar Hidden:</strong> Cleaner default appearance</li>
                <li>✅ <strong>Link Equity Consolidation:</strong> All SEO power concentrated on one page</li>
            </ul>
            <p>
                <a href="<?php echo admin_url('edit.php?post_type=press_release&page=press-releases-settings'); ?>" class="button button-primary">⚙️ View Settings</a>
                <button type="button" class="button" id="dismiss-seo-notice">Dismiss</button>
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#dismiss-seo-notice, .notice-dismiss').click(function() {
                $.post(ajaxurl, {
                    action: 'dismiss_seo_notice',
                    nonce: '<?php echo wp_create_nonce('dismiss_seo_notice'); ?>'
                });
                $('#press-releases-seo-notice').fadeOut();
            });
        });
        </script>
        <?php
    }

    /**
     * Dismiss SEO update notice
     */
    public function dismiss_seo_notice() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dismiss_seo_notice')) {
            wp_die('Security check failed');
        }
        update_option('press_releases_seo_notice_dismissed', true);
        wp_die();
    }

    /**
     * Rate limiting check
     */
    private function check_rate_limit($action, $limit = 10, $window = 60) {
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $key = "rate_limit_{$action}_{$user_id}_{$ip_address}";

        $current_count = get_transient($key);

        if ($current_count === false) {
            set_transient($key, 1, $window);
            return true;
        }

        if ($current_count >= $limit) {
            return false;
        }

        set_transient($key, $current_count + 1, $window);
        return true;
    }

    /**
     * Get client IP address securely
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Sanitize and validate URL
     */
    private function sanitize_url_input($url) {
        // Remove any potentially dangerous characters
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check for allowed protocols
        $allowed_protocols = array('http', 'https');
        $protocol = parse_url($url, PHP_URL_SCHEME);

        if (!in_array($protocol, $allowed_protocols)) {
            return false;
        }

        // Block potentially dangerous domains/patterns
        $blocked_patterns = array(
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            'file://',
            'javascript:',
            'data:',
            'vbscript:'
        );

        foreach ($blocked_patterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return false;
            }
        }

        return $url;
    }

    /**
     * Enhanced input sanitization
     */
    private function sanitize_text_input($input, $max_length = 255) {
        $input = trim($input);
        $input = substr($input, 0, $max_length);
        $input = sanitize_text_field($input);

        // Remove potentially dangerous HTML/script tags
        $input = wp_kses($input, array());

        return $input;
    }

    /**
     * Check user capabilities with additional security
     */
    private function verify_user_permissions($capability = 'edit_posts') {
        if (!is_user_logged_in()) {
            return false;
        }

        if (!current_user_can($capability)) {
            return false;
        }

        // Additional check for suspicious activity
        $user_id = get_current_user_id();
        $suspicious_key = "suspicious_activity_{$user_id}";

        if (get_transient($suspicious_key)) {
            return false;
        }

        return true;
    }

    /**
     * Log security events
     */
    private function log_security_event($event, $details = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'details' => $details
        );

        error_log('Press Releases Security: ' . json_encode($log_entry));
    }

    /**
     * Add donation link to plugin actions
     */
    public function add_donation_link($links) {
        $donation_link = '<a href="https://github.com/sponsors/inboundinteractivegit" target="_blank" style="color: #d63638; font-weight: bold;">❤️ Sponsor</a>';
        array_unshift($links, $donation_link);
        return $links;
    }

    /**
     * Show contextual donation notice
     */
    public function show_donation_notice() {
        $screen = get_current_screen();

        // Only show on Press Releases pages
        if (!$screen || strpos($screen->id, 'press_release') === false) {
            return;
        }

        // Check if user has been using the plugin (has press releases)
        $press_release_count = wp_count_posts('press_release');
        $total_releases = $press_release_count->publish + $press_release_count->draft;

        // Show notice if user has 3+ press releases and hasn't dismissed
        if ($total_releases >= 3 && !get_option('pressstack_donation_dismissed')) {
            ?>
            <div class="notice notice-info is-dismissible" id="pressstack-donation-notice">
                <p>
                    <strong>🎉 You're using PressStack actively!</strong>
                    If you find it helpful, consider supporting our development to keep it free and improving.
                    <a href="https://github.com/sponsors/inboundinteractivegit" target="_blank" class="button button-primary" style="margin-left: 10px;">❤️ GitHub Sponsor</a>
                    <a href="https://www.buymeacoffee.com/inboundinteractive" target="_blank" class="button" style="margin-left: 5px;">☕ Buy us a coffee</a>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $(document).on('click', '#pressstack-donation-notice .notice-dismiss', function() {
                    $.post(ajaxurl, {
                        action: 'dismiss_donation_notice',
                        nonce: '<?php echo wp_create_nonce('dismiss_donation_notice'); ?>'
                    });
                });
            });
            </script>
            <?php
        }

        // Show success message after saving (contextual)
        if (isset($_GET['message']) && $_GET['message'] == '1' && $screen->base == 'post') {
            ?>
            <div class="notice notice-success">
                <p>
                    <strong>✅ Press release saved successfully!</strong>
                    Love PressStack? <a href="https://github.com/sponsors/inboundinteractivegit" target="_blank">Support our development</a> to keep it free! ❤️
                </p>
            </div>
            <?php
        }
    }

    /**
     * Dismiss donation notice via AJAX
     */
    public function dismiss_donation_notice() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dismiss_donation_notice')) {
            wp_die('Security check failed');
        }
        update_option('pressstack_donation_dismissed', true);
        wp_die();
    }

    /**
     * Show Pro upgrade notices
     */
    public function show_pro_upgrade_notices() {
        // Don't show if Pro is already active
        if (class_exists('PressStackPro')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'press_release') === false) {
            return;
        }

        // Check if user has been using the plugin actively
        $press_release_count = wp_count_posts('press_release');
        $total_releases = $press_release_count->publish + $press_release_count->draft;

        // Show upgrade notice for active users (5+ press releases)
        if ($total_releases >= 5 && !get_option('pressstack_pro_notice_dismissed')) {
            ?>
            <div class="notice notice-info is-dismissible" id="pressstack-pro-notice">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 48px;">🚀</div>
                    <div>
                        <h3 style="margin: 0 0 10px;">Ready for PressStack Pro?</h3>
                        <p style="margin: 0 0 15px;">
                            <strong>You're managing <?php echo $total_releases; ?> press releases!</strong>
                            Unlock advanced features to supercharge your press release management.
                        </p>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="https://pressstack.pro" target="_blank" class="button button-primary">
                                ⭐ View Pro Features
                            </a>
                            <a href="#" class="button" onclick="dismissProNotice()">Maybe Later</a>
                        </div>
                    </div>
                </div>
            </div>
            <script>
            function dismissProNotice() {
                jQuery.post(ajaxurl, {
                    action: 'dismiss_pro_notice',
                    nonce: '<?php echo wp_create_nonce('dismiss_pro_notice'); ?>'
                });
                jQuery('#pressstack-pro-notice').fadeOut();
            }
            jQuery(document).ready(function($) {
                $(document).on('click', '#pressstack-pro-notice .notice-dismiss', function() {
                    dismissProNotice();
                });
            });
            </script>
            <?php
        }

        // Show feature-specific teasers on relevant pages
        if ($screen->id === 'press_release_page_press-releases-settings') {
            $this->show_analytics_teaser();
        }
    }

    /**
     * Add Pro upgrade link to plugin actions
     */
    public function add_pro_upgrade_link($links) {
        // Don't show if Pro is already active
        if (class_exists('PressStackPro')) {
            return $links;
        }

        $pro_link = '<a href="https://pressstack.pro" target="_blank" style="color: #d63384; font-weight: bold;">⭐ Upgrade to Pro</a>';
        array_unshift($links, $pro_link);
        return $links;
    }

    /**
     * Add Pro upgrade menu
     */
    public function add_pro_upgrade_menu() {
        // Don't show if Pro is already active
        if (class_exists('PressStackPro')) {
            return;
        }

        add_submenu_page(
            'edit.php?post_type=press_release',
            'Upgrade to Pro',
            '⭐ Upgrade to Pro',
            'manage_options',
            'pressstack-upgrade',
            array($this, 'display_upgrade_page')
        );
    }

    /**
     * Display upgrade page
     */
    public function display_upgrade_page() {
        ?>
        <div class="wrap">
            <h1>🚀 Upgrade to PressStack Pro</h1>

            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 12px; margin: 20px 0; text-align: center;">
                <h2 style="color: white; margin: 0 0 20px;">Transform Your Press Release Management</h2>
                <p style="font-size: 18px; margin: 0 0 30px; opacity: 0.9;">Get advanced analytics, custom templates, email distribution, and more!</p>
                <a href="https://pressstack.pro" target="_blank" class="button button-hero" style="background: #fff; color: #667eea; border: none; padding: 15px 30px; font-size: 16px; font-weight: bold;">
                    🛒 Get PressStack Pro
                </a>
            </div>

            <div class="pro-features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin: 30px 0;">

                <div class="feature-card" style="background: white; padding: 25px; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <h3>📊 Advanced Analytics</h3>
                    <ul style="color: #666; line-height: 1.8;">
                        <li>✅ Real-time tracking and reporting</li>
                        <li>✅ Geographic analytics by country/city</li>
                        <li>✅ Click tracking for all URLs</li>
                        <li>✅ Performance metrics dashboard</li>
                        <li>✅ Export reports to CSV/PDF</li>
                    </ul>
                    <div class="feature-demo" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px;">
                        <small><strong>Current:</strong> Basic URL display</small><br>
                        <small><strong>Pro:</strong> Track every click, view, and engagement</small>
                    </div>
                </div>

                <div class="feature-card" style="background: white; padding: 25px; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <h3>🎨 Custom Templates</h3>
                    <ul style="color: #666; line-height: 1.8;">
                        <li>✅ Visual template builder</li>
                        <li>✅ Professional pre-made designs</li>
                        <li>✅ Mobile-responsive layouts</li>
                        <li>✅ Import/export templates</li>
                        <li>✅ Brand customization options</li>
                    </ul>
                    <div class="feature-demo" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px;">
                        <small><strong>Current:</strong> Default accordion layout</small><br>
                        <small><strong>Pro:</strong> Unlimited custom designs</small>
                    </div>
                </div>

                <div class="feature-card" style="background: white; padding: 25px; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <h3>📧 Email Distribution</h3>
                    <ul style="color: #666; line-height: 1.8;">
                        <li>✅ Send to media contacts directly</li>
                        <li>✅ Professional email templates</li>
                        <li>✅ Contact database management</li>
                        <li>✅ Campaign tracking & analytics</li>
                        <li>✅ Bulk email sending</li>
                    </ul>
                    <div class="feature-demo" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px;">
                        <small><strong>Current:</strong> Manual URL sharing</small><br>
                        <small><strong>Pro:</strong> Automated media outreach</small>
                    </div>
                </div>

                <div class="feature-card" style="background: white; padding: 25px; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <h3>🔐 Enterprise Features</h3>
                    <ul style="color: #666; line-height: 1.8;">
                        <li>✅ Priority support & updates</li>
                        <li>✅ Multi-site licensing</li>
                        <li>✅ White-label options</li>
                        <li>✅ API access for integrations</li>
                        <li>✅ Advanced user permissions</li>
                    </ul>
                    <div class="feature-demo" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px;">
                        <small><strong>Current:</strong> Basic functionality</small><br>
                        <small><strong>Pro:</strong> Enterprise-grade features</small>
                    </div>
                </div>

            </div>

            <div class="pricing-section" style="background: #f8f9fa; padding: 30px; border-radius: 10px; margin: 30px 0;">
                <h2 style="text-align: center; margin-bottom: 30px;">💰 Simple, Transparent Pricing</h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">

                    <div class="pricing-card" style="background: white; padding: 25px; border-radius: 10px; border: 2px solid #e5e7eb; text-align: center;">
                        <h3>Single Site</h3>
                        <div style="font-size: 32px; font-weight: bold; color: #2271b1; margin: 15px 0;">$29<span style="font-size: 16px; color: #666;">/mo</span></div>
                        <ul style="text-align: left; color: #666; line-height: 1.8; padding-left: 20px;">
                            <li>1 WordPress site</li>
                            <li>All Pro features</li>
                            <li>Email support</li>
                            <li>Updates included</li>
                        </ul>
                        <a href="https://pressstack.pro/single" target="_blank" class="button button-primary" style="margin-top: 15px;">Get Started</a>
                    </div>

                    <div class="pricing-card" style="background: white; padding: 25px; border-radius: 10px; border: 2px solid #2271b1; text-align: center; position: relative;">
                        <div style="position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: #2271b1; color: white; padding: 5px 15px; border-radius: 15px; font-size: 12px; font-weight: bold;">POPULAR</div>
                        <h3>Multi-Site</h3>
                        <div style="font-size: 32px; font-weight: bold; color: #2271b1; margin: 15px 0;">$79<span style="font-size: 16px; color: #666;">/mo</span></div>
                        <ul style="text-align: left; color: #666; line-height: 1.8; padding-left: 20px;">
                            <li>Up to 5 WordPress sites</li>
                            <li>All Pro features</li>
                            <li>Priority support</li>
                            <li>CRM integrations</li>
                        </ul>
                        <a href="https://pressstack.pro/multi" target="_blank" class="button button-primary" style="margin-top: 15px;">Get Started</a>
                    </div>

                    <div class="pricing-card" style="background: white; padding: 25px; border-radius: 10px; border: 2px solid #e5e7eb; text-align: center;">
                        <h3>Agency</h3>
                        <div style="font-size: 32px; font-weight: bold; color: #2271b1; margin: 15px 0;">$199<span style="font-size: 16px; color: #666;">/mo</span></div>
                        <ul style="text-align: left; color: #666; line-height: 1.8; padding-left: 20px;">
                            <li>Unlimited sites</li>
                            <li>White-label options</li>
                            <li>API access</li>
                            <li>Phone support</li>
                        </ul>
                        <a href="https://pressstack.pro/agency" target="_blank" class="button button-primary" style="margin-top: 15px;">Get Started</a>
                    </div>

                </div>
            </div>

            <div style="background: #e7f3ff; padding: 20px; border-left: 4px solid #2271b1; margin: 30px 0;">
                <h3>🎁 Special Launch Offer</h3>
                <p><strong>Save 30% on your first 3 months!</strong> Use code <code>PRESSSTACK30</code> at checkout.</p>
                <p>✅ 14-day free trial • ✅ Cancel anytime • ✅ 30-day money-back guarantee</p>
            </div>

            <div style="text-align: center; margin: 40px 0;">
                <a href="https://pressstack.pro" target="_blank" class="button button-hero button-primary" style="padding: 15px 40px; font-size: 18px;">
                    🚀 Start Your Free Trial
                </a>
                <p style="margin-top: 15px; color: #666;">
                    Questions? <a href="mailto:support@pressstack.pro">Contact our team</a>
                </p>
            </div>

        </div>
        <?php
    }

    /**
     * Show analytics teaser on settings page
     */
    private function show_analytics_teaser() {
        if (class_exists('PressStackPro')) {
            return;
        }
        ?>
        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px; margin: 20px 0;">
            <h3 style="color: white; margin: 0 0 15px;">📊 Want Analytics for Your Press Releases?</h3>
            <p style="margin: 0 0 15px; opacity: 0.9;">Track clicks, views, geographic data, and performance metrics with PressStack Pro!</p>
            <a href="https://pressstack.pro" target="_blank" class="button" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white;">
                View Analytics Features
            </a>
        </div>
        <?php
    }

    /**
     * Dismiss Pro upgrade notice via AJAX
     */
    public function dismiss_pro_notice() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dismiss_pro_notice')) {
            wp_die('Security check failed');
        }
        update_option('pressstack_pro_notice_dismissed', true);
        wp_die();
    }

    /**
     * Enqueue scripts and styles (only when needed)
     */
    public function enqueue_scripts() {
        // Only enqueue on pages that have the shortcode or are press release pages
        global $post;

        $should_enqueue = false;

        // Check if current page/post has the shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'press_releases')) {
            $should_enqueue = true;
        }

        // Check if it's a press release post type page
        if (is_singular('press_release') || is_post_type_archive('press_release')) {
            $should_enqueue = true;
        }

        // Check if it's the admin and we're on press release pages
        if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'press_release') {
            $should_enqueue = true;
        }

        if (!$should_enqueue) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('press-releases-js', plugin_dir_url(__FILE__) . 'press-releases.js', array('jquery'), '1.5.5', true);
        wp_enqueue_style('press-releases-css', plugin_dir_url(__FILE__) . 'press-releases.css', array(), '1.5.5');

        wp_localize_script('press-releases-js', 'press_releases_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('press_releases_nonce')
        ));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        global $post_type;

        // Only load on press release admin pages
        if ($post_type !== 'press_release' && strpos($hook, 'press_release') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('press-releases-admin-js', plugin_dir_url(__FILE__) . 'press-releases.js', array('jquery'), '1.5.5', true);
        wp_enqueue_style('press-releases-admin-css', plugin_dir_url(__FILE__) . 'press-releases.css', array(), '1.5.5');

        wp_localize_script('press-releases-admin-js', 'press_releases_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('press_releases_nonce')
        ));
    }

    /**
     * AJAX handler to load URLs
     */
    public function ajax_load_urls() {
        // Security checks
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'press_releases_nonce')) {
            wp_die('Security check failed');
        }

        // Rate limiting check
        if (!$this->check_rate_limit('ajax_load_urls')) {
            wp_die('Too many requests. Please wait before trying again.');
        }

        // Validate and sanitize input
        $release_id = intval($_POST['release_id']);
        if (!$release_id || $release_id <= 0) {
            wp_die('Invalid press release ID');
        }

        // Verify the post exists and is a press release
        $post = get_post($release_id);
        if (!$post || $post->post_type !== 'press_release' || $post->post_status !== 'publish') {
            wp_die('Press release not found or not accessible');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'press_release_urls';

        $urls = $wpdb->get_results($wpdb->prepare(
            "SELECT url, title FROM $table_name WHERE press_release_id = %d ORDER BY id ASC",
            $release_id
        ));

        if (empty($urls)) {
            echo '<p>No URLs found for this press release.</p>';
            wp_die();
        }

        echo '<div class="press-release-urls">';
        echo '<div class="urls-header">';
        echo '<span class="urls-count">' . count($urls) . ' URLs found</span>';
        echo '<button class="copy-all-btn" data-release-id="' . $release_id . '">Copy All URLs</button>';
        echo '</div>';

        echo '<div class="urls-list">';
        foreach ($urls as $url_data) {
            $title = !empty($url_data->title) ? esc_html($url_data->title) : 'Untitled';
            echo '<div class="url-item">';
            echo '<a href="' . esc_url($url_data->url) . '" target="_blank" rel="noopener">' . $title . '</a>';
            echo '<span class="url-link">' . esc_html($url_data->url) . '</span>';
            echo '<button class="copy-url-btn" data-url="' . esc_attr($url_data->url) . '">Copy</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        wp_die();
    }

    /**
     * Shortcode to display press releases
     */
    public function shortcode_display($atts) {
        $atts = shortcode_atts(array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'style' => 'accordion',
            'show_date' => 'yes',
            'show_count' => 'yes',
            'show_description' => 'yes',
            'title_tag' => 'h3',
            'excerpt_length' => 0,
            'specific_releases' => '',
            'exclude_releases' => '',
            'search' => 'no'
        ), $atts);

        $args = array(
            'post_type' => 'press_release',
            'posts_per_page' => $atts['limit'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'post_status' => 'publish'
        );

        // Handle specific releases
        if (!empty($atts['specific_releases'])) {
            $specific_ids = array_map('trim', explode(',', $atts['specific_releases']));
            $args['post__in'] = $specific_ids;
        }

        // Handle excluded releases
        if (!empty($atts['exclude_releases'])) {
            $exclude_ids = array_map('trim', explode(',', $atts['exclude_releases']));
            $args['post__not_in'] = $exclude_ids;
        }

        $press_releases = new WP_Query($args);

        if (!$press_releases->have_posts()) {
            return '<p>No press releases found.</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'press_release_urls';

        ob_start();

        // Add search box if enabled
        if ($atts['search'] === 'yes') {
            ?>
            <div class="press-releases-search">
                <input type="text" placeholder="Search press releases..." class="press-release-search-input">
                <button class="press-release-search-btn">Search</button>
                <button class="press-release-clear-btn">Clear</button>
            </div>
            <?php
        }
        ?>
        <div class="press-releases-container" data-style="<?php echo esc_attr($atts['style']); ?>">
            <?php while ($press_releases->have_posts()) : $press_releases->the_post(); ?>
                <?php
                $post_id = get_the_ID();
                $url_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE press_release_id = %d",
                    $post_id
                ));
                ?>
                <div class="press-release-item" data-release-id="<?php echo $post_id; ?>">
                    <div class="accordion-header">
                        <<?php echo esc_attr($atts['title_tag']); ?> class="release-title"><?php the_title(); ?></<?php echo esc_attr($atts['title_tag']); ?>>
                        <div class="release-meta">
                            <?php if ($atts['show_date'] === 'yes') : ?>
                                <span class="release-date"><?php echo get_the_date(); ?></span>
                            <?php endif; ?>
                            <?php if ($atts['show_count'] === 'yes') : ?>
                                <span class="url-count">(<?php echo $url_count; ?> URLs)</span>
                            <?php endif; ?>
                            <span class="toggle-icon">+</span>
                        </div>
                    </div>
                    <div class="accordion-content">
                        <?php if ($atts['show_description'] === 'yes' && (get_the_content() || $atts['excerpt_length'] > 0)) : ?>
                            <div class="release-description">
                                <?php
                                if ($atts['excerpt_length'] > 0) {
                                    echo wp_trim_words(get_the_content(), $atts['excerpt_length'], '...');
                                } else {
                                    the_content();
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        <div class="urls-container">
                            <div class="loading-spinner">
                                <span>Loading URLs...</span>
                            </div>
                            <div class="urls-content"></div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Helper function to add URLs to a press release
     */
    public static function add_urls_to_release($release_id, $urls) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'press_release_urls';

        foreach ($urls as $url_data) {
            $url = is_array($url_data) ? $url_data['url'] : $url_data;
            $title = is_array($url_data) && isset($url_data['title']) ? $url_data['title'] : '';

            $wpdb->insert(
                $table_name,
                array(
                    'press_release_id' => $release_id,
                    'url' => $url,
                    'title' => $title
                ),
                array('%d', '%s', '%s')
            );
        }
    }
}

// Initialize the plugin
global $pressstack;
$pressstack = new PressStack();

// Activation/Deactivation Hooks
register_activation_hook(__FILE__, array($pressstack, 'activate_plugin'));
register_deactivation_hook(__FILE__, array($pressstack, 'deactivate_plugin'));

// Admin functions for bulk URL import
if (is_admin()) {
    add_action('add_meta_boxes', 'add_press_release_meta_boxes');
    add_action('save_post', 'save_press_release_urls');
    add_action('admin_menu', 'add_shortcode_builder_menu');
    add_action('admin_menu', 'add_settings_menu');
    add_action('admin_menu', 'add_security_menu');
    add_action('admin_init', 'register_settings');

    function add_press_release_meta_boxes() {
        add_meta_box(
            'press_release_urls',
            'Press Release URLs',
            'press_release_urls_callback',
            'press_release',
            'normal',
            'high'
        );
    }

    function press_release_urls_callback($post) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'press_release_urls';

        // Get existing URLs
        $existing_urls = $wpdb->get_results($wpdb->prepare(
            "SELECT id, url, title FROM $table_name WHERE press_release_id = %d ORDER BY id ASC",
            $post->ID
        ));

        wp_nonce_field('save_press_release_urls', 'press_release_urls_nonce');
        ?>
        <div class="press-release-urls-admin" id="press-release-urls-admin">
            <style>
                .url-manager-tabs { border-bottom: 1px solid #ddd; margin-bottom: 20px; }
                .url-manager-tabs button { background: #f1f1f1; border: 1px solid #ddd; border-bottom: none; padding: 10px 20px; cursor: pointer; margin-right: 5px; }
                .url-manager-tabs button.active { background: #fff; }
                .tab-content { display: none; }
                .tab-content.active { display: block; }
                .url-item { background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 10px; position: relative; }
                .url-item.new { background: #e8f5e8; border-color: #4CAF50; }
                .url-item .url-title { font-weight: bold; margin-bottom: 5px; }
                .url-item .url-link { color: #666; word-break: break-all; }
                .url-item .url-actions { position: absolute; top: 10px; right: 10px; }
                .url-item .url-actions button { margin-left: 5px; padding: 5px 10px; cursor: pointer; }
                .add-url-form { background: #fff; border: 2px dashed #ddd; border-radius: 5px; padding: 20px; margin-bottom: 20px; }
                .add-url-form.active { border-color: #2196F3; background: #f0f8ff; }
                .form-row { margin-bottom: 15px; }
                .form-row label { display: block; font-weight: bold; margin-bottom: 5px; }
                .form-row input[type="url"], .form-row input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
                .url-preview { background: #e7f3ff; padding: 10px; border-radius: 3px; margin-top: 10px; display: none; }
                .url-stats { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; }
                .bulk-import-area { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .import-preview { background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 3px; margin-top: 10px; max-height: 200px; overflow-y: auto; }
                .error { color: #d63638; }
                .success { color: #00a32a; }
            </style>

            <!-- URL Statistics -->
            <div class="url-stats">
                <h3>📊 URL Statistics</h3>
                <p><strong>Total URLs:</strong> <span id="url-count"><?php echo count($existing_urls); ?></span></p>
                <p><strong>Status:</strong> <span id="url-status"><?php echo count($existing_urls) > 0 ? 'Ready to display' : 'No URLs added yet'; ?></span></p>
            </div>

            <!-- Tab Navigation -->
            <div class="url-manager-tabs">
                <button type="button" class="tab-btn active" data-tab="individual">➕ Add Individual URLs</button>
                <button type="button" class="tab-btn" data-tab="bulk">📋 Bulk Import</button>
                <button type="button" class="tab-btn" data-tab="manage">⚙️ Manage URLs (<?php echo count($existing_urls); ?>)</button>
            </div>

            <!-- Tab 1: Individual URL Entry -->
            <div class="tab-content active" id="tab-individual">
                <div class="add-url-form" id="add-url-form">
                    <h3>➕ Add New URL</h3>
                    <div class="form-row">
                        <label for="new-url">🔗 URL (Required)</label>
                        <input type="url" id="new-url" placeholder="https://example.com/article" required>
                        <small>Enter the full URL including https://</small>
                    </div>
                    <div class="form-row">
                        <label for="new-title">📝 Title (Optional)</label>
                        <input type="text" id="new-title" placeholder="Article title or description">
                        <small>If empty, we'll try to get the title automatically</small>
                    </div>
                    <div class="url-preview" id="url-preview">
                        <strong>Preview:</strong> <span id="preview-content"></span>
                    </div>
                    <p>
                        <button type="button" id="add-url-btn" class="button button-primary">➕ Add URL</button>
                        <button type="button" id="validate-url-btn" class="button">✅ Validate URL</button>
                    </p>
                </div>

                <div id="new-urls-preview" style="margin-top: 20px;">
                    <h4>📋 URLs to be Added (<?php echo count($existing_urls); ?> existing + <span id="new-count">0</span> new)</h4>
                    <div id="new-urls-list"></div>
                </div>
            </div>

            <!-- Tab 2: Bulk Import -->
            <div class="tab-content" id="tab-bulk">
                <div class="bulk-import-area">
                    <h3>📋 Bulk Import URLs</h3>
                    <p><strong>Format Options:</strong></p>
                    <ul>
                        <li><strong>URL only:</strong> <code>https://example.com/article1</code></li>
                        <li><strong>URL with title:</strong> <code>https://example.com/article2, Article Title</code></li>
                        <li><strong>Mixed format is OK!</strong></li>
                    </ul>

                    <div class="form-row">
                        <label for="bulk-urls">Paste your URLs (one per line):</label>
                        <textarea id="bulk-urls" rows="8" cols="80" placeholder="https://example.com/url1&#10;https://example.com/url2, Page Title&#10;https://example.com/url3"></textarea>
                    </div>

                    <p>
                        <button type="button" id="preview-bulk-btn" class="button button-primary">👀 Preview Import</button>
                        <button type="button" id="clear-bulk-btn" class="button">🗑️ Clear</button>
                    </p>

                    <div id="bulk-preview" class="import-preview" style="display: none;">
                        <h4>📋 Import Preview</h4>
                        <div id="bulk-preview-content"></div>
                        <p>
                            <button type="button" id="confirm-bulk-btn" class="button button-primary">✅ Add These URLs</button>
                        </p>
                    </div>

                    <div class="form-row">
                        <label>
                            <input type="checkbox" name="replace_urls" value="1">
                            🔄 Replace all existing URLs (otherwise, new URLs will be added)
                        </label>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Manage Existing URLs -->
            <div class="tab-content" id="tab-manage">
                <div id="existing-urls-list">
                    <h3>⚙️ Manage Existing URLs</h3>
                    <?php if (empty($existing_urls)): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">
                            📭 No URLs added yet.<br>
                            <small>Switch to the "Add Individual URLs" tab to get started!</small>
                        </p>
                    <?php else: ?>
                        <?php foreach ($existing_urls as $index => $url_data): ?>
                            <div class="url-item" data-url-id="<?php echo $url_data->id; ?>">
                                <div class="url-title"><?php echo !empty($url_data->title) ? esc_html($url_data->title) : 'Untitled URL #' . ($index + 1); ?></div>
                                <div class="url-link">
                                    <a href="<?php echo esc_url($url_data->url); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html($url_data->url); ?> ↗️
                                    </a>
                                </div>
                                <div class="url-actions">
                                    <button type="button" class="button button-small edit-url-btn" data-url-id="<?php echo $url_data->id; ?>">✏️ Edit</button>
                                    <button type="button" class="button button-small delete-url-btn" data-url-id="<?php echo $url_data->id; ?>">🗑️ Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hidden field for storing URL data -->
            <input type="hidden" name="url_data_json" id="url-data-json" value="">
            <textarea name="bulk_urls" id="bulk_urls_hidden" style="display: none;"></textarea>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var newUrls = [];
            var urlIdCounter = 0;

            // Tab switching
            $('.tab-btn').click(function() {
                $('.tab-btn').removeClass('active');
                $('.tab-content').removeClass('active');
                $(this).addClass('active');
                $('#tab-' + $(this).data('tab')).addClass('active');
            });

            // URL validation
            $('#validate-url-btn').click(function() {
                var url = $('#new-url').val();
                if (url) {
                    $('#url-preview').show();
                    $('#preview-content').html('🔍 Checking: <a href="' + url + '" target="_blank">' + url + '</a>');

                    // Simple validation
                    try {
                        new URL(url);
                        $('#preview-content').html('✅ Valid URL: <a href="' + url + '" target="_blank">' + url + '</a>');
                    } catch (e) {
                        $('#preview-content').html('❌ Invalid URL format. Please check and try again.');
                    }
                }
            });

            // Add individual URL
            $('#add-url-btn').click(function() {
                var url = $('#new-url').val();
                var title = $('#new-title').val();

                if (!url) {
                    alert('Please enter a URL');
                    return;
                }

                // Validate URL
                try {
                    new URL(url);
                } catch (e) {
                    alert('Please enter a valid URL (including https://)');
                    return;
                }

                // Add to new URLs list
                urlIdCounter++;
                var newUrl = {
                    id: 'new_' + urlIdCounter,
                    url: url,
                    title: title || 'Untitled URL'
                };
                newUrls.push(newUrl);

                // Update display
                updateNewUrlsDisplay();
                updateUrlStats();

                // Clear form
                $('#new-url').val('');
                $('#new-title').val('');
                $('#url-preview').hide();
            });

            // Bulk import preview
            $('#preview-bulk-btn').click(function() {
                var bulkText = $('#bulk-urls').val();
                if (!bulkText.trim()) {
                    alert('Please paste some URLs first');
                    return;
                }

                var lines = bulkText.trim().split('\n');
                var previewHtml = '';
                var validCount = 0;
                var errorCount = 0;

                lines.forEach(function(line, index) {
                    line = line.trim();
                    if (!line) return;

                    var url, title;
                    if (line.includes(',')) {
                        var parts = line.split(',', 2);
                        url = parts[0].trim();
                        title = parts[1].trim();
                    } else {
                        url = line;
                        title = 'Untitled URL #' + (index + 1);
                    }

                    try {
                        new URL(url);
                        previewHtml += '<div style="color: #00a32a;">✅ ' + title + ' - ' + url + '</div>';
                        validCount++;
                    } catch (e) {
                        previewHtml += '<div style="color: #d63638;">❌ Invalid URL: ' + line + '</div>';
                        errorCount++;
                    }
                });

                $('#bulk-preview-content').html(
                    '<p><strong>📊 Summary:</strong> ' + validCount + ' valid URLs, ' + errorCount + ' errors</p>' +
                    previewHtml
                );
                $('#bulk-preview').show();
            });

            // Confirm bulk import
            $('#confirm-bulk-btn').click(function() {
                $('#bulk_urls_hidden').val($('#bulk-urls').val());
                alert('URLs will be added when you save/update this press release.');
                $('#bulk-preview').hide();
            });

            // Update displays
            function updateNewUrlsDisplay() {
                var html = '';
                newUrls.forEach(function(urlData, index) {
                    html += '<div class="url-item new">' +
                        '<div class="url-title">' + urlData.title + '</div>' +
                        '<div class="url-link"><a href="' + urlData.url + '" target="_blank">' + urlData.url + ' ↗️</a></div>' +
                        '<div class="url-actions">' +
                            '<button type="button" class="button button-small remove-new-url" data-index="' + index + '">🗑️ Remove</button>' +
                        '</div>' +
                    '</div>';
                });
                $('#new-urls-list').html(html);
                $('#new-count').text(newUrls.length);
            }

            function updateUrlStats() {
                var existingCount = <?php echo count($existing_urls); ?>;
                var totalCount = existingCount + newUrls.length;
                $('#url-count').text(totalCount);
                $('#url-status').text(totalCount > 0 ? 'Ready to display (' + newUrls.length + ' pending save)' : 'No URLs added yet');
            }

            // Remove new URL
            $(document).on('click', '.remove-new-url', function() {
                var index = $(this).data('index');
                newUrls.splice(index, 1);
                updateNewUrlsDisplay();
                updateUrlStats();
            });

            // Clear bulk textarea
            $('#clear-bulk-btn').click(function() {
                $('#bulk-urls').val('');
                $('#bulk-preview').hide();
            });

            // Save new URLs data to hidden field before form submission
            $('form').submit(function() {
                if (newUrls.length > 0) {
                    $('#url-data-json').val(JSON.stringify(newUrls));
                }
            });
        });
        </script>
        <?php
    }

    function save_press_release_urls($post_id) {
        // Enhanced security checks
        if (!isset($_POST['press_release_urls_nonce']) ||
            !wp_verify_nonce($_POST['press_release_urls_nonce'], 'save_press_release_urls')) {
            return;
        }

        // Check user permissions with enhanced validation
        if (!current_user_can('edit_post', $post_id) || !current_user_can('edit_posts')) {
            return;
        }

        // Validate post type
        if (get_post_type($post_id) != 'press_release') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'press_release_urls';

        // Handle individual URLs from JSON data (new interface)
        if (!empty($_POST['url_data_json'])) {
            $json_data = stripslashes($_POST['url_data_json']);

            // Validate JSON size (prevent DoS attacks)
            if (strlen($json_data) > 50000) { // 50KB limit
                wp_die('Data too large. Please reduce the number of URLs.');
            }

            $new_urls = json_decode($json_data, true);

            if (is_array($new_urls) && !empty($new_urls)) {
                // Limit number of URLs to prevent abuse
                if (count($new_urls) > 1000) {
                    wp_die('Too many URLs. Maximum 1000 URLs allowed per press release.');
                }

                foreach ($new_urls as $url_data) {
                    if (!is_array($url_data) || !isset($url_data['url'])) {
                        continue;
                    }

                    // Basic URL validation
                    $clean_url = esc_url_raw($url_data['url']);
                    if (!$clean_url || !filter_var($clean_url, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    // Basic title sanitization
                    $clean_title = sanitize_text_field(
                        isset($url_data['title']) ? $url_data['title'] : ''
                    );
                    if (strlen($clean_title) > 200) {
                        $clean_title = substr($clean_title, 0, 200);
                    }

                    // Use prepared statement for security
                    $wpdb->insert(
                        $table_name,
                        array(
                            'press_release_id' => $post_id,
                            'url' => $clean_url,
                            'title' => $clean_title
                        ),
                        array('%d', '%s', '%s')
                    );
                }
            }
        }

        // Handle bulk URLs (legacy and new bulk import)
        $bulk_urls_field = !empty($_POST['bulk_urls']) ? $_POST['bulk_urls'] : (!empty($_POST['bulk_urls_hidden']) ? $_POST['bulk_urls_hidden'] : '');
        if (!empty($bulk_urls_field)) {
            // Validate bulk data size
            if (strlen($bulk_urls_field) > 100000) { // 100KB limit
                wp_die('Bulk data too large. Please reduce the number of URLs.');
            }

            // Replace existing URLs if requested
            if (isset($_POST['replace_urls']) && $_POST['replace_urls'] == '1') {
                if (current_user_can('delete_posts')) {
                    $wpdb->delete($table_name, array('press_release_id' => $post_id), array('%d'));
                } else {
                    wp_die('Insufficient permissions to replace existing URLs.');
                }
            }

            $urls_text = sanitize_textarea_field($bulk_urls_field);
            $urls_lines = explode("\n", $urls_text);

            // Limit number of lines to prevent abuse
            if (count($urls_lines) > 1000) {
                wp_die('Too many URLs in bulk import. Maximum 1000 URLs allowed.');
            }

            $processed_count = 0;
            foreach ($urls_lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Prevent processing too many URLs
                if ($processed_count >= 1000) {
                    break;
                }

                if (strpos($line, ',') !== false) {
                    $parts = array_map('trim', explode(',', $line, 2));
                    $url = $parts[0];
                    $title = isset($parts[1]) ? $parts[1] : '';
                } else {
                    $url = $line;
                    $title = '';
                }

                // Basic URL validation
                $clean_url = esc_url_raw($url);
                if (!$clean_url || !filter_var($clean_url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                // Basic title sanitization
                $clean_title = sanitize_text_field($title);
                if (strlen($clean_title) > 200) {
                    $clean_title = substr($clean_title, 0, 200);
                }

                // Use prepared statement
                $wpdb->insert(
                    $table_name,
                    array(
                        'press_release_id' => $post_id,
                        'url' => $clean_url,
                        'title' => $clean_title
                    ),
                    array('%d', '%s', '%s')
                );

                $processed_count++;
            }
        }
    }

    function add_shortcode_builder_menu() {
        add_submenu_page(
            'edit.php?post_type=press_release',
            'Shortcode Builder',
            'Shortcode Builder',
            'manage_options',
            'press-release-shortcode-builder',
            'display_shortcode_builder_page'
        );
    }

    function display_shortcode_builder_page() {
        ?>
        <div class="wrap">
            <h1>📋 Press Releases Shortcode Builder</h1>
            <p>Create custom shortcodes for displaying your press releases. Simply select your options below and copy the generated shortcode!</p>

            <div style="background: #f1f1f1; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>🎯 Quick Start</h2>
                <p><strong>Copy this basic shortcode to any page or post:</strong></p>
                <code style="background: #fff; padding: 10px; display: block; font-size: 16px;">[press_releases]</code>
            </div>

            <form id="shortcode-builder" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h2>🛠️ Custom Shortcode Builder</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">Number of Press Releases</th>
                        <td>
                            <select name="limit" id="limit">
                                <option value="-1">Show All</option>
                                <option value="1">1</option>
                                <option value="3">3</option>
                                <option value="5">5</option>
                                <option value="10">10</option>
                            </select>
                            <p class="description">How many press releases to display</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Order By</th>
                        <td>
                            <select name="orderby" id="orderby">
                                <option value="date">Date Created</option>
                                <option value="title">Title (A-Z)</option>
                                <option value="modified">Last Modified</option>
                                <option value="menu_order">Custom Order</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sort Order</th>
                        <td>
                            <select name="order" id="order">
                                <option value="DESC">Newest First</option>
                                <option value="ASC">Oldest First</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Show Date</th>
                        <td>
                            <label><input type="radio" name="show_date" value="yes" checked> Yes</label>
                            <label><input type="radio" name="show_date" value="no"> No</label>
                            <p class="description">Display the publication date</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Show URL Count</th>
                        <td>
                            <label><input type="radio" name="show_count" value="yes" checked> Yes</label>
                            <label><input type="radio" name="show_count" value="no"> No</label>
                            <p class="description">Show how many URLs each press release has</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Show Description</th>
                        <td>
                            <label><input type="radio" name="show_description" value="yes" checked> Yes</label>
                            <label><input type="radio" name="show_description" value="no"> No</label>
                            <p class="description">Display the press release content/description</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Description Length</th>
                        <td>
                            <input type="number" name="excerpt_length" id="excerpt_length" value="0" min="0" max="200">
                            <p class="description">Limit description to X words (0 = show full content)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Search Box</th>
                        <td>
                            <label><input type="radio" name="search" value="yes"> Yes</label>
                            <label><input type="radio" name="search" value="no" checked> No</label>
                            <p class="description">Add a search box above the press releases</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Title HTML Tag</th>
                        <td>
                            <select name="title_tag" id="title_tag">
                                <option value="h1">H1</option>
                                <option value="h2">H2</option>
                                <option value="h3" selected>H3</option>
                                <option value="h4">H4</option>
                                <option value="h5">H5</option>
                                <option value="h6">H6</option>
                            </select>
                            <p class="description">HTML heading tag for press release titles</p>
                        </td>
                    </tr>
                </table>

                <h3>🎯 Advanced Options</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Specific Press Releases</th>
                        <td>
                            <input type="text" name="specific_releases" id="specific_releases" placeholder="1,5,10" style="width: 300px;">
                            <p class="description">Show only specific press releases (enter IDs separated by commas)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Exclude Press Releases</th>
                        <td>
                            <input type="text" name="exclude_releases" id="exclude_releases" placeholder="2,7,15" style="width: 300px;">
                            <p class="description">Hide specific press releases (enter IDs separated by commas)</p>
                        </td>
                    </tr>
                </table>

                <p><button type="button" id="generate-shortcode" class="button button-primary">🚀 Generate Shortcode</button></p>
            </form>

            <div id="generated-shortcode" style="margin-top: 20px; display: none;">
                <h2>📋 Your Generated Shortcode</h2>
                <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                    <p><strong>Copy this shortcode:</strong></p>
                    <textarea id="shortcode-output" style="width: 100%; height: 60px; font-family: monospace;" readonly></textarea>
                    <p><button type="button" id="copy-shortcode" class="button">📋 Copy to Clipboard</button></p>
                </div>

                <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin-top: 15px;">
                    <h3>📝 How to Use:</h3>
                    <ol>
                        <li><strong>Copy</strong> the shortcode above</li>
                        <li><strong>Go to</strong> any page or post editor</li>
                        <li><strong>Paste</strong> the shortcode where you want the press releases to appear</li>
                        <li><strong>Update/Publish</strong> the page</li>
                    </ol>
                </div>
            </div>

            <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-top: 20px;">
                <h3>💡 Pro Tips:</h3>
                <ul>
                    <li><strong>Test first:</strong> Try the basic <code>[press_releases]</code> shortcode before customizing</li>
                    <li><strong>Page vs Post:</strong> Works on both pages and posts</li>
                    <li><strong>Multiple shortcodes:</strong> You can use different shortcodes on different pages</li>
                    <li><strong>Styling:</strong> The display will match your theme's styling</li>
                </ul>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#generate-shortcode').click(function() {
                var shortcode = '[press_releases';
                var params = [];

                // Collect all form values
                var limit = $('#limit').val();
                if (limit !== '-1') params.push('limit="' + limit + '"');

                var orderby = $('#orderby').val();
                if (orderby !== 'date') params.push('orderby="' + orderby + '"');

                var order = $('#order').val();
                if (order !== 'DESC') params.push('order="' + order + '"');

                var showDate = $('input[name="show_date"]:checked').val();
                if (showDate !== 'yes') params.push('show_date="' + showDate + '"');

                var showCount = $('input[name="show_count"]:checked').val();
                if (showCount !== 'yes') params.push('show_count="' + showCount + '"');

                var showDesc = $('input[name="show_description"]:checked').val();
                if (showDesc !== 'yes') params.push('show_description="' + showDesc + '"');

                var excerptLength = $('#excerpt_length').val();
                if (excerptLength && excerptLength !== '0') params.push('excerpt_length="' + excerptLength + '"');

                var search = $('input[name="search"]:checked').val();
                if (search !== 'no') params.push('search="' + search + '"');

                var titleTag = $('#title_tag').val();
                if (titleTag !== 'h3') params.push('title_tag="' + titleTag + '"');

                var specific = $('#specific_releases').val();
                if (specific) params.push('specific_releases="' + specific + '"');

                var exclude = $('#exclude_releases').val();
                if (exclude) params.push('exclude_releases="' + exclude + '"');

                if (params.length > 0) {
                    shortcode += ' ' + params.join(' ');
                }
                shortcode += ']';

                $('#shortcode-output').val(shortcode);
                $('#generated-shortcode').show();
            });

            $('#copy-shortcode').click(function() {
                $('#shortcode-output').select();
                document.execCommand('copy');
                $(this).text('✅ Copied!');
                setTimeout(function() {
                    $('#copy-shortcode').text('📋 Copy to Clipboard');
                }, 2000);
            });
        });
        </script>
        <?php
    }

    function add_settings_menu() {
        add_submenu_page(
            'edit.php?post_type=press_release',
            'Press Releases Settings',
            'Settings',
            'manage_options',
            'press-releases-settings',
            'display_settings_page'
        );
    }

    function register_settings() {
        register_setting('press_releases_settings', 'press_releases_redirect_url');
    }

    function display_settings_page() {
        $current_url = get_option('press_releases_redirect_url', '');
        ?>
        <div class="wrap">
            <h1>⚙️ Press Releases Settings</h1>

            <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;">
                <h3>🔄 301 Redirects Active</h3>
                <p><strong>Individual press release pages now redirect to your main press releases page for better SEO.</strong></p>
                <p>This eliminates duplicate content and concentrates SEO power on one authoritative page.</p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('press_releases_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="press_releases_redirect_url">Redirect Destination URL</label>
                        </th>
                        <td>
                            <input type="url"
                                   id="press_releases_redirect_url"
                                   name="press_releases_redirect_url"
                                   value="<?php echo esc_attr($current_url); ?>"
                                   placeholder="https://example.com/press-releases/"
                                   style="width: 400px;" />
                            <p class="description">
                                <strong>Leave empty for auto-detection.</strong><br>
                                The plugin will automatically find the page containing your <code>[press_releases]</code> shortcode.<br>
                                Only set a custom URL if auto-detection doesn't work or you want to redirect somewhere specific.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-top: 20px;">
                <h3>🔍 How It Works</h3>
                <ul>
                    <li><strong>Auto-Detection:</strong> Finds pages containing <code>[press_releases]</code> shortcode</li>
                    <li><strong>301 Redirect:</strong> Search engines transfer all SEO value to the main page</li>
                    <li><strong>User Experience:</strong> Visitors land on the functional press releases page</li>
                    <li><strong>Fallback:</strong> Redirects to homepage if no press releases page found</li>
                </ul>
            </div>

            <div style="background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin-top: 20px;">
                <h3>📊 Current Status</h3>
                <p><strong>Auto-detection:</strong> Available after plugin activation</p>
                <?php if ($current_url): ?>
                    <p><strong>Custom redirect URL:</strong>
                        <a href="<?php echo esc_url($current_url); ?>" target="_blank">
                            <?php echo esc_html($current_url); ?> ↗️
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    function add_security_menu() {
        add_submenu_page(
            'edit.php?post_type=press_release',
            'Security Status',
            'Security',
            'manage_options',
            'press-releases-security',
            'display_security_page'
        );
    }

    function display_security_page() {
        ?>
        <div class="wrap">
            <h1>🔒 Press Releases Security Status</h1>

            <div style="background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 20px 0;">
                <h3>🛡️ Security Features Active (v1.5.0)</h3>
                <p><strong>Your Press Releases Manager is secured with enterprise-grade protection.</strong></p>
            </div>

            <div class="security-features" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">

                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>🔐 Access Controls</h3>
                    <ul>
                        <li>✅ <strong>Nonce verification</strong> on all forms</li>
                        <li>✅ <strong>User capability checks</strong> for admin functions</li>
                        <li>✅ <strong>Role-based permissions</strong> enforcement</li>
                        <li>✅ <strong>Session security</strong> validation</li>
                    </ul>
                </div>

                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>🚫 Rate Limiting</h3>
                    <ul>
                        <li>✅ <strong>AJAX requests:</strong> 10/minute</li>
                        <li>✅ <strong>URL saves:</strong> 5/minute</li>
                        <li>✅ <strong>IP-based tracking</strong></li>
                        <li>✅ <strong>DoS attack prevention</strong></li>
                    </ul>
                </div>

                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>🧹 Input Validation</h3>
                    <ul>
                        <li>✅ <strong>URL sanitization</strong> & validation</li>
                        <li>✅ <strong>SQL injection</strong> prevention</li>
                        <li>✅ <strong>XSS attack</strong> blocking</li>
                        <li>✅ <strong>Data size limits</strong> enforced</li>
                    </ul>
                </div>

                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>📊 Security Monitoring</h3>
                    <ul>
                        <li>✅ <strong>Security event logging</strong></li>
                        <li>✅ <strong>Suspicious activity detection</strong></li>
                        <li>✅ <strong>Malicious URL blocking</strong></li>
                        <li>✅ <strong>Protocol restriction</strong> (HTTPS/HTTP only)</li>
                    </ul>
                </div>

            </div>

            <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-top: 20px;">
                <h3>🔍 Security Limits</h3>
                <ul>
                    <li><strong>Maximum URLs per press release:</strong> 1000</li>
                    <li><strong>Maximum bulk import lines:</strong> 1000</li>
                    <li><strong>JSON data size limit:</strong> 50KB</li>
                    <li><strong>Bulk data size limit:</strong> 100KB</li>
                    <li><strong>URL title max length:</strong> 200 characters</li>
                </ul>
            </div>

            <div style="background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin-top: 20px;">
                <h3>✅ Blocked Attack Vectors</h3>
                <ul>
                    <li><strong>SQL Injection:</strong> Prepared statements & input validation</li>
                    <li><strong>Cross-Site Scripting (XSS):</strong> Output escaping & input sanitization</li>
                    <li><strong>CSRF Attacks:</strong> Nonce verification on all forms</li>
                    <li><strong>DoS Attacks:</strong> Rate limiting & data size restrictions</li>
                    <li><strong>Local File Inclusion:</strong> Protocol & domain restrictions</li>
                    <li><strong>Privilege Escalation:</strong> Capability checks on all admin functions</li>
                </ul>
            </div>

            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <div style="background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin-top: 20px;">
                <h3>⚠️ Debug Mode Active</h3>
                <p><strong>Security events are being logged to the WordPress debug log.</strong></p>
                <p>Log location: <code>/wp-content/debug.log</code></p>
                <p>For production sites, consider disabling debug mode for better performance.</p>
            </div>
            <?php endif; ?>

            <div style="background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; margin-top: 30px;">
                <h3>❤️ Support PressStack Development</h3>
                <p><strong>PressStack is completely free!</strong> If you find it helpful for managing your press releases, consider supporting our development to keep it free and improving.</p>

                <div style="display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap;">
                    <a href="https://github.com/sponsors/inboundinteractivegit" target="_blank" class="button button-primary" style="background: #24292f; border-color: #24292f;">
                        ❤️ GitHub Sponsors (0% fees)
                    </a>
                    <a href="https://www.buymeacoffee.com/inboundinteractive" target="_blank" class="button" style="background: #FFDD00; border-color: #FFDD00; color: #000;">
                        ☕ Buy us a Coffee (5% fees)
                    </a>
                    <a href="https://donate.stripe.com/YOUR_STRIPE_LINK" target="_blank" class="button" style="background: #635bff; border-color: #635bff; color: #fff;">
                        💳 Credit Card (2.9% fees)
                    </a>
                    <a href="https://github.com/inboundinteractivegit/press-releases-plugin" target="_blank" class="button">
                        ⭐ Star on GitHub (Free!)
                    </a>
                </div>

                <div style="background: #fff; padding: 15px; border-radius: 5px; margin-top: 15px;">
                    <h4>💰 How Your Support Helps:</h4>
                    <ul style="margin: 10px 0;">
                        <li><strong>🚀 New Features:</strong> Advanced analytics, integrations, export options</li>
                        <li><strong>🔧 Bug Fixes:</strong> Faster response to issues and compatibility updates</li>
                        <li><strong>📚 Documentation:</strong> Better guides, tutorials, and support resources</li>
                        <li><strong>🌟 Free Forever:</strong> Keep PressStack completely free for everyone</li>
                    </ul>
                    <p style="margin-top: 10px; font-size: 14px; color: #6c757d;">
                        <strong>💡 Recommended:</strong> GitHub Sponsors has 0% fees, so 100% goes to development!
                    </p>
                </div>

                <p style="margin-top: 15px; font-style: italic; color: #6c757d;">
                    <strong>Thank you for using PressStack!</strong> Your feedback and support make this plugin better for everyone. ❤️
                </p>
            </div>

        </div>
        <?php
    }
}
?>