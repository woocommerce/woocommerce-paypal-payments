import { useMemo } from '@wordpress/element';
import { normalizeStyleForFundingSource } from '../../../../ppcp-button/resources/js/modules/Helper/Style';
import { PayPalButtons, PayPalScriptProvider } from '@paypal/react-paypal-js';

export const BlockEditorPayPalComponent = ( {
	config,
	fundingSource,
	buttonAttributes,
} ) => {
	const urlParams = useMemo(
		() => ( {
			clientId: 'test',
			...config.scriptData.url_params,
			dataNamespace: 'ppcp-blocks-editor-paypal-buttons',
			components: 'buttons',
		} ),
		[]
	);

	const style = useMemo( () => {
		const configStyle = normalizeStyleForFundingSource(
			config.scriptData.button.style,
			fundingSource
		);

		if ( buttonAttributes ) {
			return {
				...configStyle,
				height: buttonAttributes.height
					? Number( buttonAttributes.height )
					: configStyle.height,
				borderRadius: buttonAttributes.borderRadius
					? Number( buttonAttributes.borderRadius )
					: configStyle.borderRadius,
			};
		}

		return configStyle;
	}, [ fundingSource, buttonAttributes ] );

	return (
		<PayPalScriptProvider options={ urlParams }>
			<PayPalButtons
				className={ `ppc-button-container-${ fundingSource }` }
				fundingSource={ fundingSource }
				style={ style }
				forceReRender={ [ buttonAttributes || {} ] }
				onClick={ () => false }
			/>
		</PayPalScriptProvider>
	);
};
