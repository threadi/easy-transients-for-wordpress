<?php
/**
 * This file contains the handling of a single transient in wp-admin.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Throwable;
use WP_Post;
use WP_Post_Type;

/**
 * Initialize a single transient-object.
 */
class Transient {
	/**
	 * The transient message.
	 *
	 * @var string
	 */
	private string $message = '';

	/**
	 * The internal name for this transient.
	 *
	 * @var string
	 */
	private string $name = '';

	/**
	 * The transient type.
	 *
	 * @var string
	 */
	private string $type = '';

	/**
	 * The next transient name,
	 *
	 * @var string
	 */
	private string $next = '';

	/**
	 * The prev transient name,
	 *
	 * @var string
	 */
	private string $prev = '';

	/**
	 * Set the dismissible days.
	 *
	 * @var int
	 */
	private int $dismissible_days = 0;

	/**
	 * Action-callback-array.
	 *
	 * @var array<integer,string>
	 */
	private array $action = array();

	/**
	 * List of URLs where this transient should not be visible.
	 *
	 * @var array<int,string>
	 */
	private array $hide_on = array();

	/**
	 * The prioritized marker.
	 *
	 * @var bool
	 */
	private bool $prioritized = false;

	/**
	 * Constructor for this object.
	 *
	 * If $transient is given, fill the object with its data.
	 *
	 * @param string $transient The transient-name we use for this object.
	 */
	public function __construct( string $transient = '' ) {
		$this->set_name( $transient );

		// get the transients contents.
		$entry = get_transient( $this->get_name() );

		// bail if entry is not one of our transients.
		if ( ! is_array( $entry ) || ! isset( $entry['message'] ) ) {
			return;
		}

		// get attributes from entry and set them in the object (with defaults for missing or invalid values).
		$this->set_message( is_scalar( $entry['message'] ) ? (string) $entry['message'] : '' );
		$this->set_type( isset( $entry['type'] ) && is_scalar( $entry['type'] ) ? (string) $entry['type'] : '' );
		$this->set_dismissible_days( isset( $entry['dismissible_days'] ) && is_numeric( $entry['dismissible_days'] ) ? absint( $entry['dismissible_days'] ) : 0 );
		$this->set_action( isset( $entry['action'] ) && is_array( $entry['action'] ) ? $entry['action'] : array() );
		$this->set_hide_on( ! empty( $entry['hide_on'] ) && is_array( $entry['hide_on'] ) ? array_values( array_filter( $entry['hide_on'], 'is_string' ) ) : array() );
		$this->set_prioritized( ! empty( $entry['prioritized'] ) );
	}

	/**
	 * Return the message for this transient.
	 *
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Set the message for this transient.
	 *
	 * @param string $message The text-message for the transient.
	 *
	 * @return void
	 */
	public function set_message( string $message ): void {
		$this->message = $message;
	}

	/**
	 * Save the transient in WP.
	 *
	 * @return void
	 */
	public function save(): void {
		// save the transient itself in WP.
		set_transient( $this->get_name(), $this->get_entry() );

		// save the internal name to our own list of transients.
		Transients::get_instance()->add_transient( $this );
	}

