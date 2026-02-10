/**
 * Validation Testing Panel
 *
 * Provides quick actions to test validation scenarios and error flows.
 */

export default function ValidationTestPanel({ onCreateTestCart, loading, pluginScenarios = [] }) {
	const testScenarios = [
		{
			id: 'missing-shipping-address',
			name: 'Missing Shipping Address',
			description: 'Tests ShippingValidator - MISSING_SHIPPING_ADDRESS (no address provided)',
			items: [
				{
					variant_id: '1017',
					quantity: 1,
					name: 'US Product',
					price: { currency_code: 'USD', value: '25.00' }
				}
			]
			// No shipping_address field - triggers MISSING_SHIPPING_ADDRESS
		},
		{
			id: 'out-of-stock',
			name: 'Out of Stock',
			description: 'Tests InventoryValidator - ItemOutOfStock (ID 1005)',
			items: [
				{
					variant_id: '1005',
					quantity: 1,
					name: 'Out of Stock Product',
					price: { currency_code: 'USD', value: '99.99' }
				}
			]
		},
		{
			id: 'insufficient-stock',
			name: 'Insufficient Stock',
			description: 'Tests InventoryValidator - InsufficientQuantity (ID 1015, has 1000 in stock)',
			items: [
				{
					variant_id: '1015',
					quantity: 1500,
					name: 'Wholesale T-Shirts',
					price: { currency_code: 'USD', value: '8.50' }
				}
			]
		},
		{
			id: 'missing-product',
			name: 'Product Not Found',
			description: 'Tests ProductValidator - InvalidProduct (non-existent ID)',
			items: [
				{
					variant_id: '99999',
					quantity: 1,
					name: 'Non-existent Product',
					price: { currency_code: 'USD', value: '50.00' }
				}
			]
		},
		{
			id: 'not-purchasable',
			name: 'Not Purchasable Product',
			description: 'Tests ProductValidator - product in draft status (ID 1007)',
			items: [
				{
					variant_id: '1007',
					quantity: 1,
					name: 'Discontinued Product',
					price: { currency_code: 'USD', value: '149.99' }
				}
			]
		},
		{
			id: 'missing-address',
			name: 'Missing City in Address',
			description: 'Tests ShippingValidator - InvalidAddress (missing admin_area_2)',
			items: [
				{
					variant_id: '1017',
					quantity: 1,
					name: 'US Product',
					price: { currency_code: 'USD', value: '25.00' }
				}
			],
			shipping_address: {
				address_line_1: '123 Main St',
				// Missing admin_area_2 (city) - required field
				admin_area_1: 'CA',
				postal_code: '95131',
				country_code: 'US'
			}
		},
		{
			id: 'currency-mismatch',
			name: 'Currency Mismatch',
			description: 'Tests CurrencyValidator - EUR vs store USD',
			items: [
				{
					variant_id: '1017',
					quantity: 1,
					name: 'US Product',
					price: { currency_code: 'EUR', value: '25.00' }
				}
			]
		},
		{
			id: 'multiple-issues',
			name: 'Multiple Validation Issues',
			description: 'Tests multiple validators - out of stock + currency mismatch + missing address',
			items: [
				{
					variant_id: '1005',
					quantity: 1,
					name: 'Out of Stock Product',
					price: { currency_code: 'EUR', value: '99.99' }
				},
				{
					variant_id: '1015',
					quantity: 2000,
					name: 'Wholesale T-Shirts',
					price: { currency_code: 'USD', value: '8.50' }
				}
			],
			shipping_address: {
				address_line_1: '123 Main St',
				// Missing admin_area_2 (city)
				admin_area_1: 'CA',
				postal_code: '95131',
				country_code: 'US'
			}
		},
		{
			id: 'valid-coupon',
			name: 'Valid Coupon (SAVE10)',
			description: 'Tests CouponValidator - valid 10% coupon applied successfully',
			items: [
				{
					variant_id: '1017',
					quantity: 1,
					name: 'US Product',
					price: { currency_code: 'USD', value: '25.00' }
				}
			],
			shipping_address: {
				address_line_1: '123 Main St',
				admin_area_2: 'San Jose',
				admin_area_1: 'CA',
				postal_code: '95131',
				country_code: 'US'
			},
			coupons: [
				{ code: 'SAVE10', action: 'APPLY' }
			]
		},
		{
			id: 'expired-coupon',
			name: 'Expired Coupon (EXPIRED2023)',
			description: 'Tests CouponValidator - expired coupon triggers validation error',
			items: [
				{
					variant_id: '1017',
					quantity: 1,
					name: 'US Product',
					price: { currency_code: 'USD', value: '25.00' }
				}
			],
			shipping_address: {
				address_line_1: '123 Main St',
				admin_area_2: 'San Jose',
				admin_area_1: 'CA',
				postal_code: '95131',
				country_code: 'US'
			},
			coupons: [
				{ code: 'EXPIRED2023', action: 'APPLY' }
			]
		},
		{
			id: 'usage-limit-coupon',
			name: 'Usage Limit Exceeded (LIMITREACHED)',
			description: 'Tests CouponValidator - coupon usage limit already reached',
			items: [
				{
					variant_id: '1017',
					quantity: 1,
					name: 'US Product',
					price: { currency_code: 'USD', value: '25.00' }
				}
			],
			shipping_address: {
				address_line_1: '123 Main St',
				admin_area_2: 'San Jose',
				admin_area_1: 'CA',
				postal_code: '95131',
				country_code: 'US'
			},
			coupons: [
				{ code: 'LIMITREACHED', action: 'APPLY' }
			]
		}
	];

	const allScenarios = [ ...testScenarios, ...pluginScenarios ];

	return (
		<details className="ppcp-validation-test-section">
			<summary>Validation Test Scenarios</summary>
			<table className="wc_status_table widefat">
				<thead>
					<tr>
						<th>Scenario</th>
						<th>Description</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					{allScenarios.map(scenario => (
						<tr key={scenario.id}>
							<td><strong>{scenario.name}</strong></td>
							<td>{scenario.description}</td>
							<td className="ppcp-controls-col">
								<button
									className="button button-small"
									onClick={() => onCreateTestCart(scenario)}
									disabled={loading}
								>
									Test
								</button>
							</td>
						</tr>
					))}
				</tbody>
			</table>
		</details>
	);
}
