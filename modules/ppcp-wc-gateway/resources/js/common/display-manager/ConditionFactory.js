import ElementCondition from "./condition/ElementCondition";
import BoolCondition from "./condition/BoolCondition";

class ConditionFactory {
    static make(conditionConfig, triggerUpdate) {
        switch (conditionConfig.type) {
            case 'element':
                return new ElementCondition(conditionConfig, triggerUpdate);
            case 'bool':
                return new BoolCondition(conditionConfig, triggerUpdate);
        }

        throw new Error('[ConditionFactory] Unknown condition: ' + conditionConfig.type);
    }
}

export default ConditionFactory;
