import OnboardingHeader from '../../ReusableComponents/OnboardingHeader';
import Navigation from '../../ReusableComponents/Navigation';
import { __ } from '@wordpress/i18n';
import SelectBox from '../../ReusableComponents/SelectBox';
import SelectBoxWrapper from '../../ReusableComponents/SelectBoxWrapper';
import { useState } from '@wordpress/element';

const StepProducts = ( { setStep, currentStep, stepperOrder } ) => {
	const [ products, setProducts ] = useState( [] );
	const PRODUCTS_CHECKBOX_GROUP_NAME = 'products';
	const VIRTUAL_CHECKBOX_VALUE = 'virtual';
	const PHYSICAL_CHECKBOX_VALUE = 'physical';
	const SUBSCRIPTIONS_CHECKBOX_VALUE = 'subscriptions';

	return (
		<div className="ppcp-r-page-products">
			<OnboardingHeader
				title={ __(
					'Tell Us About the Products You Sell',
					'woocommerce-paypal-payments'
				) }
			/>
			<div className="ppcp-r-inner-container">
				<SelectBoxWrapper>
					<SelectBox
						title={ __( 'Virtual', 'woocommerce-paypal-payments' ) }
						description={ __(
							'Digital items or services that don’t require shipping.',
							'woocommerce-paypal-payments'
						) }
						icon="icon-product-virtual.svg"
						name={ PRODUCTS_CHECKBOX_GROUP_NAME }
						value={ VIRTUAL_CHECKBOX_VALUE }
						changeCallback={ setProducts }
						currentValue={ products }
						type="checkbox"
					>
						<ul className="ppcp-r-services">
							<li>
								{ __(
									'Services',
									'woocommerce-paypal-payments'
								) }
							</li>
							<li>
								{ __(
									'Downloadable',
									'woocommerce-paypal-payments'
								) }
							</li>
							<li>
								{ __(
									'Bookings',
									'woocommerce-paypal-payments'
								) }
							</li>
							<li>
								{ __(
									'Deposits',
									'woocommerce-paypal-payments'
								) }
							</li>
						</ul>
					</SelectBox>
					<SelectBox
						title={ __(
							'Physical Goods',
							'woocommerce-paypal-payments'
						) }
						description={ __(
							'Items that need to be shipped.',
							'woocommerce-paypal-payments'
						) }
						icon="icon-product-physical.svg"
						name={ PRODUCTS_CHECKBOX_GROUP_NAME }
						value={ PHYSICAL_CHECKBOX_VALUE }
						changeCallback={ setProducts }
						currentValue={ products }
						type="checkbox"
					>
						<ul className="ppcp-r-services">
							<li>
								{ __( 'Goods', 'woocommerce-paypal-payments' ) }
							</li>
							<li>
								{ __(
									'Deliveries',
									'woocommerce-paypal-payments'
								) }
							</li>
						</ul>
					</SelectBox>
					<SelectBox
						title={ __(
							'Subscriptions',
							'woocommerce-paypal-payments'
						) }
						description={ __(
							'Recurring payments for physical goods or services.',
							'woocommerce-paypal-payments'
						) }
						icon="icon-product-subscription.svg"
						name={ PRODUCTS_CHECKBOX_GROUP_NAME }
						value={ SUBSCRIPTIONS_CHECKBOX_VALUE }
						changeCallback={ setProducts }
						currentValue={ products }
						type="checkbox"
					>
						<a href="#">
							{ __(
								'WooCommerce Subscriptions - TODO missing link',
								'woocommerce-paypal-payments'
							) }
						</a>
					</SelectBox>
				</SelectBoxWrapper>
				<Navigation
					setStep={ setStep }
					currentStep={ currentStep }
					stepperOrder={ stepperOrder }
					canProceeedCallback={ () => products.length > 0 }
				/>
			</div>
		</div>
	);
};

export default StepProducts;
