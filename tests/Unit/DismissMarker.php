<?php
/**
 * Tests for the dismiss-markers.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Unit;

use easyTransientsForWordPress\Tests\easyTransientsForWordPressTest;

/**
 * Object for tests of the dismiss-markers.
 */
class DismissMarker extends easyTransientsForWordPressTest {
	/**
	 * Return the name of the dismiss-marker option.
	 *
	 * @param string $name The transient name.
	 *
	 * @return string
	 */
	private function get_marker_name( string $name ): string {
		return self::$slug . '-dismissed-' . md5( $name );
	}

	/**
	 * Add a marker for some days.
	 *
	 * @return void
	 */
	public function test_add_dismiss(): void {
		$transient_obj = $this->create_transient( 'etfw_notice', 'Notice', 'info', 14 );

		$transient_obj->add_dismiss( 3 );

		$this->assertTrue( $transient_obj->is_dismissed() );
		$this->assertEqualsWithDelta( strtotime( '3 days' ), (int) get_option( $this->get_marker_name( 'etfw_notice' ) ), 60 );
		$this->assertContains( $this->get_autoload_value( $this->get_marker_name( 'etfw_notice' ) ), array( 'off', 'no' ) );
	}

	/**
	 * No marker for transients which are not set.
	 *
	 * @return void
	 */
	public function test_add_dismiss_without_transient(): void {
		$transient_obj = \easyTransientsForWordPress\Transients::get_instance()->get_transient_by_name( 'etfw_not_set' );

		$transient_obj->add_dismiss( 3 );

		$this->assertFalse( get_option( $this->get_marker_name( 'etfw_not_set' ) ) );
	}

	/**
	 * Expired markers are removed.
	 *
	 * @return void
	 */
	public function test_expired_marker_is_removed(): void {
		$transient_obj = $this->create_transient( 'etfw_notice', 'Notice', 'info', 14 );
		update_option( $this->get_marker_name( 'etfw_notice' ), time() - 100 );

		$this->assertFalse( $transient_obj->is_dismissed() );
		$this->assertFalse( get_option( $this->get_marker_name( 'etfw_notice' ) ) );
		$this->assertStringContainsString( 'Notice', $this->render_notices() );
	}

	/**
	 * Markers with "forever" (and markers of versions < 1.5.0 which are autoloaded) are still working.
	 *
	 * @return void
	 */
	public function test_forever_marker(): void {
		$transient_obj = $this->create_transient( 'etfw_notice', 'Notice', 'info', 14 );
		add_option( $this->get_marker_name( 'etfw_notice' ), 'forever', '', true );

		$this->assertTrue( $transient_obj->is_dismissed() );
	}

	/**
	 * Invalid markers are ignored.
	 *
	 * @return void
	 */
	public function test_invalid_marker(): void {
		$transient_obj = $this->create_transient( 'etfw_notice', 'Notice', 'info', 14 );
		update_option( $this->get_marker_name( 'etfw_notice' ), 'invalid' );

		$this->assertFalse( $transient_obj->is_dismissed() );
	}

	/**
	 * Delete the marker.
	 *
	 * @return void
	 */
	public function test_delete_dismiss(): void {
		$transient_obj = $this->create_transient( 'etfw_notice', 'Notice', 'info', 14 );
		$transient_obj->add_dismiss( 3 );

		$transient_obj->delete_dismiss();

		$this->assertFalse( $transient_obj->is_dismissed() );
		$this->assertStringContainsString( 'Notice', $this->render_notices() );
	}
}
