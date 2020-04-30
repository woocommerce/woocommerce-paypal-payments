const onApprove = (context) => {
    return (data, actions) => {
        return fetch(context.config.ajax.approve_order.endpoint, {
            method: 'POST',
            body: JSON.stringify({
                nonce: context.config.ajax.approve_order.nonce,
                order_id:data.orderID
            })
        }).then((res)=>{
            return res.json();
        }).then((data)=>{
            if (!data.success) {
                throw Error(data.data);
            }
            location.href = context.config.redirect;
        });

    }
}

export default onApprove;
