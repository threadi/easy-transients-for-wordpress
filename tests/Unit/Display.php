<?php
/**
 * Tests for the output of transients.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Unit;

use easyTransientsForWordPress\Transients;
use easyTransientsForWordPress\Tests\easyTransientsForWordPressTest;
use easyTransientsForWordPress\Tests\Helper\Action_Helper;

/**
 * Object for tests of the output.
 */
class Display extends easyTransientsForWordPressTest {
	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Action_Helper::reset();
	}

	/**
	 * Single output of a notice.
	 *
	 * @return void
	 */
	public function test_single_output(): void {
		$this->create_transient( 'etfw_single', 'Hello <strong>World</strong><script>alert(1)</script>', 'success', 14 );

		$output = $this->render_notices();

		$this->assertStringContainsString( 'Hello <strong>World</strong>', $output );
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( 'data-dismissible="etfw_single-14"', $output );
		$this->assertStringContainsString( 'etfw-success', $output );
		$this->assertStringContainsString( 'notice-dismiss', $output );
	}

	/**
	 * A notice without dismissible days is shown once and deleted afterward.
	 *
	 * @return void
	 */
	public function test_notice_without_dismiss_is_shown_once(): void {
		$this->create_transient( 'etfw_once', 'Only once', 'info' );

		$this->assertStringContainsString( 'Only once', $this->render_notices() );
		$this->assertStringNotContainsString( 'notice-dismiss', $this->render_notices() );
		$this->assertFalse( get_transient( 'etfw_once' ) );
	}

	/**
	 * A notice with dismissible days stays until it is dismissed.
	 *
	 * @return void
	 */
	public function test_dismissible_notice_stays(): void {
		$this->create_transient( 'etfw_stays', 'Stays', 'info', 14 );

		$this->render_notices();

		$this->assertStringContainsString( 'Stays', $this->render_notices() );
	}

	/**
	 * Dismissed notices are not shown.
	 *
	 * @return void
	 */
	public function test_dismissed_notice_is_hidden(): void {
		$transient_obj = $this->create_transient( 'etfw_dismissed', 'Dismissed', 'info', 14 );
		$transient_obj->add_dismiss( 14 );

		$this->assertStringNotContainsString( 'Dismissed', $this->render_notices() );
	}

	/**
	 * No output for users without capability.
	 *
	 * @return void
	 */
	public function test_no_output_without_capability(): void {
		$this->create_transient( 'etfw_cap', 'Capability', 'info', 14 );
		$this->login_as( 'subscriber' );

		$this->assertSame( '', $this->render_notices() );
	}

	/**
	 * Grouped output is sorted (error first) and has a circular navigation.
	 *
	 * @return void
	 */
	public function test_grouped_output(): void {
		$this->create_transient( 'etfw_success', 'Success', 'success', 14 );
		$this->create_transient( 'etfw_error', 'Error', 'error', 14 );
		$this->create_transient( 'etfw_hint', 'Hint', 'hint', 14 );
		Transients::get_instance()->set_display_method( 'grouped' );

		$output = $this->render_notices();

		$this->assertStringContainsString( 'id="etfw-transients-grouped-' . self::$slug . '"', $output );
		$this->assertStringContainsString( 'etfw-snapper', $output );
		$this->assertLessThan( strpos( $output, 'Success' ), strpos( $output, 'Error' ) );
		$this->assertLessThan( strpos( $output, 'Hint' ), strpos( $output, 'Success' ) );

		// the first entry points back to the last one.
		$this->assertMatchesRegularExpression( '/id="etfw_error".*?href="#etfw_hint"\s+class="etfw-snapper-prev"/s', $output );
		$this->assertMatchesRegularExpression( '/id="etfw_hint".*?href="#etfw_error"\s+class="etfw-snapper-next"/s', $output );
	}

	/**
	 * Prioritized notices are shown first.
	 *
	 * @return void
	 */
	public function test_prioritized_notice_first(): void {
		$this->create_transient( 'etfw_error', 'Error', 'error', 14 );
		$this->create_transient( 'etfw_info', 'Prioritized', 'info', 14, array( 'prioritized' => true ) );
		Transients::get_instance()->set_display_method( 'grouped' );

		$output = $this->render_notices();

		$this->assertLessThan( strpos( $output, 'Error' ), strpos( $output, 'Prioritized' ) );
	}

	/**
	 * Unknown types are sorted at the end without warnings.
	 *
	 * @return void
	 */
	public function test_unknown_types(): void {
		$this->create_transient( 'etfw_unknown', 'Unknown', 'something', 14 );
		$this->create_transient( 'etfw_empty', 'Empty type', '', 14 );
		$this->create_transient( 'etfw_warning', 'Warning', 'warning', 14 );
		Transients::get_instance()->set_display_method( 'grouped' );

		$output = $this->render_notices();

		$this->assertLessThan( strpos( $output, 'Unknown' ), strpos( $output, 'Warning' ) );
		$this->assertSame( 3, substr_count( $output, 'data-dismissible=' ) );
	}

	/**
	 * With only one visible notice the grouped view uses the single template.
	 *
	 * @return void
	 */
	public function test_grouped_with_one_visible_notice(): void {
		$this->go_to_backend( '/wp-admin/plugins.php' );
		$this->create_transient( 'etfw_visible', 'Visible', 'info', 14 );
		$this->create_transient( 'etfw_hidden', 'Hidden', 'info', 14, array( 'hide_on' => array( admin_url( 'plugins.php' ) ) ) );
		Transients::get_instance()->set_display_method( 'grouped' );

		$output = $this->render_notices();

		$this->assertStringContainsString( 'Visible', $output );
		$this->assertStringNotContainsString( 'Hidden', $output );
		$this->assertStringNotContainsString( 'etfw-transients-grouped', $output );
	}

	/**
	 * Action-only transients are not shown as slide, but their action runs.
	 *
	 * @return void
	 */
	public function test_action_transient_is_no_slide(): void {
		$this->create_transient( 'etfw_a', 'A', 'info', 14 );
		$this->create_transient( 'etfw_b', 'B', 'info', 14 );
		$this->create_transient( 'etfw_action', '', '', 0, array( 'action' => array( Action_Helper::class, 'run_with_output' ) ) );
		Transients::get_instance()->set_display_method( 'grouped' );

		$output = $this->render_notices();

		$this->assertSame( 2, substr_count( $output, 'data-dismissible=' ) );
		$this->assertSame( 1, Action_Helper::$runs );
		$this->assertFalse( get_transient( 'etfw_action' ) );
	}

	/**
	 * Hide on URLs with query strings which contain "/" and encoded chars.
	 *
	 * @return void
	 */
	public function test_hide_on_with_query_string(): void {
		$this->go_to_backend( '/wp-admin/admin.php?page=etfw&path=a/b%20c' );
		$this->create_transient( 'etfw_hidden', 'Hidden', 'info', 14, array( 'hide_on' => array( admin_url( 'admin.php?page=etfw&path=a/b%20c' ) ) ) );
		$this->create_transient( 'etfw_visible', 'Visible', 'info', 14, array( 'hide_on' => array( admin_url( 'admin.php?page=other' ) ) ) );

		$output = $this->render_notices();

		$this->assertStringNotContainsString( 'Hidden', $output );
		$this->assertStringContainsString( 'Visible', $output );
	}

	/**
	 * Hide on URLs as detected by versions < 1.5.0.
	 *
	 * @return void
	 */
	public function test_hide_on_compatible_with_older_versions(): void {
		// versions < 1.5.0 used only the part after the last "/" of the request.
		$this->go_to_backend( '/wp-admin/admin.php?page=etfw&path=a/settings.php' );
		$this->create_transient( 'etfw_hidden', 'Hidden', 'info', 14, array( 'hide_on' => array( admin_url( 'settings.php' ) ) ) );

		$this->assertStringNotContainsString( 'Hidden', $this->render_notices() );
	}

	/**
	 * A missing template does not cause warnings.
	 *
	 * @return void
	 */
	public function test_missing_template(): void {
		$this->create_transient( 'etfw_no_template', 'No template', 'info', 14 );
		add_filter(
			'etfw_' . self::$slug . '_template',
			static function () {
				return '/does/not/exist.php';
			}
		);

		$this->assertStringNotContainsString( 'No template', $this->render_notices() );
	}
}
