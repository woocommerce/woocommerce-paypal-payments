document.addEventListener('DOMContentLoaded', () => {
    jQuery(document.body).on('updated_checkout payment_method_selected', () => {
        jQuery('#ppcp-pui-legal-text').hide();
        if(jQuery('input[name="payment_method"]:checked').val() === 'ppcp-pay-upon-invoice-gateway') {
            jQuery('#ppcp-pui-legal-text').show();
        }
    });
});
