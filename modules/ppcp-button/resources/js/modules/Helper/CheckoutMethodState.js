export const PaymentMethods = {
    PAYPAL: 'ppcp-gateway',
    CARDS: 'ppcp-credit-card-gateway',
};

export const ORDER_BUTTON_SELECTOR = '#place_order';

export const getCurrentPaymentMethod = () => {
    const el = document.querySelector('input[name="payment_method"]:checked');
    if (!el) {
        return null;
    }

    return el.value;
};

export const isSavedCardSelected = () => {
    const savedCardList = document.querySelector('#saved-credit-card');
    return savedCardList && savedCardList.value !== '';
};
