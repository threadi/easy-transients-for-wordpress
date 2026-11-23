<?php
/**
 * Basic tests for the transients object.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Unit;

use easyTransientsForWordPress\Transient;
use easyTransientsForWordPress\Transients;
use easyTransientsForWordPress\Tests\easyTransientsForWordPressTest;

/**
 * Object for basic tests for the transients object.
 */
class Basic extends easyTransientsForWordPressTest {
	/**
	 * Test the default slug.
	 *
	 * @return void
	 */
	public function test_default_slug(): void {
		$this->reset_library();

		$this->assertSame( 'etfw', Transients::get_instance()->get_slug() );
	}

	/**
	 * Test the configured slug.
	 *
	 * @return void
	 */
	public function test_configured_slug(): void {
		$this->assertSame( self::$slug, Transients::get_instance()->get_slug() );
	}

	/**
	 * Test the default template.
	 *
	 * @return void
	 */
	public function test_default_template(): void {
		$this->reset_library();

		$this->assertSame( 'single.php', Transients::get_instance()->get_template() );
	}

	/**
	 * Test that invalid templates are rejected.
	 *
	 * @return void
	 */
	public function test_set_template_rejects_invalid_values(): void {
		$transients_obj = Transients::get_instance();

		$transients_obj->set_template( 'template.txt' );
		$this->assertSame( 'single.php', $transients_obj->get_template() );

		$transients_obj->set_template( '../../../wp-config.php' );
		$this->assertSame( 'single.php', $transients_obj->get_template() );

		$transients_obj->set_template( 'custom.php' );
		$this->assertSame( 'custom.php', $transients_obj->get_template() );
	}

	/**
	 * Test the default translations and their override.
	 *
	 * @return void
	 */
	public function test_translations(): void {
		$transients_obj = Transients::get_instance();

		$translations = $transients_obj->get_translations();
		$this->assertArrayHasKey( 'hide_message', $translations );
		$this->assertArrayHasKey( 'dismiss', $translations );
		$this->assertArrayHasKey( 'dismiss_error', $translations );

		$transients_obj->set_translations( array( 'dismiss' => 'Ausblenden' ) );
		$translations = $transients_obj->get_translations();
		$this->assertSame( 'Ausblenden', $translations['dismiss'] );
		$this->assertNotEmpty( $translations['dismiss_error'] );
	}

	/**
	 * Test the detection of the vendor path.
	 *
	 * @return void
	 */
	public function test_vendor_path(): void {
		$transients_obj = Transients::get_instance();

		$this->assertStringEndsWith( '/', $transients_obj->get_vendor_path() );

		$transients_obj->set_vendor_path( '/path/to/plugin/vendor/' );
		$this->assertSame( '/path/to/plugin/vendor/', $transients_obj->get_vendor_path() );
	}

	/**
	 * Test the base path with and without configured path.
	 *
	 * @return void
	 */
	public function test_base_path(): void {
		$transients_obj = Transients::get_instance();
		$src            = dirname( __DIR__, 2 ) . '/src/';

		$this->assertSame( $src, $transients_obj->get_base_path() );

		$transients_obj->set_path( '' );
		$this->assertSame( $src, $transients_obj->get_base_path() );

		$transients_obj->set_path( rtrim( $src, '/' ) );
		$this->assertSame( $src, $transients_obj->get_base_path() );
	}

	/**
	 * Test that add() returns a new, empty transient.
	 *
	 * @return void
	 */
	public function test_add_returns_empty_transient(): void {
		$transient_obj = Transients::get_instance()->add();

		$this->assertInstanceOf( Transient::class, $transient_obj );
		$this->assertSame( '', $transient_obj->get_name() );
		$this->assertSame( '', $transient_obj->get_message() );
		$this->assertSame( 0, $transient_obj->get_dismissible_days() );
	}

	/**
	 * Test that no transients exist by default.
	 *
	 * @return void
	 */
	public function test_no_transients(): void {
		$this->assertSame( array(), Transients::get_instance()->get_transients() );
		$this->assertSame( '', $this->render_notices() );
	}
}
