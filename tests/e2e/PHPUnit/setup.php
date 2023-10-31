<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$options = [
	'woocommerce_calc_taxes' => 'yes',
	'woocommerce_prices_include_tax' => 'yes',
	'woocommerce_tax_based_on' => 'billing',
	'woocommerce_shipping_tax_class' => 'inherit',
	'woocommerce_tax_round_at_subtotal' => 'no',
];

foreach ($options as $key => $value) {
	echo "Setting $key to $value." . PHP_EOL;
	update_option($key, $value);
}

echo 'Deleting test taxes.' . PHP_EOL;

$taxRates = WC_Tax::get_rates_for_tax_class('');
$testTaxRates = array_filter($taxRates, function ($taxRate): bool {
	return str_contains($taxRate->tax_rate_name, '[PPCP TEST]');
});
foreach ($testTaxRates as $rate) {
	WC_Tax::_delete_tax_rate($rate->tax_rate_id);
}

echo 'Importing test taxes.' . PHP_EOL;

require WP_ROOT_DIR . '/wp-admin/includes/class-wp-importer.php';
require WP_ROOT_DIR . '/wp-content/plugins/woocommerce/includes/admin/importers/class-wc-tax-rate-importer.php';

$taxImporter = new WC_Tax_Rate_Importer();
$taxImporter->import(E2E_TESTS_ROOT_DIR . '/data/tax_rates.csv');

echo PHP_EOL;
