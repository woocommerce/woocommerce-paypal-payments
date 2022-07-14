import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';

window.addEventListener('load', function() {

    const oxxoButton = document.getElementById('ppcp-oxxo');
    oxxoButton?.addEventListener('click', (event) => {
        event.preventDefault();

        const requiredFields = jQuery('form.woocommerce-checkout .validate-required:visible :input');
        requiredFields.each((i, input) => {
            jQuery(input).trigger('validate');
        });
        if (jQuery('form.woocommerce-checkout .validate-required.woocommerce-invalid:visible').length) {
            const errorHandler = new ErrorHandler(OXXOConfig.error.generic);
            errorHandler.clear();
            errorHandler.message(OXXOConfig.error.js_validation);
            return;
        }

        fetch(OXXOConfig.oxxo_endpoint, {
            method: 'POST',
            body: JSON.stringify({
                nonce: OXXOConfig.oxxo_nonce,
            })
        }).then((res)=>{
            return res.json();
        }).then((data)=>{
            if (!data.success) {
                alert('Could not get payer action from PayPal: ' + JSON.stringify(data));
                return;
            }

            window.open(
                data.data.payer_action,
                '_blank',
                'popup'
            );

            document.querySelector('#place_order').click()
        });
    });
});
