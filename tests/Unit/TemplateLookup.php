<?php
/**
 * Tests for the lookup of templates.
 *
 * @package easy-transients-for-wordpress
 */

declare(strict_types=1);

namespace easyTransientsForWordPress\Tests\Unit;

use easyTransientsForWordPress\Templates;
use easyTransientsForWordPress\Transients;
use easyTransientsForWordPress\Tests\easyTransientsForWordPressTest;

/**
 * Object for tests of the template lookup.
 */
class TemplateLookup extends easyTransientsForWordPressTest {
	/**
	 * Temporary directory for this test.
	 *
	 * @var string
	 */
	private string $tmp_dir = '';

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->tmp_dir = trailingslashit( sys_get_temp_dir() ) . 'etfw-templates-' . wp_generate_password( 8, false );
		wp_mkdir_p( $this->tmp_dir );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->remove_directory( $this->tmp_dir );

		parent::tear_down();
	}

	/**
	 * Remove a directory recursively.
	 *
	 * @param string $dir The directory.
	 *
	 * @return void
	 */
	private function remove_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $files as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $dir );
	}

	/**
	 * Create a template file.
	 *
	 * @param string $path The relative path in the temporary directory.
	 * @param string $content The output of the template.
	 *
	 * @return string The absolute path.
	 */
	private function create_template( string $path, string $content ): string {
		$file = $this->tmp_dir . '/' . $path;
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, '<?php echo ' . var_export( $content, true ) . ';' ); // phpcs:ignore
		return $file;
	}

	/**
	 * The templates of this library are found.
	 *
	 * @return void
	 */
	public function test_own_templates(): void {
		$templates_obj = Templates::get_instance();
		$base          = dirname( __DIR__, 2 ) . '/src/Templates/';

		$this->assertSame( $base . 'single.php', $templates_obj->get_template( 'single.php' ) );
		$this->assertSame( $base . 'grouped.php', $templates_obj->get_template( 'grouped.php' ) );
	}

	/**
	 * Invalid or missing templates return an empty string.
	 *
	 * @return void
	 */
	public function test_invalid_templates(): void {
		$templates_obj = Templates::get_instance();

		$this->assertSame( '', $templates_obj->get_template( '' ) );
		$this->assertSame( '', $templates_obj->get_template( 'single.txt' ) );
		$this->assertSame( '', $templates_obj->get_template( '../Transients.php' ) );
		$this->assertSame( '', $templates_obj->get_template( 'does-not-exist.php' ) );
	}

	/**
	 * Templates in the project which uses the library (configured vendor path).
	 *
	 * @return void
	 */
	public function test_project_template(): void {
		wp_mkdir_p( $this->tmp_dir . '/plugin/vendor' );
		$file = $this->create_template( 'plugin/templates/vendor/threadi/easy-transients-for-wordpress/templates/single.php', 'PROJECT-TEMPLATE' );

		// with and without trailing slash.
		Transients::get_instance()->set_vendor_path( $this->tmp_dir . '/plugin/vendor/' );
		$this->assertSame( realpath( $file ), realpath( Templates::get_instance()->get_template( 'single.php' ) ) );

		Transients::get_instance()->set_vendor_path( $this->tmp_dir . '/plugin/vendor' );
		$this->assertSame( realpath( $file ), realpath( Templates::get_instance()->get_template( 'single.php' ) ) );

		// used for output.
		$this->create_transient( 'etfw_template', 'Message', 'info', 14 );
		$this->assertStringContainsString( 'PROJECT-TEMPLATE', $this->render_notices() );
	}

	/**
	 * Templates in the theme.
	 *
	 * @return void
	 */
	public function test_theme_template(): void {
		$theme_dir = $this->tmp_dir . '/theme';
		$file      = $this->create_template( 'theme/' . self::$slug . '/vendor/threadi/easy-transients-for-wordpress/templates/single.php', 'THEME-TEMPLATE' );

		// a file in "src/" of the theme is not used anymore (versions < 1.5.0 used it by mistake).
		$this->create_template( 'theme/src/single.php', 'THEME-SRC' );

		$callback = static function () use ( $theme_dir ) {
			return $theme_dir;
		};
		add_filter( 'stylesheet_directory', $callback );
		add_filter( 'template_directory', $callback );

		$this->assertSame( $file, Templates::get_instance()->get_template( 'single.php' ) );
	}

	/**
	 * The filter for the template path.
	 *
	 * @return void
	 */
	public function test_template_filter(): void {
		$file = $this->create_template( 'filtered.php', 'FILTERED' );

		add_filter(
			'etfw_' . self::$slug . '_template',
			static function ( string $path, string $template ) use ( $file ) {
				return 'single.php' === $template ? $file : $path;
			},
			10,
			2
		);

		$this->assertSame( $file, Templates::get_instance()->get_template( 'single.php' ) );
		$this->assertStringEndsWith( 'src/Templates/grouped.php', Templates::get_instance()->get_template( 'grouped.php' ) );
	}
}
