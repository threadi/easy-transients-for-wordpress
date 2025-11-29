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
                // if it is only 1 move transient out of grouped.
                if( $("#etfw-transients > div:visible").length === 1 ) {
                    $("#etfw-transients > div:visible").insertAfter( $('#etfw-transients-grouped') );
                    $('#etfw-transients-grouped, .etfw-snapper').remove();
                }
            } );
        }
    );
});
