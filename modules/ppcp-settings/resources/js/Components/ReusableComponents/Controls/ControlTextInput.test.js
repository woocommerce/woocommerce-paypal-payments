import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import ControlTextInput from './ControlTextInput';

// `Action` pulls in useScrollTarget(), which needs scroll-highlight context
// this test does not provide. Stub it as a passthrough so the test focuses
// on ControlTextInput's own error rendering.
jest.mock( '../Elements', () => ( {
	Action: ( { children } ) => <div>{ children }</div>,
	Description: ( { children } ) => <>{ children }</>,
} ) );

// The shared @wordpress/components mock only exports Button, so TextControl
// needs a local stub. It forwards the relevant props onto a real <input>,
// since those props are what ControlTextInput computes and this test verifies.
jest.mock( '@wordpress/components', () => ( {
	TextControl: ( {
		className,
		value,
		onChange,
		onBlur,
		placeholder,
		'aria-invalid': ariaInvalid,
	} ) => (
		<input
			className={ className }
			aria-invalid={ ariaInvalid }
			value={ value }
			onChange={ ( e ) => onChange( e.target.value ) }
			onBlur={ onBlur }
			placeholder={ placeholder }
		/>
	),
} ) );

describe( 'ControlTextInput', () => {
	describe( 'when error is not set', () => {
		test( 'does not render an error message', () => {
			render(
				<ControlTextInput
					value=""
					placeholder="Input prefix"
					onChange={ jest.fn() }
				/>
			);

			expect(
				screen.queryByText( /./, { selector: '.ppcp-r-control-error' } )
			).not.toBeInTheDocument();
		} );

		test( 'does not mark the input as invalid', () => {
			render(
				<ControlTextInput
					value=""
					placeholder="Input prefix"
					onChange={ jest.fn() }
				/>
			);

			const input = screen.getByPlaceholderText( 'Input prefix' );

			expect( input ).toHaveAttribute( 'aria-invalid', 'false' );
			expect( input ).not.toHaveClass( 'ppcp--has-error' );
		} );
	} );

	test( 'calls onBlur when the input loses focus', () => {
		const onBlur = jest.fn();

		render(
			<ControlTextInput
				value=""
				placeholder="Input prefix"
				onChange={ jest.fn() }
				onBlur={ onBlur }
			/>
		);

		fireEvent.blur( screen.getByPlaceholderText( 'Input prefix' ) );

		expect( onBlur ).toHaveBeenCalled();
	} );

	describe( 'when error is set', () => {
		test( 'renders the error text in a ppcp-r-control-error element', () => {
			render(
				<ControlTextInput
					value="bad value"
					placeholder="Input prefix"
					onChange={ jest.fn() }
					error="Only letters, numbers, hyphens and underscores are allowed."
				/>
			);

			const errorMessage = screen.getByText(
				'Only letters, numbers, hyphens and underscores are allowed.'
			);

			expect( errorMessage ).toBeInTheDocument();
			expect( errorMessage ).toHaveClass( 'ppcp-r-control-error' );
		} );

		test( 'marks the input as invalid', () => {
			render(
				<ControlTextInput
					value="bad value"
					placeholder="Input prefix"
					onChange={ jest.fn() }
					error="Only letters, numbers, hyphens and underscores are allowed."
				/>
			);

			const input = screen.getByPlaceholderText( 'Input prefix' );

			expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
			expect( input ).toHaveClass( 'ppcp--has-error' );
		} );
	} );
} );
