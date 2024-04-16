import { registerPaymentMethod } from '@woocommerce/blocks-registry';

const config = wc.wcSettings.getSetting('ppcp-credit-card-gateway_data');

registerPaymentMethod({
    name: config.id,
    label: <div dangerouslySetInnerHTML={{__html: config.title}}/>,
    content: <p>content</p>,
    edit: <p>edit...</p>,
    ariaLabel: config.title,
    canMakePayment: () => {return true},
})
