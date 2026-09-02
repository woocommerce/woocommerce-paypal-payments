<?php
/**
 * Records a failure that a frontend handled and no server-side code ever saw.
 *
 * The caller names the tag its lines are grouped under, so one endpoint serves
 * every module's frontend rather than each growing its own.
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;

class FrontendLogEndpoint implements EndpointInterface {

	const ENDPOINT = 'ppc-frontend-log';

	private const MAX_VALUE_LENGTH = 500;

	private RequestData $request_data;
	private LoggerInterface $logger;

	public function __construct( RequestData $request_data, LoggerInterface $logger ) {
		$this->request_data = $request_data;
		$this->logger       = $logger;
	}

	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Logs one report at error level, since only failures are reported.
	 *
	 * Answers success even for an unusable payload: the caller reports a failure it
	 * has already handled, and a rejection it cannot act on would only be dropped.
	 */
	public function handle_request(): void {
		try {
			$data = $this->request_data->read_request( self::nonce() );

			$this->logger->error( $this->message( $data ) );

			wp_send_json_success( array( 'logged' => true ) );
		} catch ( NonceValidationException $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), 400 );
		}
	}

	/**
	 * Shaped `[tag] event key=value key=value`.
	 *
	 * @param array<string, mixed> $data The request data.
	 */
	private function message( array $data ): string {
		$tag   = $this->string_field( $data, 'tag' ) ?: 'frontend';
		$event = $this->string_field( $data, 'event' ) ?: 'unknown';
		$pairs = $this->context_pairs( $data );

		$line = sprintf( '[%1$s] %2$s', $tag, $event );

		if ( $pairs ) {
			$line .= ' ' . implode( ' ', $pairs );
		}

		return $line;
	}

	/**
	 * The reported context as `key=value` pairs, dropping unusable values.
	 *
	 * @param array<string, mixed> $data The request data.
	 * @return string[]
	 */
	private function context_pairs( array $data ): array {
		$posted = $data['context'] ?? array();

		if ( ! is_array( $posted ) ) {
			return array();
		}

		$pairs = array();

		foreach ( $posted as $key => $raw_value ) {
			if ( ! is_scalar( $raw_value ) ) {
				continue;
			}

			$value = substr( (string) $raw_value, 0, self::MAX_VALUE_LENGTH );

			if ( '' !== $value ) {
				$pairs[] = $key . '=' . $value;
			}
		}

		return $pairs;
	}

	/**
	 * A reported field as a string.
	 *
	 * Not sanitized here: RequestData already ran every key and value through
	 * sanitize_text_field, which also strips the newlines that would otherwise
	 * break one report into several log lines.
	 *
	 * @param array<string, mixed> $data The data to read from.
	 * @param string               $key  The field to read.
	 */
	private function string_field( array $data, string $key ): string {
		$value = $data[ $key ] ?? '';

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return (string) $value;
	}
}
