<?php
/**
 * Template for single view of transient.
 *
 * @version 1.0.0
 * @package easy-transients-for-wordpress
 */
?>
<div class="etfw-transient updated etfw-<?php echo esc_attr( $this->get_type() ); ?>" data-dismissible="<?php echo esc_attr( $this->get_name() ); ?>-<?php echo absint( $this->get_dismissible_days() ); ?>">
	<?php
	echo wp_kses_post( wpautop( $this->get_message() ) );
	if ( $this->get_dismissible_days() > 0 ) {
		$title = sprintf( $translations['hide_message'], $this->get_dismissible_days() );
		?>
		<button type="button" class="notice-dismiss" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $translations['dismiss'] ); ?><span class="screen-reader-text"><?php echo esc_html( $title ); ?></span></button>
		<?php
	}
	?>
</div>
<?php
