export function log(message, level = 'info') {
    const endpoint = this.axoConfig?.ajax?.frontend_logger?.endpoint;
    if(!endpoint) {
        return;
    }

    fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        body: JSON.stringify({
            nonce: this.axoConfig.ajax.frontend_logger.nonce,
            log: {
                message,
                level,
            }
        })
    }).then(() => {
        switch (level) {
            case 'error':
                console.error(`[AXO] ${message}`);
                break;
            default:
                console.log(`[AXO] ${message}`);
        }
    });
}
