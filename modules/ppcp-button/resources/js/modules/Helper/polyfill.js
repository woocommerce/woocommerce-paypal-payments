if (!window.Object.entries)
    window.Object.entries = function( obj ){
        var ownProps = Object.keys( obj ),
            i = ownProps.length,
            resArray = new Array(i); // preallocate the Array
        while (i--)
            resArray[i] = [ownProps[i], obj[ownProps[i]]];

        return resArray;
    };
const POLYFILLS_LOADED = true
export default POLYFILLS_LOADED;