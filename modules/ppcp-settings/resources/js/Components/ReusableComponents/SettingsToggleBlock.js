import { ToggleControl } from '@wordpress/components';

const SettingsToggleBlock = ( { isToggled, setToggled, ...props } ) => {
	return (
		<div className="ppcp-r-toggle-block">
			<div className="ppcp-r-toggle-block__wrapper">
				<div className="ppcp-r-toggle-block__content">
					{ props?.label && (
						<span className="ppcp-r-toggle-block__content-label">
							{ props.label }
						</span>
					) }
					{ props?.description && (
						<p
							className="ppcp-r-toggle-block__content-description"
							dangerouslySetInnerHTML={ {
								__html: props.description,
							} }
						></p>
					) }
				</div>
				<div className="ppcp-r-toggle-block__switch">
					<ToggleControl
						checked={ isToggled }
						onChange={ ( newValue ) => {
							setToggled( newValue );
						} }
					/>
				</div>
			</div>
			{ props.children && isToggled && (
				<div className="ppcp-r-toggle-block__toggled-content">
					{ props.children }
				</div>
			) }
		</div>
	);
};

export default SettingsToggleBlock;
