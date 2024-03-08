
class Fastlane {

    construct() {
        this.connection = null;
        this.identity = null;
        this.profile = null;
        this.FastlaneCardComponent = null;
        this.FastlaneEmailComponent = null;
        this.FastlaneAddressComponent = null;
        this.FastlaneWatermarkComponent = null;
    }

    connect(config) {
        return new Promise((resolve, reject) => {
            window.paypal.Connect(config) // TODO: migrate from 0.6 to 0.7
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
        this.FastlaneCardComponent = this.connection.ConnectCardComponent; // TODO: migrate from 0.6 to 0.7
        this.FastlaneEmailComponent = null; // TODO: migrate from 0.6 to 0.7
        this.FastlaneAddressComponent = null; // TODO: migrate from 0.6 to 0.7
        this.FastlaneWatermarkComponent = this.connection.ConnectWatermarkComponent // TODO: migrate from 0.6 to 0.7

        console.log('[AXO] Fastlane initialized', this);
    }

    setLocale(locale) {
        this.connection.setLocale(locale);
    }

}

export default Fastlane;
