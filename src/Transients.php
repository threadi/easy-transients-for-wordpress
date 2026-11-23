<?php
/**
 * This file contains the handling of transients in wp-admin.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyTransientsForWordPress\Views\Grouped;
use easyTransientsForWordPress\Views\Single;

/**
 * Initialize the transients-object.
 */
class Transients {
	/**
	 * Name of the option where the list of transient names of all projects is saved.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'etfw_transients';

	/**
	 * The capability.
	 *
	 * @var string
	 */
	private string $capability = 'manage_options';

	/**
	 * The slug.
	 *
	 * @var string
	 */
	private string $slug = 'etfw';

	/**
	 * The path.
	 *
	 * @var string
	 */
	private string $path = '';

	/**
	 * The URL.
	 *
	 * @var string
	 */
	private string $url = '';

	/**
	 * The template to use.
	 *
	 * @var string
	 */
	private string $template = 'single.php';

	/**
	 * The display method.
	 *
	 * @var string
	 */
	private string $display_method = 'single';

	/**
	 * The configured vendor path.
	 *
	 * @var string
	 */
	private string $vendor_path = '';

	/**
	 * Instance of actual object.
	 *
	 * @var Transients|null
	 */
	private static ?Transients $instance = null;

	/**
	 * List of translations.
	 *
	 * @var array<string,string>
	 */
	private array $translations = array();

	/**
	 * Marker whether our files have been enqueued.
	 *
	 * @var bool
	 */
	private bool $files_enqueued = false;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Transients
	 */
	public static function get_instance(): Transients {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the transients.
	 *
	 * @return void
	 */
	public function init(): void {
		// enable our own notices.
		add_action( 'admin_notices', array( $this, 'init_notices' ) );

		// run the action transients.
		add_action( 'shutdown', array( $this, 'init_actions' ) );

		// register our scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ), 10, 0 );

		// process AJAX-requests to dismiss transient notices.
		add_action( 'wp_ajax_efw_dismiss_admin_notice', array( $this, 'dismiss_transient_via_ajax' ) );
	}

	/**
	 * Initialize the visibility of any transients as notices.
	 *
	 * @return void
	 */
	public function init_notices(): void {
		// return if user has no capability for this.
		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}

		// get the transients which will be visible on this page.
		$visible_transients = $this->get_visible_transients();

		// make sure our files are loaded, e.g. if a transient has been added after "admin_enqueue_scripts".
		if ( ! empty( $visible_transients ) ) {
			$this->enqueue_files();
		}

		// show the transients depending on configured display method.
		if ( 'single' === $this->get_display_method() || count( $visible_transients ) <= 1 ) {
			$this->set_template( 'single.php' );
			Single::get_instance()->display();
			return;
		}

		// use the grouped template if no other template has been configured.
		if ( 'single.php' === $this->get_template() ) {
			$this->set_template( 'grouped.php' );
		}

		Grouped::get_instance()->display();
	}

	/**
	 * Run the actions from transients.
	 *
	 * @return void
	 */
	public function init_actions(): void {
		// loop through the transients of this project and run only the action transients.
		foreach ( $this->get_transients() as $transient ) {
			// bail if transient is not our own one.
			if ( ! $transient instanceof Transient ) {
				continue;
			}

			// bail if transient has a message (then it is a notice, no action-only-transient).
			if ( ! empty( $transient->get_message() ) ) {
				continue;
			}

			// run the action.
			$transient->run();
		}
	}

	/**
	 * Adds a single transient.
	 *
	 * @return Transient
	 */
	public function add(): Transient {
		// create the new object and return it directly.
		return new Transient();
	}

