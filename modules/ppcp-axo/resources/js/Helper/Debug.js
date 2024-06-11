export function log(message, level = 'info') {
    const endpoint = window.wc_ppcp_axo?.ajax?.frontend_logger?.endpoint;
    if(!endpoint) {
        return;
    }

    fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        body: JSON.stringify({
            nonce: window.wc_ppcp_axo.ajax.frontend_logger.nonce,
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
