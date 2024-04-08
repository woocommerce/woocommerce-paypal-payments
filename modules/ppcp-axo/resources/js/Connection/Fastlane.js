
class Fastlane {

    construct() {
        this.connection = null;
        this.identity = null;
        this.profile = null;
        this.FastlaneCardComponent = null;
        this.FastlanePaymentComponent = null;
        this.FastlaneWatermarkComponent = null;
    }

    connect(config) {
        return new Promise((resolve, reject) => {
            window.paypal.Fastlane(config)
                .then((result) => {
                    this.init(result);
                    console.log('[AXO] Connected', result);
                    resolve();
                })
                .catch((error) => {
                    console.error('[AXO] Failed to connect', error);
                    reject();
                });
        });
    }

    init(connection) {
        this.connection = connection;
        this.identity = this.connection.identity;
        this.profile = this.connection.profile;
        this.FastlaneCardComponent = this.connection.FastlaneCardComponent;
        this.FastlanePaymentComponent = this.connection.FastlanePaymentComponent;
        this.FastlaneWatermarkComponent = this.connection.FastlaneWatermarkComponent

        console.log('[AXO] Fastlane initialized', this);
    }

    setLocale(locale) {
        this.connection.setLocale(locale);
    }

}

export default Fastlane;
