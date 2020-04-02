class UpdateCart {

    constructor(endpoint, nonce) {
        this.endpoint = endpoint;
        this.nonce = nonce;
    }

    update(onResolve, product, qty, variations) {
        return new Promise( (resolve, reject) => {
            fetch(
                this.endpoint,
                {
                    method: 'POST',
                    body: JSON.stringify({
                        nonce: this.nonce,
                        product,
                        qty,
                        variations
                    })
                }
            ).then(
                (result) => {
                    return result.json();
                }
            ).then( (result) => {
                    if (! result.success) {
                        reject(result.data);
                        return;
                    }

                    const resolved = onResolve(result.data);
                    resolve(resolved);
                }
            )
        });
    }
}

export default UpdateCart;