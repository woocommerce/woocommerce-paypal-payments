document.addEventListener(
    'DOMContentLoaded',
    function() {
        jQuery('form.checkout').on('checkout_place_order_success', function(type, data)  {
            if(data.payer_action && data.payer_action !== '') {
                    const width = screen.width / 2;
                    const height = screen.height / 2;
                    const left = width - (width / 2);
                    const top = height - (height / 2);
                    window.open(
                        data.payer_action,
                        '_blank',
                        'popup, width=' + width + ', height=' + height + ', top=' + top + ', left=' + left
                    );
            }
        });
    }
);
