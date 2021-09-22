document.addEventListener(
    'DOMContentLoaded',
    () => {
        jQuery('.ppcp-delete-payment-button').click(async (event) => {
            event.preventDefault();

            console.log(event.target.id)
        });
    });
