window.addEventListener('load', function() {
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
});
