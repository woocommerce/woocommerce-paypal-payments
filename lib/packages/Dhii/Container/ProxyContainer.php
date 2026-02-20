<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\ContainerException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Util\StringTranslatingTrait;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as BaseContainerInterface;
/**
 * A proxy for another container, and nothing more.
 *
 * The advantage is that its setter can be used at any point after construction,
 * which solves the chicken-egg problem of two co-dependent containers.
 */
class ProxyContainer implements BaseContainerInterface
{
    use StringTranslatingTrait;
    /**
     * @var ?BaseContainerInterface
     */
    protected $innerContainer;
    /**
     * @param BaseContainerInterface|null $innerContainer The inner container, if any.
     *                                                    May also be set later with {@see setInnerContainer()}.
     */
    public function __construct(BaseContainerInterface $innerContainer = null)
    {
        $this->innerContainer = $innerContainer;
    }
    /**
     * @inheritDoc
     */
    public function get($key)
    {
        if (!$this->innerContainer instanceof BaseContainerInterface) {
            throw new ContainerException($this->__('Inner container not set'));
        }
        return $this->innerContainer->get($key);
    }
    /**
     * @inheritDoc
     */
    public function has($key)
    {
        if (!$this->innerContainer instanceof BaseContainerInterface) {
            /** @psalm-suppress MissingThrowsDocblock The exception class implements declared thrown interface */
            throw new ContainerException($this->__('Inner container not set'));
        }
        return $this->innerContainer->has($key);
    }
    /**
     * Assigns an inner container tot his proxy.
     *
     * Calls to `has()` and `get()` will be forwarded to this inner container.
     *
     * @param BaseContainerInterface $innerContainer The inner container to proxy.
     */
    public function setInnerContainer(BaseContainerInterface $innerContainer): void
    {
        $this->innerContainer = $innerContainer;
    }
}
