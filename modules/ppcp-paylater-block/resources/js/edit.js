import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { PayPalScriptProvider, PayPalMessages } from '@paypal/react-paypal-js';
import { useScriptParams } from './hooks/script-params';

export default function Edit( { attributes, clientId, setAttributes } ) {
	const {
		layout,
		logo,
		position,
		color,
		size,
		flexColor,
		flexRatio,
		placement,
		id,
	} = attributes;
	const isFlex = layout === 'flex';

	const [ loaded, setLoaded ] = useState( false );

	let amount;
	const postContent = String(
		wp.data.select( 'core/editor' )?.getEditedPostContent()
	);
	if (
		postContent.includes( 'woocommerce/checkout' ) ||
		postContent.includes( 'woocommerce/cart' )
	) {
		amount = 50.0;
	}

	const previewStyle = {
		layout,
		logo: {
			position,
			type: logo,
		},
		color: flexColor,
		ratio: flexRatio,
		text: {
			color,
			size,
		},
	};

	const classes = [ 'ppcp-paylater-block-preview', 'ppcp-overlay-parent' ];
	if (
		PcpPayLaterBlock.vaultingEnabled ||
		! PcpPayLaterBlock.placementEnabled
	) {
		classes.push( 'ppcp-paylater-unavailable', 'block-editor-warning' );
	}
	const props = useBlockProps( { className: classes.join( ' ' ) } );

	useEffect( () => {
		if ( ! id ) {
			setAttributes( { id: `ppcp-${ clientId }` } );
		}
	}, [ id, clientId ] );

	if ( PcpPayLaterBlock.vaultingEnabled ) {
		return (
			<div { ...props }>
				<div className="block-editor-warning__contents">
					<p className="block-editor-warning__message">
						{ __(
							'Pay Later Messaging cannot be used while PayPal Vaulting is active. Disable PayPal Vaulting in the PayPal Payment settings to reactivate this block',
							'woocommerce-paypal-payments'
						) }
					</p>
					<div className="block-editor-warning__actions">
						<span className="block-editor-warning__action">
							<a href={ PcpPayLaterBlock.payLaterSettingsUrl }>
								<button
									type="button"
									className="components-button is-primary"
								>
									{ __(
										'PayPal Payments Settings',
										'woocommerce-paypal-payments'
									) }
								</button>
							</a>
						</span>
						<span className="block-editor-warning__action">
							<button
								onClick={ () =>
									wp.data
										.dispatch( 'core/block-editor' )
										.removeBlock( clientId )
								}
								type="button"
								className="components-button is-secondary"
							>
								{ __(
									'Remove Block',
									'woocommerce-paypal-payments'
								) }
							</button>
						</span>
					</div>
				</div>
			</div>
		);
	}

	if ( ! PcpPayLaterBlock.placementEnabled ) {
		return (
			<div { ...props }>
				<div className="block-editor-warning__contents">
					<p className="block-editor-warning__message">
						{ __(
							'Pay Later Messaging cannot be used while the “WooCommerce Block” messaging placement is disabled. Enable the placement in the PayPal Payments Pay Later settings to reactivate this block.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<div className="block-editor-warning__actions">
						<span className="block-editor-warning__action">
							<a href={ PcpPayLaterBlock.payLaterSettingsUrl }>
								<button
									type="button"
									className="components-button is-primary"
								>
									{ __(
										'PayPal Payments Settings',
										'woocommerce-paypal-payments'
									) }
								</button>
							</a>
						</span>
						<span className="block-editor-warning__action">
							<button
								onClick={ () =>
									wp.data
										.dispatch( 'core/block-editor' )
										.removeBlock( clientId )
								}
								type="button"
								className="components-button is-secondary"
							>
								{ __(
									'Remove Block',
									'woocommerce-paypal-payments'
								) }
							</button>
						</span>
					</div>
				</div>
			</div>
		);
	}

	const scriptParams = useScriptParams(
		PcpPayLaterBlock.ajax.cart_script_params
	);

	if ( scriptParams === null ) {
		return (
			<div { ...props }>
				<Spinner />
			</div>
		);
	}

	const urlParams = {
		...scriptParams.url_params,
		components: 'messages',
		dataNamespace: 'ppcp-block-editor-paylater-message',
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Settings', 'woocommerce-paypal-payments' ) }
				>
					<SelectControl
						label={ __( 'Layout', 'woocommerce-paypal-payments' ) }
						options={ [
							{
								label: __(
									'Text',
									'woocommerce-paypal-payments'
								),
								value: 'text',
							},
							{
								label: __(
									'Banner',
									'woocommerce-paypal-payments'
								),
								value: 'flex',
							},
						] }
						value={ layout }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					{ ! isFlex && (
						<SelectControl
							label={ __(
								'Logo',
								'woocommerce-paypal-payments'
							) }
							options={ [
								{
									label: __(
										'Full logo',
										'woocommerce-paypal-payments'
									),
									value: 'primary',
								},
								{
									label: __(
										'Monogram',
										'woocommerce-paypal-payments'
									),
									value: 'alternative',
								},
								{
									label: __(
										'Inline',
										'woocommerce-paypal-payments'
									),
									value: 'inline',
								},
								{
									label: __(
										'Message only',
										'woocommerce-paypal-payments'
									),
									value: 'none',
								},
							] }
							value={ logo }
							onChange={ ( value ) =>
								setAttributes( { logo: value } )
							}
						/>
					) }
					{ ! isFlex && logo === 'primary' && (
						<SelectControl
							label={ __(
								'Logo Position',
								'woocommerce-paypal-payments'
							) }
							options={ [
								{
									label: __(
										'Left',
										'woocommerce-paypal-payments'
									),
									value: 'left',
								},
								{
									label: __(
										'Right',
										'woocommerce-paypal-payments'
									),
									value: 'right',
								},
								{
									label: __(
										'Top',
										'woocommerce-paypal-payments'
									),
									value: 'top',
								},
							] }
							value={ position }
							onChange={ ( value ) =>
								setAttributes( { position: value } )
							}
						/>
					) }
					{ ! isFlex && (
						<SelectControl
							label={ __(
								'Text Color',
								'woocommerce-paypal-payments'
							) }
							options={ [
								{
									label: __(
										'Black / Blue logo',
										'woocommerce-paypal-payments'
									),
									value: 'black',
								},
								{
									label: __(
										'White / White logo',
										'woocommerce-paypal-payments'
									),
									value: 'white',
								},
								{
									label: __(
										'Monochrome',
										'woocommerce-paypal-payments'
									),
									value: 'monochrome',
								},
								{
									label: __(
										'Black / Gray logo',
										'woocommerce-paypal-payments'
									),
									value: 'grayscale',
								},
							] }
							value={ color }
							onChange={ ( value ) =>
								setAttributes( { color: value } )
							}
						/>
					) }
					{ ! isFlex && (
						<SelectControl
							label={ __(
								'Text Size',
								'woocommerce-paypal-payments'
							) }
							options={ [
								{
									label: __(
										'Small',
										'woocommerce-paypal-payments'
									),
									value: '12',
								},
								{
									label: __(
										'Medium',
										'woocommerce-paypal-payments'
									),
									value: '14',
								},
								{
									label: __(
										'Large',
										'woocommerce-paypal-payments'
									),
									value: '16',
								},
							] }
							value={ size }
							onChange={ ( value ) =>
								setAttributes( { size: value } )
							}
						/>
					) }
					{ isFlex && (
						<SelectControl
							label={ __(
								'Color',
								'woocommerce-paypal-payments'
							) }
							options={ [
								{
									label: __(
										'Blue',
										'woocommerce-paypal-payments'
									),
									value: 'blue',
								},
								{
									label: __(
										'Black',
										'woocommerce-paypal-payments'
									),
									value: 'black',
								},
								{
									label: __(
										'White',
										'woocommerce-paypal-payments'
									),
									value: 'white',
								},
								{
									label: __(
										'White (no border)',
										'woocommerce-paypal-payments'
									),
									value: 'white-no-border',
								},
							] }
							value={ flexColor }
							onChange={ ( value ) =>
								setAttributes( { flexColor: value } )
							}
						/>
					) }
					{ isFlex && (
						<SelectControl
							label={ __(
								'Ratio',
								'woocommerce-paypal-payments'
							) }
							options={ [
								{
									label: __(
										'8x1',
										'woocommerce-paypal-payments'
									),
									value: '8x1',
								},
								{
									label: __(
										'20x1',
										'woocommerce-paypal-payments'
									),
									value: '20x1',
								},
							] }
							value={ flexRatio }
							onChange={ ( value ) =>
								setAttributes( { flexRatio: value } )
							}
						/>
					) }
					<SelectControl
						label={ __(
							'Placement page',
							'woocommerce-paypal-payments'
						) }
						help={ __(
							'Used for the analytics dashboard in the merchant account.',
							'woocommerce-paypal-payments'
						) }
						options={ [
							{
								label: __(
									'Detect automatically',
									'woocommerce-paypal-payments'
								),
								value: 'auto',
							},
							{
								label: __(
									'Product Page',
									'woocommerce-paypal-payments'
								),
								value: 'product',
							},
							{
								label: __(
									'Cart',
									'woocommerce-paypal-payments'
								),
								value: 'cart',
							},
							{
								label: __(
									'Checkout',
									'woocommerce-paypal-payments'
								),
								value: 'checkout',
							},
							{
								label: __(
									'Home',
									'woocommerce-paypal-payments'
								),
								value: 'home',
							},
							{
								label: __(
									'Shop',
									'woocommerce-paypal-payments'
								),
								value: 'shop',
							},
						] }
						value={ placement }
						onChange={ ( value ) =>
							setAttributes( { placement: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...props }>
				<div className="ppcp-overlay-child">
					<PayPalScriptProvider options={ urlParams }>
						<PayPalMessages
							style={ previewStyle }
							forceReRender={ [ previewStyle ] }
							onRender={ () => setLoaded( true ) }
							amount={ amount }
						/>
					</PayPalScriptProvider>
				</div>
				<div className="ppcp-overlay-child ppcp-unclicable-overlay">
					{ ' ' }
					{ /* make the message not clickable */ }
					{ ! loaded && <Spinner /> }
				</div>
			</div>
		</>
	);
}
