const onApprove = (context, errorHandler) => {
    return (data, actions) => {
        return fetch(context.config.ajax.approve_order.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                nonce: context.config.ajax.approve_order.nonce,
                order_id:data.orderID,
                funding_source: window.ppcpFundingSource,
            })
        }).then((res)=>{
            return res.json();
        }).then((data)=>{
            if (!data.success) {
                errorHandler.genericError();
                return actions.restart().catch(err => {
                    errorHandler.genericError();
                });
            }
            location.href = context.config.redirect;
        });

    }
}

export default onApprove;
