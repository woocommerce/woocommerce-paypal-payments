import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {CardFields} from './Components/card-fields';

const config = wc.wcSettings.getSetting('ppcp-credit-card-gateway_data');

const Label = ({components, config}) => {
    const {PaymentMethodIcons} = components;
    return <>
        <span dangerouslySetInnerHTML={{__html: config.title}}/>
        <PaymentMethodIcons
            icons={ config.card_icons }
            align="right"
        />
    </>
}

registerPaymentMethod({
    name: config.id,
    label: <Label config={config}/>,
    content: <CardFields config={config}/>,
    edit: <CardFields config={config}/>,
    ariaLabel: config.title,
    canMakePayment: () => {
        return true;
    },
    supports: {
        showSavedCards: true,
        features: config.supports,
    },
});
