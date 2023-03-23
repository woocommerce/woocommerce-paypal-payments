export default class FormValidator {
    constructor(url, nonce) {
        this.url = url;
        this.nonce = nonce;
    }

    async validate(form) {
        const formData = new FormData(form);
        const formJsonObj = Object.fromEntries(formData.entries());

        const res = await fetch(this.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                nonce: this.nonce,
                form: formJsonObj,
            }),
        });

        const data = await res.json();

        if (!data.success) {
            if (data.data.errors) {
                return data.data.errors;
            }
            throw Error(data.data.message);
        }

        return [];
    }
}
