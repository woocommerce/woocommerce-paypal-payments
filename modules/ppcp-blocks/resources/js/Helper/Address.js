/**
 * @param {String} fullName
 * @returns {Array}
 */
export const splitFullName = (fullName) => {
    fullName = fullName.trim()
    if (!fullName.includes(' ')) {
        return [fullName, ''];
    }
    const parts = fullName.split(' ');
    const firstName = parts[0];
    parts.shift();
    const lastName = parts.join(' ');
    return [firstName, lastName];
}

/**
 * @param {Object} address
 * @returns {Object}
 */
export const paypalAddressToWc = (address) => {
    const map = {
        country_code: 'country',
        address_line_1: 'address_1',
        address_line_2: 'address_2',
        admin_area_1: 'state',
        admin_area_2: 'city',
        postal_code: 'postcode',
    };
    const result = {};
    Object.entries(map).forEach(([paypalKey, wcKey]) => {
        if (address[paypalKey]) {
            result[wcKey] = address[paypalKey];
        }
    })

    return result;
}

/**
 * @param {Object} shipping
 * @returns {Object}
 */
export const paypalShippingToWc = (shipping) => {
    const [firstName, lastName] = splitFullName(shipping.name.full_name);
    return {
        first_name: firstName,
        last_name: lastName,
        ...paypalAddressToWc(shipping.address),
    }
}

/**
 * @param {Object} payer
 * @returns {Object}
 */
export const paypalPayerToWc = (payer) => {
    const firstName = payer.name.given_name;
    const lastName = payer.name.surname;
    const address = payer.address ? paypalAddressToWc(payer.address) : {};
    return {
        first_name: firstName,
        last_name: lastName,
        email: payer.email_address,
        ...address,
    }
}

/**
 * @param {Object} order
 * @returns {Object}
 */
export const paypalOrderToWcShippingAddress = (order) => {
    const res = paypalShippingToWc(order.purchase_units[0].shipping);

    // use the name from billing if the same, to avoid possible mistakes when splitting full_name
    const billingAddress = paypalPayerToWc(order.payer);
    if (`${res.first_name} ${res.last_name}` === `${billingAddress.first_name} ${billingAddress.last_name}`) {
        res.first_name = billingAddress.first_name;
        res.last_name = billingAddress.last_name;
    }

    return res;
}
