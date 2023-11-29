import BaseCondition from "./BaseCondition";
import {inputValue} from "../../../helper/form";

class ElementCondition extends BaseCondition {

    register() {
        jQuery(document).on('change', this.config.selector, () => {
            const status = this.check();
            if (status !== this.status) {
                this.status = status;
                this.triggerUpdate();
            }
        });

        this.status = this.check();
    }

    check() {
        let value = inputValue(this.config.selector);
        value = (value !== null ? value.toString() : value);

        return this.config.value === value;
    }

}

export default ElementCondition;
