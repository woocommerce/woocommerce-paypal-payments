<?php
/**
 * Minimal stub for the WooCommerce Blueprint StepProcessorResult class.
 *
 * The Blueprint package ships with WooCommerce core and is not autoloadable in
 * the unit-test environment. Mirrors the copy WooCommerce actually loads
 * (packages/blueprint), which is ahead of the one under vendor/woocommerce.
 */

namespace Automattic\WooCommerce\Blueprint;

if ( ! class_exists( StepProcessorResult::class ) ) {
	class StepProcessorResult {

		/**
		 * Accumulated messages.
		 *
		 * @var array<int, array{message: string, type: string}>
		 */
		private $messages = array();

		/**
		 * Whether the step succeeded.
		 *
		 * @var bool
		 */
		private $success;

		/**
		 * Step name.
		 *
		 * @var string
		 */
		private $step_name;

		/**
		 * Constructor.
		 *
		 * @param bool   $success   Whether the step succeeded.
		 * @param string $step_name Step name.
		 */
		public function __construct( bool $success, string $step_name ) {
			$this->success   = $success;
			$this->step_name = $step_name;
		}

		/**
		 * Creates a successful result.
		 *
		 * @param string $step_name Step name.
		 * @return self
		 */
		public static function success( string $step_name ): self {
			return new self( true, $step_name );
		}

		/**
		 * Records a message.
		 *
		 * @param string $message Message text.
		 * @param string $type    Message type.
		 * @return void
		 */
		public function add_message( string $message, string $type = 'error' ) {
			$this->messages[] = array(
				'message' => $message,
				'type'    => $type,
			);

			if ( 'error' === $type ) {
				$this->success = false;
			}
		}

		/**
		 * Records an error.
		 *
		 * @param string $message Message text.
		 * @return void
		 */
		public function add_error( string $message ) {
			$this->add_message( $message, 'error' );
		}

		/**
		 * Records a warning.
		 *
		 * @param string $message Message text.
		 * @return void
		 */
		public function add_warn( string $message ) {
			$this->add_message( $message, 'warn' );
		}

		/**
		 * Records an info message.
		 *
		 * @param string $message Message text.
		 * @return void
		 */
		public function add_info( string $message ) {
			$this->add_message( $message, 'info' );
		}

		/**
		 * Records a debug message.
		 *
		 * @param string $message Message text.
		 * @return void
		 */
		public function add_debug( string $message ) {
			$this->add_message( $message, 'debug' );
		}

		/**
		 * Returns recorded messages, optionally filtered by type.
		 *
		 * @param string $type Message type, or 'all'.
		 * @return array<int, array{message: string, type: string}>
		 */
		public function get_messages( string $type = 'all' ): array {
			if ( 'all' === $type ) {
				return $this->messages;
			}

			return array_values(
				array_filter(
					$this->messages,
					function ( $message ) use ( $type ) {
						return $type === $message['type'];
					}
				)
			);
		}

		/**
		 * Whether the step succeeded.
		 *
		 * @return bool
		 */
		public function is_success(): bool {
			return $this->success;
		}

		/**
		 * Returns the step name.
		 *
		 * @return string
		 */
		public function get_step_name() {
			return $this->step_name;
		}
	}
}
