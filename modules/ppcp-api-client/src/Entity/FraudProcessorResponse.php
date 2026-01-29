<?php

/**
 * The FraudProcessorResponse object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class FraudProcessorResponse
 */
class FraudProcessorResponse
{
    /**
     * The AVS response code.
     *
     * @var string
     */
    protected string $avs_code;
    /**
     * The CVV response code.
     *
     * @var string
     */
    protected string $cvv2_code;
    /**
     * FraudProcessorResponse constructor.
     *
     * @param string|null $avs_code The AVS response code.
     * @param string|null $cvv2_code The CVV response code.
     */
    public function __construct(?string $avs_code, ?string $cvv2_code)
    {
        $this->avs_code = (string) $avs_code;
        $this->cvv2_code = (string) $cvv2_code;
    }
    /**
     * Returns the AVS response code.
     *
     * @return string
     */
    public function avs_code(): string
    {
        return $this->avs_code;
    }
    /**
     * Returns the CVV response code.
     *
     * @return string
     */
    public function cvv_code(): string
    {
        return $this->cvv2_code;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array(
            'avs_code' => $this->avs_code(),
            'cvv2_code' => $this->cvv_code(),
            // For backwards compatibility.
            'address_match' => $this->avs_code() === 'M' ? 'Y' : 'N',
            'postal_match' => $this->avs_code() === 'M' ? 'Y' : 'N',
            'cvv_match' => $this->cvv_code() === 'M' ? 'Y' : 'N',
        );
    }
    /**
     * Retrieves the AVS (Address Verification System) code messages based on the AVS response code.
     *
     * Provides human-readable descriptions for various AVS response codes
     * and returns the corresponding message for the given code.
     *
     * @return string The AVS response code message. If the code is not found, an error message is returned.
     */
    public function get_avs_code_message(): string
    {
        if (!$this->avs_code()) {
            return '';
        }
        $messages = array(
            /* Visa, Mastercard, Discover, American Express */
            'A' => 'A: Address - Address only (no ZIP code)',
            'B' => 'B: International "A" - Address only (no ZIP code)',
            'C' => 'C: International "N" - None. The transaction is declined.',
            'D' => 'D: International "X" - Address and Postal Code',
            'E' => 'E: Not allowed for MOTO (Internet/Phone) transactions - Not applicable. The transaction is declined.',
            'F' => 'F: UK-specific "X" - Address and Postal Code',
            'G' => 'G: Global Unavailable - Not applicable',
            'I' => 'I: International Unavailable - Not applicable',
            'M' => 'M: Address - Address and Postal Code',
            'N' => 'N: No - None. The transaction is declined.',
            'P' => 'P: Postal (International "Z") - Postal Code only (no Address)',
            'R' => 'R: Retry - Not applicable',
            'S' => 'S: Service not Supported - Not applicable',
            'U' => 'U: Unavailable / Address not checked, or acquirer had no response. Service not available.',
            'W' => 'W: Whole ZIP - Nine-digit ZIP code (no Address)',
            'X' => 'X: Exact match - Address and nine-digit ZIP code)',
            'Y' => 'Y: Yes - Address and five-digit ZIP',
            'Z' => 'Z: ZIP - Five-digit ZIP code (no Address)',
            /* Maestro */
            '0' => '0: All the address information matched.',
            '1' => '1: None of the address information matched. The transaction is declined.',
            '2' => '2: Part of the address information matched.',
            '3' => '3: The merchant did not provide AVS information. Not processed.',
            '4' => '4: Address not checked, or acquirer had no response. Service not available.',
        );
        /**
         * Psalm suppress
         *
         * @psalm-suppress PossiblyNullArrayOffset
         * @psalm-suppress PossiblyNullArgument
         */
        return $messages[$this->avs_code()] ?? sprintf('%s: Error', $this->avs_code());
    }
    /**
     * Retrieves the CVV2 code message based on the CVV code provided.
     *
     * This method maps CVV response codes to their corresponding descriptive messages.
     *
     * @return string The descriptive message corresponding to the CVV2 code, or a formatted error message if the code is unrecognized.
     */
    public function get_cvv2_code_message(): string
    {
        if (!$this->cvv_code()) {
            return '';
        }
        $messages = array(
            /* Visa, Mastercard, Discover, American Express */
            'E' => 'E: Error - Unrecognized or Unknown response',
            'I' => 'I: Invalid or Null',
            'M' => 'M: Match or CSC',
            'N' => 'N: No match',
            'P' => 'P: Not processed',
            'S' => 'S: Service not supported',
            'U' => 'U: Unknown - Issuer is not certified',
            'X' => 'X: No response / Service not available',
            /* Maestro */
            '0' => '0: Matched CVV2',
            '1' => '1: No match',
            '2' => '2: The merchant has not implemented CVV2 code handling',
            '3' => '3: Merchant has indicated that CVV2 is not present on card',
            '4' => '4: Service not available',
        );
        /**
         * Psalm suppress
         *
         * @psalm-suppress PossiblyNullArrayOffset
         * @psalm-suppress PossiblyNullArgument
         */
        return $messages[$this->cvv_code()] ?? sprintf('%s: Error', $this->cvv_code());
    }
}