	/**
	 * Get the internal name of this transient.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Set the internal name of this transient.
	 *
	 * @param string $name The internal name for this transient.
	 *
	 * @return void
	 */
	public function set_name( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Return the settings for this transient.
	 *
	 * @return array<string,mixed>
	 */
	private function get_entry(): array {
		return array(
			'message'          => $this->get_message(),
			'type'             => $this->get_type(),
			'dismissible_days' => $this->get_dismissible_days(),
			'action'           => $this->get_action(),
			'hide_on'          => $this->get_hide_on(),
			'prioritized'      => $this->is_prioritized(),
		);
	}

	/**
	 * Check if this transient is set in WP.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function is_set(): bool {
		$transient = get_transient( $this->get_name() );
		if ( null === $transient ) {
			return false;
		}
		if ( false === $transient ) {
			return false;
		}
		return true;
	}

	/**
	 * Return whether this transient would be visible on the actual page.
	 *
	 * @return bool
	 */
	public function is_visible(): bool {
		return $this->has_message() && ! $this->is_dismissed() && ! $this->is_hidden();
	}

	/**
	 * Output the content of this transient.
	 *
	 * @return void
	 */
	public function display(): void {
		// check if this transient is dismissed.
		if ( $this->is_dismissed() ) {
			return;
		}

		// bail if called URL is on hide-list.
		if ( $this->is_hidden() ) {
			return;
		}

		// get the translations.
		$translations = Transients::get_instance()->get_translations();

		// output, if message is given and the template exists.
		if ( $this->has_message() ) {
			$template = Templates::get_instance()->get_template( Transients::get_instance()->get_template() );
			if ( '' !== $template ) {
				include $template;
			}
		}

		// call action, if set.
		if ( $this->has_action() ) {
			$this->call_action();
		}

		// remove the transient if no dismiss is set.
		if ( 0 === $this->get_dismissible_days() ) {
			$this->delete();
		}
	}

	/**
	 * Return the message-type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Set the message-type.
	 *
	 * @param string $type The type of this transient (e.g. error or success).
	 *
	 * @return void
	 */
	public function set_type( string $type ): void {
		$this->type = $type;
	}

	/**
	 * Delete this transient from WP, and our own list if it exists there.
	 *
	 * This does not remove the dismiss-marker as it should be independent of the settings itself.
	 *
	 * @return void
	 */
	public function delete(): void {
		$transients_obj = Transients::get_instance();

		// bail if this transient is not from this project.
		if ( ! $transients_obj->is_transient_in_list( $this->get_name() ) ) {
			return;
		}

		// delete from our own list.
		$transients_obj->delete_transient( $this );

		// delete from WP.
		delete_transient( $this->get_name() );
	}

	/**
	 * Return whether this transient is dismissed (true) or not (false).
	 *
	 * @return bool
	 */
	public function is_dismissed(): bool {
		// get value from the cache, if set.
		$db_record = $this->get_admin_transient_dismiss_cache();

		// return bool depending on value ("forever" is returned as timestamp in the future).
		return false !== $db_record && $db_record >= time();
	}

	/**
	 * Return the name of the option which holds the dismiss-marker.
	 *
	 * @return string
	 */
	private function get_dismiss_option_name(): string {
		return Transients::get_instance()->get_slug() . '-dismissed-' . md5( $this->get_name() );
	}

	/**
	 * Get transient-dismiss-cache.
	 *
	 * Expired dismiss-markers will be removed.
	 *
	 * @return int|false
	 */
	private function get_admin_transient_dismiss_cache(): int|false {
		$cache_key = $this->get_dismiss_option_name();
		$timeout   = get_option( $cache_key );

		// bail if no marker is set.
		if ( empty( $timeout ) ) {
			return false;
		}

		// marker is set for ever.
		if ( 'forever' === $timeout ) {
			return time() + 60;
		}

		// bail if marker is invalid.
		if ( ! is_numeric( $timeout ) ) {
			return false;
		}

		// remove the marker if it is expired.
		if ( time() > (int) $timeout ) {
			delete_option( $cache_key );
			return false;
		}

		return (int) $timeout;
	}

	/**
	 * Add dismiss-marker with given dismiss length in days.
	 *
	 * @param int $dismissible_length Dismiss length in days.
	 *
	 * @return void
	 */
	public function add_dismiss( int $dismissible_length ): void {
		if ( ! $this->is_set() ) {
			return;
		}
		$this->set_dismissed_until( (int) strtotime( absint( $dismissible_length ) . ' days' ) );
	}

	/**
	 * Set the dismiss-marker.
	 *
	 * @param int|string $until Timestamp until the transient is dismissed or "forever".
	 *
	 * @return void
	 */
	public function set_dismissed_until( int|string $until ): void {
		$this->delete_dismiss();
		add_option( $this->get_dismiss_option_name(), $until, '', false );
	}

	/**
	 * Delete dismiss-marker.
	 *
	 * @return void
	 */
	public function delete_dismiss(): void {
		delete_option( $this->get_dismiss_option_name() );
	}

	/**
	 * Return the dismissible days.
	 *
	 * @return int
	 */
	public function get_dismissible_days(): int {
		return $this->dismissible_days;
	}

	/**
	 * Set the dismissible days.
	 *
	 * @param int $days The days for the dismissible-function.
	 *
	 * @return void
	 */
	public function set_dismissible_days( int $days ): void {
		$this->dismissible_days = $days;
	}

	/**
	 * Return the defined action for this transient.
	 *
	 * @return array<integer,string>
	 */
	private function get_action(): array {
		return $this->action;
	}

	/**
	 * Add an action to run. This is meant to be a callback as array like: array( 'class-name', 'function' );
	 *
	 * @param array<integer,string> $action The action as array.
	 * @return void
	 */
	public function set_action( array $action ): void {
		$this->action = $action;
	}

	/**
	 * Return whether this transient has a message set.
	 *
	 * @return bool
	 */
	private function has_message(): bool {
		return ! empty( $this->get_message() );
	}

	/**
	 * Return whether this transient has an action set.
	 *
	 * @return bool
	 */
	private function has_action(): bool {
		return ! empty( $this->get_action() );
	}

	/**
	 * Return the action of this transient if it could be called.
	 *
	 * @return callable|null
	 */
	private function get_callable_action(): ?callable {
		$action = $this->get_action();

		// the action must be an array with class and method.
		if ( ! isset( $action[0], $action[1] ) || 2 !== count( $action ) ) {
			return null;
		}

		// bail if it is not callable.
		if ( ! is_callable( $action ) || ! method_exists( $action[0], $action[1] ) ) {
			return null;
		}

		return $action;
	}

	/**
	 * Call the action of this transient.
	 *
	 * Errors in the action are caught and logged, so they do not break the actual request.
	 *
	 * @return bool True if action has been called.
	 */
	private function call_action(): bool {
		// get the action.
		$action = $this->get_callable_action();

		// bail if action is not callable.
		if ( is_null( $action ) ) {
			return false;
		}

		// run it.
		try {
			$action();
		} catch ( Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- we must log errors of actions somewhere.
			error_log( sprintf( 'easy-transients-for-wordpress: action of transient "%1$s" failed: %2$s in %3$s:%4$d', $this->get_name(), $e->getMessage(), $e->getFile(), $e->getLine() ) );
		}

		return true;
	}

	/**
	 * Hide this transient on specified pages (its URLs).
	 *
	 * @return array<int,string>
	 */
	public function get_hide_on(): array {
		return $this->hide_on;
	}

	/**
	 * Hide this transient on specified pages (its URLs).
	 *
	 * @param array<int,string> $hide_on List of URLs where this transient should not be visible.
	 *
	 * @return void
	 */
	public function set_hide_on( array $hide_on ): void {
		$this->hide_on = $hide_on;
	}

	/**
	 * Check if called URL is on list where this transient should not be visible.
	 *
	 * @return bool
	 */
	private function is_hidden(): bool {
		// bail if no URLs are configured.
		if ( empty( $this->get_hide_on() ) ) {
			return false;
		}

		// check each variant of the actual URL.
		foreach ( $this->get_current_urls() as $url ) {
			if ( in_array( $url, $this->get_hide_on(), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the possible variants of the current URL in frontend and backend.
	 *
	 * @return array<int,string>
	 */
	private function get_current_urls(): array {
		if ( ! empty( $_SERVER['REQUEST_URI'] ) && is_admin() ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- will be sanitized below depending on usage.
			$request_uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] );

			// list of URLs.
			$urls = array();

			// the complete URL of the actual backend page incl. query string.
			$uri        = esc_url_raw( $request_uri );
			$admin_path = (string) wp_parse_url( admin_url(), PHP_URL_PATH );
			if ( '' !== $admin_path && str_starts_with( $uri, $admin_path ) ) {
				$urls[] = admin_url( substr( $uri, strlen( $admin_path ) ) );
			}

			// the URL as detected by versions < 1.5.0 (for compatibility).
			$urls[] = admin_url( basename( sanitize_text_field( $request_uri ) ) );

			return array_values( array_unique( $urls ) );
		}

		// set return value for the page url.
		$page_url = '';

		// get actual object.
		$object = get_queried_object();
		if ( $object instanceof WP_Post_Type ) {
			$page_url = get_post_type_archive_link( $object->name );
		}
		if ( $object instanceof WP_Post ) {
			$page_url = get_permalink( $object->ID );
		}

		// return empty list if no URL could be loaded.
		if ( ! $page_url ) {
			return array();
		}

		// return result.
		return array( $page_url );
	}

	/**
	 * Return next transient name for the slider.
	 *
	 * @return string
	 */
	public function get_next(): string {
		return $this->next;
	}

	/**
	 * Set next transient name for the slider.
	 *
	 * @param string $next The next transient name.
	 *
	 * @return void
	 */
	public function set_next( string $next ): void {
		$this->next = $next;
	}

	/**
	 * Return prev transient name for the slider.
	 *
	 * @return string
	 */
	public function get_prev(): string {
		return $this->prev;
	}

	/**
	 * Set prev transient name for the slider.
	 *
	 * @param string $prev The prev transient name.
	 *
	 * @return void
	 */
	public function set_prev( string $prev ): void {
		$this->prev = $prev;
	}

	/**
	 * Return whether this transient is prioritized.
	 *
	 * @return bool
	 */
	public function is_prioritized(): bool {
		return $this->prioritized;
	}

	/**
	 * Set to prioritize this transient.
	 *
	 * @param bool $prioritized True if this transient should be prioritized.
	 *
	 * @return void
	 */
	public function set_prioritized( bool $prioritized ): void {
		$this->prioritized = $prioritized;
	}

	/**
	 * Run only the action.
	 *
	 * Only one request will run the action, even if multiple requests run at the same time.
	 * Any output of the action will be discarded.
	 *
	 * @return void
	 */
	public function run(): void {
		// bail if no action is set.
		if ( ! $this->has_action() ) {
			return;
		}

		// bail if action is not callable.
		if ( is_null( $this->get_callable_action() ) ) {
			// remove it in complete backend requests as it could never be run (e.g. class or method has been removed).
			if ( is_admin() && ! wp_doing_ajax() ) {
				$this->delete();
			}
			return;
		}

		// lock: only the request which removes the transient runs the action.
		if ( ! delete_transient( $this->get_name() ) ) {
			return;
		}

		// run the action and discard any output.
		$level = ob_get_level();
		ob_start();
		$this->call_action();
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}

		// remove the transient (also from our list) as action has been run.
		$this->delete();
	}
}
