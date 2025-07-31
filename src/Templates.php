<?php
/**
 * File to handle template-tasks.
 *
 * @package easy-transients-for-wordpress
 */

namespace easyTransientsForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handler for templates.
 */
class Templates {
	/**
	 * Instance of this object.
	 *
	 * @var ?Templates
	 */
	private static ?Templates $instance = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Templates {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return path to a requested template if it exists.
	 *
	 * Also load the requested file if it is located in the /wp-content/themes/xy/plugin-slug/vendor/threadi/easy-transients-for-wordpress/templates/ directory.
	 * Or from actual plugin in /wp-content/plugins/plugin-slug/vendor/threadi/easy-transients-for-wordpress/templates/
	 *
	 * @param string $template The template to use.
	 * @return string
	 */
	public function get_template( string $template ): string {
		// check if requested template exist in theme.
		$theme_template = locate_template( trailingslashit( basename( Transients::get_instance()->get_path() ) ) . $template );
		if ( $theme_template ) {
			return $theme_template;
		}

		// check if actual plugin does contain the requested template.
		$plugin_template = Transients::get_instance()->get_path() . 'templates/' . $template;
		if( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		// return path to our own template.
		return Transients::get_instance()->get_path() . $template;
	}
}
