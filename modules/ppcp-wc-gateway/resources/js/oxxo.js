window.addEventListener('load', function() {

    const oxxoButton = document.getElementById('ppcp-oxxo');
    oxxoButton?.addEventListener('click', (event) => {
        event.preventDefault();

        fetch(OXXOConfig.oxxo_endpoint, {
            method: 'POST',
            body: JSON.stringify({
                nonce: OXXOConfig.oxxo_nonce,
            })
        }).then((res)=>{
            return res.json();
        }).then((data)=>{
            if (!data.success) {
                alert('Could not update signup buttons: ' + JSON.stringify(data));
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

    /*
    const oxxoButton = document.getElementById('ppcp-oxxo-payer-action');
    if(oxxoButton) {
        oxxoButton.addEventListener('click', (event) => {
            event.preventDefault();
            window.open(
                oxxoButton.href,
                '_blank',
                'popup'
            );
        });

        window.open(oxxoButton.href);
    }

     */
});
