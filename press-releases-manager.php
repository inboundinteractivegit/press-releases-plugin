<?php
/**
 * Plugin Name: Press Releases Manager
 * Description: Manage press releases with AJAX-loaded URLs in accordion format
 * Version: 1.3.0
 * Author: Inbound Interactive
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

        // Enable auto-updates
        if (is_admin()) {
            require_once plugin_dir_path(__FILE__) . 'plugin-updater.php';
            new PressReleasesUpdater(__FILE__, 'inboundinteractivegit', 'press-releases-plugin');
        }
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
            'order' => 'DESC',
            'style' => 'accordion',
            'show_date' => 'yes',
            'show_count' => 'yes',
            'show_description' => 'yes',
            'title_tag' => 'h3',
            'excerpt_length' => 0,
            'specific_releases' => '',
            'exclude_releases' => '',
            'search' => 'yes'
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
new PressReleasesManager();

// Admin functions for bulk URL import
if (is_admin()) {
    add_action('add_meta_boxes', 'add_press_release_meta_boxes');
    add_action('save_post', 'save_press_release_urls');
    add_action('admin_menu', 'add_shortcode_builder_menu');

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

        global $wpdb;
        $table_name = $wpdb->prefix . 'press_release_urls';

        // Handle individual URLs from JSON data (new interface)
        if (!empty($_POST['url_data_json'])) {
            $new_urls = json_decode(stripslashes($_POST['url_data_json']), true);
            if (is_array($new_urls)) {
                foreach ($new_urls as $url_data) {
                    if (filter_var($url_data['url'], FILTER_VALIDATE_URL)) {
                        $wpdb->insert(
                            $table_name,
                            array(
                                'press_release_id' => $post_id,
                                'url' => sanitize_url($url_data['url']),
                                'title' => sanitize_text_field($url_data['title'])
                            ),
                            array('%d', '%s', '%s')
                        );
                    }
                }
            }
        }

        // Handle bulk URLs (legacy and new bulk import)
        $bulk_urls_field = !empty($_POST['bulk_urls']) ? $_POST['bulk_urls'] : $_POST['bulk_urls_hidden'];
        if (!empty($bulk_urls_field)) {
            // Replace existing URLs if requested
            if (isset($_POST['replace_urls']) && $_POST['replace_urls'] == '1') {
                $wpdb->delete($table_name, array('press_release_id' => $post_id));
            }

            $urls_text = sanitize_textarea_field($bulk_urls_field);
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
                            'url' => sanitize_url($url),
                            'title' => sanitize_text_field($title)
                        ),
                        array('%d', '%s', '%s')
                    );
                }
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
                            <label><input type="radio" name="search" value="yes" checked> Yes</label>
                            <label><input type="radio" name="search" value="no"> No</label>
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
                if (search !== 'yes') params.push('search="' + search + '"');

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
}
?>