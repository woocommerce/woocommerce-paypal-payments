
/**
 * Common Form utility methods
 */
export default class FormHelper {

    static getPrefixedFields(formElement, prefix) {
        let fields = {};
        for(const element of formElement.elements) {
            if( element.name.startsWith(prefix) ) {
                fields[element.name] = element.value;
            }
        }
        return fields;
    }

}
