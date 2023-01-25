class FreeTrialHandler {
    constructor(
        config,
        formSelector,
        formSaver,
        spinner,
        errorHandler
    ) {
        this.config = config;
        this.formSelector = formSelector;
        this.formSaver = formSaver;
        this.spinner = spinner;
        this.errorHandler = errorHandler;
    }

    async handle()
    {
        this.spinner.block();

        try {
            await this.formSaver.save(document.querySelector(this.formSelector));
        } catch (error) {
            console.error(error);
        }

        try {
            const res = await fetch(this.config.ajax.vault_paypal.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: this.config.ajax.vault_paypal.nonce,
                    return_url: location.href,
                }),
            });

            const data = await res.json();

            if (!data.success) {
                throw Error(data.data.message);
            }

            location.href = data.data.approve_link;
        } catch (error) {
            this.spinner.unblock();
            console.error(error);
            this.errorHandler.message(data.data.message);
        }
    }
}
export default FreeTrialHandler;
