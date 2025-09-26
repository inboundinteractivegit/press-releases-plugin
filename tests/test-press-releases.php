<?php
/**
 * Sample unit test for Press Releases Plugin
 */

class Test_Press_Releases extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Set up test data
    }

    public function tearDown(): void {
        parent::tearDown();
        // Clean up test data
    }

    /**
     * Test plugin activation
     */
    public function test_plugin_activated() {
        $this->assertTrue( is_plugin_active( 'press-releases-manager.php' ) );
    }

    /**
     * Test shortcode registration
     */
    public function test_shortcode_exists() {
        $this->assertTrue( shortcode_exists( 'press_releases' ) );
    }

    /**
     * Test press release creation
     */
    public function test_create_press_release() {
        $post_id = $this->factory->post->create([
            'post_type' => 'press_release',
            'post_title' => 'Test Press Release',
            'post_content' => 'This is a test press release content.',
            'post_status' => 'publish'
        ]);

        $this->assertIsInt( $post_id );
        $this->assertEquals( 'press_release', get_post_type( $post_id ) );
    }

    /**
     * Test press release meta data
     */
    public function test_press_release_meta() {
        $post_id = $this->factory->post->create([
            'post_type' => 'press_release',
            'post_title' => 'Test Press Release with Meta'
        ]);

        update_post_meta( $post_id, '_press_release_date', '2024-01-01' );
        update_post_meta( $post_id, '_press_release_company', 'Test Company' );

        $this->assertEquals( '2024-01-01', get_post_meta( $post_id, '_press_release_date', true ) );
        $this->assertEquals( 'Test Company', get_post_meta( $post_id, '_press_release_company', true ) );
    }
}