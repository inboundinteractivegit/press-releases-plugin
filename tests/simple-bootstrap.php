<?php
/**
 * Simplified PHPUnit bootstrap file for basic testing
 * This allows testing plugin functionality without full WordPress test suite
 */

// Composer autoloader
require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';

// Mock WordPress functions for basic testing
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $function, $priority = 10, $accepted_args = 1 ) {
        // Mock implementation
        return true;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook, $function, $priority = 10, $accepted_args = 1 ) {
        // Mock implementation
        return true;
    }
}

if ( ! function_exists( 'register_post_type' ) ) {
    function register_post_type( $post_type, $args ) {
        // Mock implementation
        return true;
    }
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
        // Mock implementation
        return true;
    }
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
        // Mock implementation
        return true;
    }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message, $title = '', $args = array() ) {
        throw new Exception( $message );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return filter_var( $str, FILTER_SANITIZE_STRING );
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return filter_var( $url, FILTER_SANITIZE_URL );
    }
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action = -1 ) {
        // For testing, we'll assume nonces are valid
        return true;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        // For testing, assume user has all capabilities
        return true;
    }
}

if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() {
        // For testing, assume we're in admin
        return true;
    }
}

if ( ! function_exists( 'add_shortcode' ) ) {
    function add_shortcode( $tag, $callback ) {
        // Mock implementation
        return true;
    }
}

if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename( $file ) {
        // Mock implementation
        return basename( $file );
    }
}

if ( ! function_exists( 'add_submenu_page' ) ) {
    function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
        // Mock implementation
        return $menu_slug;
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '', $scheme = 'admin' ) {
        // Mock implementation
        return 'http://example.com/wp-admin/' . $path;
    }
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
    function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
        // Mock implementation
        return $actionurl . '?' . $name . '=test_nonce';
    }
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) {
        // Mock implementation
        return dirname( $file ) . '/';
    }
}

if ( ! function_exists( 'get_plugin_data' ) ) {
    function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {
        // Mock implementation
        return array(
            'Name' => 'Press Releases Manager',
            'Version' => '1.5.6',
            'Description' => 'Test plugin',
            'Author' => 'Test Author',
            'TextDomain' => 'press-releases',
        );
    }
}

if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( $file, $callback ) {
        // Mock implementation
        return true;
    }
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
    function register_deactivation_hook( $file, $callback ) {
        // Mock implementation
        return true;
    }
}

// Define WordPress constants for testing
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', true );
}

// Mock global $wpdb for database operations
global $wpdb;
$wpdb = new stdClass();
$wpdb->prefix = 'wp_';
$wpdb->press_releases_urls = 'wp_press_releases_urls';

// Mock database methods
$wpdb->prepare = function( $query, ...$args ) {
    return sprintf( $query, ...$args );
};

$wpdb->get_results = function( $query ) {
    // Return empty results for testing
    return array();
};

$wpdb->insert = function( $table, $data ) {
    // Mock successful insert
    return 1;
};

$wpdb->update = function( $table, $data, $where ) {
    // Mock successful update
    return 1;
};

$wpdb->delete = function( $table, $where ) {
    // Mock successful delete
    return 1;
};

echo "Simple WordPress test environment loaded successfully!\n";