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
	const contentId = id
		? `${ id }-content`
		: `accordion-${ title.replace( /\s+/g, '-' ).toLowerCase() }-content`;

	return (
		<div
			className={ classNames( 'ppcp-r-accordion', className, {
				'ppcp--is-open': isOpen,
			} ) }
			id={ id || undefined }
		>
			<button
				type="button"
				className="ppcp--toggler"
				onClick={ toggleOpen }
				aria-expanded={ isOpen }
				aria-controls={ contentId }
			>
				<Header>
					<TitleWrapper>
						<Title noCaps={ noCaps }>{ title }</Title>
						<Action>
							<Icon icon={ isOpen ? chevronUp : chevronDown } />
						</Action>
					</TitleWrapper>
					{ description && (
						<Description>{ description }</Description>
					) }
				</Header>
			</button>
			<div
				className={ classNames( 'ppcp--accordion-content', {
					'ppcp--is-open': isOpen,
				} ) }
				id={ contentId }
				aria-hidden={ ! isOpen }
				inert={ isOpen ? undefined : '' }
			>
				<Content asCard={ false }>{ children }</Content>
			</div>
		</div>
	);
};

export default Accordion;
