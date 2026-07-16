<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Helper;

/**
 * Stream wrapper stub that serves a preset body for php://input.
 *
 * RequestData::read_request() reads the request body via
 * file_get_contents( 'php://input' ), which cannot be populated in CLI PHPUnit.
 * Tests register this wrapper for the 'php' scheme only around handle_request()
 * (and restore the native wrapper in a finally block).
 *
 * Every stream_open() serves a fresh copy of self::$body — some endpoints read
 * php://input more than once per request (e.g. ChangeCartEndpoint::handle_data()
 * and AbstractCartEndpoint::products_from_request()).
 *
 * Any php:// path other than php://input fails loudly, so an unexpected
 * php://temp / php://memory consumer inside the dispatch window surfaces as a
 * diagnosable test failure instead of silent corruption.
 */
final class PhpInputStreamStub {

	/**
	 * The body served for php://input.
	 *
	 * @var string
	 */
	public static $body = '';

	/**
	 * The stream context (set by PHP, unused).
	 *
	 * @var resource|null
	 */
	public $context;

	/**
	 * The data served by this stream instance.
	 *
	 * @var string
	 */
	private $data = '';

	/**
	 * The current read position.
	 *
	 * @var int
	 */
	private $position = 0;

	/**
	 * Opens the stream.
	 *
	 * @param string $path The requested path.
	 * @param string $mode The open mode.
	 * @param int    $options Stream options.
	 * @param string $opened_path The opened path (by reference).
	 * @return bool
	 */
	public function stream_open( string $path, string $mode, int $options, ?string &$opened_path ): bool {
		if ( 'php://input' !== $path ) {
			trigger_error( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				'PhpInputStreamStub only supports php://input, got ' . $path,
				E_USER_WARNING
			);
			return false;
		}

		$this->data     = self::$body;
		$this->position = 0;

		return true;
	}

	/**
	 * Reads from the stream.
	 *
	 * @param int $count Number of bytes to read.
	 * @return string
	 */
	public function stream_read( int $count ): string {
		$chunk           = substr( $this->data, $this->position, $count );
		$this->position += strlen( $chunk );

		return $chunk;
	}

	/**
	 * Whether the end of the stream was reached.
	 *
	 * @return bool
	 */
	public function stream_eof(): bool {
		return $this->position >= strlen( $this->data );
	}

	/**
	 * Returns stream metadata (file_get_contents uses the size as a read hint).
	 *
	 * @return array
	 */
	public function stream_stat(): array {
		return array( 'size' => strlen( $this->data ) );
	}

	/**
	 * Closes the stream.
	 *
	 * @return void
	 */
	public function stream_close(): void {
	}
}
