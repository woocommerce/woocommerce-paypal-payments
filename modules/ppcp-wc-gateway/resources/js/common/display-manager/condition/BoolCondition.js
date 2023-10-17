import BaseCondition from "./BaseCondition";

class BoolCondition extends BaseCondition {

    register() {
        this.status = this.check();
    }

    check() {
        return !! this.config.value;
    }

}

export default BoolCondition;
