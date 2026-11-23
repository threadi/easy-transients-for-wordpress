<?php
/**
 * File to handle template-tasks.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

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
	 * Search order:
	 * 1. In the active (child-)theme: /wp-content/themes/xy/{slug}/vendor/threadi/easy-transients-for-wordpress/templates/
	 * 2. In the project which uses this library: {vendor-path}/../templates/vendor/threadi/easy-transients-for-wordpress/templates/
	 * 3. The templates of this library.
	 *
	 * The result could be changed by the filter "etfw_{slug}_template".
	 *
	 * @param string $template The template to use.
	 * @return string Absolute path to the template or empty string if it does not exist.
	 */
	public function get_template( string $template ): string {
		// bail if template name is not valid.
		if ( '' === $template || ! str_ends_with( $template, '.php' ) || str_contains( $template, '..' ) ) {
			return '';
		}

		// get the transients object.
		$transients_obj = Transients::get_instance();

		// check if requested template exist in the theme.
		$path = locate_template( trailingslashit( $transients_obj->get_slug() ) . 'vendor/threadi/easy-transients-for-wordpress/templates/' . $template );

		// check if actual plugin does contain the requested template.
		if ( empty( $path ) ) {
			$plugin_template = trailingslashit( $transients_obj->get_vendor_path() ) . '../templates/vendor/threadi/easy-transients-for-wordpress/templates/' . $template;
			if ( file_exists( $plugin_template ) ) {
				$path = $plugin_template;
			}
		}

		// use our own template.
		if ( empty( $path ) ) {
			$path = $transients_obj->get_base_path() . 'Templates/' . $template;
		}

		/**
		 * Filter the path of the template to use.
		 *
		 * @since 1.5.0 Available since 1.5.0.
		 * @param string $path The absolute path to the template.
		 * @param string $template The requested template.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- etfw_ is this library's own intentional hook prefix.
		$path = apply_filters( 'etfw_' . $transients_obj->get_slug() . '_template', $path, $template );

		// bail if template does not exist.
		if ( ! is_string( $path ) || ! file_exists( $path ) ) {
			return '';
		}

		// return the path.
		return $path;
	}
}
