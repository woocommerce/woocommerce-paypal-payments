<?php

/**
 * The DCC Applies helper checks if the current installation can use DCC or not.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * Class DccApplies
 */
class DccApplies
{
    /**
     * The matrix which countries and currency combinations can be used for DCC.
     *
     * @var array
     */
    private $allowed_country_currency_matrix;
    /**
     * Which countries support which credit cards. Empty credit card arrays mean no restriction on
     * currency.
     *
     * @var array
     */
    private $country_card_matrix;
    /**
     * The getter of the 3-letter currency code of the shop.
     *
     * @var CurrencyGetter
     */
    private \WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter $currency;
    /**
     * 2-letter country code of the shop.
     *
     * @var string
     */
    private $country;
    /**
     * DccApplies constructor.
     *
     * @param array          $allowed_country_currency_matrix The matrix which countries and currency combinations can be used for DCC.
     * @param array          $country_card_matrix Which countries support which credit cards. Empty credit card arrays mean no restriction on
     *          currency.
     * @param CurrencyGetter $currency The getter of the 3-letter currency code of the shop.
     * @param string         $country 2-letter country code of the shop.
     */
    public function __construct(array $allowed_country_currency_matrix, array $country_card_matrix, \WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter $currency, string $country)
    {
        $this->allowed_country_currency_matrix = $allowed_country_currency_matrix;
        $this->country_card_matrix = $country_card_matrix;
        $this->currency = $currency;
        $this->country = $country;
    }
    /**
     * Returns whether DCC can be used in the current country and the current currency used.
     *
     * @return bool
     */
    public function for_country_currency(): bool
    {
        if (!in_array($this->country, array_keys($this->allowed_country_currency_matrix), \true)) {
            return \false;
        }
        $applies = in_array($this->currency->get(), $this->allowed_country_currency_matrix[$this->country], \true);
        return $applies;
    }
    /**
     * Returns whether WooCommerce Payments plugin is available for the store country.
     *
     * @return bool
     */
    public function for_wc_payments(): bool
    {
        $countries = array_keys($this->allowed_country_currency_matrix);
        array_push($countries, 'AT', 'BE', 'HK', 'IE', 'NL', 'PL', 'PT', 'SG', 'CH');
        return in_array($this->country, $countries, \true);
    }
    /**
     * Returns credit cards, which can be used.
     *
     * @return array
     */
    public function valid_cards(): array
    {
        $cards = array();
        if (!isset($this->country_card_matrix[$this->country])) {
            return $cards;
        }
        $supported_currencies = $this->country_card_matrix[$this->country];
        foreach ($supported_currencies as $card => $currencies) {
            if ($this->can_process_card($card)) {
                $cards[] = $card;
            }
        }
        if (in_array('amex', $cards, \true)) {
            $cards[] = 'american-express';
        }
        if (in_array('mastercard', $cards, \true)) {
            $cards[] = 'master-card';
        }
        return $cards;
    }
    /**
     * Whether a card can be used or not.
     *
     * @param string $card The card.
     *
     * @return bool
     */
    public function can_process_card(string $card): bool
    {
        if (!isset($this->country_card_matrix[$this->country])) {
            return \false;
        }
        if (!isset($this->country_card_matrix[$this->country][$card])) {
            return \false;
        }
        /**
         * If the supported currencies array is empty, there are no
         * restrictions, which currencies are supported by a card.
         */
        $supported_currencies = $this->country_card_matrix[$this->country][$card];
        return empty($supported_currencies) || in_array($this->currency->get(), $supported_currencies, \true);
    }
}
