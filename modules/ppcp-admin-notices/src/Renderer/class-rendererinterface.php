<?php
/**
 * The renderer interface.
 *
 * @package Inpsyde\PayPalCommerce\AdminNotices\Renderer
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices\Renderer;

/**
 * Interface RendererInterface
 */
interface RendererInterface {

	/**
	 * Renders the messages.
	 *
	 * @return bool
	 */
	public function render(): bool;
}
