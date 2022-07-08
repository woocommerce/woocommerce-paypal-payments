const dccInputFactory = (original) => {
    const styles = window.getComputedStyle(original);
    const newElement = document.createElement('span');

    newElement.setAttribute('id', original.id);
    newElement.setAttribute('class', original.className);

    Object.values(styles).forEach( (prop) => {
        if (! styles[prop] || ! isNaN(prop) || prop === 'background-image' ) {
            return;
        }
        newElement.style.setProperty(prop,'' + styles[prop]);
    });
    return newElement;
}

export default dccInputFactory;
