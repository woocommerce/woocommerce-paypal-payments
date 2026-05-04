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
     * The processor response code (e.g. 9500, 9100).
     *
     * @var string
     */
    protected string $response_code;
    /**
     * FraudProcessorResponse constructor.
     *
     * @param string|null $avs_code The AVS response code.
     * @param string|null $cvv2_code The CVV response code.
     * @param string|null $response_code The processor response code.
     */
    public function __construct(?string $avs_code, ?string $cvv2_code, ?string $response_code = null)
    {
        $this->avs_code = (string) $avs_code;
        $this->cvv2_code = (string) $cvv2_code;
        $this->response_code = (string) $response_code;
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
     * Returns the processor response code.
     *
     * @return string
     */
    public function response_code(): string
    {
        return $this->response_code;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array('avs_code' => $this->avs_code(), 'cvv2_code' => $this->cvv_code(), 'response_code' => $this->response_code());
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
    /**
     * Returns the customer-facing decline message, including the processor response code when available.
     *
     * @return string
     */
    public function get_customer_decline_message(): string
    {
        if ($this->response_code()) {
            return sprintf(
                /* translators: %s - processor response code and description */
                __('Payment declined by card processor: %s. Please use a different payment method or contact your bank.', 'woocommerce-paypal-payments'),
                $this->get_response_code_message()
            );
        }
        return __('Payment provider declined the payment, please use a different payment method.', 'woocommerce-paypal-payments');
    }
    /**
     * Returns the human-readable description for the processor response code.
     *
     * @return string
     */
    public function get_response_code_message(): string
    {
        if (!$this->response_code()) {
            return '';
        }
        $messages = array('0000' => '0000: Approved', '00N7' => '00N7: Decline CVV2 Failure', '0100' => '0100: Refer to card issuer', '0390' => '0390: No credit account', '0500' => '0500: Do not honor', '0580' => '0580: Transaction not permitted to cardholder', '0800' => '0800: Bad response reversal amount', '0880' => '0880: Cryptographic failure', '0890' => '0890: Unavailable', '0960' => '0960: System malfunction', '1000' => '1000: Partial Approval', '10BR' => '10BR: 3DS Authentication Failure', '1300' => '1300: Invalid data format', '1310' => '1310: Invalid amount', '1312' => '1312: Invalid transaction card issuer acquirer', '1317' => '1317: Invalid capture date', '1320' => '1320: Invalid currency code', '1330' => '1330: Invalid account', '1335' => '1335: Invalid account type', '1340' => '1340: Invalid terminal id', '1350' => '1350: Invalid merchant/terminal city', '1360' => '1360: Bad or malformed request', '1370' => '1370: Issuer unavailable', '1380' => '1380: Updates not allowed', '1382' => '1382: Bad CVV2', '1384' => '1384: Similar transaction recently submitted', '1390' => '1390: Trace number error', '1393' => '1393: Transaction amount range error', '5100' => '5100: Generic Decline', '5110' => '5110: CVV2 Failure', '5120' => '5120: Insufficient funds', '5130' => '5130: Invalid PIN', '5140' => '5140: Card closed', '5150' => '5150: Pick up card (fraud)', '5160' => '5160: Unauthorized user', '5170' => '5170: Card blocked', '5180' => '5180: Declined by the issuer', '5200' => '5200: Account closed', '5400' => '5400: Expired card', '5910' => '5910: Issuer not available, return to issuer', '5920' => '5920: Issuer not available, return to issuer', '5930' => '5930: Card not activated', '6300' => '6300: Account blocked', '9100' => '9100: Declined, Please Retry', '9500' => '9500: Suspected Fraud', '9510' => '9510: Security Violation', '9520' => '9520: Lost or Stolen Card', '9530' => '9530: Hold - Call issuer', '9540' => '9540: Refused Card', '9600' => '9600: Unacceptable PIN - Transaction Declined - Retry', 'PCNF' => 'PCNF: Purchase Confirmation Not Received', 'PCOM' => 'PCOM: Purchase Confirmation Received');
        return $messages[$this->response_code()] ?? sprintf('%s: Unknown response code', $this->response_code());
    }
}
