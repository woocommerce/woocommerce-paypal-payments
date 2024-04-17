import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import {CardFields} from "./Components/CardFields";

const config = wc.wcSettings.getSetting('ppcp-credit-card-gateway_data');

registerPaymentMethod({
    name: config.id,
    label: <div dangerouslySetInnerHTML={{__html: config.title}}/>,
    content: <CardFields config={config}/>,
    edit: <p>edit...</p>,
    ariaLabel: config.title,
    canMakePayment: () => {return true},
})
