import { Icon } from '@wordpress/components';
import { chevronDown, chevronUp } from '@wordpress/icons';
import classNames from 'classnames';

import { useToggleState } from '../../hooks/useToggleState';
import {
	Content,
	Description,
	Header,
	Title,
	Action,
	TitleWrapper,
} from './Elements';

const Accordion = ( {
	title,
	id = '',
	noCaps = false,
	initiallyOpen = null,
	description = '',
	children = null,
	className = '',
} ) => {
	const { isOpen, toggleOpen } = useToggleState( id, initiallyOpen );
	const wrapperClasses = classNames( 'ppcp-r-accordion', className, {
		'ppcp--is-open': isOpen,
	} );
	const contentClass = classNames( 'ppcp--accordion-content', {
		'ppcp--is-open': isOpen,
	} );

	const icon = isOpen ? chevronUp : chevronDown;

	return (
		<div className={ wrapperClasses } { ...( id && { id } ) }>
			<button
				type="button"
				className="ppcp--toggler"
				onClick={ toggleOpen }
			>
				<Header>
					<TitleWrapper>
						<Title noCaps={ noCaps }>{ title }</Title>
						<Action>
							<Icon icon={ icon } />
						</Action>
					</TitleWrapper>
					<Description>{ description }</Description>
				</Header>
			</button>
			<div className={ contentClass }>
				<Content asCard={ false }>{ children }</Content>
			</div>
		</div>
	);
};

export default Accordion;
