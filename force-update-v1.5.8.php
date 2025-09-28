<?php
/**
 * Force Update Script for v1.5.8 Sites
 * Upload this file to your WordPress site and run it to force update
 */

// WordPress security
if (!defined('ABSPATH')) {
    define('ABSPATH', true); // For testing - remove in production
}

class ForceUpdateHelper {

    public function __construct() {
        add_action('init', array($this, 'maybe_force_update'));
    }

    public function maybe_force_update() {
        // Only run if explicitly requested
        if (!isset($_GET['force_pressstack_update']) || !current_user_can('manage_options')) {
            return;
        }

        echo "<h2>Force Update PressStack from v1.5.8 to v1.5.9</h2>";

        // Step 1: Clear all update caches
        echo "<p>Clearing WordPress update caches...</p>";
        delete_site_transient('update_plugins');
        delete_transient('press_releases_remote_version');
        wp_clean_plugins_cache();

        // Step 2: Force download and install
        echo "<p>Downloading v1.5.9...</p>";
        $download_url = 'https://github.com/inboundinteractivegit/press-releases-plugin/releases/download/v1.5.9/press-releases-plugin-v1.5.9.zip';

        // Use WordPress HTTP API
        $response = wp_remote_get($download_url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        ));

        if (is_wp_error($response)) {
            echo "<p style='color:red;'>Download failed: " . $response->get_error_message() . "</p>";
            return;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            echo "<p style='color:red;'>Download failed: HTTP " . wp_remote_retrieve_response_code($response) . "</p>";
            return;
        }

        echo "<p style='color:green;'>Download successful!</p>";

        // Step 3: Install the update
        echo "<p>Installing update...</p>";

        $body = wp_remote_retrieve_body($response);
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['path'] . '/press-releases-v1.5.9.zip';

        file_put_contents($temp_file, $body);

        // Use WordPress upgrader
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/misc.php';

        $upgrader = new Plugin_Upgrader();
        $result = $upgrader->install($temp_file);

        // Clean up
        unlink($temp_file);

        if (is_wp_error($result)) {
            echo "<p style='color:red;'>Installation failed: " . $result->get_error_message() . "</p>";
        } else {
            echo "<p style='color:green;'>Update completed successfully!</p>";
            echo "<p>PressStack should now be updated to v1.5.9 with all features working.</p>";
        }

        echo "<p><a href='" . admin_url('plugins.php') . "'>Go to Plugins Page</a></p>";

        exit;
    }
}

new ForceUpdateHelper();

echo "
<h3>PressStack Force Update Tool</h3>
<p>This tool will force update your PressStack plugin from v1.5.8 to v1.5.9.</p>
<p><strong>Before using:</strong> Make sure you're logged in as an administrator.</p>
<p><a href='?force_pressstack_update=1' style='background:#0073aa;color:white;padding:10px 20px;text-decoration:none;border-radius:3px;'>Force Update Now</a></p>
<p><small>This bypasses the broken v1.5.8 auto-updater and manually installs v1.5.9.</small></p>
";
?>