export const payerData = () => {
    const payer = PayPalCommerceGateway.payer;
    if (! payer) {
        return null;
    }

    const phone = (document.querySelector('#billing_phone') || typeof payer.phone !== 'undefined') ?
    {
        phone_type:"HOME",
            phone_number:{
            national_number : (document.querySelector('#billing_phone')) ? document.querySelector('#billing_phone').value : payer.phone.phone_number.national_number
        }
    } : null;
    const payerData = {
        email_address:(document.querySelector('#billing_email')) ? document.querySelector('#billing_email').value : payer.email_address,
        name : {
            surname: (document.querySelector('#billing_last_name')) ? document.querySelector('#billing_last_name').value : payer.name.surname,
            given_name: (document.querySelector('#billing_first_name')) ? document.querySelector('#billing_first_name').value : payer.name.given_name
        },
        address : {
            country_code : (document.querySelector('#billing_country')) ? document.querySelector('#billing_country').value : payer.address.country_code,
            address_line_1 : (document.querySelector('#billing_address_1')) ? document.querySelector('#billing_address_1').value : payer.address.address_line_1,
            address_line_2 : (document.querySelector('#billing_address_2')) ? document.querySelector('#billing_address_2').value : payer.address.address_line_2,
            admin_area_1 : (document.querySelector('#billing_state')) ? document.querySelector('#billing_state').value : payer.address.admin_area_1,
            admin_area_2 : (document.querySelector('#billing_city')) ? document.querySelector('#billing_city').value : payer.address.admin_area_2,
            postal_code : (document.querySelector('#billing_postcode')) ? document.querySelector('#billing_postcode').value : payer.address.postal_code
        }
    };

    if (phone) {
        payerData.phone = phone;
    }
    return payerData;
}
