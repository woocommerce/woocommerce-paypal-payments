<?php

/**
 * The Singleton Trait can be used to wrap an execution block, so it behaves like a Singleton.
 * It executes the callable once, on subsequent calls returns the same result.
 */
namespace WooCommerce\PayPalCommerce\Common\Pattern;

/**
 * Class SingletonDecorator.
 */
class SingletonDecorator
{
    /**
     * The callable with the executing code
     *
     * @var callable
     */
    private $callable;
    /**
     * The execution result
     *
     * @var mixed
     */
    private $result;
    /**
     * Indicates if the callable is resolved
     *
     * @var bool
     */
    private $executed = \false;
    /**
     * SingletonDecorator constructor.
     *
     * @param callable $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }
    /**
     * The make constructor.
     *
     * @param callable $callable
     * @return self
     */
    public static function make(callable $callable): self
    {
        return new static($callable);
    }
    /**
     * Invokes a callable once and returns the same result on subsequent invokes.
     *
     * @param mixed ...$args Arguments to be passed to the callable.
     * @return mixed
     */
    public function __invoke(...$args)
    {
        if (!$this->executed) {
            $this->result = call_user_func_array($this->callable, $args);
            $this->executed = \true;
        }
        return $this->result;
    }
}
