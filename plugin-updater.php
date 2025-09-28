<?php
/**
 * Plugin Updater Class
 * Handles automatic updates for Press Releases Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressReleasesUpdater {

	private $plugin_slug;
	private $version;
	private $plugin_path;
	private $plugin_file;
	private $github_username;
	private $github_repo;

	public function __construct( $plugin_file, $github_username, $github_repo ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_slug     = plugin_basename( $plugin_file );
		$this->version         = $this->get_plugin_version();
		$this->plugin_path     = plugin_dir_path( $plugin_file );
		$this->github_username = $github_username;
		$this->github_repo     = $github_repo;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

		// Add update notice and force check handler
		add_action( 'admin_notices', array( $this, 'update_notice' ) );
		add_action( 'admin_post_force_update_check', array( $this, 'force_update_check' ) );
		add_action( 'wp_ajax_force_update_check', array( $this, 'ajax_force_update_check' ) );
	}

	/**
	 * Get current plugin version
	 */
	private function get_plugin_version() {
		$plugin_data = get_plugin_data( $this->plugin_file );
		return $plugin_data['Version'];
	}

	/**
	 * Check for updates from GitHub releases
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote_version = $this->get_remote_version();

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "PressStack Updater: Current version: {$this->version}, Remote version: {$remote_version}" );
		}

		if ( $remote_version && version_compare( $this->version, $remote_version, '<' ) ) {
			$transient->response[ $this->plugin_slug ] = (object) array(
				'slug'        => dirname( $this->plugin_slug ),
				'plugin'      => $this->plugin_slug,
				'new_version' => $remote_version,
				'url'         => $this->get_github_repo_url(),
				'package'     => $this->get_download_url( $remote_version ),
			);
		}

		return $transient;
	}

	/**
	 * Get latest version from GitHub
	 */
	private function get_remote_version() {
		// Check for cached version first (but allow force refresh)
		$cache_key   = 'press_releases_remote_version';
		$force_check = isset( $_GET['force-check'] ) || isset( $_POST['force_update_check'] );

		if ( ! $force_check ) {
			$cached_version = get_transient( $cache_key );
			if ( $cached_version !== false && ! empty( $cached_version ) ) {
				return $cached_version;
			}
		}

		$request = wp_remote_get(
			$this->get_api_url(),
			array(
				'timeout' => 15,
				'headers' => array(
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				),
			)
		);

		if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) === 200 ) {
			$body = wp_remote_retrieve_body( $request );
			$data = json_decode( $body, true );

			if ( isset( $data['tag_name'] ) && ! empty( $data['tag_name'] ) ) {
				$version = ltrim( $data['tag_name'], 'v' );
				// Clean version format and ensure it's valid
				$version = preg_replace( '/[^0-9.]/', '', $version );
				if ( ! empty( $version ) ) {
					// Cache for 15 minutes for faster update detection
					set_transient( $cache_key, $version, 15 * MINUTE_IN_SECONDS );
					return $version;
				}
			}
		}

		// Log error for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'PressStack Updater: Failed to get remote version from GitHub API' );
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
	private function get_download_url( $version ) {
		return "https://github.com/{$this->github_username}/{$this->github_repo}/releases/download/v{$version}/press-releases-plugin-v{$version}.zip";
	}

	/**
	 * Plugin information popup
	 */
	public function plugin_popup( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return false;
		}

		if ( $args->slug !== dirname( $this->plugin_slug ) ) {
			return false;
		}

		$remote_version = $this->get_remote_version();
		$changelog      = $this->get_changelog();

		return (object) array(
			'name'          => 'PressStack',
			'slug'          => dirname( $this->plugin_slug ),
			'version'       => $remote_version,
			'author'        => 'Inbound Interactive',
			'homepage'      => $this->get_github_repo_url(),
			'requires'      => '5.0',
			'tested'        => '6.8.2',
			'downloaded'    => 0,
			'last_updated'  => date( 'Y-m-d' ),
			'sections'      => array(
				'description' => 'Free press releases management with enterprise-grade security, AJAX-loaded URLs, and donation support.',
				'changelog'   => $changelog,
			),
			'download_link' => $this->get_download_url( $remote_version ),
		);
	}

	/**
	 * Get changelog from GitHub
	 */
	private function get_changelog() {
		$request = wp_remote_get( $this->get_api_url() );

		if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) === 200 ) {
			$body = wp_remote_retrieve_body( $request );
			$data = json_decode( $body, true );

			if ( isset( $data['body'] ) ) {
				return wp_kses_post( $data['body'] );
			}
		}

		return 'No changelog available.';
	}

	/**
	 * Post-install actions
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		$install_directory = plugin_dir_path( $this->plugin_file );
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		if ( $this->plugin_slug ) {
			activate_plugin( $this->plugin_slug );
		}

		return $result;
	}

	/**
	 * Show update notice in admin
	 */
	public function update_notice() {
		$screen = get_current_screen();
		if ( $screen->id !== 'plugins' ) {
			return;
		}

		$remote_version = $this->get_remote_version();

		if ( version_compare( $this->version, $remote_version, '<' ) ) {
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p><strong>Press Releases Manager:</strong> Version ' . $remote_version . ' is available. ';
			echo '<a href="' . admin_url( 'plugins.php' ) . '">Update now</a></p>';
			echo '</div>';
		}

		// Add force check button for admins
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-info" style="position: relative;">';
			echo '<p><strong>Press Releases Manager Auto-Updater:</strong> Current v' . $this->version . ' | ';
			if ( $remote_version ) {
				echo 'Latest v' . $remote_version;
			} else {
				echo 'Checking...';
			}
			echo '</p>';
			echo '<form method="post" action="' . admin_url( 'admin-post.php' ) . '" style="display: inline;">';
			echo '<input type="hidden" name="action" value="force_update_check">';
			wp_nonce_field( 'force_update_check', 'force_check_nonce' );
			echo '<input type="submit" class="button" value="🔄 Force Check for Updates">';
			echo '</form>';
			echo '</div>';
		}
	}

	/**
	 * Handle force update check
	 */
	public function force_update_check() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['force_check_nonce'] ) || ! wp_verify_nonce( $_POST['force_check_nonce'], 'force_update_check' ) ) {
			wp_die( 'Security check failed' );
		}

		// Clear all plugin update caches
		delete_site_transient( 'update_plugins' );
		delete_transient( 'press_releases_remote_version' );

		// Force recheck
		$remote_version = $this->get_remote_version();

		wp_redirect( admin_url( 'plugins.php?force-checked=1&remote_version=' . urlencode( $remote_version ) ) );
		exit();
	}

	/**
	 * AJAX handler for force update check
	 */
	public function ajax_force_update_check() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'force_update_check' ) ) {
			wp_die( 'Security check failed' );
		}

		delete_site_transient( 'update_plugins' );
		delete_transient( 'press_releases_remote_version' );

		$remote_version = $this->get_remote_version();

		wp_send_json_success(
			array(
				'message'         => 'Update check completed',
				'current_version' => $this->version,
				'remote_version'  => $remote_version,
			)
		);
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

