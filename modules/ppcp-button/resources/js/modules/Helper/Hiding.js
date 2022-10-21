/**
 * @param selectorOrElement
 * @returns {Element}
 */
const getElement = (selectorOrElement) => {
    if (typeof selectorOrElement === 'string') {
        return document.querySelector(selectorOrElement);
    }
    return selectorOrElement;
}

export const isVisible = (element) => {
    return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
}

export const setVisible = (selectorOrElement, show, important = false) => {
    const element = getElement(selectorOrElement);
    if (!element) {
        return;
    }

    const currentValue = element.style.getPropertyValue('display');

    if (!show) {
        if (currentValue === 'none') {
            return;
        }

        element.style.setProperty('display', 'none', important ? 'important' : '');
    } else {
        if (currentValue === 'none') {
            element.style.removeProperty('display');
        }

        // still not visible (if something else added display: none in CSS)
        if (!isVisible(element)) {
            element.style.setProperty('display', 'block');
        }
    }
};

export const setVisibleByClass = (selectorOrElement, show, hiddenClass) => {
    const element = getElement(selectorOrElement);
    if (!element) {
        return;
    }

    if (show) {
        element.classList.remove(hiddenClass);
    } else {
        element.classList.add(hiddenClass);
    }
};

export const hide = (selectorOrElement, important = false) => {
    setVisible(selectorOrElement, false, important);
};

export const show = (selectorOrElement) => {
    setVisible(selectorOrElement, true);
};
