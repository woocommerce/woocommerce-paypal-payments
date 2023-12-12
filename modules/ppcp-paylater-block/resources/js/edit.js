import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { PayPalScriptProvider, PayPalMessages } from "@paypal/react-paypal-js";
import { useScriptParams } from "./hooks/script-params";

export default function Edit( { attributes, clientId, setAttributes } ) {
    const { layout, logo, position, color, flexColor, flexRatio, placement, id } = attributes;
    const isFlex = layout === 'flex';

    const [loaded, setLoaded] = useState(false);

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

    const props = useBlockProps({className: ['ppcp-paylater-block-preview', 'ppcp-overlay-parent']});

    useEffect(() => {
        if (!id) {
            setAttributes({id: 'ppcp-' + clientId});
        }
    }, []);

    const scriptParams = useScriptParams(PcpPayLaterBlock.ajax.cart_script_params);
    if (scriptParams === null) {
        return (<div {...props}><Spinner/></div>)
    }

    const urlParams = scriptParams === false ? {
        clientId: 'test',
        components: 'messages',
    } : {
        ...scriptParams.url_params,
        ...{
            components: 'messages,buttons,funding-eligibility',
        }
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
                    <PayPalScriptProvider
                        options={urlParams}
                    >
                        <PayPalMessages
                            style={previewStyle}
                            forceReRender={[previewStyle]}
                            onRender={() => setLoaded(true)}
                        />
                    </PayPalScriptProvider>
                </div>
                <div className={'ppcp-overlay-child ppcp-unclicable-overlay'}> {/* make the message not clickable */}
                    {!loaded && (<Spinner/>)}
                </div>
            </div>
		</>
	);
}