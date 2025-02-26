import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

import { OptionSelector } from '../../../ReusableComponents/Fields';
import { OnboardingHooks, PRODUCT_TYPES } from '../../../../data';
import OnboardingHeader from '../Components/OnboardingHeader';

const StepProducts = () => {
	const { products, setProducts } = OnboardingHooks.useProducts();
	const { canUseSubscriptions } = OnboardingHooks.useFlags();
	const [ optionState, setOptionState ] = useState( null );
	const [ productChoices, setProductChoices ] = useState( [] );
	const { isCasualSeller } = OnboardingHooks.useBusiness();

	useEffect( () => {
		const initChoices = () => {
			const choices = productChoicesFull.map( ( choice ) => {
				if (
					choice.value === PRODUCT_TYPES.SUBSCRIPTIONS &&
					! canUseSubscriptions
				) {
					return {
						...choice,
						isDisabled: true,
						contents: (
							<DetailsSubscriptions
								showLink={ true }
								showNotice={ isCasualSeller }
							/>
						),
					};
				}
				return choice;
			} );

			setProductChoices( choices );
			setOptionState( canUseSubscriptions );
		};

		initChoices();
	}, [ canUseSubscriptions, optionState, products, setProducts ] );

	const handleChange = ( key, checked ) => {
		const getNewValue = () => {
			if ( checked ) {
				return [ ...products, key ];
			}
			return products.filter( ( val ) => val !== key );
		};

		setProducts( getNewValue() );
	};
	const productChoicesFull = [
		{
			value: PRODUCT_TYPES.VIRTUAL,
			title: __( 'Virtual', 'woocommerce-paypal-payments' ),
			description: __(
				'Items do not require shipping.',
				'woocommerce-paypal-payments'
			),
			contents: <DetailsVirtual />,
		},
		{
			value: PRODUCT_TYPES.PHYSICAL,
			title: __( 'Physical Goods', 'woocommerce-paypal-payments' ),
			description: __(
				'Items require shipping.',
				'woocommerce-paypal-payments'
			),
			contents: <DetailsPhysical />,
		},
		{
			value: PRODUCT_TYPES.SUBSCRIPTIONS,
			title: __( 'Subscriptions', 'woocommerce-paypal-payments' ),
			description: __(
				'Recurring payments for either physical goods or services.',
				'woocommerce-paypal-payments'
			),
			isDisabled: isCasualSeller,
			contents: (
				/*
				 * Note: The link should be only displayed if the subscriptions plugin is not installed.
				 * But when the plugin is not active, this option is completely hidden;
				 * This means: In the current configuration, we never show the link.
				 */
				<DetailsSubscriptions
					showLink={ false }
					showNotice={ isCasualSeller }
				/>
			),
		},
	];
	return (
		<div className="ppcp-r-page-products">
			<OnboardingHeader
				title={ __(
					'Tell us about the products you sell',
					'woocommerce-paypal-payments'
				) }
			/>
			<div className="ppcp-r-inner-container">
				<OptionSelector
					multiSelect={ true }
					options={ productChoices }
					onChange={ handleChange }
					value={ products }
				/>
			</div>
		</div>
	);
};

export default StepProducts;

const DetailsVirtual = () => (
	<ul className="ppcp-r-services">
		<li>{ __( 'Services', 'woocommerce-paypal-payments' ) }</li>
		<li>{ __( 'Downloadable', 'woocommerce-paypal-payments' ) }</li>
		<li>{ __( 'Bookings', 'woocommerce-paypal-payments' ) }</li>
		<li>{ __( 'Deposits', 'woocommerce-paypal-payments' ) }</li>
	</ul>
);

const DetailsPhysical = () => (
	<ul className="ppcp-r-services">
		<li>{ __( 'Goods', 'woocommerce-paypal-payments' ) }</li>
		<li>{ __( 'Deliveries', 'woocommerce-paypal-payments' ) }</li>
	</ul>
);

const DetailsSubscriptions = ( { showLink, showNotice } ) => (
	<>
		{ showLink && (
			<p
				dangerouslySetInnerHTML={ {
					__html: sprintf(
						/* translators: %s is the URL to the WooCommerce Subscriptions product page */
						__(
							'* To use subscriptions, you must have <a target="_blank" href="%s">WooCommerce Subscriptions</a> enabled.',
							'woocommerce-paypal-payments'
						),
						'https://woocommerce.com/products/woocommerce-subscriptions/'
					),
				} }
			/>
		) }
		{ showNotice && (
			<p>
				{ __(
					'* Business account is required for subscriptions.',
					'woocommerce-paypal-payments'
				) }
			</p>
		) }
	</>
);
