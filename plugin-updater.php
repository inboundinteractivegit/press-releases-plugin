<?php
/**
 * Plugin Updater Class
 * Handles automatic updates for Press Releases Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class PressReleasesUpdater {

    private $plugin_slug;
    private $version;
    private $plugin_path;
    private $plugin_file;
    private $github_username;
    private $github_repo;

    public function __construct($plugin_file, $github_username, $github_repo) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $this->get_plugin_version();
        $this->plugin_path = plugin_dir_path($plugin_file);
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        // Add update notice
        add_action('admin_notices', array($this, 'update_notice'));
    }

    /**
     * Get current plugin version
     */
    private function get_plugin_version() {
        $plugin_data = get_plugin_data($this->plugin_file);
        return $plugin_data['Version'];
    }

    /**
     * Check for updates from GitHub releases
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_version = $this->get_remote_version();

        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => $this->get_github_repo_url(),
                'package' => $this->get_download_url($remote_version)
            );
        }

        return $transient;
    }

    /**
     * Get latest version from GitHub
     */
    private function get_remote_version() {
        $request = wp_remote_get($this->get_api_url());

        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);

            if (isset($data['tag_name'])) {
                return ltrim($data['tag_name'], 'v');
            }
        }

        return false;
    }

    /**
     * Get GitHub API URL for latest release
     */
    private function get_api_url() {
        return "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
    }

    /**
     * Get GitHub repository URL
     */
    private function get_github_repo_url() {
        return "https://github.com/{$this->github_username}/{$this->github_repo}";
    }

    /**
     * Get download URL for specific version
     */
    private function get_download_url($version) {
        return "https://github.com/{$this->github_username}/{$this->github_repo}/archive/v{$version}.zip";
    }

    /**
     * Plugin information popup
     */
    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return false;
        }

        if ($args->slug !== dirname($this->plugin_slug)) {
            return false;
        }

        $remote_version = $this->get_remote_version();
        $changelog = $this->get_changelog();

        return (object) array(
            'name' => 'Press Releases Manager',
            'slug' => dirname($this->plugin_slug),
            'version' => $remote_version,
            'author' => 'Your Name',
            'homepage' => $this->get_github_repo_url(),
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'downloaded' => 0,
            'last_updated' => date('Y-m-d'),
            'sections' => array(
                'description' => 'Manage press releases with 500+ URLs using accordion interface and AJAX loading.',
                'changelog' => $changelog
            ),
            'download_link' => $this->get_download_url($remote_version)
        );
    }

    /**
     * Get changelog from GitHub
     */
    private function get_changelog() {
        $request = wp_remote_get($this->get_api_url());

        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);

            if (isset($data['body'])) {
                return wp_kses_post($data['body']);
            }
        }

        return 'No changelog available.';
    }

    /**
     * Post-install actions
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->plugin_slug) {
            activate_plugin($this->plugin_slug);
        }

        return $result;
    }

    /**
     * Show update notice in admin
     */
    public function update_notice() {
        $screen = get_current_screen();
        if ($screen->id !== 'plugins') {
            return;
        }

        $remote_version = $this->get_remote_version();

        if (version_compare($this->version, $remote_version, '<')) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>Press Releases Manager:</strong> Version ' . $remote_version . ' is available. ';
            echo '<a href="' . admin_url('plugins.php') . '">Update now</a></p>';
            echo '</div>';
        }
    }
}

/**
 * Initialize updater
 *
 * Usage: Uncomment and configure these lines in your main plugin file:
 *
 * // GitHub configuration
 * $github_username = 'your-username';
 * $github_repo = 'press-releases-manager';
 *
 * // Initialize updater
 * if (is_admin()) {
 *     require_once plugin_dir_path(__FILE__) . 'plugin-updater.php';
 *     new PressReleasesUpdater(__FILE__, $github_username, $github_repo);
 * }
 */
?>