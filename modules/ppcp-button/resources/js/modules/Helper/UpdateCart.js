import Product from "../Entity/Product";
class UpdateCart {

    constructor(endpoint, nonce)
    {
        this.endpoint = endpoint;
        this.nonce = nonce;
    }

    /**
     *
     * @param onResolve
     * @param {Product[]} products
     * @returns {Promise<unknown>}
     */
    update(onResolve, products)
    {
        return new Promise((resolve, reject) => {
            fetch(
                this.endpoint,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        nonce: this.nonce,
                        products,
                    })
                }
            ).then(
                (result) => {
                return result.json();
                }
            ).then((result) => {
                if (! result.success) {
                    reject(result.data);
                    return;
                }

                    const resolved = onResolve(result.data);
                    resolve(resolved);
                })
        });
    }
}

export default UpdateCart;
