const storageKey = 'ppcp-data-client-id';

const validateToken = (token, user) => {
    if (! token) {
        return false;
    }
    if (token.user !== user) {
        return false;
    }
    const currentTime = new Date().getTime();
    const isExpired = currentTime >= token.expiration * 1000;
    return ! isExpired;
}

const storedTokenForUser = (user) => {
    const token = JSON.parse(sessionStorage.getItem(storageKey));
    if (validateToken(token, user)) {
        return token.token;
    }
    return null;
}

const storeToken = (token) => {
    sessionStorage.setItem(storageKey, JSON.stringify(token));
}

const dataClientIdAttributeHandler = (script, config) => {
    fetch(config.endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            nonce: config.nonce
        })
    }).then((res)=>{
        return res.json();
    }).then((data)=>{
        const isValid = validateToken(data, config.user);
        if (!isValid) {
            return;
        }
        storeToken(data);
        script.setAttribute('data-client-token', data.token);
        document.body.appendChild(script);
    });
}

export default dataClientIdAttributeHandler;
