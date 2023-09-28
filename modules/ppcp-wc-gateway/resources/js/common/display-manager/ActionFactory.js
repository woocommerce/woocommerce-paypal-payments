import ElementAction from "./action/ElementAction";

class ActionFactory {
    static make(actionConfig) {
        switch (actionConfig.type) {
            case 'element':
                return new ElementAction(actionConfig);
        }

        throw new Error('[ActionFactory] Unknown action: ' + actionConfig.type);
    }
}

export default ActionFactory;
