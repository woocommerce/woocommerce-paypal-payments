const dccInputFactory = (original) => {
    const styles = window.getComputedStyle(original);
    const newElement = document.createElement('span');
    newElement.setAttribute('id', original.id);
    Object.values(styles).forEach( (prop) => {
        if (! styles[prop] || ! isNaN(prop) ) {
            return;
        }
        newElement.style.setProperty(prop,'' + styles[prop]);
    });
    return newElement;
}

export default dccInputFactory;