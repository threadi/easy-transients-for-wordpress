<?php
/**
 * Tests for action-transients.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Unit;

use easyTransientsForWordPress\Transients;
use easyTransientsForWordPress\Tests\easyTransientsForWordPressTest;
use easyTransientsForWordPress\Tests\Helper\Action_Helper;

/**
 * Object for tests of action-transients.
 */
class Actions extends easyTransientsForWordPressTest {
	/**
	 * Path to the temporary error log.
	 *
	 * @var string
	 */
	private string $error_log = '';

	/**
	 * The original error log setting.
	 *
	 * @var string
	 */
	private string $original_error_log = '';

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Action_Helper::reset();

		// write errors in a temporary file.
		$this->error_log          = (string) tempnam( sys_get_temp_dir(), 'etfw' );
		$this->original_error_log = (string) ini_get( 'error_log' );
		ini_set( 'error_log', $this->error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		ini_set( 'error_log', $this->original_error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		if ( file_exists( $this->error_log ) ) {
			unlink( $this->error_log );
		}

		parent::tear_down();
	}

	/**
	 * The action runs only once, even if two requests handle it at the same time.
	 *
	 * @return void
	 */
	public function test_action_runs_once(): void {
		$this->create_transient( 'etfw_job', '', '', 0, array( 'action' => array( Action_Helper::class, 'run_with_output' ) ) );

		// two objects for the same transient, like two parallel requests.
		$first  = Transients::get_instance()->get_transient_by_name( 'etfw_job' );
		$second = Transients::get_instance()->get_transient_by_name( 'etfw_job' );

		ob_start();
		$first->run();
		$second->run();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, Action_Helper::$runs );
		$this->assertSame( '', $output );
		$this->assertFalse( get_transient( 'etfw_job' ) );
		$this->assertEmpty( $this->get_raw_list()[ self::$slug ] );
	}

	/**
	 * Errors in actions are caught and logged and the transient is removed.
	 *
	 * @return void
	 */
	public function test_exception_in_action(): void {
		$this->create_transient( 'etfw_failing', '', '', 0, array( 'action' => array( Action_Helper::class, 'run_with_exception' ) ) );

		Transients::get_instance()->init_actions();

		$this->assertSame( 1, Action_Helper::$runs );
		$this->assertFalse( get_transient( 'etfw_failing' ) );
		$this->assertStringContainsString( 'Action failed in test', (string) file_get_contents( $this->error_log ) );
	}

	/**
	 * Not callable actions are kept in frontend and removed in backend.
	 *
	 * @return void
	 */
	public function test_not_callable_actions(): void {
		$this->create_transient( 'etfw_missing_class', '', '', 0, array( 'action' => array( 'Does\\Not\\Exist', 'run' ) ) );
		$this->create_transient( 'etfw_not_static', '', '', 0, array( 'action' => array( Action_Helper::class, 'not_static' ) ) );

		$this->go_to_frontend();
		Transients::get_instance()->init_actions();
		$this->assertNotFalse( get_transient( 'etfw_missing_class' ) );
		$this->assertNotFalse( get_transient( 'etfw_not_static' ) );

		$this->go_to_backend();
		Transients::get_instance()->init_actions();
		$this->assertFalse( get_transient( 'etfw_missing_class' ) );
		$this->assertFalse( get_transient( 'etfw_not_static' ) );
		$this->assertSame( 0, Action_Helper::$runs );
	}

	/**
	 * Notices with message are not run as action.
	 *
	 * @return void
	 */
	public function test_notice_is_not_run_as_action(): void {
		$this->create_transient( 'etfw_notice', 'Notice', 'info', 14, array( 'action' => array( Action_Helper::class, 'run_with_output' ) ) );

		Transients::get_instance()->init_actions();

		$this->assertSame( 0, Action_Helper::$runs );
		$this->assertNotFalse( get_transient( 'etfw_notice' ) );
	}

	/**
	 * The actions are run at the end of each request, also in frontend.
	 *
	 * @return void
	 */
	public function test_action_runs_in_frontend(): void {
		$this->assertSame( 10, has_action( 'shutdown', array( Transients::get_instance(), 'init_actions' ) ) );

		$this->create_transient( 'etfw_job', '', '', 0, array( 'action' => array( Action_Helper::class, 'run_with_output' ) ) );
		$this->go_to_frontend();

		ob_start();
		Transients::get_instance()->init_actions();
		ob_end_clean();

		$this->assertSame( 1, Action_Helper::$runs );
	}
}
