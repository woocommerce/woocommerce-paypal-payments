class CartHelper {

    constructor(cartItemKeys = [])
    {
        this.endpoint = wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'remove_from_cart');
        this.cartItemKeys = cartItemKeys;
    }

    addFromPurchaseUnits(purchaseUnits) {
        for (const purchaseUnit of purchaseUnits || []) {
            for (const item of purchaseUnit.items || []) {
                if (!item.cart_item_key) {
                    continue;
                }
                this.cartItemKeys.push(item.cart_item_key);
            }
        }

        return this;
    }

    removeFromCart()
    {
        return new Promise((resolve, reject) => {
            if (!this.cartItemKeys || !this.cartItemKeys.length) {
                resolve();
                return;
            }

            const numRequests = this.cartItemKeys.length;
            let numResponses = 0;

            const tryToResolve = () => {
                numResponses++;
                if (numResponses >= numRequests) {
                    resolve();
                }
            }

            for (const cartItemKey of this.cartItemKeys) {
                const params = new URLSearchParams();
                params.append('cart_item_key', cartItemKey);

                if (!cartItemKey) {
                    tryToResolve();
                    continue;
                }

                fetch(this.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: params
                }).then(function (res) {
                    return res.json();
                }).then(() => {
                    tryToResolve();
                }).catch(() => {
                    tryToResolve();
                });
            }
        });
    }
}

export default CartHelper;
