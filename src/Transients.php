<?php
/**
 * This file contains the handling of transients in wp-admin.
 *
 * @package easy-transients-for-wordpress
 */

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
    private string $template = 'single';

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

        // show the transients depending on configured display method.
        if( 'single' === $this->get_display_method() || 1 === count( $this->get_transients( true ) ) ) {
            $this->set_template( 'single.php' );
            Single::get_instance()->display();
        }
        else {
            Grouped::get_instance()->display();
        }
    }

    /**
     * Run the actions from transients.
     *
     * @return void
     */
    public function init_actions(): void {
        // get list of transients of this plugin.
        $transients = $this->get_transients();

        // loop through them and run only the action transients.
        foreach( $transients as $transient ) {
            if( ! empty( $transient->get_message() ) ) {
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
        // create new object and return it directly.
        return new Transient();
    }

    /**
     * Return all known transients of any plugin as objects.
     *
     * @param bool $only_with_text True to load only transients with messages.
     *
     * @return array<string,array<string,Transient>>
     */
    public function get_all_transients( bool $only_with_text = false ): array {
        // get list of our own transients from DB as array.
        $transients_from_db = get_option( 'etfw_transients', array() );
        if ( ! is_array( $transients_from_db ) ) {
            $transients_from_db = array();
        }

        // add plugin entry if it does not exist.
        if( ! isset( $transients_from_db[ $this->get_slug() ] ) ) {
            $transients_from_db[ $this->get_slug() ] = array();
        }

        // loop through the list and create the corresponding transient-objects for this project.
        foreach ( $transients_from_db[ $this->get_slug() ] as $index => $transient ) {
            if( is_string( $transient ) ) {
                // remove this entry.
                unset( $transients_from_db[ $this->get_slug() ][ $index ] );

                // create the object from setting.
                $transient = new Transient( $transient );
            }

            // bail if transient is not set.
            if ( ! $transient->is_set() || $transient->is_dismissed() ) {
                // remove this entry.
                unset( $transients_from_db[ $this->get_slug() ][ $index ] );

                // do not add this.
                continue;
            }

            // bail if transient has no text, if it is requested.
            if( $only_with_text && empty( $transient->get_message() ) ) {
                // remove this entry.
                unset( $transients_from_db[ $this->get_slug() ][ $index ] );

                // do not add this.
                continue;
            }

            // add object to list.
            $transients_from_db[ $this->get_slug() ][ $transient->get_name() ] = $transient;
        }

        // return the resulting list as array.
        return $transients_from_db;
    }

    /**
     * Return all known transients of this plugin as objects.
     *
     * @param bool $only_with_text True to load only transients with messages.
     *
     * @return array<string,Transient>
     */
    public function get_transients( bool $only_with_text = false ): array {
        // get all actual known transients as array.
        $transients = $this->get_all_transients( $only_with_text );

        // bail if no transients for this plugin are set.
        if( empty( $transients[ $this->get_slug() ] ) ) {
            return array();
        }

        // return the transients of this plugin.
        return $transients[ $this->get_slug() ];
    }

    /**
     * Check if a given transient is known to this handler.
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
     * Add new transient to list of our plugin-specific transients.
     *
     * @param Transient $transient_obj The transient-object to add.
     *
     * @return void
     */
    public function add_transient( Transient $transient_obj ): void {
        // get all actual known transients as array.
        $transients_from_db = $this->get_all_transients();

        // bail if transient is already on list.
        if ( ! empty( $transients_from_db[ $this->get_slug() ][ $transient_obj->get_name() ] ) ) {
            return;
        }

        // add plugin entry if not exist.
        if( ! isset( $transients_from_db[ $this->get_slug() ] ) ) {
            $transients_from_db[ $this->get_slug() ] = array();
        }

        // add the new one to the list.
        $transients_from_db[ $this->get_slug() ][ $transient_obj->get_name() ] = $transient_obj->get_name();

        // update the transients-list in db.
        update_option( 'etfw_transients', $transients_from_db );
    }

    /**
     * Delete single transient from our own list.
     *
     * @param Transient $transient_to_delete_obj The transient-object to delete.
     *
     * @return void
     */
    public function delete_transient( Transient $transient_to_delete_obj ): void {
        // get all actual known transients as array.
        $transients = $this->get_all_transients();

        // bail if transient is not in our list.
        if ( empty( $transients[ $this->get_slug() ][ $transient_to_delete_obj->get_name() ] ) ) {
            return;
        }

        // remove it from the list.
        unset( $transients[ $this->get_slug() ][ $transient_to_delete_obj->get_name() ] );

        // save the updated transients.
        update_option( 'etfw_transients', $transients );
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
     * @return void
     * @noinspection PhpUnused
     */
    public function dismiss_transient_via_ajax(): void {
        // check nonce.
        check_ajax_referer( 'etfw-dismiss-nonce', 'nonce' );

        // get values.
        $option_name        = filter_input( INPUT_POST, 'option_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $dismissible_length = filter_input( INPUT_POST, 'dismissible_length', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        if ( 'forever' !== $dismissible_length ) {
            // if $dismissible_length is not an integer default to 14.
            $dismissible_length = ( 0 === absint( $dismissible_length ) ) ? 14 : $dismissible_length;
            $dismissible_length = strtotime( absint( $dismissible_length ) . ' days' );
        }

        // save value.
        delete_option( $this->get_slug() . '-dismissed-' . md5( $option_name ) );
        add_option( $this->get_slug() . '-dismissed-' . md5( $option_name ), $dismissible_length, '', true );

        // remove transient.
        $this->get_transient_by_name( $option_name )->delete();

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
     * Add our scripts for the setup.
     *
     * @return void
     */
    public function add_scripts(): void {
        // add our script.
        wp_enqueue_script(
            'easy-transients-for-wordpress',
            $this->get_url() . 'Files/js.js',
            array( 'jquery' ),
            filemtime( $this->get_path() . 'Files/js.js' ),
            true
        );

        // embed the dialog-components CSS-script.
        wp_enqueue_style(
            'easy-transients-for-wordpress',
            $this->get_url() . 'Files/style.css',
            array(),
            filemtime( $this->get_path() . 'Files/style.css' )
        );

        // add php-vars to our js-script.
        wp_localize_script(
            'easy-transients-for-wordpress',
            'etfwJsVars',
            array(
                'ajax_url'                           => admin_url( 'admin-ajax.php' ),
                'dismiss_nonce'                      => wp_create_nonce( 'etfw-dismiss-nonce' ),
            )
        );
    }

    /**
     * Return the vendor path.
     *
     * @return string
     */
    public function get_vendor_path(): string {
        // return configured vendor path.
        if( ! empty( $this->vendor_path ) ) {
            return $this->vendor_path;
        }

        // detect vendor path.
        $path = str_replace('/threadi/easy-transients-for-wordpress/src', '', __DIR__ );
        return basename( $path );
    }

    /**
     * Set vendor path.
     *
     * @param string $vendor_path
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
     * Hint: must end with ".php".
     *
     * @param string $template The template to use.
     *
     * @return void
     */
    public function set_template( string $template ): void {
        // check for ending string.
        if( ! str_ends_with( $template, '.php' ) ) {
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
     * - single => every transient in single admin_notice via configured template, unsorted.
     * - grouped => all transient in one admin_notice as slider, sorted by errors first, success last.
     *
     * @param string $display_method The display method to use.
     *
     * @return void
     */
    public function set_display_method( string $display_method ): void {
        // bail if given display method is not supported.
        if( ! in_array( $display_method, array( 'single', 'grouped' ), true ) ) {
            return;
        }
        $this->display_method = $display_method;
    }
}
