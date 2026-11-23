<?php
/**
 * File with helper methods for all test classes.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests;

use easyTransientsForWordPress\Init;
use easyTransientsForWordPress\Templates;
use easyTransientsForWordPress\Transient;
use easyTransientsForWordPress\Transients;
use easyTransientsForWordPress\Views\Grouped;
use easyTransientsForWordPress\Views\Single;
use ReflectionProperty;

/**
 * Helper methods to prepare the library for each test.
 */
trait TransientsTestTrait {
	/**
	 * The slug used in the tests.
	 *
	 * @var string
	 */
	protected static string $slug = 'etfw-test';

	/**
	 * Reset all singletons of the library, so each test starts with defaults.
	 *
	 * @return void
	 */
	protected function reset_library(): void {
		foreach ( array( Transients::class, Templates::class, Grouped::class, Single::class, Init::class ) as $class_name ) {
			$property = new ReflectionProperty( $class_name, 'instance' );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$property->setValue( null, null );
		}
	}

	/**
	 * Reset and configure the library like a plugin would do it.
	 *
	 * @return Transients
	 */
	protected function prepare_library(): Transients {
		$this->reset_library();

		$transients_obj = Transients::get_instance();
		$transients_obj->set_slug( self::$slug );
		$transients_obj->set_path( dirname( __DIR__ ) . '/src/' );
		$transients_obj->set_url( 'https://example.org/wp-content/plugins/etfw-test/vendor/threadi/easy-transients-for-wordpress/src/' );
		$transients_obj->init();

		return $transients_obj;
	}

	/**
	 * Log in as a new user with the given role.
	 *
	 * @param string $role The role.
	 *
	 * @return int The user ID.
	 */
	protected function login_as( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	/**
	 * Create and save a transient.
	 *
	 * @param string               $name The name.
	 * @param string               $message The message (empty for action-only-transients).
	 * @param string               $type The type.
	 * @param int                  $dismissible_days The dismissible days.
	 * @param array<string,mixed>  $settings Additional settings: action, hide_on, prioritized.
	 *
	 * @return Transient
	 */
	protected function create_transient( string $name, string $message, string $type = 'success', int $dismissible_days = 0, array $settings = array() ): Transient {
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( $name );
		$transient_obj->set_message( $message );
		$transient_obj->set_type( $type );
		$transient_obj->set_dismissible_days( $dismissible_days );
		if ( isset( $settings['action'] ) ) {
			$transient_obj->set_action( $settings['action'] );
		}
		if ( isset( $settings['hide_on'] ) ) {
			$transient_obj->set_hide_on( $settings['hide_on'] );
		}
		if ( isset( $settings['prioritized'] ) ) {
			$transient_obj->set_prioritized( $settings['prioritized'] );
		}
		$transient_obj->save();
		return $transient_obj;
	}

	/**
	 * Return the raw list of transient names from the database.
	 *
	 * @return array<mixed>
	 */
	protected function get_raw_list(): array {
		$value = get_option( 'etfw_transients', array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Write a raw (serialized) value for an option directly into the database.
	 *
	 * @param string $option_name The option name.
	 * @param string $serialized_value The serialized value.
	 *
	 * @return void
	 */
	protected function write_raw_option( string $option_name, string $serialized_value ): void {
		global $wpdb;
		$wpdb->replace(
			$wpdb->options,
			array(
				'option_name'  => $option_name,
				'option_value' => $serialized_value,
				'autoload'     => 'on',
			)
		);
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( $option_name, 'options' );
	}

	/**
	 * Return the autoload value of an option from the database.
	 *
	 * @param string $option_name The option name.
	 *
	 * @return string|null
	 */
	protected function get_autoload_value( string $option_name ): ?string {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT autoload FROM %i WHERE option_name = %s', $wpdb->options, $option_name ) );
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Remove the scripts and styles of this library, so each test starts without them.
	 *
	 * @return void
	 */
	protected function reset_scripts(): void {
		wp_dequeue_script( 'easy-transients-for-wordpress' );
		wp_deregister_script( 'easy-transients-for-wordpress' );
		wp_dequeue_style( 'etfw-transients' );
		wp_deregister_style( 'etfw-transients' );
	}

	/**
	 * Prevent update checks of WordPress during tests (they would request wordpress.org on "admin_init").
	 *
	 * @return void
	 */
	protected function disable_update_checks(): void {
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
	}

	/**
	 * Return the output of the notices.
	 *
	 * @return string
	 */
	protected function render_notices(): string {
		ob_start();
		Transients::get_instance()->init_notices();
		return (string) ob_get_clean();
	}

	/**
	 * Simulate a request in the backend.
	 *
	 * @param string $request_uri The requested URI.
	 *
	 * @return void
	 */
	protected function go_to_backend( string $request_uri = '/wp-admin/index.php' ): void {
		set_current_screen( 'dashboard' );
		$_SERVER['REQUEST_URI'] = $request_uri;
	}

	/**
	 * Simulate a request in the frontend.
	 *
	 * @return void
	 */
	protected function go_to_frontend(): void {
		unset( $GLOBALS['current_screen'] );
		$_SERVER['REQUEST_URI'] = '/';
	}
}
