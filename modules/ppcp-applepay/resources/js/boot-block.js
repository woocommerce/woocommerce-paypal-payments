import {useEffect, useState} from '@wordpress/element';
import {registerExpressPaymentMethod, registerPaymentMethod} from '@woocommerce/blocks-registry';
import {loadPaypalScript} from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading'
import ApplepayManager from "./ApplepayManager";
import {loadCustomScript} from "@paypal/paypal-js";

const ppcpData = wc.wcSettings.getSetting('ppcp-gateway_data');
const ppcpConfig = ppcpData.scriptData;

const buttonData = wc.wcSettings.getSetting('ppcp-applepay_data');
const buttonConfig = buttonData.scriptData;

if (typeof window.PayPalCommerceGateway === 'undefined') {
    window.PayPalCommerceGateway = ppcpConfig;
}

const ApplePayComponent = () => {
    const [bootstrapped, setBootstrapped] = useState(false);
    const [paypalLoaded, setPaypalLoaded] = useState(false);
    const [applePayLoaded, setApplePayLoaded] = useState(false);

    const bootstrap = function () {
        const manager = new ApplepayManager(buttonConfig, ppcpConfig);
        manager.init();
    };
    useEffect(() => {
        const bodyClass = 'ppcp-has-applepay-block';
        if (!document.body.classList.contains(bodyClass)) {
            document.body.classList.add(bodyClass);
        }
    }, []);

    useEffect(() => {
        // Load ApplePay SDK
        loadCustomScript({ url: buttonConfig.sdk_url }).then(() => {
            setApplePayLoaded(true);
        });

        // Load PayPal
        loadPaypalScript(ppcpConfig, () => {
            setPaypalLoaded(true);
        });
    }, []);

    useEffect(() => {
        if (!bootstrapped && paypalLoaded && applePayLoaded) {
            setBootstrapped(true);
            bootstrap();
        }
    }, [paypalLoaded, applePayLoaded]);

    return (
        <div id={buttonConfig.button.wrapper.replace('#', '')}></div>
    );
}

const features = ['products'];
let registerMethod = registerExpressPaymentMethod;

registerMethod({
    name: buttonData.id,
    label: <div dangerouslySetInnerHTML={{__html:  buttonData.title}}/>,
    content: <ApplePayComponent isEditing={false}/>,
    edit: <ApplePayComponent isEditing={true}/>,
    ariaLabel: buttonData.title,
    canMakePayment: () => buttonData.enabled,
    supports: {
        features: features,
    },
});
