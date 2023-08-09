
/**
 * Common Form utility methods
 */
export default class FormHelper {

    static getPrefixedFields(formElement, prefix) {
        let fields = {};
        for(const element of formElement.elements) {
            if( ( ! prefix ) || element.name.startsWith(prefix) ) {
                fields[element.name] = element.value;
            }
        }
        return fields;
    }

    static getFilteredFields(formElement, exactFilters, prefixFilters) {
        let fields = {};

        for(const element of formElement.elements) {
            if (!element.name) {
                continue;
            }
            if (exactFilters && (exactFilters.indexOf(element.name) !== -1)) {
                continue;
            }
            if (prefixFilters && prefixFilters.some(prefixFilter => element.name.startsWith(prefixFilter))) {
                continue;
            }
            fields[element.name] = element.value;
        }
        return fields;
    }

}
