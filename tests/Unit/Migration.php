<?php
/**
 * Tests for data saved by older versions and invalid data.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Unit;

use easyTransientsForWordPress\Transient;
use easyTransientsForWordPress\Transients;
use easyTransientsForWordPress\Tests\easyTransientsForWordPressTest;

/**
 * Object for tests with old or invalid data.
 */
class Migration extends easyTransientsForWordPressTest {
	/**
	 * Return the entry of a transient like the library saves it.
	 *
	 * @param string $message The message.
	 * @param string $type The type.
	 *
	 * @return array<string,mixed>
	 */
	private function get_entry( string $message, string $type ): array {
		return array(
			'message'          => $message,
			'type'             => $type,
			'dismissible_days' => 14,
			'action'           => array(),
			'hide_on'          => array(),
			'prioritized'      => false,
		);
	}

	/**
	 * Versions < 1.5.0 saved complete objects in the list. They are read and converted.
	 *
	 * @return void
	 */
	public function test_list_with_objects_of_older_versions(): void {
		set_transient( 'etfw_old', $this->get_entry( 'Current message', 'error' ) );
		set_transient( 'etfw_string', $this->get_entry( 'String entry', 'success' ) );

		// the object contains an outdated message, like versions < 1.5.0 saved it.
		$outdated = new Transient( 'etfw_old' );
		$outdated->set_message( 'Outdated message' );
		update_option(
			'etfw_transients',
			array(
				self::$slug => array(
					'etfw_old'    => $outdated,
					'etfw_string' => 'etfw_string',
				),
			)
		);

		$transients = Transients::get_instance()->get_transients();
		$this->assertSame( array( 'etfw_old', 'etfw_string' ), array_keys( $transients ) );
		$this->assertSame( 'Current message', $transients['etfw_old']->get_message() );

		// the next save converts the list.
		$this->create_transient( 'etfw_new', 'New', 'info', 14 );
		$this->assertSame(
			array(
				'etfw_old'    => 'etfw_old',
				'etfw_string' => 'etfw_string',
				'etfw_new'    => 'etfw_new',
			),
			$this->get_raw_list()[ self::$slug ]
		);
	}

	/**
	 * Objects of classes which do not exist anymore (e.g. after changing the namespace prefix) do not break the output.
	 *
	 * @return void
	 */
	public function test_list_with_unknown_classes(): void {
		set_transient( 'etfw_x', $this->get_entry( 'Message X', 'error' ) );
		set_transient( 'etfw_y', $this->get_entry( 'Message Y', 'success' ) );

		$this->write_raw_option(
			'etfw_transients',
			'a:1:{s:9:"etfw-test";a:2:{s:6:"etfw_x";O:13:"Old\Transient":0:{}s:6:"etfw_y";O:13:"Old\Transient":0:{}}}'
		);

		Transients::get_instance()->set_display_method( 'grouped' );
		$output = $this->render_notices();

		$this->assertStringContainsString( 'Message X', $output );
		$this->assertStringContainsString( 'Message Y', $output );
		$this->assertStringContainsString( 'etfw-transients-grouped', $output );
	}

	/**
	 * Transients with missing or invalid values do not cause type errors.
	 *
	 * @return void
	 */
	public function test_incomplete_transient_data(): void {
		set_transient(
			'etfw_legacy',
			array(
				'message' => 'Legacy',
				'type'    => 'error',
			)
		);
		set_transient(
			'etfw_broken',
			array(
				'message'          => array( 'no string' ),
				'type'             => array(),
				'dismissible_days' => 'abc',
				'action'           => 'no array',
				'hide_on'          => 'no array',
				'prioritized'      => 'yes',
			)
		);

		$legacy = new Transient( 'etfw_legacy' );
		$this->assertSame( 'Legacy', $legacy->get_message() );
		$this->assertSame( 'error', $legacy->get_type() );
		$this->assertSame( 0, $legacy->get_dismissible_days() );

		$broken = new Transient( 'etfw_broken' );
		$this->assertSame( '', $broken->get_message() );
		$this->assertSame( '', $broken->get_type() );
		$this->assertSame( 0, $broken->get_dismissible_days() );
		$this->assertSame( array(), $broken->get_hide_on() );
		$this->assertTrue( $broken->is_prioritized() );
	}

	/**
	 * Transients of WordPress or other plugins with the same name are ignored.
	 *
	 * @return void
	 */
	public function test_foreign_values_are_ignored(): void {
		set_transient( 'etfw_string_value', 'just a string' );
		set_transient( 'etfw_object_value', new \stdClass() );

		$this->assertSame( '', ( new Transient( 'etfw_string_value' ) )->get_message() );
		$this->assertSame( '', ( new Transient( 'etfw_object_value' ) )->get_message() );
	}

	/**
	 * An invalid list does not break anything.
	 *
	 * @return void
	 */
	public function test_invalid_list(): void {
		update_option( 'etfw_transients', 'invalid' );

		$this->assertSame( array(), Transients::get_instance()->get_transients() );

		$this->create_transient( 'etfw_a', 'A', 'error', 14 );
		$this->assertSame( array( 'etfw_a' => 'etfw_a' ), $this->get_raw_list()[ self::$slug ] );
	}

	/**
	 * A filter which returns invalid entries does not break the output.
	 *
	 * @return void
	 */
	public function test_filter_with_invalid_entries(): void {
		$this->create_transient( 'etfw_a', 'A', 'error', 14 );
		$this->create_transient( 'etfw_b', 'B', 'error', 14 );

		add_filter(
			'etfw_' . self::$slug . '_transients',
			static function ( array $transients ): array {
				$transients[ self::$slug ]['invalid'] = 'no object';
				return $transients;
			}
		);

		Transients::get_instance()->set_display_method( 'grouped' );
		$output = $this->render_notices();

		$this->assertSame( array( 'etfw_a', 'etfw_b' ), array_keys( Transients::get_instance()->get_transients() ) );
		$this->assertStringContainsString( 'etfw-transients-grouped', $output );
	}
}
