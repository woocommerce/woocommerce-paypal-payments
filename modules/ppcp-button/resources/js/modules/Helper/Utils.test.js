/* global describe, test, expect */
import { strAddWord, strRemoveWord } from './Utils';

describe( 'strAddWord', () => {
	test( 'adds a word to an existing comma-separated list', () => {
		expect( strAddWord( 'venmo,card', 'paylater' ) ).toBe(
			'venmo,card,paylater'
		);
	} );

	test( 'does not duplicate a word already in the list', () => {
		expect( strAddWord( 'venmo,card', 'card' ) ).toBe( 'venmo,card' );
	} );

	// Regression test: callers (e.g. SingleProductBootstrap.simulateCart()) may not
	// have an existing list at all, since PHP only sends enable-funding/disable-funding
	// when there is something to enable/disable - calling .split() on undefined/empty
	// previously threw here.
	test( 'starts a new list when given undefined', () => {
		expect( strAddWord( undefined, 'venmo' ) ).toBe( 'venmo' );
	} );

	test( 'starts a new list when given an empty string', () => {
		expect( strAddWord( '', 'venmo' ) ).toBe( 'venmo' );
	} );
} );

describe( 'strRemoveWord', () => {
	test( 'removes a word from an existing comma-separated list', () => {
		expect( strRemoveWord( 'venmo,card,paylater', 'card' ) ).toBe(
			'venmo,paylater'
		);
	} );

	test( 'is a no-op when the word is not in the list', () => {
		expect( strRemoveWord( 'venmo,card', 'paylater' ) ).toBe(
			'venmo,card'
		);
	} );

	test( 'returns an empty string when given undefined', () => {
		expect( strRemoveWord( undefined, 'venmo' ) ).toBe( '' );
	} );

	test( 'returns an empty string when given an empty string', () => {
		expect( strRemoveWord( '', 'venmo' ) ).toBe( '' );
	} );
} );
