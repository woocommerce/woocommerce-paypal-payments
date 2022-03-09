document.addEventListener('DOMContentLoaded', () => {
    const script = document.createElement('script');
    script.setAttribute('src', 'https://c.paypal.com/da/r/fb.js');
    document.body.append(script);

    jQuery(document.body).on('updated_checkout payment_method_selected', () => {
        jQuery('#ppcp-pui-legal-text').hide();
        if(jQuery('input[name="payment_method"]:checked').val() === 'ppcp-pay-upon-invoice-gateway') {
            jQuery('#ppcp-pui-legal-text').show();
        }
    });
});
