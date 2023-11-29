import BaseAction from "./BaseAction";

class ElementAction extends BaseAction {

    run(status) {

        if (status) {
            if (this.config.action === 'visible') {
                jQuery(this.config.selector).removeClass('ppcp-field-hidden');
            }
            if (this.config.action === 'enable') {
                jQuery(this.config.selector).removeClass('ppcp-field-disabled')
                    .off('mouseup')
                    .find('> *')
                    .css('pointer-events', '');
            }
        } else {
            if (this.config.action === 'visible') {
                jQuery(this.config.selector).addClass('ppcp-field-hidden');
            }
            if (this.config.action === 'enable') {
                jQuery(this.config.selector).addClass('ppcp-field-disabled')
                    .on('mouseup', function(event) {
                        event.stopImmediatePropagation();
                    })
                    .find('> *')
                    .css('pointer-events', 'none');
            }
        }

    }

}

export default ElementAction;
