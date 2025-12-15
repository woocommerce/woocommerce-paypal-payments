/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
/* unused harmony export cardFieldStyles */
const cardFieldStyles = field => {
  const allowedProperties = ['appearance', 'color', 'direction', 'font', 'font-family', 'font-size', 'font-size-adjust', 'font-stretch', 'font-style', 'font-variant', 'font-variant-alternates', 'font-variant-caps', 'font-variant-east-asian', 'font-variant-ligatures', 'font-variant-numeric', 'font-weight', 'letter-spacing', 'line-height', 'opacity', 'outline', 'padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top', 'text-shadow', 'transition', '-moz-appearance', '-moz-osx-font-smoothing', '-moz-tap-highlight-color', '-moz-transition', '-webkit-appearance', '-webkit-osx-font-smoothing', '-webkit-tap-highlight-color', '-webkit-transition'];
  const stylesRaw = window.getComputedStyle(field);
  const styles = {};
  Object.values(stylesRaw).forEach(prop => {
    if (!stylesRaw[prop] || !allowedProperties.includes(prop)) {
      return;
    }
    styles[prop] = '' + stylesRaw[prop];
  });
  return styles;
};
/******/ })()
;
//# sourceMappingURL=helper.js.map