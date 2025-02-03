import { useCallback, useEffect } from '@wordpress/element';

// TODO: Migrate to Tabs (TabPanel v2) once its API is publicly available, as it provides programmatic tab switching support: https://github.com/WordPress/gutenberg/issues/52997
import { TabPanel } from '@wordpress/components';

import { updateQueryString } from '../../utils/navigation';

const TabBar = ( { tabs, activePanel, setActivePanel } ) => {
	const isValidTab = ( tabsList, checkTab ) => {
		return tabsList.some( ( tab ) => tab.name === checkTab );
	};
	const updateActivePanel = useCallback(
		( tabName ) => {
			if ( isValidTab( tabs, tabName ) ) {
				setActivePanel( tabName );
			} else {
				console.warn( `Invalid tab name: ${ tabName }` );
			}
		},
		[ tabs, setActivePanel ]
	);

	useEffect( () => {
		updateQueryString( { panel: activePanel } );
	}, [ activePanel ] );

	return (
		<TabPanel
			className={ `ppcp-r-tabs ${ activePanel }` }
			initialTabName={ activePanel }
			onSelect={ updateActivePanel }
			tabs={ tabs }
		>
			{ () => '' }
		</TabPanel>
	);
};

export default TabBar;
