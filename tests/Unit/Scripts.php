<?php
/**
 * Tests for loading scripts and styles.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Unit;

use easyTransientsForWordPress\Transients;
use easyTransientsForWordPress\Tests\easyTransientsForWordPressTest;

/**
 * Object for tests of scripts and styles.
 */
class Scripts extends easyTransientsForWordPressTest {
	/**
	 * Nothing is loaded without notices.
	 *
	 * @return void
	 */
	public function test_not_loaded_without_notices(): void {
		Transients::get_instance()->add_scripts();

		$this->assertFalse( wp_script_is( 'easy-transients-for-wordpress', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'etfw-transients', 'enqueued' ) );
	}

	/**
	 * Script and style are loaded if notices exist.
	 *
	 * @return void
	 */
	public function test_loaded_with_notice(): void {
		$this->create_transient( 'etfw_notice', 'Notice', 'info', 14 );

		Transients::get_instance()->add_scripts();

		$this->assertTrue( wp_script_is( 'easy-transients-for-wordpress', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'etfw-transients', 'enqueued' ) );

		// the version is the modification time of the file.
		$script = wp_scripts()->registered['easy-transients-for-wordpress'];
		$this->assertSame( (string) filemtime( dirname( __DIR__, 2 ) . '/src/Files/js.js' ), $script->ver );
		$this->assertStringEndsWith( 'src/Files/js.js', $script->src );

		// the vars for the script.
		$data = (string) wp_scripts()->get_data( 'easy-transients-for-wordpress', 'data' );
		$this->assertStringContainsString( 'etfwJsVars', $data );
		$this->assertStringContainsString( 'dismiss_nonce', $data );
		$this->assertStringContainsString( 'dismiss_error', $data );
	}

	/**
	 * Nothing is loaded for users without capability.
	 *
	 * @return void
	 */
	public function test_not_loaded_without_capability(): void {
		$this->create_transient( 'etfw_notice', 'Notice', 'info', 14 );
		$this->login_as( 'subscriber' );

		Transients::get_instance()->add_scripts();

		$this->assertFalse( wp_script_is( 'easy-transients-for-wordpress', 'enqueued' ) );
	}

	/**
	 * Nothing is loaded without configured URL.
	 *
	 * @return void
	 */
	public function test_not_loaded_without_url(): void {
		$this->create_transient( 'etfw_notice', 'Notice', 'info', 14 );
		Transients::get_instance()->set_url( '' );

		Transients::get_instance()->add_scripts();

		$this->assertFalse( wp_script_is( 'easy-transients-for-wordpress', 'enqueued' ) );
	}

	/**
	 * Files are loaded late, if a notice is added after "admin_enqueue_scripts".
	 *
	 * @return void
	 */
	public function test_loaded_late(): void {
		Transients::get_instance()->add_scripts();
		$this->assertFalse( wp_script_is( 'easy-transients-for-wordpress', 'enqueued' ) );

		$this->create_transient( 'etfw_late', 'Late notice', 'info', 14 );
		$this->render_notices();

		$this->assertTrue( wp_script_is( 'easy-transients-for-wordpress', 'enqueued' ) );
	}
}
