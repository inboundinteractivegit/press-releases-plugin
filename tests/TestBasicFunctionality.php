<?php
/**
 * Basic functionality tests for Press Releases Plugin
 */

use PHPUnit\Framework\TestCase;

class TestBasicFunctionality extends TestCase {

    public function setUp(): void {
        parent::setUp();

        // Include the main plugin file
        require_once dirname( dirname( __FILE__ ) ) . '/press-releases-manager.php';
    }

    /**
     * Test that the main plugin class exists
     */
    public function test_pressstack_class_exists() {
        $this->assertTrue( class_exists( 'PressStack' ), 'PressStack class should exist' );
    }

    /**
     * Test that the plugin can be instantiated
     */
    public function test_pressstack_can_be_instantiated() {
        $plugin = new PressStack();
        $this->assertInstanceOf( 'PressStack', $plugin, 'PressStack should be instantiable' );
    }

    /**
     * Test security validation methods exist
     */
    public function test_security_methods_exist() {
        $plugin = new PressStack();

        $reflection = new ReflectionClass( $plugin );

        // Check for key security methods
        $this->assertTrue( $reflection->hasMethod( 'validate_urls_data' ), 'validate_urls_data method should exist' );
        $this->assertTrue( $reflection->hasMethod( 'save_press_release_urls' ), 'save_press_release_urls method should exist' );
        $this->assertTrue( $reflection->hasMethod( 'handle_bulk_import' ), 'handle_bulk_import method should exist' );
    }

    /**
     * Test URL validation logic
     */
    public function test_url_validation() {
        $plugin = new PressStack();
        $reflection = new ReflectionClass( $plugin );
        $method = $reflection->getMethod( 'validate_urls_data' );
        $method->setAccessible( true );

        // Test valid URLs data
        $valid_data = array(
            array(
                'url' => 'https://example.com',
                'title' => 'Test Title'
            )
        );

        $result = $method->invokeArgs( $plugin, array( $valid_data ) );
        $this->assertTrue( $result['is_valid'], 'Valid URL data should pass validation' );
    }

    /**
     * Test that security limits are enforced
     */
    public function test_security_limits() {
        $plugin = new PressStack();
        $reflection = new ReflectionClass( $plugin );
        $method = $reflection->getMethod( 'validate_urls_data' );
        $method->setAccessible( true );

        // Test URL limit (should fail with too many URLs)
        $too_many_urls = array();
        for ( $i = 0; $i < 1001; $i++ ) { // Limit is 1000
            $too_many_urls[] = array(
                'url' => "https://example{$i}.com",
                'title' => "Title {$i}"
            );
        }

        $result = $method->invokeArgs( $plugin, array( $too_many_urls ) );
        $this->assertFalse( $result['is_valid'], 'Too many URLs should fail validation' );
        $this->assertStringContainsString( 'maximum of 1000 URLs', $result['message'] );
    }

    /**
     * Test malicious URL blocking
     */
    public function test_malicious_url_blocking() {
        $plugin = new PressStack();
        $reflection = new ReflectionClass( $plugin );
        $method = $reflection->getMethod( 'validate_urls_data' );
        $method->setAccessible( true );

        // Test dangerous protocols
        $malicious_data = array(
            array(
                'url' => 'javascript:alert("xss")',
                'title' => 'Malicious URL'
            )
        );

        $result = $method->invokeArgs( $plugin, array( $malicious_data ) );
        $this->assertFalse( $result['is_valid'], 'Malicious URLs should be blocked' );
    }

    /**
     * Test that required constants are defined
     */
    public function test_required_constants() {
        $this->assertTrue( defined( 'ABSPATH' ), 'ABSPATH should be defined' );
        $this->assertTrue( defined( 'WP_DEBUG' ), 'WP_DEBUG should be defined for testing' );
    }

    /**
     * Test plugin file structure
     */
    public function test_plugin_file_structure() {
        $plugin_dir = dirname( dirname( __FILE__ ) );

        // Check for required files
        $this->assertFileExists( $plugin_dir . '/press-releases-manager.php', 'Main plugin file should exist' );
        $this->assertFileExists( $plugin_dir . '/plugin-updater.php', 'Updater file should exist' );
        $this->assertFileExists( $plugin_dir . '/composer.json', 'Composer config should exist' );
        $this->assertFileExists( $plugin_dir . '/CHANGELOG.md', 'Changelog should exist' );
    }

    /**
     * Test that the updater class exists
     */
    public function test_updater_class_exists() {
        require_once dirname( dirname( __FILE__ ) ) . '/plugin-updater.php';
        $this->assertTrue( class_exists( 'PressReleasesUpdater' ), 'PressReleasesUpdater class should exist' );
    }

    /**
     * Test updater instantiation
     */
    public function test_updater_can_be_instantiated() {
        require_once dirname( dirname( __FILE__ ) ) . '/plugin-updater.php';

        $updater = new PressReleasesUpdater(
            'test-file.php',
            'test-user',
            'test-repo',
            '1.0.0'
        );

        $this->assertInstanceOf( 'PressReleasesUpdater', $updater, 'PressReleasesUpdater should be instantiable' );
    }
}