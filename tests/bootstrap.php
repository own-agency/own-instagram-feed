<?php
/**
 * Minimal stand-ins for the few WordPress pieces the pure classes touch, so
 * tests run with plain `php tests/<Name>Test.php` — no WordPress, no PHPUnit.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

// Only from the command line: this file sits in a web-reachable folder.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );

function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
	return $text;
}

class WP_Error { // phpcs:ignore
	public function __construct( private string $code = '', private string $message = '', private $data = '' ) {}
	public function get_error_message(): string { return $this->message; }
	public function get_error_data() { return $this->data; }
	public function get_error_code(): string { return $this->code; }
}

function wp_json_encode( $data, int $flags = 0 ) { // phpcs:ignore
	return json_encode( $data, $flags );
}

function is_wp_error( $thing ): bool { // phpcs:ignore
	return $thing instanceof WP_Error;
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'OWN\\InstagramFeed\\';
		if ( 0 === strpos( $class_name, $prefix ) ) {
			$file = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	}
);

$GLOBALS['own_tests_failed'] = 0;

function check( string $label, bool $ok ): void { // phpcs:ignore
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . PHP_EOL;
	if ( ! $ok ) {
		++$GLOBALS['own_tests_failed'];
	}
}

function done(): void { // phpcs:ignore
	echo PHP_EOL . ( $GLOBALS['own_tests_failed'] ? $GLOBALS['own_tests_failed'] . ' FAILED' : 'ALL PASSED' ) . PHP_EOL;
	exit( $GLOBALS['own_tests_failed'] ? 1 : 0 );
}
