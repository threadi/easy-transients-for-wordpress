<?php
/**
 * File for handling grouped output of multiple transients.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Views;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyTransientsForWordPress\Transient;
use easyTransientsForWordPress\Transients;

/**
 * Object for handling single output of multiple transients.
 */
class Grouped {
	/**
	 * Instance of this object.
	 *
	 * @var ?Grouped
	 */
	private static ?Grouped $instance = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Grouped {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Show the transients.
	 *
	 * @return void
	 */
	public function display(): void {
		// get list of transients of this plugin.
		$transients = Transients::get_instance()->get_transients();

		// bail if list is empty.
		if ( empty( $transients ) ) {
			return;
		}

		// get the transients to show in the slider.
		$transient_list = array();
		foreach ( $transients as $transient_obj ) {
			// bail if transient is not our own one.
			if ( ! $transient_obj instanceof Transient ) {
				continue;
			}

			// handle transients which are not visible as slide (e.g. action-only-transients) as before, outside the slider.
			if ( ! $transient_obj->is_visible() ) {
				$transient_obj->display();
				continue;
			}

			$transient_list[] = $transient_obj;
		}

		// bail if no transients are visible.
		if ( empty( $transient_list ) ) {
			return;
		}

		// sort the transients: prioritized first, then by their types (error > success).
		usort( $transient_list, array( $this, 'sort_by_type' ) );

		// get prev and next values (circular).
		$prev  = array();
		$next  = array();
		$count = count( $transient_list );
		foreach ( $transient_list as $index => $transient_obj ) {
			$prev[ $transient_obj->get_name() ] = $transient_list[ ( $index - 1 + $count ) % $count ]->get_name();
			$next[ $transient_obj->get_name() ] = $transient_list[ ( $index + 1 ) % $count ]->get_name();
		}

		?>
		<div id="etfw-transients-grouped-<?php echo esc_attr( sanitize_html_class( Transients::get_instance()->get_slug() ) ); ?>" class="notice etfw-transients-grouped">
			<div class="etfw-transients">
				<?php

				// show the transients.
				foreach ( $transient_list as $transient_obj ) {
					// set prev and next (necessary for the slider).
					$transient_obj->set_prev( $prev[ $transient_obj->get_name() ] );
					$transient_obj->set_next( $next[ $transient_obj->get_name() ] );

					// show this transient hint.
					$transient_obj->display();
				}

				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Return mapping of states for sorting.
	 *
	 * @return array<string,int>
	 */
	private function type_map(): array {
		return array(
			'error'       => 10,
			'warning'     => 15,
			'success'     => 20,
			'updated'     => 20,
			'hint'        => 30,
			'info'        => 40,
			'notice-info' => 40,
		);
	}

	/**
	 * Return the sort weight of a given type. Unknown types will be sorted at the end.
	 *
	 * @param string $type The type.
	 *
	 * @return int
	 */
	private function get_type_weight( string $type ): int {
		$state_mapping = $this->type_map();
		return $state_mapping[ $type ] ?? 100;
	}

	/**
	 * Sort the list of transients: prioritized first, then by type (first error, then success).
	 *
	 * Transients with the same weight keep their order.
	 *
	 * @param Transient $a The first transient to compare.
	 * @param Transient $b The second transient to compare.
	 *
	 * @return int
	 */
	public function sort_by_type( Transient $a, Transient $b ): int {
		if ( $a->is_prioritized() !== $b->is_prioritized() ) {
			return $a->is_prioritized() ? -1 : 1;
		}
		return $this->get_type_weight( $a->get_type() ) <=> $this->get_type_weight( $b->get_type() );
	}
}
