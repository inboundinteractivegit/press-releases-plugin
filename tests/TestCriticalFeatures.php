<?php
/**
 * Critical features tests for Press Releases Plugin
 * Focus on the most important functionality from the changelog
 */

use PHPUnit\Framework\TestCase;

class TestCriticalFeatures extends TestCase {

    public function setUp(): void {
        parent::setUp();
    }

    /**
     * Test plugin file structure and basic requirements
     */
    public function test_plugin_files_exist() {
        $plugin_dir = dirname( dirname( __FILE__ ) );

        // Critical files must exist
        $this->assertFileExists( $plugin_dir . '/press-releases-manager.php', 'Main plugin file missing' );
        $this->assertFileExists( $plugin_dir . '/plugin-updater.php', 'Auto-updater file missing' );
        $this->assertFileExists( $plugin_dir . '/composer.json', 'Composer config missing' );
        $this->assertFileExists( $plugin_dir . '/CHANGELOG.md', 'Changelog missing' );
    }

    /**
     * Test that critical classes can be loaded without errors
     */
    public function test_classes_can_be_loaded() {
        // Test main plugin class
        require_once dirname( dirname( __FILE__ ) ) . '/press-releases-manager.php';
        $this->assertTrue( class_exists( 'PressStack' ), 'PressStack class should exist' );

        // Test updater class
        require_once dirname( dirname( __FILE__ ) ) . '/plugin-updater.php';
        $this->assertTrue( class_exists( 'PressReleasesUpdater' ), 'PressReleasesUpdater class should exist' );
    }

    /**
     * Test core methods exist on main class
     */
    public function test_core_methods_exist() {
        require_once dirname( dirname( __FILE__ ) ) . '/press-releases-manager.php';

        $reflection = new ReflectionClass( 'PressStack' );

        // Critical methods from recent changelog fixes
        $this->assertTrue( $reflection->hasMethod( 'init' ), 'init method missing' );
        $this->assertTrue( $reflection->hasMethod( 'create_press_release_post_type' ), 'post type creation missing' );
        $this->assertTrue( $reflection->hasMethod( 'enqueue_scripts' ), 'script enqueuing missing' );
        $this->assertTrue( $reflection->hasMethod( 'activate_plugin' ), 'activation hook missing' );
        $this->assertTrue( $reflection->hasMethod( 'check_rate_limit' ), 'rate limiting missing' );
    }

    /**
     * Test updater class critical methods
     */
    public function test_updater_methods_exist() {
        require_once dirname( dirname( __FILE__ ) ) . '/plugin-updater.php';

        $reflection = new ReflectionClass( 'PressReleasesUpdater' );

        // Critical updater methods
        $this->assertTrue( $reflection->hasMethod( 'check_for_update' ), 'update checking missing' );
        $this->assertTrue( $reflection->hasMethod( 'get_remote_version' ), 'version checking missing' );
        $this->assertTrue( $reflection->hasMethod( 'plugin_popup' ), 'plugin popup missing' );
    }

    /**
     * Test that security-related functions are in place
     */
    public function test_security_functions_exist() {
        $plugin_file = dirname( dirname( __FILE__ ) ) . '/press-releases-manager.php';
        $content = file_get_contents( $plugin_file );

        // Test for critical security implementations from changelog
        $this->assertStringContainsString( 'wp_verify_nonce', $content, 'Nonce verification missing' );
        $this->assertStringContainsString( 'current_user_can', $content, 'User capability checks missing' );
        $this->assertStringContainsString( 'sanitize_text_field', $content, 'Input sanitization missing' );
        $this->assertStringContainsString( 'esc_url_raw', $content, 'URL escaping missing' );
    }

    /**
     * Test critical fixes from v1.5.5 are in place
     */
    public function test_v155_critical_fixes() {
        $plugin_file = dirname( dirname( __FILE__ ) ) . '/press-releases-manager.php';
        $content = file_get_contents( $plugin_file );

        // v1.5.5 fixes: proper activation hooks
        $this->assertStringContainsString( 'register_activation_hook', $content, 'Activation hook missing from v1.5.5 fix' );

        // v1.5.5 fixes: conditional script loading
        $this->assertStringContainsString( 'wp_enqueue_script', $content, 'Script enqueuing missing from v1.5.5 fix' );

        // v1.5.5 fixes: isset checks for $_POST
        $this->assertStringContainsString( 'isset', $content, 'isset checks missing from v1.5.5 security fix' );
    }

    /**
     * Test that auto-updater improvements from v1.5.6 are present
     */
    public function test_v156_updater_improvements() {
        $updater_file = dirname( dirname( __FILE__ ) ) . '/plugin-updater.php';
        $content = file_get_contents( $updater_file );

        // v1.5.6 improvements: cache time reduction and version handling
        $this->assertStringContainsString( 'get_transient', $content, 'Transient caching missing' );
        $this->assertStringContainsString( 'set_transient', $content, 'Transient setting missing' );

        // Force check functionality
        $this->assertStringContainsString( 'force_check', $content, 'Force check functionality missing' );
    }

    /**
     * Test changelog version consistency
     */
    public function test_version_consistency() {
        $changelog_file = dirname( dirname( __FILE__ ) ) . '/CHANGELOG.md';
        $changelog_content = file_get_contents( $changelog_file );

        // Should contain recent version entries
        $this->assertStringContainsString( '[1.5.6]', $changelog_content, 'Current version missing from changelog' );
        $this->assertStringContainsString( '[1.5.5]', $changelog_content, 'Previous version missing from changelog' );

        // Should document critical fixes
        $this->assertStringContainsString( 'Auto-update detection improved', $changelog_content, 'v1.5.6 improvements not documented' );
        $this->assertStringContainsString( 'CRITICAL Bug Fixes', $changelog_content, 'v1.5.5 critical fixes not documented' );
    }

    /**
     * Test composer.json structure
     */
    public function test_composer_structure() {
        $composer_file = dirname( dirname( __FILE__ ) ) . '/composer.json';
        $this->assertFileExists( $composer_file, 'composer.json missing' );

        $composer_data = json_decode( file_get_contents( $composer_file ), true );

        $this->assertNotNull( $composer_data, 'composer.json should be valid JSON' );
        $this->assertArrayHasKey( 'require-dev', $composer_data, 'Development dependencies missing' );
        $this->assertArrayHasKey( 'phpunit/phpunit', $composer_data['require-dev'], 'PHPUnit dependency missing' );
    }
}