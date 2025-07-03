import { useCallback, useLayoutEffect } from '@wordpress/element';
import { Button, Icon } from '@wordpress/components';
import { chevronLeft } from '@wordpress/icons';
import classNames from 'classnames';

import useIsScrolled from '../../hooks/useIsScrolled';
import { useNavigation } from '../../hooks/useNavigation';
import BusyStateWrapper from './BusyStateWrapper';

const TopNavigation = ( {
	title,
	children,
	isMainTitle = true,
	exitOnTitleClick = false,
	onTitleClick = null,
	showProgressBar = false,
	progressBarPercent = 0,
	subNavigation = null,
} ) => {
	const { goToWooCommercePaymentsTab } = useNavigation();
	const { isScrolled } = useIsScrolled();

	const className = classNames( 'ppcp-r-navigation-container', {
		'ppcp--is-scrolled': isScrolled,
	} );
	const titleClassName = classNames( 'ppcp--nav-title', {
		'ppcp--big': isMainTitle,
	} );

	const handleTitleClick = useCallback( () => {
		if ( exitOnTitleClick ) {
			goToWooCommercePaymentsTab();
		} else if ( 'function' === typeof onTitleClick ) {
			onTitleClick();
		}
	}, [ exitOnTitleClick, goToWooCommercePaymentsTab, onTitleClick ] );

	// Removes the excess padding at the top of the navigation bar.
	useLayoutEffect( () => {
		window.dispatchEvent( new Event( 'resize' ) );
	}, [] );

	return (
		<>
			<nav className={ className }>
				<div className="ppcp-r-navigation">
					<BusyStateWrapper
						className="ppcp-r-navigation--left"
						busySpinner={ false }
						enabled={ ! exitOnTitleClick }
					>
						<Button
							variant="link"
							onClick={ handleTitleClick }
							className="is-title"
						>
							<Icon icon={ chevronLeft } />
							<span className={ titleClassName }>{ title }</span>
						</Button>
					</BusyStateWrapper>

					<BusyStateWrapper
						className="ppcp-r-navigation--right"
						busySpinner={ false }
					>
						{ children }
					</BusyStateWrapper>
				</div>

				{ subNavigation && (
					<section className="ppcp--top-sub-navigation">
						{ subNavigation }
					</section>
				) }

				{ showProgressBar && (
					<ProgressBar percent={ progressBarPercent } />
				) }
			</nav>
		</>
	);
};

const ProgressBar = ( { percent } ) => {
	percent = Math.min( Math.max( percent, 0 ), 100 );

	return (
		<div
			className="ppcp-r-navigation--progress-bar"
			style={ { width: `${ percent }%` } }
		/>
	);
};

export default TopNavigation;
