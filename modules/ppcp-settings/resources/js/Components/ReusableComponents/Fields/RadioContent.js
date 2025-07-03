import classNames from 'classnames';
import { PayPalRdb } from './index';

const RadioButtonWithContent = ( {
	className,
	id,
	name,
	label,
	description,
	value,
	currentValue,
	handleRdbState,
	toggleAdditionalContent,
	children,
} ) => {
	const wrapperClasses = classNames( 'ppcp-r__radio-wrapper', className );

	return (
		<div className="ppcp-r__radio-outer-wrapper">
			<div className={ wrapperClasses }>
				<PayPalRdb
					id={ id }
					name={ name }
					value={ value }
					currentValue={ currentValue }
					handleRdbState={ handleRdbState }
				/>

				<div className="ppcp-r__radio-content">
					<label htmlFor={ id }>{ label }</label>
					{ description && (
						<p
							className="ppcp-r__radio-description"
							dangerouslySetInnerHTML={ {
								__html: description,
							} }
						/>
					) }
				</div>
			</div>
			{ toggleAdditionalContent && children && value === currentValue && (
				<div className="ppcp-r__radio-content-additional">
					{ children }
				</div>
			) }
		</div>
	);
};

export default RadioButtonWithContent;
