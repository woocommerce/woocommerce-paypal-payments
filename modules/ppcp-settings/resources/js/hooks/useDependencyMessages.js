// hooks/useDependencyMessages.js
import { useMemo } from '@wordpress/element';
import PaymentDependencyMessage from '../Components/Screens/Settings/Components/Payment/PaymentDependencyMessage';
import PaymentMethodValueDependencyMessage from '../Components/Screens/Settings/Components/Payment/PaymentMethodValueDependencyMessage';
import SettingDependencyMessage from '../Components/Screens/Settings/Components/Payment/SettingDependencyMessage';

/**
 * Hook to process dependency messages for all methods
 *
 * @param {Array}   methods             - List of payment methods
 * @param {Object}  paymentDependencies - Payment method dependencies
 * @param {Object}  settingDependencies - Setting dependencies
 * @param {boolean} isDisabled          - Whether methods are globally disabled
 * @return {Object} Map of method IDs to their dependency messages and disabled states
 */
const useDependencyMessages = (
	methods,
	paymentDependencies,
	settingDependencies,
	isDisabled = false
) => {
	return useMemo( () => {
		const result = {};

		if ( ! methods || ! methods.length ) {
			return result;
		}

		// Process each method once to create their dependency messages.
		methods.forEach( ( method ) => {
			if ( ! method || ! method.id ) {
				return;
			}

			let dependencyMessage = null;
			let isMethodDisabled = method.isDisabled || isDisabled;

			// Check payment dependencies
			const dependency = paymentDependencies?.[ method.id ];
			if ( dependency ) {
				if ( dependency.type === 'parent' ) {
					dependencyMessage = (
						<PaymentDependencyMessage
							parentId={ dependency.parentId }
							parentName={ dependency.parentName }
						/>
					);
				} else if ( dependency.type === 'value' ) {
					dependencyMessage = (
						<PaymentMethodValueDependencyMessage
							dependentMethodId={ dependency.dependentId }
							dependentMethodName={ dependency.dependentName }
							requiredValue={ dependency.requiredValue }
						/>
					);
				}
				isMethodDisabled = true;
			}
			// Check setting dependencies
			else if ( settingDependencies?.[ method.id ]?.isDisabled ) {
				const settingDependency = settingDependencies[ method.id ];
				dependencyMessage = (
					<SettingDependencyMessage
						settingId={ settingDependency.settingId }
						requiredValue={ settingDependency.requiredValue }
						methodId={ method.id }
					/>
				);
				isMethodDisabled = true;
			}

			// Store the results for this method
			result[ method.id ] = {
				dependencyMessage,
				isMethodDisabled,
			};
		} );

		return result;
	}, [ methods, paymentDependencies, settingDependencies, isDisabled ] );
};

export default useDependencyMessages;
