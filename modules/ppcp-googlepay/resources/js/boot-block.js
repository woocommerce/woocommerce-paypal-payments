import {useEffect, useState} from '@wordpress/element';
import {registerExpressPaymentMethod, registerPaymentMethod} from '@woocommerce/blocks-registry';
import {loadPaypalScript} from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading'
import GooglepayManager from "./GooglepayManager";
import {loadCustomScript} from "@paypal/paypal-js";

const ppcpData = wc.wcSettings.getSetting('ppcp-gateway_data');
const ppcpConfig = ppcpData.scriptData;

const buttonData = wc.wcSettings.getSetting('ppcp-googlepay_data');
const buttonConfig = buttonData.scriptData;

if (typeof window.PayPalCommerceGateway === 'undefined') {
    window.PayPalCommerceGateway = ppcpConfig;
}

const GooglePayComponent = () => {
    const [bootstrapped, setBootstrapped] = useState(false);
    const [paypalLoaded, setPaypalLoaded] = useState(false);
    const [googlePayLoaded, setGooglePayLoaded] = useState(false);

    const bootstrap = function () {
        const manager = new GooglepayManager(buttonConfig, ppcpConfig);
        manager.init();
    };

    useEffect(() => {
        const bodyClass = 'ppcp-has-googlepay-block';
        if (!document.body.classList.contains(bodyClass)) {
            document.body.classList.add(bodyClass);
        }
    }, []);

    useEffect(() => {
        // Load GooglePay SDK
        loadCustomScript({ url: buttonConfig.sdk_url }).then(() => {
            setGooglePayLoaded(true);
        });

        // Load PayPal
        loadPaypalScript(ppcpConfig, () => {
            setPaypalLoaded(true);
        });
    }, []);

    useEffect(() => {
        if (!bootstrapped && paypalLoaded && googlePayLoaded) {
            setBootstrapped(true);
            bootstrap();
        }
    }, [paypalLoaded, googlePayLoaded]);

    return (
        <div id={buttonConfig.button.wrapper.replace('#', '')} className="ppcp-button-googlepay"></div>
    );
}

const features = ['products'];
let registerMethod = registerExpressPaymentMethod;

registerMethod({
    name: buttonData.id,
    label: <div dangerouslySetInnerHTML={{__html:  buttonData.title}}/>,
    content: <GooglePayComponent isEditing={false}/>,
    edit: <GooglePayComponent isEditing={true}/>,
    ariaLabel: buttonData.title,
    canMakePayment: () => buttonData.enabled,
    supports: {
        features: features,
    },
});
