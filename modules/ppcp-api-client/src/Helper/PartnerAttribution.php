<?php

/**
 * PayPal Partner Attribution Helper.
 *
 * This class handles the retrieval and persistence of the BN (Build Notation) Code,
 * which is used to track and attribute transactions for PayPal partner integrations.
 *
 * The BN Code is set once and remains persistent, even after disconnecting
 * or uninstalling the plugin. It is determined based on the installation path
 * and stored as a WordPress option.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * PayPal Partner Attribution Helper.
 *
 * @psalm-type installationPath = string
 * @psalm-type bnCode = string
 */
class PartnerAttribution
{
    /**
     * The BN code option name in DB.
     *
     * @var string
     */
    protected string $bn_code_option_name;
    /**
     * BN Codes mapping for different installation paths.
     *
     * @var array<installationPath, bnCode>
     */
    protected array $bn_codes;
    /**
     * The default BN code.
     *
     * @var string
     */
    protected string $default_bn_code;
    /**
     * PartnerAttribution constructor.
     *
     * @param string                          $bn_code_option_name The BN code option name in DB.
     * @param array<installationPath, bnCode> $bn_codes BN Codes mapping for different installation paths.
     * @param string                          $default_bn_code The default BN code.
     */
    public function __construct(string $bn_code_option_name, array $bn_codes, string $default_bn_code)
    {
        $this->bn_code_option_name = $bn_code_option_name;
        $this->bn_codes = $bn_codes;
        $this->default_bn_code = $default_bn_code;
    }
    /**
     * Initializes or updates the BN Code.
     *
     * @param string $installation_path The installation path used to determine the BN Code.
     * @param bool   $force_update Whether to force an update of the BN code if it already exists.
     */
    public function initialize_bn_code(string $installation_path, bool $force_update = \false): void
    {
        $selected_bn_code = $this->bn_codes[$installation_path] ?? '';
        if (!$selected_bn_code) {
            return;
        }
        $existing_bn_code = get_option($this->bn_code_option_name);
        if ($existing_bn_code && !$force_update) {
            return;
        }
        update_option($this->bn_code_option_name, $selected_bn_code);
    }
    /**
     * Retrieves the persisted BN Code.
     *
     * @return string The stored BN Code, or the default value if no path is detected.
     */
    public function get_bn_code(): string
    {
        $bn_code = (string) (get_option($this->bn_code_option_name, $this->default_bn_code) ?? $this->default_bn_code);
        if (!in_array($bn_code, $this->bn_codes, \true)) {
            return $this->default_bn_code;
        }
        return $bn_code;
    }
}
