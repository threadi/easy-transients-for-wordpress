jQuery(document).ready(function($) {
    /**
     * Save to hide transient-messages via AJAX-request.
     */
    $( 'div[data-dismissible] button.notice-dismiss' ).on( 'click',
        function (event) {
            event.preventDefault();
            let $this = $( this );
            let attr_value, option_name, dismissible_length, data;
            attr_value = $this.closest( 'div[data-dismissible]' ).attr( 'data-dismissible' ).split( '-' );

            // Remove the dismissible length from the attribute value and rejoin the array.
            dismissible_length = attr_value.pop();
            option_name = attr_value.join( '-' );
            data = {
                'action': 'efw_dismiss_admin_notice',
                'option_name': option_name,
                'dismissible_length': dismissible_length,
                'nonce': etfwJsVars.dismiss_nonce
            };

            // run ajax request to save this setting
            $.post( etfwJsVars.ajax_url, data );
            $this.closest( 'div[data-dismissible]' ).hide( 'slow', function() {
                // remove grouped if empty.
                if( $("#etfw-transients > div:visible").length === 0 ) {
                    $('#etfw-transients-grouped').remove();
                }
                // remove snapper if it is only 1.
                if( $("#etfw-transients > div:visible").length === 1 ) {
                    $('#etfw-transients-grouped .etfw-snapper').remove();
                    easy_transients_for_wordpress_set_height();
                }
            } );
        }
    );
    easy_transients_for_wordpress_set_height();
});

/**
 * Set height for transients depending on its contents for grouped views.
 */
function easy_transients_for_wordpress_set_height() {
    let height = 0;
    jQuery('#etfw-transients-grouped #etfw-transients > div:visible').each( function() {
        let inner_height = 0;
        jQuery(this).find('> *:not(.etfw-snapper)').each( function() {
            inner_height += jQuery(this).outerHeight( true );
        });
        if( inner_height > height ) {
            height = inner_height;
        }
    });
    jQuery('#etfw-transients-grouped').css( 'height', height );
}
