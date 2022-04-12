window.addEventListener('load', function() {

    const getSessionIdFromJson = () => {
        const form = document.querySelector('form.checkout');
        if(!form) {
            return;
        }

        const fncls = document.querySelector("[fncls='fnparams-dede7cc5-15fd-4c75-a9f4-36c430ee3a99']");
        const fncls_params = JSON.parse(fncls.textContent);

        if(document.querySelector("[name='fraudnet-session-id']") !== null) {
            document.querySelector("[name='fraudnet-session-id']").remove();
        }

        const fraudnetSessionId = document.createElement('input');
        fraudnetSessionId.setAttribute('type', 'hidden');
        fraudnetSessionId.setAttribute('name', 'fraudnet-session-id');
        fraudnetSessionId.setAttribute('value', fncls_params.f);

        form.appendChild(fraudnetSessionId);
        console.log(fncls_params)
    }

    document.addEventListener('hosted_fields_loaded', (event) => {
        getSessionIdFromJson();
    });

    getSessionIdFromJson();

    const replaceButtonLabel = () => {
        const form = document.querySelector('form.checkout');
        if(!form) {
            return;
        }

        const buttonLabel = document.querySelector('#place_order')?.textContent;
        const buttonLegalTextLabel = document.querySelector('#ppcp-legal-text-button-label');
        if (buttonLabel && buttonLegalTextLabel) {
            buttonLegalTextLabel.textContent = '"' + buttonLabel + '"';
        }
    }

    jQuery(document.body).on('payment_method_selected', () => {
        replaceButtonLabel();
    });

    replaceButtonLabel();
})

