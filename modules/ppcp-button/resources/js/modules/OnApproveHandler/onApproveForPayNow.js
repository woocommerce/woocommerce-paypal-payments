const onApprove = (context, errorHandler, spinner) => {
    return (data, actions) => {
        spinner.block();
        errorHandler.clear();

        return fetch(context.config.ajax.approve_order.endpoint, {
            method: 'POST',
            body: JSON.stringify({
                nonce: context.config.ajax.approve_order.nonce,
                order_id:data.orderID,
                funding_source: window.ppcpFundingSource,
            })
        }).then((res)=>{
            return res.json();
        }).then((data)=>{
            spinner.unblock();
            if (!data.success) {
                if (data.data.code === 100) {
                    errorHandler.message(data.data.message);
                } else {
                    errorHandler.genericError();
                }
                if (typeof actions !== 'undefined' && typeof actions.restart !== 'undefined') {
                    return actions.restart();
                }
                throw new Error(data.data.message);
            }
            document.querySelector('#place_order').click()
        });

    }
}

export default onApprove;
