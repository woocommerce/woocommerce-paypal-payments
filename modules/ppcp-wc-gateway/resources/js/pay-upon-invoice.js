window.addEventListener('load', function() {

    function _loadBeaconJS(options) {
        var script = document.createElement('script');
        script.src = options.fnUrl;
        document.body.appendChild(script);
    }

    function _injectConfig() {
        var script = document.querySelector("[fncls='fnparams-dede7cc5-15fd-4c75-a9f4-36c430ee3a99']");
        if (script) {
            if (script.parentNode) {
                script.parentNode.removeChild(script);
            }
        }

        script = document.createElement('script');
        script.id = 'fconfig';
        script.type = 'application/json';
        script.setAttribute('fncls', 'fnparams-dede7cc5-15fd-4c75-a9f4-36c430ee3a99');

        var configuration = {
            'f': FraudNetConfig.f,
            's': FraudNetConfig.s
        };
        if(FraudNetConfig.sandbox === '1') {
            configuration.sandbox = true;
        }

        script.text = JSON.stringify(configuration);
        document.body.appendChild(script);

        const payForOrderForm = document.forms.order_review;
        if(payForOrderForm) {
            const puiPayForOrderSessionId = document.createElement('input');
            puiPayForOrderSessionId.setAttribute('type', 'hidden');
            puiPayForOrderSessionId.setAttribute('name', 'pui_pay_for_order_session_id');
            puiPayForOrderSessionId.setAttribute('value', FraudNetConfig.f);
            payForOrderForm.appendChild(puiPayForOrderSessionId);
        }

        _loadBeaconJS({fnUrl: "https://c.paypal.com/da/r/fb.js"})
    }

    document.addEventListener('hosted_fields_loaded', (event) => {
        if (PAYPAL.asyncData && typeof PAYPAL.asyncData.initAndCollect === 'function') {
            PAYPAL.asyncData.initAndCollect()
        }

        _injectConfig();
    });

    _injectConfig();
})