	/**
	 * Return all known transients of any plugin.
	 *
	 * The entry of the actual project contains Transient-objects, all other entries contain only the names.
	 *
	 * @param bool $only_with_text True to load only transients with messages.
	 * @param bool $show_dismissed Show also dismissed transients.
	 *
	 * @return array<string,array<string,Transient|string>>
	 */
	public function get_all_transients( bool $only_with_text = false, bool $show_dismissed = false ): array {
		// get list of our own transients from the database as array.
		$transients_from_db = $this->get_list();

		// create the corresponding transient-objects for this project.
		$transients = array();
		foreach ( $this->get_names_of_project( $transients_from_db ) as $name ) {
			// create the object.
			$transient = new Transient( $name );

			// bail if transient is not set or dismissed.
			if ( ! $transient->is_set() || ( ! $show_dismissed && $transient->is_dismissed() ) ) {
				continue;
			}

			// bail if transient has no text.
			if ( $only_with_text && empty( $transient->get_message() ) ) {
				continue;
			}

			// add the object to list.
			$transients[ $transient->get_name() ] = $transient;
		}

		// set the list for this project.
		$transients_from_db[ $this->get_slug() ] = $transients;

		/**
		 * Filter the transients from the database.
		 *
		 * Hint: the filtered value is only used for output, it will not be saved.
		 *
		 * @since 1.3.0 Available since 1.3.0.
		 * @param array $transients_from_db The transients from the database.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- etfw_ is this library's own intentional hook prefix.
		$transients_from_db = apply_filters( 'etfw_' . $this->get_slug() . '_transients', $transients_from_db );

		// return empty array if filter returns an invalid value.
		if ( ! is_array( $transients_from_db ) ) {
			return array();
		}

		// return the resulting list.
		return $transients_from_db;
	}

	/**
	 * Return all known transients of this plugin as objects.
	 *
	 * @param bool $only_with_text True to load only transients with messages.
	 * @param bool $show_dismissed Show also dismissed transients.
	 *
	 * @return array<string,Transient>
	 */
	public function get_transients( bool $only_with_text = false, bool $show_dismissed = false ): array {
		// get all actual known transients as array.
		$transients = $this->get_all_transients( $only_with_text, $show_dismissed );

		// bail if no transients for this plugin are set.
		if ( empty( $transients[ $this->get_slug() ] ) || ! is_array( $transients[ $this->get_slug() ] ) ) {
			return array();
		}

		// return the transients of this plugin, only valid objects.
		return array_filter(
			$transients[ $this->get_slug() ],
			static function ( $transient ) {
				return $transient instanceof Transient;
			}
		);
	}

	/**
	 * Return the transients of this plugin, which will be visible on the actual page.
	 *
	 * Visible means: with message, not dismissed and not hidden on the actual URL.
	 *
	 * @return array<string,Transient>
	 */
	public function get_visible_transients(): array {
		return array_filter(
			$this->get_transients( true ),
			static function ( Transient $transient ) {
				return $transient->is_visible();
			}
		);
	}

	/**
	 * Check if a given transient is known to this handler (and not dismissed).
	 *
	 * @param string $transient_name The requested transient-name.
	 *
	 * @return bool
	 */
	public function is_transient_set( string $transient_name ): bool {
		$transients = $this->get_transients();
		return ! empty( $transients[ $transient_name ] );
	}

	/**
	 * Check if a given transient name is on the list of this project, independent of its state.
	 *
	 * @param string $transient_name The requested transient-name.
	 *
	 * @return bool
	 */
	public function is_transient_in_list( string $transient_name ): bool {
		if ( '' === $transient_name ) {
			return false;
		}

		return in_array( $transient_name, $this->get_names_of_project( $this->get_list() ), true );
	}

	/**
	 * Add new transient to list of our plugin-specific transients.
	 *
	 * Only the name of the transient is saved in the list. Its content is stored in the transient itself.
	 *
	 * @param Transient $transient_obj The transient-object to add.
	 *
	 * @return void
	 */
	public function add_transient( Transient $transient_obj ): void {
		// bail if transient has no name.
		if ( '' === $transient_obj->get_name() ) {
			return;
		}

		// get the actual list directly from the database.
		$transients_from_db = $this->get_list( true );

		// get the cleaned list of this project and add the new entry.
		$names = $this->cleanup_names( $this->get_names_of_project( $transients_from_db ) );

		$names[ $transient_obj->get_name() ] = $transient_obj->get_name();

		// update the list.
		$transients_from_db[ $this->get_slug() ] = $names;

		// save the transients-list in the database.
		update_option( self::OPTION_NAME, $transients_from_db );
	}

	/**
	 * Delete single transient from our own list.
	 *
	 * @param Transient $transient_to_delete_obj The transient-object to delete.
	 *
	 * @return void
	 */
	public function delete_transient( Transient $transient_to_delete_obj ): void {
		// get the actual list directly from the database.
		$transients_from_db = $this->get_list( true );

		// get the list of this project.
		$names = $this->get_names_of_project( $transients_from_db );

		// bail if transient is not in our list.
		if ( ! isset( $names[ $transient_to_delete_obj->get_name() ] ) ) {
			return;
		}

		// remove it from the list.
		unset( $names[ $transient_to_delete_obj->get_name() ] );

		// update the list.
		$transients_from_db[ $this->get_slug() ] = $this->cleanup_names( $names );

		// save the updated transients.
		update_option( self::OPTION_NAME, $transients_from_db );
	}

	/**
	 * Return a specific transient by its internal name.
	 *
	 * @param string $transient The transient-name we search.
	 *
	 * @return Transient
	 */
	public function get_transient_by_name( string $transient ): Transient {
		return new Transient( $transient );
	}

	/**
	 * Handles Ajax request to persist notices dismissal.
	 * Uses check_ajax_referer to verify nonce.
	 *
	 * If the requested transient does not belong to this project, the request will be ignored,
	 * so other projects which use this library could handle it.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function dismiss_transient_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'etfw-dismiss-nonce', 'nonce' );

		// get the transient name.
		$option_name = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';

		// bail if no name is given.
		if ( '' === $option_name ) {
			wp_send_json_error();
		}

		// bail without response if transient is not from this project (another project with this library could handle it).
		if ( ! $this->is_transient_in_list( $option_name ) ) {
			return;
		}

		// bail if the user is not allowed to see (and with it to dismiss) the transients.
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_send_json_error();
		}

		// get the transient object.
		$transient = $this->get_transient_by_name( $option_name );

		// get the requested dismiss length.
		$dismissible_length = isset( $_POST['dismissible_length'] ) ? sanitize_text_field( wp_unslash( $_POST['dismissible_length'] ) ) : '';

		// calculate the dismiss timestamp: the setting of the transient is preferred, the requested value is the fallback, 14 days the default.
		if ( 'forever' !== $dismissible_length ) {
			$days = $transient->get_dismissible_days();
			if ( $days <= 0 ) {
				$days = absint( $dismissible_length );
			}
			if ( $days <= 0 ) {
				$days = 14;
			}
			$dismissible_length = (int) strtotime( $days . ' days' );
		}

		// save value.
		$transient->set_dismissed_until( $dismissible_length );

		// remove transient.
		$transient->delete();

		// return ok message.
		wp_send_json_success();
	}

	/**
	 * Return the capability.
	 *
	 * @return string
	 */
	private function get_capability(): string {
		return $this->capability;
	}

	/**
	 * Set the capability to show the transients.
	 *
	 * @param string $capability The capability.
	 *
	 * @return void
	 */
	public function set_capability( string $capability ): void {
		$this->capability = $capability;
	}

	/**
	 * Return the plugin slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Set the slug to show the transients.
	 *
	 * @param string $slug The slug.
	 *
	 * @return void
	 */
	public function set_slug( string $slug ): void {
		$this->slug = $slug;
	}

	/**
	 * Return the path.
	 *
	 * @return string
	 */
	public function get_path(): string {
		return $this->path;
	}

	/**
	 * Set the plugin path to show the transients.
	 *
	 * @param string $path The plugin path.
	 *
	 * @return void
	 */
	public function set_path( string $path ): void {
		$this->path = $path;
	}

	/**
	 * Return the path to the "src"-directory of this library with trailing slash.
	 *
	 * Uses the configured path and falls back to the directory of this file.
	 *
	 * @return string
	 */
	public function get_base_path(): string {
		if ( '' !== $this->get_path() ) {
			return trailingslashit( $this->get_path() );
		}
		return trailingslashit( __DIR__ );
	}

	/**
	 * Add our scripts for the transients.
	 *
	 * Only loaded for users which could see the transients and only if transients with messages exist.
	 *
	 * @return void
	 */
	public function add_scripts(): void {
		// bail if user could not see the transients.
		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}

		// bail if no transients with messages exist.
		if ( empty( $this->get_transients( true ) ) ) {
			return;
		}

		// enqueue the files.
		$this->enqueue_files();
	}

