<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Util;

/**
 * Methods for classes which can translate.
 *
 * @since [*next-version*]
 */
trait StringTranslatingTrait
{
    /**
     * Translates a string, and replaces placeholders.
     *
     * The translation itself is delegated to another method.
     *
     * @see sprintf()
     * @see _translate()
     *
     * @param string       $string  The format string to translate.
     * @param list<scalar> $args    Placeholder values to replace in the string.
     * @param mixed        $context The context for translation.
     *
     * @return string The translated string.
     */
    protected function __(string $string, array $args = array(), $context = null): string
    {
        $string = $this->_translate($string, $context);
        array_unshift($args, $string);
        return call_user_func_array('sprintf', $args);
    }

    /**
     * Translates a string.
     *
     * A no-op implementation.
     *
     * @since [*next-version*]
     *
     * @param string $string The string to translate.
     * @param string $context The context to translate the string in.
     *
     * @return string The translated string.
     * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    protected function _translate(string $string, string $context = null): string
    {
        return $string;
    }
}
