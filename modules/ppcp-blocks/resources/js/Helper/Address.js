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
    let map = {
        country_code: 'country',
        address_line_1: 'address_1',
        address_line_2: 'address_2',
        admin_area_1: 'state',
        admin_area_2: 'city',
        postal_code: 'postcode',
    };
    if (address.city) { // address not from API, such as onShippingChange
        map = {
            country_code: 'country',
            state: 'state',
            city: 'city',
            postal_code: 'postcode',
        };
    }
    const result = {};
    Object.entries(map).forEach(([paypalKey, wcKey]) => {
        if (address[paypalKey]) {
            result[wcKey] = address[paypalKey];
        }
    });

    const defaultAddress = {
        first_name: '',
        last_name: '',
        company: '',
        address_1: '',
        address_2: '',
        city: '',
        state: '',
        postcode: '',
        country: '',
        phone: '',
    };

    return {...defaultAddress, ...result};
}

/**
 * @param {Object} shipping
 * @returns {Object}
 */
export const paypalShippingToWc = (shipping) => {
    const [firstName, lastName] = splitFullName(shipping.name.full_name);
    return {
        ...paypalAddressToWc(shipping.address),
        first_name: firstName,
        last_name: lastName,
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
        ...address,
        first_name: firstName,
        last_name: lastName,
        email: payer.email_address,
    }
}

/**
 * @param {Object} order
 * @returns {Object}
 */
export const paypalOrderToWcShippingAddress = (order) => {
    const shipping = order.purchase_units[0].shipping;
    if (!shipping) {
        return {};
    }

    const res = paypalShippingToWc(shipping);

    // use the name from billing if the same, to avoid possible mistakes when splitting full_name
    const billingAddress = paypalPayerToWc(order.payer);
    if (`${res.first_name} ${res.last_name}` === `${billingAddress.first_name} ${billingAddress.last_name}`) {
        res.first_name = billingAddress.first_name;
        res.last_name = billingAddress.last_name;
    }

    return res;
}

/**
 *
 * @param order
 * @returns {{shippingAddress: Object, billingAddress: Object}}
 */
export const paypalOrderToWcAddresses = (order) => {
    const shippingAddress = paypalOrderToWcShippingAddress(order);
    let billingAddress = paypalPayerToWc(order.payer);
    // no billing address, such as if billing address retrieval is not allowed in the merchant account
    if (!billingAddress.address_line_1) {
        billingAddress = {...shippingAddress, ...paypalPayerToWc(order.payer)};
    }

    return {billingAddress, shippingAddress};
}
