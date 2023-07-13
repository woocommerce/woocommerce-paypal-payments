export const isChangePaymentPage = () => {
    const urlParams = new URLSearchParams(window.location.search)
    return urlParams.has('change_payment_method');
}