	/**
	 * Enqueue our files (only once per request).
	 *
	 * @return void
	 */
	private function enqueue_files(): void {
		// bail if already done.
		if ( $this->files_enqueued ) {
			return;
		}

		// bail if no URL is configured.
		if ( '' === $this->get_url() ) {
			return;
		}

		// mark as done.
		$this->files_enqueued = true;

		// get the URL.
		$url = trailingslashit( $this->get_url() );

		// add our script.
		wp_enqueue_script(
			'easy-transients-for-wordpress',
			$url . 'Files/js.js',
			array( 'jquery' ),
			$this->get_file_version( 'Files/js.js' ),
			true
		);

		// embed the CSS-file.
		// Hint: the handle differs from versions < 1.5.0 so that old and new styles could be loaded side by side.
		wp_enqueue_style(
			'etfw-transients',
			$url . 'Files/style.css',
			array(),
			$this->get_file_version( 'Files/style.css' )
		);

		// get the translations.
		$translations = $this->get_translations();

		// add php-vars to our js-script.
		wp_localize_script(
			'easy-transients-for-wordpress',
			'etfwJsVars',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'dismiss_nonce' => wp_create_nonce( 'etfw-dismiss-nonce' ),
				'dismiss_error' => $translations['dismiss_error'],
			)
		);
	}

	/**
	 * Return the version of a given file of this library, based on its modification time.
	 *
	 * @param string $file The file, relative to the "src"-directory.
	 *
	 * @return string|false
	 */
	private function get_file_version( string $file ): string|false {
		$path = $this->get_base_path() . $file;

		// bail if file does not exist.
		if ( ! file_exists( $path ) ) {
			return false;
		}

		// get the modification time.
		$time = filemtime( $path );

		// bail if it could not be read.
		if ( false === $time ) {
			return false;
		}

		return (string) $time;
	}

	/**
	 * Return the vendor path with trailing slash.
	 *
	 * @return string
	 */
	public function get_vendor_path(): string {
		// return configured vendor path.
		if ( ! empty( $this->vendor_path ) ) {
			return $this->vendor_path;
		}

		// detect vendor path: this file is located in vendor/threadi/easy-transients-for-wordpress/src.
		return trailingslashit( dirname( __DIR__, 3 ) );
	}

	/**
	 * Set vendor path.
	 *
	 * @param string $vendor_path The path to the vendor-directory of the project.
	 *
	 * @return void
	 */
	public function set_vendor_path( string $vendor_path ): void {
		$this->vendor_path = $vendor_path;
	}

	/**
	 * Return the URL.
	 *
	 * @return string
	 */
	private function get_url(): string {
		return $this->url;
	}

	/**
	 * Set the URL.
	 *
	 * @param string $url The URL to use.
	 *
	 * @return void
	 */
	public function set_url( string $url ): void {
		$this->url = $url;
	}

	/**
	 * Return the template.
	 *
	 * @return string
	 */
	public function get_template(): string {
		return $this->template;
	}

	/**
	 * Set the template.
	 *
	 * Hint: must end with ".php" and must not contain "..".
	 *
	 * @param string $template The template to use.
	 *
	 * @return void
	 */
	public function set_template( string $template ): void {
		// check for ending string.
		if ( ! str_ends_with( $template, '.php' ) ) {
			return;
		}

		// prevent path traversal.
		if ( str_contains( $template, '..' ) ) {
			return;
		}

		$this->template = $template;
	}

	/**
	 * Return the display method.
	 *
	 * @return string
	 */
	private function get_display_method(): string {
		return $this->display_method;
	}

	/**
	 * Set the display method.
	 *
	 * We support:
	 * - single => every transient in single "admin_notice" via the configured template, unsorted.
	 * - grouped => all transient in one "admin_notice" as slider, sorted by errors first, success last.
	 *
	 * @param string $display_method The display method to use.
	 *
	 * @return void
	 */
	public function set_display_method( string $display_method ): void {
		// bail if given display method is not supported.
		if ( ! in_array( $display_method, array( 'single', 'grouped' ), true ) ) {
			return;
		}
		$this->display_method = $display_method;
	}

	/**
	 * Return the list of translations.
	 *
	 * @return array<string,string>
	 */
	public function get_translations(): array {
		// set the main translations.
		$translations = array(
			'hide_message'  => 'Hide this message for %1$d days.',
			'dismiss'       => 'Dismiss',
			'dismiss_error' => 'The message could not be hidden. Please reload the page and try again.',
		);

		// return combined list of translations.
		return array_merge( $translations, $this->translations );
	}

	/**
	 * Set the list of custom translations.
	 *
	 * @param array<string,string> $translations List of translations.
	 * @return void
	 */
	public function set_translations( array $translations ): void {
		$this->translations = $translations;
	}

	/**
	 * Return the list of transient names of all projects from the database.
	 *
	 * The list is normalized to "slug => array( name => name )".
	 *
	 * @param bool $fresh True to read the value directly from the database, bypassing the cache.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function get_list( bool $fresh = false ): array {
		$transients_list = false;

		// read the actual value from the database to minimize the risk of overwriting changes from parallel requests.
		if ( $fresh ) {
			global $wpdb;
			if ( $wpdb instanceof \wpdb ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- we need the uncached value here.
				$value = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s LIMIT 1', $wpdb->options, self::OPTION_NAME ) );
				if ( is_string( $value ) ) {
					$transients_list = maybe_unserialize( $value );
				}
			}
		}

		// use the cached value as fallback.
		if ( false === $transients_list ) {
			$transients_list = get_option( self::OPTION_NAME, array() );
		}

		// return the normalized list.
		return $this->normalize_list( $transients_list );
	}

	/**
	 * Normalize the list from database.
	 *
	 * Versions < 1.5.0 saved complete objects in this list. They are converted to their names here.
	 *
	 * @param mixed $transients_list The list from database.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function normalize_list( mixed $transients_list ): array {
		// bail if list is not an array.
		if ( ! is_array( $transients_list ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $transients_list as $slug => $entries ) {
			// bail if entry is not an array.
			if ( ! is_array( $entries ) ) {
				continue;
			}

			// collect the names.
			$names = array();
			foreach ( $entries as $index => $entry ) {
				$name = $this->get_name_of_entry( $index, $entry );
				if ( '' === $name ) {
					continue;
				}
				$names[ $name ] = $name;
			}

			// add them to the list.
			$normalized[ $slug ] = $names;
		}

		// return the resulting list.
		return $normalized;
	}

	/**
	 * Return the transient name of a single entry of the list.
	 *
	 * @param int|string $index The index of the entry.
	 * @param mixed      $entry The entry.
	 *
	 * @return string
	 */
	private function get_name_of_entry( int|string $index, mixed $entry ): string {
		// the entry is the name.
		if ( is_string( $entry ) ) {
			return $entry;
		}

		// for objects (saved by versions < 1.5.0, maybe from classes which does not exist anymore) the index is the name.
		if ( is_object( $entry ) ) {
			return (string) $index;
		}

		// all other entries are invalid.
		return '';
	}

	/**
	 * Return the list of transient names of this project from the given list.
	 *
	 * @param array<string,array<string,string>> $transients_list The list of all projects.
	 *
	 * @return array<string,string>
	 */
	private function get_names_of_project( array $transients_list ): array {
		if ( empty( $transients_list[ $this->get_slug() ] ) ) {
			return array();
		}

		$names = array();
		foreach ( $transients_list[ $this->get_slug() ] as $name ) {
			$names[ (string) $name ] = (string) $name;
		}
		return $names;
	}

	/**
	 * Remove names of transients which do not exist anymore.
	 *
	 * @param array<string,string> $names List of names.
	 *
	 * @return array<string,string>
	 */
	private function cleanup_names( array $names ): array {
		foreach ( $names as $index => $name ) {
			if ( false === get_transient( $name ) ) {
				unset( $names[ $index ] );
			}
		}
		return $names;
	}
}
