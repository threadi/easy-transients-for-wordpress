<?php
/**
 * File for handling single output of multiple transients.
 *
 * @package easy-transients-for-wordpress
 */

namespace easyTransientsForWordPress\Views;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyTransientsForWordPress\Transients;

/**
 * Object for handling single output of multiple transients.
 */
class Single {
	/**
	 * Instance of this object.
	 *
	 * @var ?Single
	 */
	private static ?Single $instance = null;

	/**
	 * Constructor for this object.
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
	public static function get_instance(): Single {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Show the transients.
	 *
	 * @return void
	 */
	public function display(): void {
		// get list of transients of this plugin.
		$transients = Transients::get_instance()->get_transients();

		// check for active transients and show them.
		foreach ( $transients as $transient_obj ) {
			// bail if transient is not set.
			if ( ! $transient_obj->is_set() ) {
				continue;
			}

			// show this transient hint.
			$transient_obj->display();
		}
	}
}
