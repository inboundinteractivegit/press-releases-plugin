<?php
/**
 * Plugin Name: Press Releases Manager
 * Description: Manage press releases with AJAX-loaded URLs in accordion format
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PressReleasesManager {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_load_press_release_urls', array($this, 'ajax_load_urls'));
        add_action('wp_ajax_nopriv_load_press_release_urls', array($this, 'ajax_load_urls'));
        add_shortcode('press_releases', array($this, 'shortcode_display'));
    }

    public function init() {
        $this->create_press_release_post_type();
        $this->create_database_table();
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
     * Create database table for URLs
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
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('press-releases-js', plugin_dir_url(__FILE__) . 'press-releases.js', array('jquery'), '1.0', true);
        wp_enqueue_style('press-releases-css', plugin_dir_url(__FILE__) . 'press-releases.css', array(), '1.0');

        wp_localize_script('press-releases-js', 'press_releases_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('press_releases_nonce')
        ));
    }

    /**
     * AJAX handler to load URLs
     */
    public function ajax_load_urls() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'press_releases_nonce')) {
            wp_die('Security check failed');
        }

        $release_id = intval($_POST['release_id']);
        if (!$release_id) {
            wp_die('Invalid press release ID');
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
            'order' => 'DESC'
        ), $atts);

        $args = array(
            'post_type' => 'press_release',
            'posts_per_page' => $atts['limit'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'post_status' => 'publish'
        );

        $press_releases = new WP_Query($args);

        if (!$press_releases->have_posts()) {
            return '<p>No press releases found.</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'press_release_urls';

        ob_start();
        ?>
        <div class="press-releases-container">
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
                        <h3 class="release-title"><?php the_title(); ?></h3>
                        <div class="release-meta">
                            <span class="release-date"><?php echo get_the_date(); ?></span>
                            <span class="url-count">(<?php echo $url_count; ?> URLs)</span>
                            <span class="toggle-icon">+</span>
                        </div>
                    </div>
                    <div class="accordion-content">
                        <?php if (get_the_content()) : ?>
                            <div class="release-description">
                                <?php the_content(); ?>
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
new PressReleasesManager();

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
        global $wpdb;
        $table_name = $wpdb->prefix . 'press_release_urls';

        $url_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE press_release_id = %d",
            $post->ID
        ));

        wp_nonce_field('save_press_release_urls', 'press_release_urls_nonce');
        ?>
        <div class="press-release-urls-admin">
            <p><strong>Current URLs: <?php echo $url_count; ?></strong></p>

            <h4>Bulk Import URLs</h4>
            <p>Paste URLs (one per line) or URL,Title pairs separated by commas:</p>
            <textarea name="bulk_urls" rows="10" cols="80" placeholder="https://example.com/url1&#10;https://example.com/url2,Page Title&#10;https://example.com/url3"></textarea>

            <p>
                <label>
                    <input type="checkbox" name="replace_urls" value="1">
                    Replace existing URLs (otherwise, new URLs will be added)
                </label>
            </p>
        </div>
        <?php
    }

    function save_press_release_urls($post_id) {
        if (!isset($_POST['press_release_urls_nonce']) ||
            !wp_verify_nonce($_POST['press_release_urls_nonce'], 'save_press_release_urls')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) != 'press_release') {
            return;
        }

        if (empty($_POST['bulk_urls'])) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'press_release_urls';

        // Replace existing URLs if requested
        if (isset($_POST['replace_urls']) && $_POST['replace_urls'] == '1') {
            $wpdb->delete($table_name, array('press_release_id' => $post_id));
        }

        $urls_text = sanitize_textarea_field($_POST['bulk_urls']);
        $urls_lines = explode("\n", $urls_text);

        foreach ($urls_lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, ',') !== false) {
                $parts = array_map('trim', explode(',', $line, 2));
                $url = $parts[0];
                $title = isset($parts[1]) ? $parts[1] : '';
            } else {
                $url = $line;
                $title = '';
            }

            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'press_release_id' => $post_id,
                        'url' => $url,
                        'title' => $title
                    ),
                    array('%d', '%s', '%s')
                );
            }
        }
    }
}
?>