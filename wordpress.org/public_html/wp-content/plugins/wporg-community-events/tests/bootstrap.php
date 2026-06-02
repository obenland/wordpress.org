<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events\Tests;

if ( 'cli' !== php_sapi_name() ) {
	return;
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$src_position = stripos( __FILE__, '/src/wp-content/plugins/' );

	if ( false !== $src_position ) {
		$_tests_dir = substr( __FILE__, 0, $src_position ) . '/tests/phpunit/';
	}
}

if ( ! $_tests_dir && file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
	$_tests_dir = '/wordpress-phpunit/';
} elseif ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit/';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	exit( 1 );
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/wporg-community-events.php';
}
tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

register_phpunit_class_aliases();

/**
 * Register aliases for PHPUnit's filename-derived class names.
 *
 * PHPUnit 9 derives the expected test class from the PHP filename. WPCS expects
 * files like `class-registration-test.php`, while the matching class should be
 * `Registration_Test`. This keeps each test file conventionally named without
 * requiring file-local aliases.
 */
function register_phpunit_class_aliases(): void {
	foreach ( get_phpunit_test_files() as $test_file ) {
		$phpunit_class = basename( $test_file, '.php' );
		$test_class    = test_class_name_from_file( $test_file );

		require_once $test_file;

		if ( class_exists( __NAMESPACE__ . '\\' . $test_class, false ) && ! class_exists( $phpunit_class, false ) ) {
			class_alias( __NAMESPACE__ . '\\' . $test_class, $phpunit_class );
		}
	}
}

/**
 * Get WPCS-style PHPUnit test files.
 *
 * @return string[]
 */
function get_phpunit_test_files(): array {
	$test_files = array();
	$iterator   = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( __DIR__, \FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file_info ) {
		if ( ! $file_info->isFile() ) {
			continue;
		}

		if ( 1 === preg_match( '/^class-.+-test\.php$/', $file_info->getFilename() ) ) {
			$test_files[] = $file_info->getPathname();
		}
	}

	sort( $test_files );

	return $test_files;
}

/**
 * Convert a WPCS test filename to a test class short name.
 *
 * @param string $test_file Test file path.
 *
 * @return string
 */
function test_class_name_from_file( string $test_file ): string {
	$base_name = basename( $test_file, '.php' );
	$base_name = preg_replace( '/^class-(.+)-test$/', '$1-test', $base_name );
	$parts     = explode( '-', $base_name );
	$parts     = array_map( 'ucfirst', $parts );

	return implode( '_', $parts );
}
