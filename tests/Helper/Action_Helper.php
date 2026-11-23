<?php
/**
 * File with callbacks for action-transients in tests.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Helper;

use RuntimeException;

/**
 * Callbacks for action-transients.
 */
class Action_Helper {
	/**
	 * Counter for calls.
	 *
	 * @var int
	 */
	public static int $runs = 0;

	/**
	 * Reset the counter.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$runs = 0;
	}

	/**
	 * Action which produces output.
	 *
	 * @return void
	 */
	public static function run_with_output(): void {
		++self::$runs;
		echo 'ACTION-OUTPUT';
	}

	/**
	 * Action which throws an exception.
	 *
	 * @return void
	 */
	public static function run_with_exception(): void {
		++self::$runs;
		throw new RuntimeException( 'Action failed in test' );
	}

	/**
	 * A non-static method, which could not be used as action.
	 *
	 * @return void
	 */
	public function not_static(): void {
		++self::$runs;
	}
}
