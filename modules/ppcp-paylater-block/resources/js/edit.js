import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useScriptParams } from "./hooks/script-params";
import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading'
import PayPalMessages from "./components/PayPalMessages";

export default function Edit( { attributes, clientId, setAttributes } ) {
    const { layout, logo, position, color, flexColor, flexRatio, placement, id } = attributes;
    const isFlex = layout === 'flex';

    const [paypalScriptState, setPaypalScriptState] = useState(null);

    const [rendered, setRendered] = useState(false);

    let amount = undefined;
    const postContent = String(wp.data.select('core/editor')?.getEditedPostContent());
    if (postContent.includes('woocommerce/checkout') || postContent.includes('woocommerce/cart')) {
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
        },
    };

    let classes = ['ppcp-paylater-block-preview', 'ppcp-overlay-parent'];
    if (PcpPayLaterBlock.vaultingEnabled) {
        classes = ['ppcp-paylater-block-preview', 'ppcp-paylater-unavailable', 'block-editor-warning'];
    }
    const props = useBlockProps({className: classes});

    const loadingElement = <div {...props}><Spinner/></div>;

    useEffect(() => {
        if (!id) {
            setAttributes({id: 'ppcp-' + clientId});
        }
    }, []);

    if (PcpPayLaterBlock.vaultingEnabled) {
        return <div {...props}>
            <div className={'block-editor-warning__contents'}>
                <h3>{__('PayPal Pay Later Messaging', 'woocommerce-paypal-payments')}</h3>
                <p className={'block-editor-warning__message'}>{__('Pay Later Messaging cannot be used while PayPal Vaulting is active. Disable PayPal Vaulting in the PayPal Payment settings to reactivate this block', 'woocommerce-paypal-payments')}</p>
                <div className={'class="block-editor-warning__actions"'}>
                    <span className={'block-editor-warning__action'}>
                        <a href={PcpPayLaterBlock.settingsUrl} className={'components-button is-primary'}>
                            {__('PayPal Payments Settings', 'woocommerce-paypal-payments')}
                        </a>
                    </span>
                    <span className={'block-editor-warning__action'}>
                        <button onClick={() => wp.data.dispatch( 'core/block-editor' ).removeBlock(clientId)} type={'button'} className={'components-button is-secondary'}>
                            {__('Remove Block', 'woocommerce-paypal-payments')}
                        </button>
                    </span>
                </div>
            </div>
        </div>
    }

    let scriptParams = useScriptParams(PcpPayLaterBlock.ajax.cart_script_params);
    if (scriptParams === null) {
        return loadingElement;
    }
    if (scriptParams === false) {
        scriptParams = {
            url_params: {
                clientId: 'test',
            }
        }
    }
    scriptParams.url_params.components = 'messages,buttons,funding-eligibility';

    if (!paypalScriptState) {
        loadPaypalScript(scriptParams, () => {
            setPaypalScriptState('loaded')
        }, () => {
            setPaypalScriptState('failed')
        });
    }
    if (paypalScriptState !== 'loaded') {
        return loadingElement;
    }

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'woocommerce-paypal-payments' ) }>
                    <SelectControl
                        label={ __( 'Layout', 'woocommerce-paypal-payments' ) }
                        options={ [
                            { label: __( 'Text', 'woocommerce-paypal-payments' ), value: 'text' },
                            { label: __( 'Banner', 'woocommerce-paypal-payments' ), value: 'flex' },
                        ] }
                        value={ layout }
                        onChange={ ( value ) => setAttributes( { layout: value } ) }
                    />
                    { !isFlex && (<SelectControl
                        label={__('Logo', 'woocommerce-paypal-payments')}
                        options={[
                            { label: __('Primary', 'woocommerce-paypal-payments'), value: 'primary' },
                            { label: __('Alternative', 'woocommerce-paypal-payments'), value: 'alternative' },
                            { label: __('Inline', 'woocommerce-paypal-payments'), value: 'inline' },
                            { label: __('None', 'woocommerce-paypal-payments'), value: 'none' },
                        ]}
                        value={logo}
                        onChange={(value) => setAttributes({logo: value})}
                    />)}
                    { !isFlex && logo === 'primary' && (<SelectControl
                        label={__('Logo Position', 'woocommerce-paypal-payments')}
                        options={[
                            { label: __( 'Left', 'woocommerce-paypal-payments' ), value: 'left' },
                            { label: __( 'Right', 'woocommerce-paypal-payments' ), value: 'right' },
                            { label: __( 'Top', 'woocommerce-paypal-payments' ), value: 'top' },
                        ]}
                        value={position}
                        onChange={(value) => setAttributes({position: value})}
                    />)}
                    { !isFlex && (<SelectControl
                        label={__('Text Color', 'woocommerce-paypal-payments')}
                        options={[
                            { label: __( 'Black', 'woocommerce-paypal-payments' ), value: 'black' },
                            { label: __( 'White', 'woocommerce-paypal-payments' ), value: 'white' },
                            { label: __( 'Monochrome', 'woocommerce-paypal-payments' ), value: 'monochrome' },
                            { label: __( 'Grayscale', 'woocommerce-paypal-payments' ), value: 'grayscale' },
                        ]}
                        value={color}
                        onChange={(value) => setAttributes({color: value})}
                    />)}
                    { isFlex && (<SelectControl
                        label={__('Color', 'woocommerce-paypal-payments')}
                        options={[
                            { label: __( 'Blue', 'woocommerce-paypal-payments' ), value: 'blue' },
                            { label: __( 'Black', 'woocommerce-paypal-payments' ), value: 'black' },
                            { label: __( 'White', 'woocommerce-paypal-payments' ), value: 'white' },
                            { label: __( 'White no border', 'woocommerce-paypal-payments' ), value: 'white-no-border' },
                            { label: __( 'Gray', 'woocommerce-paypal-payments' ), value: 'gray' },
                            { label: __( 'Monochrome', 'woocommerce-paypal-payments' ), value: 'monochrome' },
                            { label: __( 'Grayscale', 'woocommerce-paypal-payments' ), value: 'grayscale' },
                        ]}
                        value={flexColor}
                        onChange={(value) => setAttributes({flexColor: value})}
                    />)}
                    { isFlex && (<SelectControl
                        label={__('Ratio', 'woocommerce-paypal-payments')}
                        options={[
                            { label: __( '1x1', 'woocommerce-paypal-payments' ), value: '1x1' },
                            { label: __( '1x4', 'woocommerce-paypal-payments' ), value: '1x4' },
                            { label: __( '8x1', 'woocommerce-paypal-payments' ), value: '8x1' },
                            { label: __( '20x1', 'woocommerce-paypal-payments' ), value: '20x1' },
                        ]}
                        value={flexRatio}
                        onChange={(value) => setAttributes({flexRatio: value})}
                    />)}
                    <SelectControl
                        label={ __( 'Placement page', 'woocommerce-paypal-payments' ) }
                        help={ __( 'Used for the analytics dashboard in the merchant account.', 'woocommerce-paypal-payments' ) }
                        options={ [
                            { label: __( 'Detect automatically', 'woocommerce-paypal-payments' ), value: 'auto' },
                            { label: __( 'Cart', 'woocommerce-paypal-payments' ), value: 'cart' },
                            { label: __( 'Payment', 'woocommerce-paypal-payments' ), value: 'payment' },
                            { label: __( 'Product', 'woocommerce-paypal-payments' ), value: 'product' },
                            { label: __( 'Product list', 'woocommerce-paypal-payments' ), value: 'product-list' },
                            { label: __( 'Home', 'woocommerce-paypal-payments' ), value: 'home' },
                            { label: __( 'Category', 'woocommerce-paypal-payments' ), value: 'category' },
                        ] }
                        value={ placement }
                        onChange={ ( value ) => setAttributes( { placement: value } ) }
                    />
				</PanelBody>
			</InspectorControls>
            <div {...props}>
                <div className={'ppcp-overlay-child'}>
                    <PayPalMessages
                        style={previewStyle}
                        amount={amount}
                        onRender={() => setRendered(true)}
                    />
                </div>
                <div className={'ppcp-overlay-child ppcp-unclicable-overlay'}> {/* make the message not clickable */}
                    {!rendered && (<Spinner/>)}
                </div>
            </div>
		</>
	);
}
