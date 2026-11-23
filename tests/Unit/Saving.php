<?php
/**
 * Tests for saving and deleting transients.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Unit;

use easyTransientsForWordPress\Transients;
use easyTransientsForWordPress\Tests\easyTransientsForWordPressTest;
use easyTransientsForWordPress\Tests\Helper\Action_Helper;

/**
 * Object for tests for saving and deleting transients.
 */
class Saving extends easyTransientsForWordPressTest {
	/**
	 * Only the names are saved in the list.
	 *
	 * @return void
	 */
	public function test_list_contains_only_names(): void {
		$this->create_transient( 'etfw_a', 'A', 'error', 14 );
		$this->create_transient( 'etfw_b', 'B', 'success', 14 );

		$list = $this->get_raw_list();
		$this->assertSame(
			array(
				'etfw_a' => 'etfw_a',
				'etfw_b' => 'etfw_b',
			),
			$list[ self::$slug ]
		);
	}

	/**
	 * A changed message of an existing transient will be used (bug in versions < 1.5.0).
	 *
	 * @return void
	 */
	public function test_changed_message_is_used(): void {
		$this->create_transient( 'etfw_a', 'Old message', 'error', 14 );
		$this->create_transient( 'etfw_b', 'B', 'success', 14 );
		$this->create_transient( 'etfw_a', 'New message', 'error', 14 );

		$transients = Transients::get_instance()->get_transients();
		$this->assertSame( 'New message', $transients['etfw_a']->get_message() );
		$this->assertStringContainsString( 'New message', $this->render_notices() );
	}

	/**
	 * Delete removes the transient and its entry in the list.
	 *
	 * @return void
	 */
	public function test_delete(): void {
		$transient_obj = $this->create_transient( 'etfw_a', 'A', 'error', 14 );
		$this->create_transient( 'etfw_b', 'B', 'error', 14 );

		$transient_obj->delete();

		$this->assertFalse( get_transient( 'etfw_a' ) );
		$this->assertNotFalse( get_transient( 'etfw_b' ) );
		$this->assertSame( array( 'etfw_b' => 'etfw_b' ), $this->get_raw_list()[ self::$slug ] );
	}

	/**
	 * Delete does not remove transients of other projects or WordPress.
	 *
	 * @return void
	 */
	public function test_delete_ignores_foreign_transients(): void {
		set_transient( 'foreign_transient', 'value' );

		Transients::get_instance()->get_transient_by_name( 'foreign_transient' )->delete();

		$this->assertSame( 'value', get_transient( 'foreign_transient' ) );
	}

	/**
	 * The filter for transients with messages.
	 *
	 * @return void
	 */
	public function test_only_with_text(): void {
		$this->create_transient( 'etfw_notice', 'Notice', 'info', 14 );
		$this->create_transient( 'etfw_action', '', '', 0, array( 'action' => array( Action_Helper::class, 'reset' ) ) );

		$this->assertCount( 2, Transients::get_instance()->get_transients() );
		$this->assertSame( array( 'etfw_notice' ), array_keys( Transients::get_instance()->get_transients( true ) ) );
	}

	/**
	 * Dismissed transients are hidden, but stay in the list.
	 *
	 * @return void
	 */
	public function test_dismissed_transient_stays_in_list(): void {
		$transient_obj  = $this->create_transient( 'etfw_a', 'A', 'error', 14 );
		$transients_obj = Transients::get_instance();

		$transient_obj->add_dismiss( 5 );

		$this->assertSame( array(), $transients_obj->get_transients() );
		$this->assertFalse( $transients_obj->is_transient_set( 'etfw_a' ) );
		$this->assertTrue( $transients_obj->is_transient_in_list( 'etfw_a' ) );
		$this->assertArrayHasKey( 'etfw_a', $transients_obj->get_transients( false, true ) );

		// saving another transient does not remove it from the list.
		$this->create_transient( 'etfw_b', 'B', 'error', 14 );
		$this->assertArrayHasKey( 'etfw_a', $this->get_raw_list()[ self::$slug ] );
	}

	/**
	 * Names of transients which do not exist anymore are removed on save.
	 *
	 * @return void
	 */
	public function test_cleanup_on_save(): void {
		$this->create_transient( 'etfw_a', 'A', 'error', 14 );
		delete_transient( 'etfw_a' );

		$this->create_transient( 'etfw_b', 'B', 'error', 14 );

		$this->assertSame( array( 'etfw_b' => 'etfw_b' ), $this->get_raw_list()[ self::$slug ] );
	}

	/**
	 * Entries of other projects are not changed.
	 *
	 * @return void
	 */
	public function test_other_projects_are_untouched(): void {
		update_option( 'etfw_transients', array( 'other-plugin' => array( 'other_notice' => 'other_notice' ) ) );

		$transient_obj = $this->create_transient( 'etfw_a', 'A', 'error', 14 );
		$transient_obj->delete();

		$this->assertSame( array( 'other_notice' => 'other_notice' ), $this->get_raw_list()['other-plugin'] );
	}

	/**
	 * A transient without name is not added to the list.
	 *
	 * @return void
	 */
	public function test_transient_without_name(): void {
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_message( 'Without name' );

		Transients::get_instance()->add_transient( $transient_obj );

		$this->assertEmpty( $this->get_raw_list()[ self::$slug ] ?? array() );
	}

	/**
	 * Saving reads the list directly from the database, so changes of parallel requests are kept.
	 *
	 * @return void
	 */
	public function test_save_uses_actual_database_value(): void {
		global $wpdb;

		$this->create_transient( 'etfw_a', 'A', 'error', 14 );

		// simulate a parallel request, which has added an entry directly in the database.
		set_transient( 'etfw_parallel', array( 'message' => 'Parallel' ) );
		$wpdb->update(
			$wpdb->options,
			array(
				'option_value' => maybe_serialize(
					array(
						self::$slug => array(
							'etfw_a'        => 'etfw_a',
							'etfw_parallel' => 'etfw_parallel',
						),
					)
				),
			),
			array( 'option_name' => 'etfw_transients' )
		);

		$this->create_transient( 'etfw_b', 'B', 'error', 14 );

		$this->assertSame( array( 'etfw_a', 'etfw_parallel', 'etfw_b' ), array_keys( $this->get_raw_list()[ self::$slug ] ) );
	}
}
