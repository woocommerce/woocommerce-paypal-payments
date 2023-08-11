
/**
 * Common Form utility methods
 */
export default class FormHelper {

    static getPrefixedFields(formElement, prefix) {
        const formData = new FormData(formElement);
        let fields = {};

        for (const [name, value] of formData.entries()) {
            if (!prefix || name.startsWith(prefix)) {
                fields[name] = value;
            }
        }

        return fields;
    }

    static getFilteredFields(formElement, exactFilters, prefixFilters) {
        const formData = new FormData(formElement);
        let fields = {};
        let counters = {};

        for (let [name, value] of formData.entries()) {

            // Handle array format
            if (name.indexOf('[]') !== -1) {
                const k = name;
                counters[k] = counters[k] || 0;
                name = name.replace('[]', `[${counters[k]}]`);
                counters[k]++;
            }

            if (!name) {
                continue;
            }
            if (exactFilters && (exactFilters.indexOf(name) !== -1)) {
                continue;
            }
            if (prefixFilters && prefixFilters.some(prefixFilter => name.startsWith(prefixFilter))) {
                continue;
            }

            fields[name] = value;
        }

        return fields;
    }
}
