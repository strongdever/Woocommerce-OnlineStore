(window.webpackJsonp=window.webpackJsonp||[]).push([[2,5],{58:function(t,e,r){},60:function(t,e,r){"use strict";r.r(e);var n=r(0),o=(r(58),r(14)),a=r.n(o),c=r(57),i=r(6),s=r(59),u=r(22),l=r(8),f=r(5);function h(t,e){return function(t){if(Array.isArray(t))return t}(t)||function(t,e){var r=null==t?null:"undefined"!=typeof Symbol&&t[Symbol.iterator]||t["@@iterator"];if(null==r)return;var n,o,a=[],c=!0,i=!1;try{for(r=r.call(t);!(c=(n=r.next()).done)&&(a.push(n.value),!e||a.length!==e);c=!0);}catch(t){i=!0,o=t}finally{try{c||null==r.return||r.return()}finally{if(i)throw o}}return a}(t,e)||function(t,e){if(!t)return;if("string"==typeof t)return p(t,e);var r=Object.prototype.toString.call(t).slice(8,-1);"Object"===r&&t.constructor&&(r=t.constructor.name);if("Map"===r||"Set"===r)return Array.from(t);if("Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r))return p(t,e)}(t,e)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}function p(t,e){(null==e||e>t.length)&&(e=t.length);for(var r=0,n=new Array(e);r<e;r++)n[r]=t[r];return n}e.default=function(){var t=location&&location.search?Object(l.parse)(location.search.substring(1)):{},e=Object(c.a)().setIsRightHeader,r=h(Object(n.useState)(""),2),o=r[0],p=r[1],b=h(Object(n.useState)(!1),2),m=b[0],y=b[1],d=h(Object(n.useState)(!1),2),w=d[0],v=d[1],g=h(Object(n.useState)(!1),2),O=g[0],j=g[1];Object(n.useEffect)((function(){e(!1)}),[]),Object(n.useEffect)((function(){try{a()({path:"fkwcs-onboard/error-stripe",method:"get"}).then((function(t){t.status&&p(t.data.connect_url)}))}catch(t){console.log(t)}}),[]);return Object(n.useEffect)((function(){O&&Object(u.b)({},"/checkout",t)}),[O]),Object(n.createElement)("div",{className:"bwf-container"},!O&&Object(n.createElement)("div",{className:"bwf-failed-container"},Object(n.createElement)("div",{className:"bwf-align-center bwf-mb-16 bwf-mt-72"},Object(n.createElement)("img",{src:"".concat(bwfsg.images_dir,"Failed.png")})),Object(n.createElement)("div",{className:"bwf-page-title bwf-mb-4 bwf-align-center"},Object(f.__)("Oops! Unable to connect","funnelkit-stripe-woo-payment-gateway")),Object(n.createElement)("p",{className:"bwf-align-center bwf-description bwf-mb-16"},Object(f.__)("Try again or add API Keys manually","funnelkit-stripe-woo-payment-gateway")),Object(n.createElement)("div",{className:"bwf-align-center "},Object(n.createElement)(i.Button,{isPrimary:!0,className:"bwf-button",onClick:function(){y(!0),window.location.replace(o)},isBusy:m,disabled:m},Object(f.__)("Try Again","funnelkit-stripe-woo-payment-gateway")),Object(n.createElement)("br",null),Object(n.createElement)("button",{className:"bwf-page-default-btn bwf-mt-16",onClick:function(){return v(!0)}},Object(f.__)("Add API keys manually","funnelkit-stripe-woo-payment-gateway")))),w&&Object(n.createElement)(s.a,{isFailed:!0,onCloseModal:function(){return v(!1)},onSuccess:function(){v(!1),j(!0)}}))}},62:function(t,e,r){},64:function(t,e,r){"use strict";r.r(e);var n=r(0),o=r(6),a=r(60),c=(r(62),r(14)),i=r.n(c),s=r(57),u=r(22),l=r(8),f=r(5);function h(t){return(h="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t})(t)}function p(){p=function(){return t};var t={},e=Object.prototype,r=e.hasOwnProperty,n="function"==typeof Symbol?Symbol:{},o=n.iterator||"@@iterator",a=n.asyncIterator||"@@asyncIterator",c=n.toStringTag||"@@toStringTag";function i(t,e,r){return Object.defineProperty(t,e,{value:r,enumerable:!0,configurable:!0,writable:!0}),t[e]}try{i({},"")}catch(t){i=function(t,e,r){return t[e]=r}}function s(t,e,r,n){var o=e&&e.prototype instanceof f?e:f,a=Object.create(o.prototype),c=new k(n||[]);return a._invoke=function(t,e,r){var n="suspendedStart";return function(o,a){if("executing"===n)throw new Error("Generator is already running");if("completed"===n){if("throw"===o)throw a;return S()}for(r.method=o,r.arg=a;;){var c=r.delegate;if(c){var i=j(c,r);if(i){if(i===l)continue;return i}}if("next"===r.method)r.sent=r._sent=r.arg;else if("throw"===r.method){if("suspendedStart"===n)throw n="completed",r.arg;r.dispatchException(r.arg)}else"return"===r.method&&r.abrupt("return",r.arg);n="executing";var s=u(t,e,r);if("normal"===s.type){if(n=r.done?"completed":"suspendedYield",s.arg===l)continue;return{value:s.arg,done:r.done}}"throw"===s.type&&(n="completed",r.method="throw",r.arg=s.arg)}}}(t,r,c),a}function u(t,e,r){try{return{type:"normal",arg:t.call(e,r)}}catch(t){return{type:"throw",arg:t}}}t.wrap=s;var l={};function f(){}function b(){}function m(){}var y={};i(y,o,(function(){return this}));var d=Object.getPrototypeOf,w=d&&d(d(_([])));w&&w!==e&&r.call(w,o)&&(y=w);var v=m.prototype=f.prototype=Object.create(y);function g(t){["next","throw","return"].forEach((function(e){i(t,e,(function(t){return this._invoke(e,t)}))}))}function O(t,e){var n;this._invoke=function(o,a){function c(){return new e((function(n,c){!function n(o,a,c,i){var s=u(t[o],t,a);if("throw"!==s.type){var l=s.arg,f=l.value;return f&&"object"==h(f)&&r.call(f,"__await")?e.resolve(f.__await).then((function(t){n("next",t,c,i)}),(function(t){n("throw",t,c,i)})):e.resolve(f).then((function(t){l.value=t,c(l)}),(function(t){return n("throw",t,c,i)}))}i(s.arg)}(o,a,n,c)}))}return n=n?n.then(c,c):c()}}function j(t,e){var r=t.iterator[e.method];if(void 0===r){if(e.delegate=null,"throw"===e.method){if(t.iterator.return&&(e.method="return",e.arg=void 0,j(t,e),"throw"===e.method))return l;e.method="throw",e.arg=new TypeError("The iterator does not provide a 'throw' method")}return l}var n=u(r,t.iterator,e.arg);if("throw"===n.type)return e.method="throw",e.arg=n.arg,e.delegate=null,l;var o=n.arg;return o?o.done?(e[t.resultName]=o.value,e.next=t.nextLoc,"return"!==e.method&&(e.method="next",e.arg=void 0),e.delegate=null,l):o:(e.method="throw",e.arg=new TypeError("iterator result is not an object"),e.delegate=null,l)}function E(t){var e={tryLoc:t[0]};1 in t&&(e.catchLoc=t[1]),2 in t&&(e.finallyLoc=t[2],e.afterLoc=t[3]),this.tryEntries.push(e)}function x(t){var e=t.completion||{};e.type="normal",delete e.arg,t.completion=e}function k(t){this.tryEntries=[{tryLoc:"root"}],t.forEach(E,this),this.reset(!0)}function _(t){if(t){var e=t[o];if(e)return e.call(t);if("function"==typeof t.next)return t;if(!isNaN(t.length)){var n=-1,a=function e(){for(;++n<t.length;)if(r.call(t,n))return e.value=t[n],e.done=!1,e;return e.value=void 0,e.done=!0,e};return a.next=a}}return{next:S}}function S(){return{value:void 0,done:!0}}return b.prototype=m,i(v,"constructor",m),i(m,"constructor",b),b.displayName=i(m,c,"GeneratorFunction"),t.isGeneratorFunction=function(t){var e="function"==typeof t&&t.constructor;return!!e&&(e===b||"GeneratorFunction"===(e.displayName||e.name))},t.mark=function(t){return Object.setPrototypeOf?Object.setPrototypeOf(t,m):(t.__proto__=m,i(t,c,"GeneratorFunction")),t.prototype=Object.create(v),t},t.awrap=function(t){return{__await:t}},g(O.prototype),i(O.prototype,a,(function(){return this})),t.AsyncIterator=O,t.async=function(e,r,n,o,a){void 0===a&&(a=Promise);var c=new O(s(e,r,n,o),a);return t.isGeneratorFunction(r)?c:c.next().then((function(t){return t.done?t.value:c.next()}))},g(v),i(v,c,"Generator"),i(v,o,(function(){return this})),i(v,"toString",(function(){return"[object Generator]"})),t.keys=function(t){var e=[];for(var r in t)e.push(r);return e.reverse(),function r(){for(;e.length;){var n=e.pop();if(n in t)return r.value=n,r.done=!1,r}return r.done=!0,r}},t.values=_,k.prototype={constructor:k,reset:function(t){if(this.prev=0,this.next=0,this.sent=this._sent=void 0,this.done=!1,this.delegate=null,this.method="next",this.arg=void 0,this.tryEntries.forEach(x),!t)for(var e in this)"t"===e.charAt(0)&&r.call(this,e)&&!isNaN(+e.slice(1))&&(this[e]=void 0)},stop:function(){this.done=!0;var t=this.tryEntries[0].completion;if("throw"===t.type)throw t.arg;return this.rval},dispatchException:function(t){if(this.done)throw t;var e=this;function n(r,n){return c.type="throw",c.arg=t,e.next=r,n&&(e.method="next",e.arg=void 0),!!n}for(var o=this.tryEntries.length-1;o>=0;--o){var a=this.tryEntries[o],c=a.completion;if("root"===a.tryLoc)return n("end");if(a.tryLoc<=this.prev){var i=r.call(a,"catchLoc"),s=r.call(a,"finallyLoc");if(i&&s){if(this.prev<a.catchLoc)return n(a.catchLoc,!0);if(this.prev<a.finallyLoc)return n(a.finallyLoc)}else if(i){if(this.prev<a.catchLoc)return n(a.catchLoc,!0)}else{if(!s)throw new Error("try statement without catch or finally");if(this.prev<a.finallyLoc)return n(a.finallyLoc)}}}},abrupt:function(t,e){for(var n=this.tryEntries.length-1;n>=0;--n){var o=this.tryEntries[n];if(o.tryLoc<=this.prev&&r.call(o,"finallyLoc")&&this.prev<o.finallyLoc){var a=o;break}}a&&("break"===t||"continue"===t)&&a.tryLoc<=e&&e<=a.finallyLoc&&(a=null);var c=a?a.completion:{};return c.type=t,c.arg=e,a?(this.method="next",this.next=a.finallyLoc,l):this.complete(c)},complete:function(t,e){if("throw"===t.type)throw t.arg;return"break"===t.type||"continue"===t.type?this.next=t.arg:"return"===t.type?(this.rval=this.arg=t.arg,this.method="return",this.next="end"):"normal"===t.type&&e&&(this.next=e),l},finish:function(t){for(var e=this.tryEntries.length-1;e>=0;--e){var r=this.tryEntries[e];if(r.finallyLoc===t)return this.complete(r.completion,r.afterLoc),x(r),l}},catch:function(t){for(var e=this.tryEntries.length-1;e>=0;--e){var r=this.tryEntries[e];if(r.tryLoc===t){var n=r.completion;if("throw"===n.type){var o=n.arg;x(r)}return o}}throw new Error("illegal catch attempt")},delegateYield:function(t,e,r){return this.delegate={iterator:_(t),resultName:e,nextLoc:r},"next"===this.method&&(this.arg=void 0),l}},t}function b(t,e,r,n,o,a,c){try{var i=t[a](c),s=i.value}catch(t){return void r(t)}i.done?e(s):Promise.resolve(s).then(n,o)}function m(t,e){return function(t){if(Array.isArray(t))return t}(t)||function(t,e){var r=null==t?null:"undefined"!=typeof Symbol&&t[Symbol.iterator]||t["@@iterator"];if(null==r)return;var n,o,a=[],c=!0,i=!1;try{for(r=r.call(t);!(c=(n=r.next()).done)&&(a.push(n.value),!e||a.length!==e);c=!0);}catch(t){i=!0,o=t}finally{try{c||null==r.return||r.return()}finally{if(i)throw o}}return a}(t,e)||function(t,e){if(!t)return;if("string"==typeof t)return y(t,e);var r=Object.prototype.toString.call(t).slice(8,-1);"Object"===r&&t.constructor&&(r=t.constructor.name);if("Map"===r||"Set"===r)return Array.from(t);if("Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r))return y(t,e)}(t,e)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}function y(t,e){(null==e||e>t.length)&&(e=t.length);for(var r=0,n=new Array(e);r<e;r++)n[r]=t[r];return n}e.default=function(){var t=location&&location.search?Object(l.parse)(location.search.substring(1)):{},e=m(Object(n.useState)(!1),2),r=e[0],c=e[1],h=m(Object(n.useState)(!1),2),y=h[0],d=h[1],w=m(Object(n.useState)(!1),2),v=w[0],g=w[1],O=m(Object(n.useState)(!0),2),j=O[0],E=O[1],x=Object(s.a)(),k=x.setIsEnableGateways,_=x.setIsEnableWebhook,S=x.setExpressCheckout,N=x.setIsRightHeader;Object(n.useEffect)((function(){N(!0),k("success"),_("success"),S("active")}),[]);var L=function(){var e,r=(e=p().mark((function e(){var r;return p().wrap((function(e){for(;;)switch(e.prev=e.next){case 0:return g(!0),e.prev=1,r={webhook:{checkout:!0}},e.next=5,i()({path:"fkwcs-onboard/enable-checkout",method:"POST",data:r}).then((function(e){e.status?(d(!0),S("success"),N(!1),Object(u.b)({},"/success",t)):(c(!0),d(!0),N(!1),g(!1))}));case 5:e.next=10;break;case 7:e.prev=7,e.t0=e.catch(1),console.log(e.t0);case 10:case"end":return e.stop()}}),e,null,[[1,7]])})),function(){var t=this,r=arguments;return new Promise((function(n,o){var a=e.apply(t,r);function c(t){b(a,n,o,c,i,"next",t)}function i(t){b(a,n,o,c,i,"throw",t)}c(void 0)}))});return function(){return r.apply(this,arguments)}}();return Object(n.createElement)("div",{className:"bwf-container"},!y&&Object(n.createElement)("div",{className:"bwf-express-container"},Object(n.createElement)("div",{className:"bwf-align-center bwf-mb-16 bwf-mt-48"},Object(n.createElement)("img",{src:"".concat(bwfsg.images_dir,"Shopping.png")})),Object(n.createElement)("div",{className:"bwf-title bwf-mb-4"},Object(f.__)("Almost Done! Enable Express Checkout","funnelkit-stripe-woo-payment-gateway")),Object(n.createElement)("p",{className:"bwf-align-center bwf-description bwf-mb-36"},Object(f.__)("To get more conversions, we recommend you to enable Express Checkout for Google and Apple Pay.","funnelkit-stripe-woo-payment-gateway")),Object(n.createElement)("div",{className:"bwf-express-checkout"},Object(n.createElement)("div",{className:"bwf-flex bwf-flex-col"},Object(n.createElement)("div",{className:"bwf-flex bwf-gap-30"},Object(n.createElement)("img",{src:"".concat(bwfsg.images_dir,"GooglePay.svg"),className:"bwf-width-64"}),Object(n.createElement)("img",{src:"".concat(bwfsg.images_dir,"AppleLogo.svg"),className:"bwf-width-64"})),Object(n.createElement)("p",{className:"bwf-mt-8 bwf-desc"},Object(f.__)("Express Payment buttons will show on appropriate device. ","funnelkit-stripe-woo-payment-gateway"),Object(n.createElement)("a",{href:"https://funnelkit.com/docs/stripe-gateway-for-woocommerce/troubleshooting/express-payment-buttons-not-showing/",className:"bwf-a-no-underline",target:"_blank"},Object(f.__)("Learn More.","funnelkit-stripe-woo-payment-gateway")))),Object(n.createElement)(o.ToggleControl,{checked:j,onChange:function(){return E((function(t){return!t}))}})),Object(n.createElement)("div",{className:"bwf-align-center bwf-mt-36 bwf-flex bwf-flex-col"},Object(n.createElement)(o.Button,{isPrimary:!0,className:"bwf-button",onClick:L,isBusy:v,disabled:v},Object(f.__)("Confirm","funnelkit-stripe-woo-payment-gateway")),Object(n.createElement)(o.Button,{className:"components-button is-secondary bwf-mt-12",onClick:function(){Object(u.b)({},"/success",t)}},Object(f.__)("Skip this Step","funnelkit-stripe-woo-payment-gateway")))),r&&Object(n.createElement)(a.default,null))}}}]);