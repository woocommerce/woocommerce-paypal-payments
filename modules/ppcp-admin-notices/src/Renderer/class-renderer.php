<?php
/**
 * The renderer.
 *
 * @package Inpsyde\PayPalCommerce\AdminNotices\Renderer
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices\Renderer;

use Inpsyde\PayPalCommerce\AdminNotices\Repository\RepositoryInterface;

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
				'<div class="notice notice-%s %s"><p>%s</p></div>',
				$message->type(),
				( $message->is_dismissable() ) ? 'is-dismissible' : '',
				wp_kses_post( $message->message() )
			);
		}

		return (bool) count( $messages );
	}
}
