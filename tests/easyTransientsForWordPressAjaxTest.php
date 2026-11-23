<?php
/**
 * File to handle the main object for each AJAX test class.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests;

use WP_Ajax_UnitTestCase;

/**
 * Object to handle the preparations for each AJAX test class.
 */
abstract class easyTransientsForWordPressAjaxTest extends WP_Ajax_UnitTestCase {
	use TransientsTestTrait;

	/**
	 * Prepare each test: configured library and logged in administrator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->disable_update_checks();
		$this->reset_scripts();
		$this->prepare_library();
		$this->login_as( 'administrator' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->reset_scripts();
		$this->reset_library();
		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Run the dismiss request and return the decoded response.
	 *
	 * @param array<string,string> $data The POST data (without action).
	 *
	 * @return array<string,mixed>|string|null Decoded JSON, the die-message as string or null if no project answered.
	 */
	protected function dismiss( array $data ): array|string|null {
		$_POST = $data;

		try {
			$this->_handleAjax( 'efw_dismiss_admin_notice' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			return is_array( $response ) ? $response : null;
		} catch ( \WPAjaxDieStopException $e ) {
			return $e->getMessage();
		}

		// no handler has answered.
		return null;
	}
}
