<?php
/**
 * File to handle the main object for each test class.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests;

use WP_UnitTestCase;

/**
 * Object to handle the preparations for each test class.
 */
abstract class easyTransientsForWordPressTest extends WP_UnitTestCase {
	use TransientsTestTrait;

	/**
	 * Prepare each test: configured library, logged in administrator, backend request.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// enable error reporting.
		error_reporting( E_ALL );

		$this->disable_update_checks();
		$this->reset_scripts();
		$this->prepare_library();
		$this->login_as( 'administrator' );
		$this->go_to_backend();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->go_to_frontend();
		$this->reset_scripts();
		$this->reset_library();

		parent::tear_down();
	}
}
