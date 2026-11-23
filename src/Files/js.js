jQuery( function ( $ ) {
    'use strict';

    // bail if our vars are missing.
    if ( typeof etfwJsVars === 'undefined' ) {
        return;
    }

    /**
     * Let the prev/next-links of a grouped container point only to visible entries.
     *
     * @param {jQuery} $group The grouped container.
     */
    function etfw_update_snapper( $group ) {
        let $slides = $group.find( '[data-dismissible]' ).filter( ':visible' );
        let count = $slides.length;
        $slides.each( function ( index ) {
            let $prev = $slides.eq( ( index - 1 + count ) % count );
            let $next = $slides.eq( ( index + 1 ) % count );
            $( this ).find( '.etfw-snapper-prev' ).attr( 'href', '#' + $prev.attr( 'id' ) );
            $( this ).find( '.etfw-snapper-next' ).attr( 'href', '#' + $next.attr( 'id' ) );
        } );
    }

    /**
     * Hide a dismissed notice and clean up its grouped container.
     *
     * @param {jQuery} $notice The notice.
     */
    function etfw_hide_notice( $notice ) {
        // get the grouped container (also supports the markup of versions < 1.5.0).
        let $group = $notice.closest( '.etfw-transients-grouped, #etfw-transients-grouped' );

        $notice.hide( 'slow', function () {
            // bail if notice is not grouped.
            if ( 0 === $group.length ) {
                return;
            }

            let $visible = $group.find( '[data-dismissible]' ).filter( ':visible' );

            // remove grouped if empty.
            if ( 0 === $visible.length ) {
                $group.remove();
                return;
            }

            // if it is only 1 move transient out of grouped.
            if ( 1 === $visible.length ) {
                $visible.find( '.etfw-snapper' ).remove();
                $visible.insertAfter( $group );
                $group.remove();
                return;
            }

            // update the slider navigation.
            etfw_update_snapper( $group );
        } );
    }

    /**
     * Show an error in the notice.
     *
     * @param {jQuery} $notice The notice.
     */
    function etfw_show_error( $notice ) {
        // bail if error is already shown.
        if ( $notice.find( '.etfw-dismiss-error' ).length > 0 ) {
            return;
        }

        let message = etfwJsVars.dismiss_error ? etfwJsVars.dismiss_error : 'The message could not be hidden. Please reload the page and try again.';
        $( '<p class="etfw-dismiss-error"></p>' ).text( message ).appendTo( $notice );
    }

    /**
     * Save to hide transient-messages via AJAX-request.
     *
     * Only for notices of this library (class "etfw-transient"), not for other notices with "data-dismissible".
     */
    $( document ).off( 'click.etfw' ).on( 'click.etfw', '.etfw-transient[data-dismissible] .notice-dismiss', function ( event ) {
        event.preventDefault();

        let $notice = $( this ).closest( '[data-dismissible]' );

        // bail if request is already running for this notice.
        if ( $notice.data( 'etfwDismissing' ) ) {
            return;
        }
        $notice.data( 'etfwDismissing', true );

        // Remove the dismissible length from the attribute value and rejoin the array.
        let attr_value = String( $notice.attr( 'data-dismissible' ) ).split( '-' );
        let dismissible_length = attr_value.pop();
        let option_name = attr_value.join( '-' );

        // run ajax request to save this setting.
        $.post( etfwJsVars.ajax_url, {
            'action': 'efw_dismiss_admin_notice',
            'option_name': option_name,
            'dismissible_length': dismissible_length,
            'nonce': etfwJsVars.dismiss_nonce
        } )
            .done( function ( response ) {
                // show error if the request has been rejected.
                // Hint: the response "0" means that no project knows this transient (anymore), so it could be hidden.
                if ( response && 'object' === typeof response && false === response.success ) {
                    etfw_show_error( $notice );
                    return;
                }
                etfw_hide_notice( $notice );
            } )
            .fail( function () {
                etfw_show_error( $notice );
            } )
            .always( function () {
                $notice.data( 'etfwDismissing', false );
            } );
    } );
} );
