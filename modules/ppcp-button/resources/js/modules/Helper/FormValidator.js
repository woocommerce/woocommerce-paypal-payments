export default class FormValidator {
    constructor(url, nonce) {
        this.url = url;
        this.nonce = nonce;
    }

    async validate(form) {
        const formData = new FormData(form);

        const res = await fetch(this.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                nonce: this.nonce,
                form_encoded: new URLSearchParams(formData).toString(),
            }),
        });

        const data = await res.json();

        if (!data.success) {
            if (data.data.refresh) {
                jQuery( document.body ).trigger( 'update_checkout' );
            }

            if (data.data.errors) {
                return data.data.errors;
            }
            throw Error(data.data.message);
        }

        return [];
    }
}
