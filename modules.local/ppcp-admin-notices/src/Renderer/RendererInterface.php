<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices\Renderer;


interface RendererInterface
{

    public function render() : bool;
}