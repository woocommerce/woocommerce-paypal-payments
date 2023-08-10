import {loginAsAdmin} from "./user";
import {expect} from "@playwright/test";

const wcApi = require('@woocommerce/woocommerce-rest-api').default;
const {
    BASEURL,
    WP_MERCHANT_USER,
    WP_MERCHANT_PASSWORD,
} = process.env;

const wc = () => {
    return new wcApi({
        url: BASEURL,
        consumerKey: WP_MERCHANT_USER,
        consumerSecret: WP_MERCHANT_PASSWORD,
        version: 'wc/v3',
    });
}

export const createProduct = async (data) => {
    const api = wc();

    return await api.post('products', data)
        .then((response) => {
            return response.data.id
        }).catch((error) => {
            console.log(error)
        })
}

export const updateProduct = async (data, id) => {
    const api = wc();

    return await api.put(`products/${id}`, data)
        .then((response) => {
            return response.data.id
        }).catch((error) => {
            console.log(error)
        })
}

export const deleteProduct = async (id) => {
    const api = wc();

    return await api.delete(`products/${id}`)
        .then((response) => {
            return response.data.id
        }).catch((error) => {
            console.log(error)
        })
}

export const updateProductUi = async (id, page) => {
    await loginAsAdmin(page);
    await page.goto(`/wp-admin/post.php?post=${id}&action=edit`)
    await page.locator('#publish').click();
    await expect(page.getByText('Product updated.')).toBeVisible();
}

