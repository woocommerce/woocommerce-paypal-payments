export const toCamelCase = (str) => {
    return str.replace(/([-_]\w)/g, function(match) {
        return match[1].toUpperCase();
    });
}

export const keysToCamelCase = (obj) => {
    let output = {};
    for (const key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
            output[toCamelCase(key)] = obj[key];
        }
    }
    return output;
}

export const strAddWord = (str, word, separator = ',') => {
    let arr = str.split(separator);
    if (!arr.includes(word)) {
        arr.push(word);
    }
    return arr.join(separator);
};

export const strRemoveWord = (str, word, separator = ',') => {
    let arr = str.split(separator);
    let index = arr.indexOf(word);
    if (index !== -1) {
        arr.splice(index, 1);
    }
    return arr.join(separator);
};

const Utils = {
    toCamelCase,
    keysToCamelCase,
    strAddWord,
    strRemoveWord
};

export default Utils;
