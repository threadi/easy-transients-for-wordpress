<?php
/**
 * Tests for dismissing notices via AJAX.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Unit;

use easyTransientsForWordPress\Tests\easyTransientsForWordPressAjaxTest;

/**
 * Object for tests of the AJAX request to dismiss notices.
 */
class Dismiss extends easyTransientsForWordPressAjaxTest {
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
	 * Dismiss an own notice.
	 *
	 * @return void
	 */
	public function test_dismiss_own_notice(): void {
		$this->create_transient( 'etfw_notice', 'Notice', 'info', 7 );

		$response = $this->dismiss(
			array(
				'nonce'              => wp_create_nonce( 'etfw-dismiss-nonce' ),
				'option_name'        => 'etfw_notice',
				'dismissible_length' => '99',
			)
		);

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );

		// the days of the transient are used, not the requested value.
		$marker = (int) get_option( $this->get_marker_name( 'etfw_notice' ) );
		$this->assertEqualsWithDelta( strtotime( '7 days' ), $marker, 60 );
		$this->assertContains( $this->get_autoload_value( $this->get_marker_name( 'etfw_notice' ) ), array( 'off', 'no' ) );

		// the transient itself is removed.
		$this->assertFalse( get_transient( 'etfw_notice' ) );
	}

	/**
	 * Dismiss with "forever".
	 *
	 * @return void
	 */
	public function test_dismiss_forever(): void {
		$this->create_transient( 'etfw_notice', 'Notice', 'info', 7 );

		$response = $this->dismiss(
			array(
				'nonce'              => wp_create_nonce( 'etfw-dismiss-nonce' ),
				'option_name'        => 'etfw_notice',
				'dismissible_length' => 'forever',
			)
		);

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertSame( 'forever', get_option( $this->get_marker_name( 'etfw_notice' ) ) );
	}

	/**
	 * Notices of other projects are ignored, so their own handler could answer.
	 *
	 * @return void
	 */
	public function test_unknown_notice_is_ignored(): void {
		set_transient( 'other_notice', array( 'message' => 'Other' ) );

		$response = $this->dismiss(
			array(
				'nonce'              => wp_create_nonce( 'etfw-dismiss-nonce' ),
				'option_name'        => 'other_notice',
				'dismissible_length' => '14',
			)
		);

		$this->assertNull( $response );
		$this->assertFalse( get_option( $this->get_marker_name( 'other_notice' ) ) );
		$this->assertNotFalse( get_transient( 'other_notice' ) );
	}

	/**
	 * Invalid nonce.
	 *
	 * @return void
	 */
	public function test_invalid_nonce(): void {
		$this->create_transient( 'etfw_notice', 'Notice', 'info', 7 );

		$response = $this->dismiss(
			array(
				'nonce'       => 'invalid',
				'option_name' => 'etfw_notice',
			)
		);

		$this->assertSame( '-1', $response );
		$this->assertNotFalse( get_transient( 'etfw_notice' ) );
	}

	/**
	 * Missing name.
	 *
	 * @return void
	 */
	public function test_missing_name(): void {
		$response = $this->dismiss(
			array(
				'nonce' => wp_create_nonce( 'etfw-dismiss-nonce' ),
			)
		);

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
	}

	/**
	 * Users without capability could not dismiss notices.
	 *
	 * @return void
	 */
	public function test_without_capability(): void {
		$this->create_transient( 'etfw_notice', 'Notice', 'info', 7 );
		$this->login_as( 'subscriber' );

		$response = $this->dismiss(
			array(
				'nonce'              => wp_create_nonce( 'etfw-dismiss-nonce' ),
				'option_name'        => 'etfw_notice',
				'dismissible_length' => '7',
			)
		);

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
		$this->assertNotFalse( get_transient( 'etfw_notice' ) );
		$this->assertFalse( get_option( $this->get_marker_name( 'etfw_notice' ) ) );
	}
}
