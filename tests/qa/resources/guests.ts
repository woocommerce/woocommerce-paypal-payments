/**
 * External dependencies
 */
import { guests as guestsBase } from '@inpsyde/playwright-utils/build/e2e/plugins/woocommerce';

const emailGary = `guest-${ Date.now() }@personal.example.us`;
const emailRyan = process.env.FASTLANE_EMAIL_RYAN;

const usaFastlaneGary: WooCommerce.CreateCustomer = {
	email: emailGary,
	first_name: 'Gary',
	last_name: 'From-USA',
	birth_date: '12.08.1985',
	billing: {
		first_name: 'Gary',
		last_name: 'From-USA',
		company: '',
		address_1: '123 Elm Street',
		address_2: 'Apt 4B',
		city: 'Los Angeles',
		state: 'CA',
		postcode: '90001',
		country: 'US',
		countryName: 'United States (US)',
		email: emailGary,
		phone: '2135551234',
	},
		shipping: {
		first_name: 'Gary',
		last_name: 'From-USA',
		company: '',
		address_1: '123 Elm Street',
		address_2: 'Apt 4B',
		city: 'Los Angeles',
		state: 'CA',
		postcode: '90001',
		country: 'US',
		countryName: 'United States (US)',
		email: emailGary,
		phone: '2135551234',
	},
};

const usaFastlaneRyan: WooCommerce.CreateCustomer = {
	email: emailRyan,
	first_name: 'Fred',
	last_name: 'Flintstone',
	birth_date: '05.11.1990',
	billing: {
		first_name: 'Fred',
		last_name: 'Flintstone',
		company: '',
		address_1: '4262 Stanley Blvd',
		address_2: '',
		city: 'Pleasanton',
		state: 'CA',
		postcode: '94566',
		country: 'US',
		countryName: 'United States (US)',
		email: emailRyan,
		phone: '4026607986',
	},
	shipping: {
		first_name: 'Fred',
		last_name: 'Flintstone',
		company: '',
		address_1: 'TestB 306',
		address_2: '',
		city: 'Los Angeles',
		state: 'AL',
		postcode: '12345',
		country: 'US',
		countryName: 'United States (US)',
		email: emailRyan,
		phone: '4026607986',
	},
};

export const guests: {
	[ key: string ]: WooCommerce.CreateCustomer;
} = {
	...guestsBase,
	usaFastlaneGary,
	usaFastlaneRyan,
}