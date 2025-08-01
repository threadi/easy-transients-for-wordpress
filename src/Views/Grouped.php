<?php
/**
 * File for handling grouped output of multiple transients.
 *
 * @package easy-transients-for-wordpress
 */

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
		// set other template
		Transients::get_instance()->set_template( 'grouped.php' );

		// get list of transients of this plugin.
		$transients = Transients::get_instance()->get_transients();

        // bail if list is empty.
        if( empty( $transients ) ) {
            return;
        }

		// sort the transients: primary by their types (error > success).
		usort( $transients, array( $this, 'sort_by_type' ) );

		// first loop to get prev and next values.
		$prev = array();
		$next = array();
		$last = $transients[array_key_first( $transients )]->get_name();
		$first = $transients[array_key_first( $transients )]->get_name();
		$counter = 0;
		foreach ( $transients as $transient_obj ) {
			// bail if transient is not set.
			if ( ! $transient_obj->is_set() || $transient_obj->is_dismissed() ) {
				continue;
			}

			// save the last entry as prev for this object.
			if( $last !== $transient_obj->get_name() ) {
				$prev[ $transient_obj->get_name() ] = $last;
			}

			// save this entry as next for the last object.
			$next[ $last ] = $transient_obj->get_name();

			// remember this entry.
			$last = $transient_obj->get_name();

			// update counter.
			$counter++;
		}

		// bail if no transients are visible.
		if( 0 === $counter ) {
			return;
		}

		$next[ $last ] = $first;
		$prev[ $first ] = $last;

		?>
			<div id="etfw-transients-grouped" class="notice">
				<div id="etfw-transients">
					<?php

					// check for active transients and show them.
					foreach ( $transients as $transient_obj ) {
						// bail if transient is not set.
						if ( ! $transient_obj->is_set() ) {
							continue;
						}

						// set prev and next (necessary for slider).
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
			'error' => 1,
			'success' => 2
		);
	}

	/**
	 * Sort the list of transients by type (first error, then success).
	 *
	 * @param Transient $a The first transient to compare.
	 * @param Transient $b The second transient to compare.
	 *
	 * @return bool
	 */
	public function sort_by_type( Transient $a, Transient $b ): bool {
		if( $a->is_prioritized() ) {
			return false;
		}
		if( $b->is_prioritized() ) {
			return true;
		}
		$state_mapping = $this->type_map();
		return $state_mapping[$a->get_type()] > $state_mapping[$b->get_type()];
	}
}
