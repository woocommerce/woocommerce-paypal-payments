<?php
/**
 * The renderer.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Renderer
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AdminNotices\Renderer;

use WooCommerce\PayPalCommerce\AdminNotices\Repository\RepositoryInterface;

/**
 * Class Renderer
 */
class Renderer implements RendererInterface {

	/**
	 * The message repository.
	 *
	 * @var RepositoryInterface
	 */
	private $repository;

	/**
	 * Renderer constructor.
	 *
	 * @param RepositoryInterface $repository The message repository.
	 */
	public function __construct( RepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Renders the current messages.
	 *
	 * @return bool
	 */
	public function render(): bool {
		$messages = $this->repository->current_message();
		foreach ( $messages as $message ) {
			printf(
				'<div class="notice notice-%s %s" %s><p>%s</p></div>',
				$message->type(),
				( $message->is_dismissable() ) ? 'is-dismissible' : '',
				( $message->wrapper() ? sprintf( 'data-ppcp-wrapper="%s"', esc_attr( $message->wrapper() ) ) : '' ),
				wp_kses_post( $message->message() )
			);
		}

		return (bool) count( $messages );
	}
}
