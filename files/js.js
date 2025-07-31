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
      $this.closest( 'div[data-dismissible]' ).hide( 'slow' );
    }
  );

  /**
   * Set height of grouped transients.
   */
  let height = 0;
  $('#etfw-transients-grouped #etfw-transients > div').each( function() {
    let inner_height = 0;
    $(this).find('> *:not(.etfw-snapper)').each( function() {
      inner_height += $(this).height();
    });
    if( inner_height > height ) {
      height = inner_height;
    }
  });
  height += 24;
  if (navigator.userAgent.includes("Chrome")) {
    height += 18;
  }
  $('#etfw-transients-grouped').css( 'height', height );
});
