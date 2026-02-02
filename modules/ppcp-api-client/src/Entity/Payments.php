<?php

/**
 * The Payments object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Payments
 */
class Payments
{
    /**
     * The Authorizations.
     *
     * @var Authorization[]
     */
    private $authorizations;
    /**
     * The Captures.
     *
     * @var Capture[]
     */
    private $captures;
    /**
     * The Refunds.
     *
     * @var Refund[]
     */
    private $refunds;
    /**
     * Payments constructor.
     *
     * @param array $authorizations The Authorizations.
     * @param array $captures The Captures.
     * @param array $refunds The Refunds.
     */
    public function __construct(array $authorizations, array $captures, array $refunds = array())
    {
        foreach ($authorizations as $key => $authorization) {
            if (is_a($authorization, \WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization::class)) {
                continue;
            }
            unset($authorizations[$key]);
        }
        foreach ($captures as $key => $capture) {
            if (is_a($capture, \WooCommerce\PayPalCommerce\ApiClient\Entity\Capture::class)) {
                continue;
            }
            unset($captures[$key]);
        }
        foreach ($refunds as $key => $refund) {
            if (is_a($refund, \WooCommerce\PayPalCommerce\ApiClient\Entity\Refund::class)) {
                continue;
            }
            unset($refunds[$key]);
        }
        $this->authorizations = $authorizations;
        $this->captures = $captures;
        $this->refunds = $refunds;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array('authorizations' => array_map(static function (\WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization $authorization): array {
            return $authorization->to_array();
        }, $this->authorizations()), 'captures' => array_map(static function (\WooCommerce\PayPalCommerce\ApiClient\Entity\Capture $capture): array {
            return $capture->to_array();
        }, $this->captures()), 'refunds' => array_map(static function (\WooCommerce\PayPalCommerce\ApiClient\Entity\Refund $refund): array {
            return $refund->to_array();
        }, $this->refunds()));
    }
    /**
     * Returns the Authoriatzions.
     *
     * @return Authorization[]
     **/
    public function authorizations(): array
    {
        return $this->authorizations;
    }
    /**
     * Returns the Captures.
     *
     * @return Capture[]
     **/
    public function captures(): array
    {
        return $this->captures;
    }
    /**
     * Returns the Refunds.
     *
     * @return Refund[]
     **/
    public function refunds(): array
    {
        return $this->refunds;
    }
}
