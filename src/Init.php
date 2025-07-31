<?php
/**
 * File to handle the main tasks for this library.
 *
 * @package easy-transients-for-wordpress
 */

namespace easyTransientsForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the main tasks for this library.
 */
class Init {

	/**
	 * Instance of this object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Init {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Initialize the setup wrapper.
	 *
	 * @return void
	 */
	public function init(): void {

	}
}
