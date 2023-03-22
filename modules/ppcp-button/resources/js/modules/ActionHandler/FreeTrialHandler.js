class FreeTrialHandler {
    /**
     * @param config
     * @param formSelector
     * @param {FormSaver} formSaver
     * @param {FormValidator|null} formValidator
     * @param {Spinner} spinner
     * @param {ErrorHandler} errorHandler
     */
    constructor(
        config,
        formSelector,
        formSaver,
        formValidator,
        spinner,
        errorHandler
    ) {
        this.config = config;
        this.formSelector = formSelector;
        this.formSaver = formSaver;
        this.formValidator = formValidator;
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
            if (this.formValidator) {
                try {
                    const errors = await this.formValidator.validate(document.querySelector(this.formSelector));
                    if (errors.length > 0) {
                        this.spinner.unblock();
                        this.errorHandler.messages(errors);
                        return;
                    }
                } catch (error) {
                    console.error(error);
                }
            }

            const res = await fetch(this.config.ajax.vault_paypal.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
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
