<?php
/**
 * Plugin Name: PressStack
 * Plugin URI: https://github.com/inboundinteractivegit/press-releases-plugin
 * Description: Free press releases management with AJAX-loaded URLs, advanced security, and beginner-friendly interface. Manage hundreds of press release URLs with SEO optimization and comprehensive protection. Support our development with a donation!
 * Version: 1.5.6
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

            // Admin menus
            add_action('admin_menu', array($this, 'add_shortcode_builder_menu'));
            add_action('admin_menu', array($this, 'add_settings_menu'));
            add_action('admin_menu', array($this, 'add_security_menu'));
            add_action('admin_init', array($this, 'register_settings'));

            // Donation system
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_donation_link'));
            add_action('admin_notices', array($this, 'show_donation_notice'));
            add_action('wp_ajax_dismiss_donation_notice', array($this, 'dismiss_donation_notice'));

            // Pro upgrade integration (enabled only for testing environments)
            if (class_exists('PressStackPro') || class_exists('PressStackProTestLicenseActivator')) {
                add_action('admin_notices', array($this, 'show_pro_upgrade_notices'));
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_pro_upgrade_link'));
                add_action('admin_menu', array($this, 'add_pro_upgrade_menu'));
                add_action('wp_ajax_dismiss_pro_notice', array($this, 'dismiss_pro_notice'));
            }
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
        update_option('pressstack_version', '1.5.6');
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
     * Show Pro upgrade notices (DISABLED - Enable when Pro is ready)
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
     * Add Pro upgrade link to plugin actions (DISABLED - Enable when Pro is ready)
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
     * Add Pro upgrade menu (DISABLED - Enable when Pro is ready)
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
        wp_enqueue_script('press-releases-js', plugin_dir_url(__FILE__) . 'press-releases.js', array('jquery'), '1.5.6', true);
        wp_enqueue_style('press-releases-css', plugin_dir_url(__FILE__) . 'press-releases.css', array(), '1.5.6');

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
        wp_enqueue_script('press-releases-admin-js', plugin_dir_url(__FILE__) . 'press-releases.js', array('jquery'), '1.5.6', true);
        wp_enqueue_style('press-releases-admin-css', plugin_dir_url(__FILE__) . 'press-releases.css', array(), '1.5.6');

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

    /**
     * Add shortcode builder menu
     */
    public function add_shortcode_builder_menu() {
        add_submenu_page(
            'edit.php?post_type=press_release',
            'Shortcode Builder',
            'Shortcode Builder',
            'manage_options',
            'press-release-shortcode-builder',
            array($this, 'display_shortcode_builder_page')
        );
    }

    /**
     * Display shortcode builder page
     */
    public function display_shortcode_builder_page() {
        ?>
        <div class="wrap">
            <h1>📋 Press Releases Shortcode Builder</h1>
            <p>Create custom shortcodes for displaying your press releases. Simply select your options below and copy the generated shortcode!</p>

            <div style="background: #f1f1f1; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>🎯 Quick Start</h2>
                <p><strong>Copy this basic shortcode to any page or post:</strong></p>
                <code style="background: #fff; padding: 10px; display: block; font-size: 16px;">[press_releases]</code>
            </div>
        </div>
        <?php
    }

    /**
     * Add settings menu
     */
    public function add_settings_menu() {
        add_submenu_page(
            'edit.php?post_type=press_release',
            'Press Releases Settings',
            'Settings',
            'manage_options',
            'press-releases-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('press_releases_settings', 'press_releases_redirect_url');
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        $current_url = get_option('press_releases_redirect_url', '');
        ?>
        <div class="wrap">
            <h1>⚙️ Press Releases Settings</h1>
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
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Add security menu
     */
    public function add_security_menu() {
        add_submenu_page(
            'edit.php?post_type=press_release',
            'Security Status',
            'Security',
            'manage_options',
            'press-releases-security',
            array($this, 'display_security_page')
        );
    }

    /**
     * Display security page
     */
    public function display_security_page() {
        ?>
        <div class="wrap">
            <h1>🔒 Press Releases Security Status</h1>
            <div style="background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 20px 0;">
                <h3>🛡️ Security Features Active (v1.5.6)</h3>
                <p><strong>Your Press Releases Manager is secured with enterprise-grade protection.</strong></p>
            </div>
        </div>
        <?php
    }
}

// Admin functions for bulk URL import
if (is_admin()) {
    add_action('add_meta_boxes', 'add_press_release_meta_boxes');
    add_action('save_post', 'save_press_release_urls');

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
        wp_nonce_field('save_press_release_urls', 'press_release_urls_nonce');
        ?>
        <div class="press-release-urls-admin">
            <p>Add URLs for this press release:</p>
            <textarea name="bulk_urls" rows="10" cols="80" placeholder="https://example.com/url1&#10;https://example.com/url2, Page Title&#10;https://example.com/url3"></textarea>
            <p><em>Enter one URL per line. Optionally add a title after a comma.</em></p>
        </div>
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

        // Handle bulk URLs
        if (!empty($_POST['bulk_urls'])) {
            // Validate bulk data size
            if (strlen($_POST['bulk_urls']) > 100000) { // 100KB limit
                wp_die('Bulk data too large. Please reduce the number of URLs.');
            }

            $urls_text = sanitize_textarea_field($_POST['bulk_urls']);
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
}

// Initialize the plugin
$pressstack = new PressStack();

// Set up activation and deactivation hooks
// Temporarily disabled for debugging
// register_activation_hook(__FILE__, array($pressstack, 'activate_plugin'));
// register_deactivation_hook(__FILE__, array($pressstack, 'deactivate_plugin'));

?>