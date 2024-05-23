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
                should_create_wc_order: !context.config.vaultingEnabled || data.paymentSource !== 'venmo'
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

            let orderReceivedUrl = data.data?.order_received_url

            location.href = orderReceivedUrl ? orderReceivedUrl : context.config.redirect;

        });

    }
}

export default onApprove;
