export default class FormSaver {
    constructor(url, nonce) {
        this.url = url;
        this.nonce = nonce;
    }

    async save(form) {
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
            throw Error(data.data.message);
        }
    }
}
