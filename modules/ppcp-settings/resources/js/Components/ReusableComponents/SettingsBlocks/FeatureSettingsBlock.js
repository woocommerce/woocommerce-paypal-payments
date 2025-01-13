import { Button } from '@wordpress/components';
import SettingsBlock from './SettingsBlock';
import { Header, Title, Action, Description } from './SettingsBlockElements';
import TitleBadge from '../TitleBadge';

const FeatureSettingsBlock = ( { title, description, ...props } ) => {
	const printNotes = () => {
		const notes = props.actionProps?.notes;
		if ( ! notes || ( Array.isArray( notes ) && notes.length === 0 ) ) {
			return null;
		}

		return (
			<span className="ppcp-r-feature-item__notes">
				{ notes.map( ( note, index ) => (
					<span key={ index }>{ note }</span>
				) ) }
			</span>
		);
	};

	const renderButton = ( button ) => {
		const buttonElement = (
			<Button
				className={ button.class ? button.class : '' }
				key={ button.text }
                isBusy={ props.actionProps?.isBusy }
				variant={ button.type }
				onClick={ button.onClick }
			>
				{ button.text }
			</Button>
		);

		return button.urls ? (
			<a href={ button.urls.live } key={ button.text }>
				{ buttonElement }
			</a>
		) : (
			buttonElement
		);
	};

	return (
		<SettingsBlock { ...props } className="ppcp-r-settings-block__feature">
			<Header>
				<Title>
					{ title }
					{ props.actionProps?.enabled && (
						<TitleBadge { ...props.actionProps?.badge } />
					) }
				</Title>
				<Description className="ppcp-r-settings-block__feature__description">
					{ description }
					{ printNotes() }
				</Description>
			</Header>
			<Action>
				<div className="ppcp-r-feature-item__buttons">
					{ props.actionProps?.buttons.map( renderButton ) }
				</div>
			</Action>
		</SettingsBlock>
	);
};

export default FeatureSettingsBlock;
