import { PayPalButtons, PayPalScriptProvider } from '@paypal/react-paypal-js';
import { STYLING_PAYMENT_METHODS, StylingHooks } from '../../../../../data';
import { useMemo } from '@wordpress/element';

const PREVIEW_CLIENT_ID = 'test';
const PREVIEW_MERCHANT_ID = 'QTQX5NP6N9WZU';

const PreviewPanel = ( { location } ) => {
	const { paymentMethods } = StylingHooks.usePaymentMethodProps( location );
	const { layout } = StylingHooks.useLayoutProps( location );
	const { shape } = StylingHooks.useShapeProps( location );
	const { label } = StylingHooks.useLabelProps( location );
	const { color } = StylingHooks.useColorProps( location );
	const { tagline } = StylingHooks.useTaglineProps( location );

	const style = useMemo(
		() => ( {
			layout,
			shape,
			label,
			color,
			tagline,
		} ),
		[ color, label, layout, shape, tagline ]
	);

	const disableFunding = useMemo( () => {
		const disabled = [ 'card' ];
		Object.values( STYLING_PAYMENT_METHODS )
			.filter( ( method ) => method.isFunding )
			.filter( ( method ) => ! paymentMethods.includes( method.value ) )
			.forEach( ( method ) => {
				disabled.push( method.value );
			} );
		return disabled;
	}, [ paymentMethods ] );

	// TODO: Changes in the providerOptions are not reflected on the page.
	const providerOptions = useMemo(
		() => ( {
			clientId: PREVIEW_CLIENT_ID,
			merchantId: PREVIEW_MERCHANT_ID,
			components: 'buttons',
			'disable-funding': disableFunding.join( ',' ),
			'buyer-country': 'US', // Todo: simulate shop country here?
			currency: 'USD',
		} ),
		[ disableFunding ]
	);

	return (
		<div className="preview-panel">
			<div className="preview-panel-inner">
				<PayPalScriptProvider options={ providerOptions }>
					<PayPalButtons style={ style } forceReRender={ [ style ] }>
						Error
					</PayPalButtons>
				</PayPalScriptProvider>
			</div>
		</div>
	);
};

export default PreviewPanel;
