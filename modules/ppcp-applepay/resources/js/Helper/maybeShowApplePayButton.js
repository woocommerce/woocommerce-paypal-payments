import {buttonID} from "./utils";
export const maybeShowButton = () => {
    const {ApplePaySession} = window
    const applePayMethodElement = document.querySelector(
        '#' + buttonID,
    )
    const canShowButton = applePayMethodElement && (ApplePaySession && ApplePaySession.canMakePayments())
    if (!canShowButton) {
        console.error('This device does not support Apple Pay');
        return false
    }
    return true
}
