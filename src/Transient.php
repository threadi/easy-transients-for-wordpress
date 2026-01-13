<?php
/**
 * This file contains the handling of a single transient in wp-admin.
 *
 * @package easy-transients-for-wordpress
 */

namespace easyTransientsForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

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
    private string $name;

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

        // bail if entry is empty.
        if ( empty( $entry ) || ! isset( $entry['message'] ) ) {
            return;
        }

        // get attributes from entry and set them in the object.
        $this->set_message( $entry['message'] );
        $this->set_type( $entry['type'] );
        $this->set_dismissible_days( $entry['dismissible_days'] );
        $this->set_action( $entry['action'] );
        $this->set_hide_on( ! empty( $entry['hide_on'] ) ? $entry['hide_on'] : array() );
        $this->set_prioritized( isset( $entry['prioritized'] ) ? $entry['prioritized'] : false );
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
        // save the internal name to our own list of transients.
        Transients::get_instance()->add_transient( $this );

        // save the transient itself in WP.
        set_transient( $this->get_name(), $this->get_entry() );
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
     * Output the content of this transient.
     *
     * @return void
     */
    public function display(): void {
        // check if this transient is dismissed.
        if ( false !== $this->is_dismissed() ) {
            return;
        }

        // bail if called URL is on hide-list.
        if ( $this->is_hidden() ) {
            return;
        }

        // get the translations.
        $translations = Transients::get_instance()->get_translations();

        // output, if message is given.
        if ( $this->has_message() ) {
            include Templates::get_instance()->get_template( Transients::get_instance()->get_template() );
        }

        // call action, if set.
        if ( $this->has_action() ) {
            // get the action.
            $action = $this->get_action();

            // check if it is callable and exist.
            if ( is_callable( $action ) && method_exists( $action[0], $action[1] ) ) {
                $action();
            }
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

        if ( $transients_obj->is_transient_set( $this->get_name() ) ) {
            // delete from our own list.
            Transients::get_instance()->delete_transient( $this );

            // delete from WP.
            delete_transient( $this->get_name() );
        }
    }

    /**
     * Return whether this transient is dismissed (true) or not (false).
     *
     * @return bool
     */
    public function is_dismissed(): bool {
        // get value from the cache, if set.
        $db_record = $this->get_admin_transient_dismiss_cache();

        // return bool depending on value.
        return 'forever' === $db_record || absint( $db_record ) >= time();
    }

    /**
     * Get transient-dismiss-cache.
     *
     * @return string|int|false
     */
    private function get_admin_transient_dismiss_cache(): string|int|false {
        $cache_key = Transients::get_instance()->get_slug() . '-dismissed-' . md5( $this->get_name() );
        $timeout   = get_option( $cache_key );
        $timeout   = 'forever' === $timeout ? time() + 60 : $timeout;

        if ( empty( $timeout ) || time() > $timeout ) {
            return false;
        }

        return $timeout;
    }

    /**
     * Add dismiss-marker with given dismiss length in days.
     *
     * @param int $dismissible_length Dismiss length in days.
     *
     * @return void
     */
    public function add_dismiss( int $dismissible_length ): void {
        if( ! $this->is_set() ) {
            return;
        }
        $this->delete_dismiss();
        add_option( Transients::get_instance()->get_slug() . '-dismissed-' . md5( $this->get_name() ), strtotime( absint( $dismissible_length ) . ' days' ), '', true );
    }

    /**
     * Delete dismiss-marker.
     *
     * @return void
     */
    public function delete_dismiss(): void {
        delete_option(Transients::get_instance()->get_slug() . '-dismissed-' . md5( $this->get_name() ) );
    }

    /**
     * Return the dismissible days.
     *
     * @return int
     */
    private function get_dismissible_days(): int {
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
        return in_array( $this->get_current_url(), $this->get_hide_on(), true );
    }

    /**
     * Get current URL in frontend and backend.
     *
     * @return string
     */
    private function get_current_url(): string {
        if ( ! empty( $_SERVER['REQUEST_URI'] ) && is_admin() ) {
            return admin_url( basename( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
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

        // return empty string if no URL could be loaded.
        if ( ! $page_url ) {
            return '';
        }

        // return result.
        return $page_url;
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
     * @return void
     */
    public function run(): void {
        // call action, if set.
        if ( $this->has_action() ) {
            // get the action.
            $action = $this->get_action();

            // check if it is callable and exist.
            if ( is_callable( $action ) && method_exists( $action[0], $action[1] ) ) {
                // run it.
                $action();

                // remove the transient as action has been run.
                $this->delete();
            }
        }
    }
}
