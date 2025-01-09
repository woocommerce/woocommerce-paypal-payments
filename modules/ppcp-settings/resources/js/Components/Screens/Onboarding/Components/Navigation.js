import { Button, Icon } from '@wordpress/components';
import { chevronLeft } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

import { OnboardingHooks } from '../../../../data';
import useIsScrolled from '../../../../hooks/useIsScrolled';
import BusyStateWrapper from '../../../ReusableComponents/BusyStateWrapper';

const Navigation = ( { stepDetails, onNext, onPrev, onExit } ) => {
	const { title, isFirst, percentage, showNext, canProceed } = stepDetails;
	const { isScrolled } = useIsScrolled();

	const state = OnboardingHooks.useNavigationState();
	const isDisabled = ! canProceed( state );
	const className = classNames( 'ppcp-r-navigation-container', {
		'is-scrolled': isScrolled,
	} );

	return (
		<div className={ className }>
			<div className="ppcp-r-navigation">
				<BusyStateWrapper
					className="ppcp-r-navigation--left"
					busySpinner={ false }
					enabled={ ! isFirst }
				>
					<Button
						variant="link"
						onClick={ isFirst ? onExit : onPrev }
						className="is-title"
					>
						<Icon icon={ chevronLeft } />
						<span className={ 'title ' + ( isFirst ? 'big' : '' ) }>
							{ title }
						</span>
					</Button>
				</BusyStateWrapper>
				{ ! isFirst &&
					NextButton( { showNext, isDisabled, onNext, onExit } ) }
				<ProgressBar percent={ percentage } />
			</div>
		</div>
	);
};

const NextButton = ( { showNext, isDisabled, onNext, onExit } ) => {
	return (
		<BusyStateWrapper
			className="ppcp-r-navigation--right"
			busySpinner={ false }
		>
			<Button variant="link" onClick={ onExit }>
				{ __( 'Save and exit', 'woocommerce-paypal-payments' ) }
			</Button>
			{ showNext && (
				<Button
					variant="primary"
					disabled={ isDisabled }
					onClick={ onNext }
				>
					{ __( 'Continue', 'woocommerce-paypal-payments' ) }
				</Button>
			) }
		</BusyStateWrapper>
	);
};

const ProgressBar = ( { percent } ) => {
	percent = Math.min( Math.max( percent, 0 ), 100 );

	return (
		<div
			className="ppcp-r-navigation--progress-bar"
			style={ { width: `${ percent * 0.9 }%` } }
		/>
	);
};

export default Navigation;
