<?php
/**
 * Plugin Name: PressStack
 * Plugin URI: https://github.com/inboundinteractivegit/press-releases-plugin
 * Description: Efficiently manage and display press releases with advanced features like URL tracking, AJAX loading, and bulk import capabilities. Perfect for PR agencies, corporations, and marketing teams.
 * Version: 1.5.8
 * Author: Inbound Interactive
 * Author URI: https://inboundinteractive.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: press-releases-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8.2
 * Requires PHP: 7.4
 * Network: false
 * Update Server: https://api.github.com/repos/inboundinteractivegit/press-releases-plugin/releases/latest
 *
 * @package PressReleasesManager
 * @version 1.5.8
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main Press Releases Manager Class
 *
 * @since 1.0.0
 */
class PressReleasesManager {

    /**
     * Plugin version
     *
     * @var string
     * @since 1.0.0
     */
    const VERSION = '1.5.8';

    /**
     * Database version
     *
     * @var string
     * @since 1.0.0
     */
    const DB_VERSION = '1.5.8';

    /**
     * Table name
     *
     * @var string
     * @since 1.0.0
     */
    private $table_name;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'press_release_urls';

        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'wp_ajax_load_more_press_releases', array( $this, 'load_more_press_releases' ) );
        add_action( 'wp_ajax_nopriv_load_more_press_releases', array( $this, 'load_more_press_releases' ) );
        add_shortcode( 'press_releases', array( $this, 'display_press_releases' ) );

        register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );

        // Admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // AJAX handlers for admin
        add_action( 'wp_ajax_add_press_release', array( $this, 'ajax_add_press_release' ) );
        add_action( 'wp_ajax_delete_press_release', array( $this, 'ajax_delete_press_release' ) );
        add_action( 'wp_ajax_bulk_import_press_releases', array( $this, 'ajax_bulk_import_press_releases' ) );
        add_action( 'wp_ajax_update_press_release', array( $this, 'ajax_update_press_release' ) );

        // Security features
        add_action( 'admin_init', array( $this, 'security_headers' ) );
        add_action( 'wp_loaded', array( $this, 'verify_nonces' ) );
    }

    /**
     * Initialize plugin
     *
     * @since 1.0.0
     */
    public function init() {
        load_plugin_textdomain( 'press-releases-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script( 'press-releases-js', plugin_dir_url( __FILE__ ) . 'press-releases.js', array( 'jquery' ), '1.5.8', true );
        wp_enqueue_style( 'press-releases-css', plugin_dir_url( __FILE__ ) . 'press-releases.css', array(), '1.5.8' );

        wp_localize_script( 'press-releases-js', 'press_releases_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'press_releases_nonce' )
        ) );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @since 1.0.0
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'press-releases' ) !== false ) {
            wp_enqueue_script( 'press-releases-admin-js', plugin_dir_url( __FILE__ ) . 'admin/admin.js', array( 'jquery' ), '1.5.8', true );
            wp_enqueue_style( 'press-releases-admin-css', plugin_dir_url( __FILE__ ) . 'admin/admin.css', array(), '1.5.8' );

            wp_localize_script( 'press-releases-admin-js', 'press_releases_admin_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'press_releases_admin_nonce' )
            ) );
        }
    }

    /**
     * Plugin activation
     *
     * @since 1.0.0
     */
    public function activate_plugin() {
        $this->create_table();
        update_option( 'pressstack_version', '1.5.8' );

        // Set default options
        add_option( 'pressstack_security_enabled', true );
        add_option( 'pressstack_nonce_verification', true );
        add_option( 'pressstack_rate_limiting', true );

        // Schedule cleanup
        if ( ! wp_next_scheduled( 'pressstack_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'pressstack_cleanup' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     *
     * @since 1.0.0
     */
    public function deactivate_plugin() {
        wp_clear_scheduled_hook( 'pressstack_cleanup' );
        flush_rewrite_rules();
    }

    /**
     * Create database table
     *
     * @since 1.0.0
     */
    private function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            click_count int(11) DEFAULT 0,
            last_clicked datetime,
            category varchar(100),
            tags text,
            priority int(3) DEFAULT 0,
            featured tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY url_index (url(191)),
            KEY date_index (date_added),
            KEY active_index (is_active),
            KEY category_index (category),
            KEY priority_index (priority),
            KEY featured_index (featured)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'pressstack_db_version', self::DB_VERSION );
    }

    /**
     * Add admin menu
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Press Releases', 'press-releases-manager' ),
            __( 'Press Releases', 'press-releases-manager' ),
            'manage_options',
            'press-releases-manager',
            array( $this, 'admin_page' ),
            'dashicons-megaphone',
            30
        );

        add_submenu_page(
            'press-releases-manager',
            __( 'All Press Releases', 'press-releases-manager' ),
            __( 'All Press Releases', 'press-releases-manager' ),
            'manage_options',
            'press-releases-manager',
            array( $this, 'admin_page' )
        );

        add_submenu_page(
            'press-releases-manager',
            __( 'Add New', 'press-releases-manager' ),
            __( 'Add New', 'press-releases-manager' ),
            'manage_options',
            'press-releases-add-new',
            array( $this, 'add_new_page' )
        );

        add_submenu_page(
            'press-releases-manager',
            __( 'Bulk Import', 'press-releases-manager' ),
            __( 'Bulk Import', 'press-releases-manager' ),
            'manage_options',
            'press-releases-bulk-import',
            array( $this, 'bulk_import_page' )
        );

        add_submenu_page(
            'press-releases-manager',
            __( 'Settings', 'press-releases-manager' ),
            __( 'Settings', 'press-releases-manager' ),
            'manage_options',
            'press-releases-settings',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Admin page
     *
     * @since 1.0.0
     */
    public function admin_page() {
        global $wpdb;

        // Handle search and pagination
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset = ( $page - 1 ) * $per_page;

        // Build query
        $where = "WHERE 1=1";
        $params = array();

        if ( ! empty( $search ) ) {
            $where .= " AND (url LIKE %s OR title LIKE %s OR description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$this->table_name} {$where}";
        if ( ! empty( $params ) ) {
            $total = $wpdb->get_var( $wpdb->prepare( $total_query, $params ) );
        } else {
            $total = $wpdb->get_var( $total_query );
        }

        // Get press releases
        $query = "SELECT * FROM {$this->table_name} {$where} ORDER BY date_added DESC LIMIT %d OFFSET %d";
        $final_params = array_merge( $params, array( $per_page, $offset ) );
        $press_releases = $wpdb->get_results( $wpdb->prepare( $query, $final_params ) );

        // Calculate pagination
        $total_pages = ceil( $total / $per_page );

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e( 'Press Releases', 'press-releases-manager' ); ?></h1>
            <a href="<?php echo admin_url( 'admin.php?page=press-releases-add-new' ); ?>" class="page-title-action"><?php _e( 'Add New', 'press-releases-manager' ); ?></a>

            <div class="pressstack-security-notice" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 20px 0; border-radius: 5px;">
                <strong>🛡️ <?php _e( 'Security Features Active', 'press-releases-manager' ); ?> (v1.5.8)</strong><br>
                <?php _e( 'Nonce verification, rate limiting, and input sanitization are protecting your press releases.', 'press-releases-manager' ); ?>
            </div>

            <form method="get" class="search-form" style="margin: 20px 0;">
                <input type="hidden" name="page" value="press-releases-manager" />
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php _e( 'Search press releases...', 'press-releases-manager' ); ?>" />
                    <input type="submit" class="button" value="<?php _e( 'Search', 'press-releases-manager' ); ?>" />
                    <?php if ( ! empty( $search ) ): ?>
                        <a href="<?php echo admin_url( 'admin.php?page=press-releases-manager' ); ?>" class="button"><?php _e( 'Clear', 'press-releases-manager' ); ?></a>
                    <?php endif; ?>
                </p>
            </form>

            <?php if ( ! empty( $search ) ): ?>
                <p><?php printf( __( 'Search results for: <strong>%s</strong> (%d found)', 'press-releases-manager' ), esc_html( $search ), $total ); ?></p>
            <?php endif; ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e( 'Bulk Actions', 'press-releases-manager' ); ?></option>
                        <option value="delete"><?php _e( 'Delete', 'press-releases-manager' ); ?></option>
                        <option value="activate"><?php _e( 'Activate', 'press-releases-manager' ); ?></option>
                        <option value="deactivate"><?php _e( 'Deactivate', 'press-releases-manager' ); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e( 'Apply', 'press-releases-manager' ); ?>" />
                </div>

                <?php if ( $total_pages > 1 ): ?>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf( __( '%d items', 'press-releases-manager' ), $total ); ?></span>
                    <?php
                    $pagination_args = array(
                        'base' => add_query_arg( 'paged', '%#%' ),
                        'format' => '',
                        'prev_text' => __( '&laquo;', 'press-releases-manager' ),
                        'next_text' => __( '&raquo;', 'press-releases-manager' ),
                        'total' => $total_pages,
                        'current' => $page
                    );

                    if ( ! empty( $search ) ) {
                        $pagination_args['add_args'] = array( 's' => $search );
                    }

                    echo paginate_links( $pagination_args );
                    ?>
                </div>
                <?php endif; ?>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" />
                        </td>
                        <th class="manage-column"><?php _e( 'Title', 'press-releases-manager' ); ?></th>
                        <th class="manage-column"><?php _e( 'URL', 'press-releases-manager' ); ?></th>
                        <th class="manage-column"><?php _e( 'Category', 'press-releases-manager' ); ?></th>
                        <th class="manage-column"><?php _e( 'Date Added', 'press-releases-manager' ); ?></th>
                        <th class="manage-column"><?php _e( 'Clicks', 'press-releases-manager' ); ?></th>
                        <th class="manage-column"><?php _e( 'Status', 'press-releases-manager' ); ?></th>
                        <th class="manage-column"><?php _e( 'Actions', 'press-releases-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $press_releases ) ): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <?php if ( ! empty( $search ) ): ?>
                                    <?php _e( 'No press releases found matching your search.', 'press-releases-manager' ); ?>
                                <?php else: ?>
                                    <?php _e( 'No press releases found. Start by adding your first press release!', 'press-releases-manager' ); ?>
                                    <br><br>
                                    <a href="<?php echo admin_url( 'admin.php?page=press-releases-add-new' ); ?>" class="button button-primary"><?php _e( 'Add Your First Press Release', 'press-releases-manager' ); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ( $press_releases as $release ): ?>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" name="press_release[]" value="<?php echo esc_attr( $release->id ); ?>" />
                                </th>
                                <td>
                                    <strong><?php echo esc_html( $release->title ); ?></strong>
                                    <?php if ( $release->featured ): ?>
                                        <span class="dashicons dashicons-star-filled" style="color: #ffb900;" title="<?php _e( 'Featured', 'press-releases-manager' ); ?>"></span>
                                    <?php endif; ?>
                                    <?php if ( $release->description ): ?>
                                        <br><small style="color: #666;"><?php echo esc_html( wp_trim_words( $release->description, 15 ) ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( $release->url ); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html( wp_trim_words( $release->url, 8, '...' ) ); ?>
                                        <span class="dashicons dashicons-external" style="font-size: 12px;"></span>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $release->category ? $release->category : __( 'Uncategorized', 'press-releases-manager' ) ); ?></td>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $release->date_added ) ) ); ?></td>
                                <td>
                                    <?php echo esc_html( $release->click_count ); ?>
                                    <?php if ( $release->last_clicked ): ?>
                                        <br><small style="color: #666;"><?php _e( 'Last:', 'press-releases-manager' ); ?> <?php echo esc_html( date_i18n( 'M j', strtotime( $release->last_clicked ) ) ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $release->is_active ): ?>
                                        <span style="color: #46b450;">●</span> <?php _e( 'Active', 'press-releases-manager' ); ?>
                                    <?php else: ?>
                                        <span style="color: #dc3232;">●</span> <?php _e( 'Inactive', 'press-releases-manager' ); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="#" class="edit-press-release" data-id="<?php echo esc_attr( $release->id ); ?>"><?php _e( 'Edit', 'press-releases-manager' ); ?></a> |
                                    <a href="#" class="delete-press-release" data-id="<?php echo esc_attr( $release->id ); ?>" style="color: #dc3232;"><?php _e( 'Delete', 'press-releases-manager' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf( __( '%d items', 'press-releases-manager' ), $total ); ?></span>
                    <?php echo paginate_links( $pagination_args ); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Edit Modal -->
        <div id="edit-press-release-modal" class="press-release-modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><?php _e( 'Edit Press Release', 'press-releases-manager' ); ?></h2>
                <form id="edit-press-release-form">
                    <input type="hidden" id="edit-press-release-id" name="id" />
                    <?php wp_nonce_field( 'press_releases_admin_nonce', 'edit_nonce' ); ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="edit-title"><?php _e( 'Title', 'press-releases-manager' ); ?></label></th>
                            <td><input type="text" id="edit-title" name="title" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th><label for="edit-url"><?php _e( 'URL', 'press-releases-manager' ); ?></label></th>
                            <td><input type="url" id="edit-url" name="url" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th><label for="edit-description"><?php _e( 'Description', 'press-releases-manager' ); ?></label></th>
                            <td><textarea id="edit-description" name="description" rows="3" class="large-text"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="edit-category"><?php _e( 'Category', 'press-releases-manager' ); ?></label></th>
                            <td><input type="text" id="edit-category" name="category" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="edit-tags"><?php _e( 'Tags', 'press-releases-manager' ); ?></label></th>
                            <td><input type="text" id="edit-tags" name="tags" class="regular-text" placeholder="<?php _e( 'Comma separated', 'press-releases-manager' ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="edit-priority"><?php _e( 'Priority', 'press-releases-manager' ); ?></label></th>
                            <td>
                                <select id="edit-priority" name="priority">
                                    <option value="0"><?php _e( 'Normal', 'press-releases-manager' ); ?></option>
                                    <option value="1"><?php _e( 'High', 'press-releases-manager' ); ?></option>
                                    <option value="2"><?php _e( 'Urgent', 'press-releases-manager' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e( 'Options', 'press-releases-manager' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="edit-is-active" name="is_active" value="1" />
                                    <?php _e( 'Active', 'press-releases-manager' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" id="edit-featured" name="featured" value="1" />
                                    <?php _e( 'Featured', 'press-releases-manager' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php _e( 'Update Press Release', 'press-releases-manager' ); ?>" />
                        <button type="button" class="button cancel-edit"><?php _e( 'Cancel', 'press-releases-manager' ); ?></button>
                    </p>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Edit press release
            $('.edit-press-release').on('click', function(e) {
                e.preventDefault();
                var id = $(this).data('id');

                // Get press release data via AJAX
                $.post(ajaxurl, {
                    action: 'get_press_release',
                    id: id,
                    nonce: '<?php echo wp_create_nonce( 'press_releases_admin_nonce' ); ?>'
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#edit-press-release-id').val(data.id);
                        $('#edit-title').val(data.title);
                        $('#edit-url').val(data.url);
                        $('#edit-description').val(data.description);
                        $('#edit-category').val(data.category);
                        $('#edit-tags').val(data.tags);
                        $('#edit-priority').val(data.priority);
                        $('#edit-is-active').prop('checked', data.is_active == '1');
                        $('#edit-featured').prop('checked', data.featured == '1');

                        $('#edit-press-release-modal').show();
                    }
                });
            });

            // Close modal
            $('.close, .cancel-edit').on('click', function() {
                $('#edit-press-release-modal').hide();
            });

            // Update press release
            $('#edit-press-release-form').on('submit', function(e) {
                e.preventDefault();

                $.post(ajaxurl, $(this).serialize() + '&action=update_press_release', function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });

            // Delete press release
            $('.delete-press-release').on('click', function(e) {
                e.preventDefault();

                if (confirm('<?php _e( 'Are you sure you want to delete this press release?', 'press-releases-manager' ); ?>')) {
                    var id = $(this).data('id');

                    $.post(ajaxurl, {
                        action: 'delete_press_release',
                        id: id,
                        nonce: '<?php echo wp_create_nonce( 'press_releases_admin_nonce' ); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                }
            });
        });
        </script>

        <style>
        .press-release-modal {
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 600px;
            border-radius: 5px;
            position: relative;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            right: 15px;
            top: 10px;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
        }
        </style>
        <?php
    }

    /**
     * Add new press release page
     *
     * @since 1.0.0
     */
    public function add_new_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Add New Press Release', 'press-releases-manager' ); ?></h1>

            <form id="add-press-release-form" method="post">
                <?php wp_nonce_field( 'press_releases_admin_nonce', 'add_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="title"><?php _e( 'Title', 'press-releases-manager' ); ?> <span class="description">(required)</span></label>
                        </th>
                        <td>
                            <input name="title" type="text" id="title" class="regular-text" required />
                            <p class="description"><?php _e( 'Enter a descriptive title for this press release.', 'press-releases-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="url"><?php _e( 'URL', 'press-releases-manager' ); ?> <span class="description">(required)</span></label>
                        </th>
                        <td>
                            <input name="url" type="url" id="url" class="regular-text" required />
                            <p class="description"><?php _e( 'The full URL to the press release.', 'press-releases-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="description"><?php _e( 'Description', 'press-releases-manager' ); ?></label>
                        </th>
                        <td>
                            <textarea name="description" id="description" rows="4" cols="50" class="large-text"></textarea>
                            <p class="description"><?php _e( 'Optional description or summary of the press release.', 'press-releases-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="category"><?php _e( 'Category', 'press-releases-manager' ); ?></label>
                        </th>
                        <td>
                            <input name="category" type="text" id="category" class="regular-text" />
                            <p class="description"><?php _e( 'Categorize this press release for better organization.', 'press-releases-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tags"><?php _e( 'Tags', 'press-releases-manager' ); ?></label>
                        </th>
                        <td>
                            <input name="tags" type="text" id="tags" class="regular-text" />
                            <p class="description"><?php _e( 'Comma-separated tags for this press release.', 'press-releases-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="priority"><?php _e( 'Priority', 'press-releases-manager' ); ?></label>
                        </th>
                        <td>
                            <select name="priority" id="priority">
                                <option value="0"><?php _e( 'Normal', 'press-releases-manager' ); ?></option>
                                <option value="1"><?php _e( 'High', 'press-releases-manager' ); ?></option>
                                <option value="2"><?php _e( 'Urgent', 'press-releases-manager' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Set the priority level for this press release.', 'press-releases-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Options', 'press-releases-manager' ); ?></th>
                        <td>
                            <fieldset>
                                <label for="is_active">
                                    <input name="is_active" type="checkbox" id="is_active" value="1" checked />
                                    <?php _e( 'Active', 'press-releases-manager' ); ?>
                                </label>
                                <br />
                                <label for="featured">
                                    <input name="featured" type="checkbox" id="featured" value="1" />
                                    <?php _e( 'Featured', 'press-releases-manager' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Add Press Release', 'press-releases-manager' ) ); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#add-press-release-form').on('submit', function(e) {
                e.preventDefault();

                $.post(ajaxurl, $(this).serialize() + '&action=add_press_release', function(response) {
                    if (response.success) {
                        alert('<?php _e( 'Press release added successfully!', 'press-releases-manager' ); ?>');
                        window.location.href = '<?php echo admin_url( 'admin.php?page=press-releases-manager' ); ?>';
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Bulk import page
     *
     * @since 1.0.0
     */
    public function bulk_import_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Bulk Import Press Releases', 'press-releases-manager' ); ?></h1>

            <div class="card">
                <h2><?php _e( 'Import from Text', 'press-releases-manager' ); ?></h2>
                <p><?php _e( 'Paste URLs (one per line) or formatted text with titles and URLs.', 'press-releases-manager' ); ?></p>

                <form id="bulk-import-form" method="post">
                    <?php wp_nonce_field( 'press_releases_admin_nonce', 'bulk_import_nonce' ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="bulk_urls"><?php _e( 'URLs or Formatted Text', 'press-releases-manager' ); ?></label>
                            </th>
                            <td>
                                <textarea name="bulk_urls" id="bulk_urls" rows="10" cols="80" class="large-text" placeholder="<?php _e( 'Enter URLs (one per line) or formatted text...', 'press-releases-manager' ); ?>"></textarea>
                                <p class="description">
                                    <?php _e( 'Supported formats:', 'press-releases-manager' ); ?><br>
                                    • <?php _e( 'Simple URLs (one per line)', 'press-releases-manager' ); ?><br>
                                    • <?php _e( 'Title | URL', 'press-releases-manager' ); ?><br>
                                    • <?php _e( 'Title - URL', 'press-releases-manager' ); ?><br>
                                    • <?php _e( 'HTML links will be parsed automatically', 'press-releases-manager' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="default_category"><?php _e( 'Default Category', 'press-releases-manager' ); ?></label>
                            </th>
                            <td>
                                <input name="default_category" type="text" id="default_category" class="regular-text" />
                                <p class="description"><?php _e( 'Category to assign to imported press releases.', 'press-releases-manager' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="default_tags"><?php _e( 'Default Tags', 'press-releases-manager' ); ?></label>
                            </th>
                            <td>
                                <input name="default_tags" type="text" id="default_tags" class="regular-text" />
                                <p class="description"><?php _e( 'Comma-separated tags to assign to imported press releases.', 'press-releases-manager' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Options', 'press-releases-manager' ); ?></th>
                            <td>
                                <fieldset>
                                    <label for="skip_duplicates">
                                        <input name="skip_duplicates" type="checkbox" id="skip_duplicates" value="1" checked />
                                        <?php _e( 'Skip duplicate URLs', 'press-releases-manager' ); ?>
                                    </label>
                                    <br />
                                    <label for="auto_activate">
                                        <input name="auto_activate" type="checkbox" id="auto_activate" value="1" checked />
                                        <?php _e( 'Activate imported press releases', 'press-releases-manager' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php _e( 'Import Press Releases', 'press-releases-manager' ); ?>" />
                        <button type="button" id="preview-import" class="button"><?php _e( 'Preview Import', 'press-releases-manager' ); ?></button>
                    </p>
                </form>
            </div>

            <div id="import-preview" class="card" style="display: none;">
                <h2><?php _e( 'Import Preview', 'press-releases-manager' ); ?></h2>
                <div id="preview-content"></div>
            </div>

            <div id="import-results" class="card" style="display: none;">
                <h2><?php _e( 'Import Results', 'press-releases-manager' ); ?></h2>
                <div id="results-content"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Preview import
            $('#preview-import').on('click', function() {
                var urls = $('#bulk_urls').val();
                if (!urls.trim()) {
                    alert('<?php _e( 'Please enter some URLs or text to preview.', 'press-releases-manager' ); ?>');
                    return;
                }

                $.post(ajaxurl, {
                    action: 'preview_bulk_import',
                    bulk_urls: urls,
                    nonce: '<?php echo wp_create_nonce( 'press_releases_admin_nonce' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#preview-content').html(response.data);
                        $('#import-preview').show();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });

            // Bulk import
            $('#bulk-import-form').on('submit', function(e) {
                e.preventDefault();

                var $button = $(this).find('input[type="submit"]');
                $button.prop('disabled', true).val('<?php _e( 'Importing...', 'press-releases-manager' ); ?>');

                $.post(ajaxurl, $(this).serialize() + '&action=bulk_import_press_releases', function(response) {
                    $button.prop('disabled', false).val('<?php _e( 'Import Press Releases', 'press-releases-manager' ); ?>');

                    if (response.success) {
                        $('#results-content').html(response.data);
                        $('#import-results').show();
                        $('#bulk_urls').val('');
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Settings page
     *
     * @since 1.0.0
     */
    public function settings_page() {
        // Handle form submission
        if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['settings_nonce'], 'press_releases_settings_nonce' ) ) {
            update_option( 'pressstack_security_enabled', isset( $_POST['security_enabled'] ) );
            update_option( 'pressstack_nonce_verification', isset( $_POST['nonce_verification'] ) );
            update_option( 'pressstack_rate_limiting', isset( $_POST['rate_limiting'] ) );
            update_option( 'pressstack_default_limit', intval( $_POST['default_limit'] ) );
            update_option( 'pressstack_cache_duration', intval( $_POST['cache_duration'] ) );

            echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully!', 'press-releases-manager' ) . '</p></div>';
        }

        // Get current settings
        $security_enabled = get_option( 'pressstack_security_enabled', true );
        $nonce_verification = get_option( 'pressstack_nonce_verification', true );
        $rate_limiting = get_option( 'pressstack_rate_limiting', true );
        $default_limit = get_option( 'pressstack_default_limit', 10 );
        $cache_duration = get_option( 'pressstack_cache_duration', 3600 );

        ?>
        <div class="wrap">
            <h1><?php _e( 'Press Releases Settings', 'press-releases-manager' ); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field( 'press_releases_settings_nonce', 'settings_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Security Settings', 'press-releases-manager' ); ?></th>
                        <td>
                            <fieldset>
                                <label for="security_enabled">
                                    <input name="security_enabled" type="checkbox" id="security_enabled" value="1" <?php checked( $security_enabled ); ?> />
                                    <?php _e( 'Enable security features', 'press-releases-manager' ); ?>
                                </label>
                                <br />
                                <label for="nonce_verification">
                                    <input name="nonce_verification" type="checkbox" id="nonce_verification" value="1" <?php checked( $nonce_verification ); ?> />
                                    <?php _e( 'Enable nonce verification', 'press-releases-manager' ); ?>
                                </label>
                                <br />
                                <label for="rate_limiting">
                                    <input name="rate_limiting" type="checkbox" id="rate_limiting" value="1" <?php checked( $rate_limiting ); ?> />
                                    <?php _e( 'Enable rate limiting', 'press-releases-manager' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_limit"><?php _e( 'Default Display Limit', 'press-releases-manager' ); ?></label>
                        </th>
                        <td>
                            <input name="default_limit" type="number" id="default_limit" value="<?php echo esc_attr( $default_limit ); ?>" min="1" max="100" class="small-text" />
                            <p class="description"><?php _e( 'Default number of press releases to display in shortcode.', 'press-releases-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cache_duration"><?php _e( 'Cache Duration', 'press-releases-manager' ); ?></label>
                        </th>
                        <td>
                            <input name="cache_duration" type="number" id="cache_duration" value="<?php echo esc_attr( $cache_duration ); ?>" min="0" class="small-text" />
                            <p class="description"><?php _e( 'Cache duration in seconds (0 to disable caching).', 'press-releases-manager' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <div class="card">
                <h2><?php _e( 'System Information', 'press-releases-manager' ); ?></h2>
                <table class="widefat">
                    <tr>
                        <td><strong><?php _e( 'Plugin Version', 'press-releases-manager' ); ?></strong></td>
                        <td><?php echo esc_html( self::VERSION ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e( 'Database Version', 'press-releases-manager' ); ?></strong></td>
                        <td><?php echo esc_html( get_option( 'pressstack_db_version', 'Unknown' ) ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e( 'Total Press Releases', 'press-releases-manager' ); ?></strong></td>
                        <td><?php echo esc_html( $this->get_total_count() ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e( 'Active Press Releases', 'press-releases-manager' ); ?></strong></td>
                        <td><?php echo esc_html( $this->get_active_count() ); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get total press releases count
     *
     * @return int
     * @since 1.0.0
     */
    private function get_total_count() {
        global $wpdb;
        return $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
    }

    /**
     * Get active press releases count
     *
     * @return int
     * @since 1.0.0
     */
    private function get_active_count() {
        global $wpdb;
        return $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE is_active = 1" );
    }

    /**
     * Display press releases shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function display_press_releases( $atts ) {
        $atts = shortcode_atts( array(
            'limit' => get_option( 'pressstack_default_limit', 10 ),
            'category' => '',
            'featured' => '',
            'orderby' => 'date_added',
            'order' => 'DESC',
            'show_search' => true,
            'show_categories' => true,
            'accordion' => true
        ), $atts, 'press_releases' );

        global $wpdb;

        // Build query
        $where = "WHERE is_active = 1";
        $params = array();

        if ( ! empty( $atts['category'] ) ) {
            $where .= " AND category = %s";
            $params[] = $atts['category'];
        }

        if ( ! empty( $atts['featured'] ) && $atts['featured'] !== 'false' ) {
            $where .= " AND featured = 1";
        }

        // Sanitize orderby and order
        $allowed_orderby = array( 'date_added', 'title', 'click_count', 'priority' );
        $orderby = in_array( $atts['orderby'], $allowed_orderby ) ? $atts['orderby'] : 'date_added';
        $order = strtoupper( $atts['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $query = "SELECT * FROM {$this->table_name} {$where} ORDER BY {$orderby} {$order} LIMIT %d";
        $params[] = intval( $atts['limit'] );

        $press_releases = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

        if ( empty( $press_releases ) ) {
            return '<p class="press-releases-empty">' . __( 'No press releases found.', 'press-releases-manager' ) . '</p>';
        }

        $output = '<div class="press-releases-container" data-accordion="' . ( $atts['accordion'] ? 'true' : 'false' ) . '">';

        // Search and filters
        if ( $atts['show_search'] || $atts['show_categories'] ) {
            $output .= '<div class="press-releases-filters">';

            if ( $atts['show_search'] ) {
                $output .= '<div class="press-releases-search">';
                $output .= '<input type="text" id="press-releases-search" placeholder="' . esc_attr__( 'Search press releases...', 'press-releases-manager' ) . '" />';
                $output .= '</div>';
            }

            if ( $atts['show_categories'] ) {
                $categories = $wpdb->get_results( "SELECT DISTINCT category FROM {$this->table_name} WHERE is_active = 1 AND category IS NOT NULL AND category != ''" );

                if ( ! empty( $categories ) ) {
                    $output .= '<div class="press-releases-categories">';
                    $output .= '<select id="press-releases-category-filter">';
                    $output .= '<option value="">' . __( 'All Categories', 'press-releases-manager' ) . '</option>';

                    foreach ( $categories as $category ) {
                        $output .= '<option value="' . esc_attr( $category->category ) . '">' . esc_html( $category->category ) . '</option>';
                    }

                    $output .= '</select>';
                    $output .= '</div>';
                }
            }

            $output .= '</div>';
        }

        // Press releases list
        if ( $atts['accordion'] ) {
            $output .= '<div class="press-releases-accordion">';

            foreach ( $press_releases as $release ) {
                $output .= '<div class="press-release-item" data-category="' . esc_attr( $release->category ) . '">';
                $output .= '<div class="press-release-header">';
                $output .= '<h3 class="press-release-title">' . esc_html( $release->title );

                if ( $release->featured ) {
                    $output .= ' <span class="press-release-featured">★</span>';
                }

                $output .= '</h3>';
                $output .= '<span class="press-release-toggle">+</span>';
                $output .= '</div>';

                $output .= '<div class="press-release-content">';

                if ( $release->description ) {
                    $output .= '<p class="press-release-description">' . esc_html( $release->description ) . '</p>';
                }

                $output .= '<p class="press-release-meta">';
                $output .= '<a href="' . esc_url( $release->url ) . '" target="_blank" rel="noopener" class="press-release-link" data-id="' . esc_attr( $release->id ) . '">';
                $output .= __( 'Read Full Press Release', 'press-releases-manager' ) . ' →</a>';

                if ( $release->category ) {
                    $output .= ' <span class="press-release-category">' . esc_html( $release->category ) . '</span>';
                }

                $output .= ' <span class="press-release-date">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $release->date_added ) ) ) . '</span>';
                $output .= '</p>';

                $output .= '</div>';
                $output .= '</div>';
            }

            $output .= '</div>';
        } else {
            $output .= '<div class="press-releases-list">';

            foreach ( $press_releases as $release ) {
                $output .= '<div class="press-release-item" data-category="' . esc_attr( $release->category ) . '">';
                $output .= '<h3 class="press-release-title">' . esc_html( $release->title );

                if ( $release->featured ) {
                    $output .= ' <span class="press-release-featured">★</span>';
                }

                $output .= '</h3>';

                if ( $release->description ) {
                    $output .= '<p class="press-release-description">' . esc_html( $release->description ) . '</p>';
                }

                $output .= '<p class="press-release-meta">';
                $output .= '<a href="' . esc_url( $release->url ) . '" target="_blank" rel="noopener" class="press-release-link" data-id="' . esc_attr( $release->id ) . '">';
                $output .= __( 'Read Full Press Release', 'press-releases-manager' ) . ' →</a>';

                if ( $release->category ) {
                    $output .= ' <span class="press-release-category">' . esc_html( $release->category ) . '</span>';
                }

                $output .= ' <span class="press-release-date">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $release->date_added ) ) ) . '</span>';
                $output .= '</p>';
                $output .= '</div>';
            }

            $output .= '</div>';
        }

        // Load more button (if there are more items)
        $total_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} {$where}", array_slice( $params, 0, -1 ) ) );

        if ( $total_count > intval( $atts['limit'] ) ) {
            $output .= '<div class="press-releases-load-more">';
            $output .= '<button id="load-more-press-releases" data-loaded="' . intval( $atts['limit'] ) . '" data-total="' . esc_attr( $total_count ) . '">';
            $output .= __( 'Load More Press Releases', 'press-releases-manager' );
            $output .= '</button>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Load more press releases (AJAX)
     *
     * @since 1.0.0
     */
    public function load_more_press_releases() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'press_releases_nonce' ) ) {
            wp_die( __( 'Security check failed', 'press-releases-manager' ) );
        }

        $offset = intval( $_POST['offset'] );
        $limit = intval( $_POST['limit'] );
        $category = sanitize_text_field( $_POST['category'] );
        $search = sanitize_text_field( $_POST['search'] );

        global $wpdb;

        // Build query
        $where = "WHERE is_active = 1";
        $params = array();

        if ( ! empty( $category ) ) {
            $where .= " AND category = %s";
            $params[] = $category;
        }

        if ( ! empty( $search ) ) {
            $where .= " AND (title LIKE %s OR description LIKE %s OR url LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $query = "SELECT * FROM {$this->table_name} {$where} ORDER BY date_added DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $press_releases = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

        $html = '';

        foreach ( $press_releases as $release ) {
            $html .= '<div class="press-release-item" data-category="' . esc_attr( $release->category ) . '">';
            $html .= '<div class="press-release-header">';
            $html .= '<h3 class="press-release-title">' . esc_html( $release->title );

            if ( $release->featured ) {
                $html .= ' <span class="press-release-featured">★</span>';
            }

            $html .= '</h3>';
            $html .= '<span class="press-release-toggle">+</span>';
            $html .= '</div>';

            $html .= '<div class="press-release-content">';

            if ( $release->description ) {
                $html .= '<p class="press-release-description">' . esc_html( $release->description ) . '</p>';
            }

            $html .= '<p class="press-release-meta">';
            $html .= '<a href="' . esc_url( $release->url ) . '" target="_blank" rel="noopener" class="press-release-link" data-id="' . esc_attr( $release->id ) . '">';
            $html .= __( 'Read Full Press Release', 'press-releases-manager' ) . ' →</a>';

            if ( $release->category ) {
                $html .= ' <span class="press-release-category">' . esc_html( $release->category ) . '</span>';
            }

            $html .= ' <span class="press-release-date">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $release->date_added ) ) ) . '</span>';
            $html .= '</p>';

            $html .= '</div>';
            $html .= '</div>';
        }

        wp_send_json_success( $html );
    }

    /**
     * Add press release (AJAX)
     *
     * @since 1.0.0
     */
    public function ajax_add_press_release() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['add_nonce'], 'press_releases_admin_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed', 'press-releases-manager' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'press-releases-manager' ) );
        }

        $title = sanitize_text_field( $_POST['title'] );
        $url = esc_url_raw( $_POST['url'] );
        $description = sanitize_textarea_field( $_POST['description'] );
        $category = sanitize_text_field( $_POST['category'] );
        $tags = sanitize_text_field( $_POST['tags'] );
        $priority = intval( $_POST['priority'] );
        $is_active = isset( $_POST['is_active'] ) ? 1 : 0;
        $featured = isset( $_POST['featured'] ) ? 1 : 0;

        // Validate required fields
        if ( empty( $title ) || empty( $url ) ) {
            wp_send_json_error( __( 'Title and URL are required', 'press-releases-manager' ) );
        }

        global $wpdb;

        // Check for duplicate URL
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE url = %s", $url ) );

        if ( $existing ) {
            wp_send_json_error( __( 'A press release with this URL already exists', 'press-releases-manager' ) );
        }

        // Insert press release
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'title' => $title,
                'url' => $url,
                'description' => $description,
                'category' => $category,
                'tags' => $tags,
                'priority' => $priority,
                'is_active' => $is_active,
                'featured' => $featured,
                'date_added' => current_time( 'mysql' )
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
        );

        if ( $result === false ) {
            wp_send_json_error( __( 'Failed to add press release', 'press-releases-manager' ) );
        }

        wp_send_json_success( __( 'Press release added successfully', 'press-releases-manager' ) );
    }

    /**
     * Delete press release (AJAX)
     *
     * @since 1.0.0
     */
    public function ajax_delete_press_release() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'press_releases_admin_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed', 'press-releases-manager' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'press-releases-manager' ) );
        }

        $id = intval( $_POST['id'] );

        if ( ! $id ) {
            wp_send_json_error( __( 'Invalid press release ID', 'press-releases-manager' ) );
        }

        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( $result === false ) {
            wp_send_json_error( __( 'Failed to delete press release', 'press-releases-manager' ) );
        }

        wp_send_json_success( __( 'Press release deleted successfully', 'press-releases-manager' ) );
    }

    /**
     * Update press release (AJAX)
     *
     * @since 1.0.0
     */
    public function ajax_update_press_release() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['edit_nonce'], 'press_releases_admin_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed', 'press-releases-manager' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'press-releases-manager' ) );
        }

        $id = intval( $_POST['id'] );
        $title = sanitize_text_field( $_POST['title'] );
        $url = esc_url_raw( $_POST['url'] );
        $description = sanitize_textarea_field( $_POST['description'] );
        $category = sanitize_text_field( $_POST['category'] );
        $tags = sanitize_text_field( $_POST['tags'] );
        $priority = intval( $_POST['priority'] );
        $is_active = isset( $_POST['is_active'] ) ? 1 : 0;
        $featured = isset( $_POST['featured'] ) ? 1 : 0;

        // Validate required fields
        if ( ! $id || empty( $title ) || empty( $url ) ) {
            wp_send_json_error( __( 'ID, title and URL are required', 'press-releases-manager' ) );
        }

        global $wpdb;

        // Check for duplicate URL (excluding current record)
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE url = %s AND id != %d", $url, $id ) );

        if ( $existing ) {
            wp_send_json_error( __( 'A press release with this URL already exists', 'press-releases-manager' ) );
        }

        // Update press release
        $result = $wpdb->update(
            $this->table_name,
            array(
                'title' => $title,
                'url' => $url,
                'description' => $description,
                'category' => $category,
                'tags' => $tags,
                'priority' => $priority,
                'is_active' => $is_active,
                'featured' => $featured
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' ),
            array( '%d' )
        );

        if ( $result === false ) {
            wp_send_json_error( __( 'Failed to update press release', 'press-releases-manager' ) );
        }

        wp_send_json_success( __( 'Press release updated successfully', 'press-releases-manager' ) );
    }

    /**
     * Bulk import press releases (AJAX)
     *
     * @since 1.0.0
     */
    public function ajax_bulk_import_press_releases() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['bulk_import_nonce'], 'press_releases_admin_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed', 'press-releases-manager' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'press-releases-manager' ) );
        }

        $bulk_urls = sanitize_textarea_field( $_POST['bulk_urls'] );
        $default_category = sanitize_text_field( $_POST['default_category'] );
        $default_tags = sanitize_text_field( $_POST['default_tags'] );
        $skip_duplicates = isset( $_POST['skip_duplicates'] );
        $auto_activate = isset( $_POST['auto_activate'] );

        if ( empty( $bulk_urls ) ) {
            wp_send_json_error( __( 'No URLs provided', 'press-releases-manager' ) );
        }

        $lines = array_filter( array_map( 'trim', explode( "\n", $bulk_urls ) ) );
        $imported = 0;
        $skipped = 0;
        $errors = array();

        global $wpdb;

        foreach ( $lines as $line ) {
            $title = '';
            $url = '';

            // Parse different formats
            if ( strpos( $line, '|' ) !== false ) {
                // Format: Title | URL
                $parts = array_map( 'trim', explode( '|', $line, 2 ) );
                $title = $parts[0];
                $url = isset( $parts[1] ) ? $parts[1] : '';
            } elseif ( strpos( $line, ' - ' ) !== false ) {
                // Format: Title - URL
                $parts = array_map( 'trim', explode( ' - ', $line, 2 ) );
                $title = $parts[0];
                $url = isset( $parts[1] ) ? $parts[1] : '';
            } elseif ( preg_match( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/', $line, $matches ) ) {
                // HTML link format
                $url = $matches[1];
                $title = strip_tags( $matches[2] );
            } else {
                // Assume it's just a URL
                $url = $line;
                $title = $this->generate_title_from_url( $url );
            }

            // Validate URL
            $url = esc_url_raw( $url );
            if ( ! $url ) {
                $errors[] = sprintf( __( 'Invalid URL: %s', 'press-releases-manager' ), $line );
                continue;
            }

            // Check for duplicates
            if ( $skip_duplicates ) {
                $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE url = %s", $url ) );

                if ( $existing ) {
                    $skipped++;
                    continue;
                }
            }

            // Insert press release
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'title' => $title,
                    'url' => $url,
                    'category' => $default_category,
                    'tags' => $default_tags,
                    'is_active' => $auto_activate ? 1 : 0,
                    'date_added' => current_time( 'mysql' )
                ),
                array( '%s', '%s', '%s', '%s', '%d', '%s' )
            );

            if ( $result !== false ) {
                $imported++;
            } else {
                $errors[] = sprintf( __( 'Failed to import: %s', 'press-releases-manager' ), $title );
            }
        }

        // Build success message
        $message = sprintf( __( 'Import completed: %d imported, %d skipped', 'press-releases-manager' ), $imported, $skipped );

        if ( ! empty( $errors ) ) {
            $message .= '<br><br><strong>' . __( 'Errors:', 'press-releases-manager' ) . '</strong><br>';
            $message .= implode( '<br>', array_slice( $errors, 0, 10 ) );

            if ( count( $errors ) > 10 ) {
                $message .= '<br>' . sprintf( __( '... and %d more errors', 'press-releases-manager' ), count( $errors ) - 10 );
            }
        }

        wp_send_json_success( $message );
    }

    /**
     * Generate title from URL
     *
     * @param string $url
     * @return string
     * @since 1.0.0
     */
    private function generate_title_from_url( $url ) {
        $parsed = parse_url( $url );

        if ( isset( $parsed['host'] ) ) {
            return ucfirst( str_replace( 'www.', '', $parsed['host'] ) ) . ' Press Release';
        }

        return __( 'Press Release', 'press-releases-manager' );
    }

    /**
     * Security headers
     *
     * @since 1.5.0
     */
    public function security_headers() {
        if ( ! get_option( 'pressstack_security_enabled', true ) ) {
            return;
        }

        // Add security headers
        if ( ! headers_sent() ) {
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: SAMEORIGIN' );
            header( 'X-XSS-Protection: 1; mode=block' );
        }
    }

    /**
     * Verify nonces for admin actions
     *
     * @since 1.5.0
     */
    public function verify_nonces() {
        if ( ! get_option( 'pressstack_nonce_verification', true ) ) {
            return;
        }

        // Verify nonces for specific actions
        if ( isset( $_POST['action'] ) && strpos( $_POST['action'], 'press_release' ) !== false ) {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'press_releases_admin_nonce' ) ) {
                wp_die( __( 'Security verification failed', 'press-releases-manager' ) );
            }
        }
    }
}

// Initialize the plugin
new PressReleasesManager();

// Plugin updater
if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'plugin-updater.php';
    $updater = new PressReleasesUpdater( __FILE__, 'inboundinteractivegit', 'press-releases-plugin' );
}