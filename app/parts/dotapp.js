/*
    DOTAPP.js
    Author: Štefan Miščík
    Website: https://dotsystems.sk/
    Email: info@dotsystems.sk

    Version: 1.0 (Stable)
    License: MIT License

    Description:
    A lightweight and customizable JavaScript framework for building web applications.

    Note:
    You are free to use, modify, and distribute this code. Please include this header with the author's information in all copies or substantial portions of the software.
*/

(function (global) {

    class DotAppVariable {
        #name;
        #variable;
        #initialValue;
        #callbacks = [];
    
        constructor(variable,initialValue) {
            this.#name = variable;
            this.#variable = initialValue;
            this.#initialValue = initialValue;            
        }

        get value() {
            return this.#variable;
        }

        get name() {
            return this.#name;
        }
    
        set value(newValue) {
            if (newValue !== this.#variable) {
                const oldValue = this.#variable;
                this.#variable = newValue;
                if (this.#callbacks.length > 0) {
                    this.#callbacks.forEach(fn => fn(oldValue, newValue));
                }
            }
        }
    
        onChange(callback) {
            this.#callbacks.push(callback);
            // Vratime objekt listenera
            // neskor vyuzivame napriklad: listener1.off();
            const listener = {
                off: () => {
                    this.#callbacks = this.#callbacks.filter(fn => fn !== callback);
                    listener.isActive = false;
                },
                isActive: true,
                pause: () => {
                    this.#callbacks = this.#callbacks.filter(fn => fn !== callback);
                    listener.isActive = false;
                },
                resume: () => {
                    if (!listener.isActive) {
                        this.#callbacks.push(callback);
                        listener.isActive = true;
                    }
                }
            };
            return listener;
        }

        watch(callback, now = false) {
            if (now) callback(undefined, this.#variable);
            return this.onChange(callback);
        }
    
        reset(initialValue = this.#initialValue) {
            this.value = initialValue;
        }

        hasChange() {
            return this.#variable !== this.#initialValue;
        }
    
        // Len alias ak by mal niekto zvyk
        isDirty() {
            this.hasChange();
        }
    
        toString() {
            return `${this.#name}: ${this.#variable}`;
        }
    }

    class DotApp {
        #bridgeelements = new WeakMap();
        #bridges = {}
        #routes = {}
        #hooks = {}
        #variables = {}

        constructor() {
            if (!localStorage.getItem('ckey')) {
                this.#exchange();
            }
            this.#bridgeinputs();            
            this.#_dotlink();
            this.#_protectMethods();
            this.#initializeBridgeElements();
            window.dispatchEvent(new Event('dotapp'));
        }

        #_dotlink() {
            document.addEventListener("click", function(event) {
                let target = event.target.closest("[dotlink]");
                if (target) {
                    let url = target.getAttribute("dotlink");
            
                    if (url) {
                        window.location.href = url;
                    }
                }
            });
        }

        #_protectMethods() {
            // Get all methods from the prototype of this class
            const methods = Object.getOwnPropertyNames(Object.getPrototypeOf(this));
    
            methods.forEach(method => {
                // Skip the constructor
                if (method !== 'constructor') {
                    // Protect the method from being overwritten or deleted
                    Object.defineProperty(this, method, {
                        value: this[method],
                        writable: false,   // Prevents overwriting the method
                        configurable: false // Prevents reconfiguration or deletion
                    });
                }
            });
        }
		
		#exchange() {}

        #bridgeinputs() {
            const input_elements = document.querySelectorAll('[dotbridge-input]');
            input_elements.forEach((element) => {
                if (element.tagName === 'INPUT'
                   && (element.getAttribute('type') == "text" || element.getAttribute('type') == "password")
                    && (this.isSet(element.getAttribute('dotbridge-pattern')))
                ) {
                    var pattern = new RegExp(atob(element.getAttribute('dotbridge-pattern')).trim());

                    var minlength = 0;
                    if (this.isSet(element.getAttribute('dotbridge-min'))) {
                        minlength = element.getAttribute('dotbridge-min');
                    }

                    var okClass = "val-ok";
                    if (this.isSet(element.getAttribute('dotbridge-ok'))) {
                         okClass = element.getAttribute('dotbridge-ok');
                    }

                    var badClass = "val-bad";
                    if (this.isSet(element.getAttribute('dotbridge-bad'))) {
                            badClass = element.getAttribute('dotbridge-bad');
                    }

                    element.addEventListener("change", () => {
                        if (element.value.length == 0) {
                             element.classList.remove(okClass);
                             element.classList.remove(badClass);
                        } else if (element.value.length >= minlength) {
                            if (pattern.test(element.value.trim())) {
                                element.classList.add(okClass);
                                element.classList.remove(badClass);
                                element.setAttribute('dotbridge-result',1);
                            } else {
                                element.classList.remove(okClass);
                                element.classList.add(badClass);
                                element.setAttribute('dotbridge-result',0);
                            }
                        } else {
                            element.classList.remove(okClass);
                            element.classList.add(badClass);
                            element.setAttribute('dotbridge-result',0);
                        }
                    });
                }
            });
        }

        #initializeBridgeElements() {
            const bridgeElements = document.querySelectorAll('[dotbridge-function]');
            bridgeElements.forEach(element => {
                // Uložíme atribúty do objektu elementu
                element._dotbridgeData = {
                    key: element.getAttribute('dotbridge-key'),
                    data: element.getAttribute('dotbridge-data'),
                    id: element.getAttribute('dotbridge-id'),
                    dataId: element.getAttribute('dotbridge-data-id'),
                    event: element.getAttribute('dotbridge-event'),
                    functionName: element.getAttribute('dotbridge-function'),
                    inputs: element.getAttribute('dotbridge-inputs'),
                    eventArg: element.getAttribute('dotbridge-event-arg')
                };

                // Odstránime atribúty z DOM-u
                element.removeAttribute('dotbridge-key');
                element.removeAttribute('dotbridge-data');
                element.removeAttribute('dotbridge-id');
                element.removeAttribute('dotbridge-data-id');
                element.removeAttribute('dotbridge-inputs');
                element.removeAttribute('dotbridge-event-arg');
            });
        }

        bridge(functionName, event) {
            if (this.isSet(this.#bridges[functionName]) && this.isSet(this.#bridges[functionName][event])) {
                return this.#bridges[functionName][event];
            }

            const elements = document.querySelectorAll(`[dotbridge-function="${functionName}"][dotbridge-event="${event}"]`);
            var bridgeObj = this;

            const instance = {
                hooks: {},
                chain: {
                    before: (fn) => {
                        instance.chain.listen('before', fn);
                        return instance.chain;
                    },
                    after: (fn) => {
                        instance.chain.listen('after', fn);
                        return instance.chain;
                    },
                    onError: (fn) => {
                        instance.chain.listen('onError', fn);
                        return instance.chain;
                    },
                    onResponseCode: (fn) => {
                        instance.chain.listen('onResponseCode', fn, code);
                        return instance.chain;
                    },
                    onValueError: (fn) => {
                        instance.chain.listen('onValueError', fn);
                        return instance.chain;
                    },
                    listen: (hook, fn, code=0) => {
                        if (!this.isFunction(fn)) {
                            throw new Error('Second argument is not a function!');
                        }
                        const allowedHooks = ['before', 'after', 'onError', 'onValueError'];
                        if (allowedHooks.includes(hook)) {
                            if (!instance.hooks[hook]) {
                                instance.hooks[hook] = [];
                            }
                            instance.hooks[hook].push(fn);
                        } else {
                            const allowedHooks = ['onResponseCode'];
                            if (allowedHooks.includes(hook)) {
                                if (!instance.hooks[hook]) {
                                    instance.hooks[hook] = {};
                                    instance.hooks[hook][code] = [];
                                }
                                instance.hooks[hook][code].push(fn);
                            } else {
                                throw new Error(`Hook ${hook} is not allowed. Allowed hooks: before, after, onError, onResponseCode.`);
                            }
                        }
                        return instance.chain;
                    },
                },
            };
    
            elements.forEach((element) => {
                let postData = {};
                if (!this.isSet(element._eventenable)) {
                    element._eventenable = {};
                }                
                element._eventenable[event] = true;

                element.addEventListener(event, (e) => {

                    if (element._eventenable[event] == false) return;

                    if (element._dotbridgeData.eventArg) {
                        if (!this.isSet(element._eventkey)) {
                            element._eventkey = {};
                        }
                        element._eventkey[event] = element._dotbridgeData.eventArg;

                        if (! (e.key === element._eventkey[event])) {
                            return;
                        }
                    }

                    element._eventenable[event] = false;

                    let data = {
                        'dotbridge-key': element._dotbridgeData.key,
                        'dotbridge-data': element._dotbridgeData.data,
                        'dotbridge-id': element._dotbridgeData.id,
                        'dotbridge-data-id': element._dotbridgeData.dataId,
                        'dotbridge-event': event
                    };
    
                    postData['data'] = data;

                    var problems = 0;

                    if (element._dotbridgeData.inputs) {
                        let inputs = element._dotbridgeData.inputs;
                        let inputsarr = inputs.split(",");
                        inputsarr.forEach(function(dotbridgeinput) {
                            const dotbridgeinput_elements = document.querySelectorAll('[dotbridge-input="' + dotbridgeinput + '"]');
                            dotbridgeinput_elements.forEach((elementobj) => {
                                // Check if the element is an input, textarea, or select element
                                if (elementobj.tagName === 'INPUT' || elementobj.tagName === 'TEXTAREA' || elementobj.tagName === 'SELECT') {
    
                                    if (elementobj.type === 'checkbox' || elementobj.type === 'radio') {
                                        data[dotbridgeinput] = elementobj.checked ? elementobj.value : 'Unchecked';
                                    } else {
                                        if (bridgeObj.isSet(elementobj.getAttribute('dotbridge-result')) && elementobj.getAttribute('dotbridge-result') == 0) {
                                            // Ak je problem s premennymi tak zavolame handler on Value error. Ak nie je definovany, tak pokracujeme...
                                            if (instance.hooks['onValueError']) {
                                                instance.hooks['onValueError'].forEach(fn => fn(dotbridgeinput,elementobj,e));
                                            }
                                            problems = 1;
                                        }
                                        data[dotbridgeinput] = elementobj.value;
                                    }
                                } else {
                                    data[dotbridgeinput] = null;
                                }
                            });
                        });
                    }

                    if (problems == 1) {
                        element._eventenable[event] = true;
                        return;
                    }

                    if (instance.hooks['before']) {
                        instance.hooks['before'].forEach(fn => fn(postData['data'],element,e));
                    }
                    
                    postData['crc'] = this.#crcCalculate(postData['data'],data['dotbridge-id']);
    
                    fetch(this.domain() + '/dotapp/bridge', {
                        method: 'POST',
                        body: this.#serializeData(postData),
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'dotbridge' : event
                        },
                    })
                        .then(response => {
                            if (!response.ok) {
                                if (instance.hooks['onResponseCode'] && instance.hooks['onResponseCode'][response.status]) {
                                    /* Call the handler for the specific status code
                                        400 - CRC check failed
                                        403 - Bridge keys does not match
                                        404 - Function not found - Not defined at PHP side
                                        429 - Rate limit exceeded
                                    */

                                    return response.text().then(text => {
                                        if (instance.hooks['onCode'][response.status]) {
                                            instance.hooks['onCode'][response.status].forEach(fn => fn(response.status,text,element,e));
                                        }
                                        element._eventenable[event] = true;
                                        return; // We called functions defined for statuses. Since we not return promise, next .then will not be executed.
                                    });
                                } else {
                                    element._eventenable[event] = true;
                                    throw new Error('Network response was not ok');
                                }
                            }
                            return response.text();
                        })
                        .then(data => {
                            try {
                                data = JSON.parse(atob(data));

                                if (this.isSet(data['dotbridge-regenerate'])) {
                                    if (data['dotbridge-regenerate'] == 1) {
                                        try {
                                            element.setAttribute('dotbridge-id', data['dotbridge-id']);
                                            element.setAttribute('dotbridge-data', data['dotbridge-data']);
                                            element.setAttribute('dotbridge-data-id', data['dotbridge-data-id']);
                                        } catch (err) {
                                            if (instance.hooks['onError']) {
                                                instance.hooks['onError'].forEach(fn => fn(err,element,e));
                                            }
                                            element._eventenable[event] = true;
                                        }
                                    }
                                }

                                if (instance.hooks['after']) {
                                    instance.hooks['after'].forEach(fn => fn(data,element,e));
                                }
                                
                                element._eventenable[event] = true;
                            } catch (err) {
                                if (instance.hooks['onError']) {
                                    instance.hooks['onError'].forEach(fn => fn(err,element,e));
                                }
                                element._eventenable[event] = true;
                            }
                        })
                        .catch(err => {
                            if (instance.hooks['onError']) {
                                instance.hooks['onError'].forEach(fn => fn(err,element,e));
                            }
                            element._eventenable[event] = true;
                        });
                });
            });
    
            if (! this.isSet(this.#bridges[functionName])) {
                this.#bridges[functionName] = {};
            }

            this.#bridges[functionName][event] = instance.chain;
            
            return instance.chain;
        }


        // Spúšťa event a posiela ľubovoľný počet argumentov
        trigger(eventname, ...variables) {
            if (this.#hooks[eventname]) {
                this.#hooks[eventname].forEach(handler => {
                    try {
                        handler(...variables);
                    } catch (error) {
                        console.error(`Error in handler for event ${eventname}:`, error);
                    }
                });
            }
        }

        // Registruje handler pre špecifický event
        on(eventname, handler) {
            if (!this.#hooks[eventname]) {
                this.#hooks[eventname] = [];
            }
            this.#hooks[eventname].push(handler);
            // Vratime funkciu pomocou ktorej vieme zrusit on. Ak ju zavolame zrusime tento jeden samostatny listener.
            // Ekvivalent v php casti frameworku event->off();
            return () => {
                this.#hooks[eventname] = this.#hooks[eventname].filter(h => h !== handler);
            };
        }
    
        hashRouter(route, handler) {
            this.#routes[route] = handler;
            this.checkHash();
            window.addEventListener('hashchange', this.checkHash.bind(this));
        }
    
        checkHash() {
            const currentHash = window.location.hash || '#default';
            this.trigger("route.onchange", currentHash, oldHash);
            if (this.#routes[currentHash]) {
                this.trigger("route." + currentHash + ".before");
                var navrat = this.#routes[currentHash]();
                this.trigger("route." + currentHash + ".after",navrat);
            } else if (this.#routes['#default']) {
                this.#routes['#default']();
            }
        }

        variable(variable, initialValue = undefined) {
            if (typeof variable === 'string' && /^[a-zA-Z_$][a-zA-Z0-9_$-]*$/.test(variable)) {
                if (!this.isSet(this.#variables[variable])) {
                    this.#variables[variable] = new DotAppVariable(variable, initialValue);
                }
                return this.#variables[variable];
            }
            return undefined;
        }
    
        isSet(value) {
            return value !== "" && value != null && typeof value !== "undefined" && (!value.jquery || value.length > 0);
        }
    
        isFunction(input) {
            return typeof input === 'function';
        }
    
        domain() {
            const { protocol, hostname } = window.location;
            return `${protocol}//${hostname}`;
        }
    
        #serializeData(data) {
            const urlParams = new URLSearchParams();
    
            function addToParams(prefix, obj) {
                for (let key in obj) {
                    if (obj.hasOwnProperty(key)) {
                        let value = obj[key];
                        let paramKey = prefix ? `${prefix}[${key}]` : key;
                        if (typeof value === 'object' && value !== null) {
                            addToParams(paramKey, value);
                        } else {
                            urlParams.append(paramKey, value);
                        }
                    }
                }
            }
    
            addToParams('', data);
            return urlParams.toString();
        }
    
        #crcCalculate(data, keo = '') {
            // Minified
            (function(_0x27f3cc,_0x2d383c){var _0x55131a=_0x463f,_0xc65e00=_0x27f3cc();while(!![]){try{var _0x405918=-parseInt(_0x55131a(0x172))/0x1*(-parseInt(_0x55131a(0x16a))/0x2)+parseInt(_0x55131a(0x177))/0x3+-parseInt(_0x55131a(0x16b))/0x4+-parseInt(_0x55131a(0x16f))/0x5+parseInt(_0x55131a(0x170))/0x6*(parseInt(_0x55131a(0x16d))/0x7)+parseInt(_0x55131a(0x174))/0x8*(-parseInt(_0x55131a(0x178))/0x9)+-parseInt(_0x55131a(0x171))/0xa*(parseInt(_0x55131a(0x173))/0xb);if(_0x405918===_0x2d383c)break;else _0xc65e00['push'](_0xc65e00['shift']());}catch(_0x330cbc){_0xc65e00['push'](_0xc65e00['shift']());}}}(_0x10f5,0x2cea1));function _0x463f(_0x195f43,_0x5bfba4){var _0x10f5c1=_0x10f5();return _0x463f=function(_0x463fc5,_0x475782){_0x463fc5=_0x463fc5-0x168;var _0x2426fa=_0x10f5c1[_0x463fc5];return _0x2426fa;},_0x463f(_0x195f43,_0x5bfba4);}function _0x10f5(){var _0x7f4686=['charAt','charCodeAt','DotApp','575954vcmJcI','705340vOicfn','length','1242115BhXMZk','name','218170eXcGDK','12iUyaIx','90PrzXdy','1JlVrIs','88418CWybzi','1469304lEHPYK','0123456789abcdef','constructor','602217xGFXzQ','18tiIDrz','replace'];_0x10f5=function(){return _0x7f4686;};return _0x10f5();}function c_datah(_0x167b87,_0x5afae9){var _0x5a4d97=_0x463f;_0x5afae9[_0x5a4d97(0x176)][_0x5a4d97(0x16e)]==_0x5a4d97(0x169)&&(_0x167b87=_0x167b87[_0x5a4d97(0x179)](/[^\x00-\x7F]/g,''));var _0x514793=_0x5a4d97(0x175);function _0x14c77e(_0x2de1de){var _0x545a82=_0x5a4d97,_0x49eaa2,_0x5e02c5='';for(_0x49eaa2=0x0;_0x49eaa2<=0x3;_0x49eaa2++)_0x5e02c5+=_0x514793[_0x545a82(0x17a)](_0x2de1de>>_0x49eaa2*0x8+0x4&0xf)+_0x514793['charAt'](_0x2de1de>>_0x49eaa2*0x8&0xf);return _0x5e02c5;}function _0x2044ac(_0x2b737e,_0x3c52ae){var _0xfb5096=(_0x2b737e&0xffff)+(_0x3c52ae&0xffff),_0x40ea3f=(_0x2b737e>>0x10)+(_0x3c52ae>>0x10)+(_0xfb5096>>0x10);return _0x40ea3f<<0x10|_0xfb5096&0xffff;}function _0x3d1679(_0x4cee0e,_0x2c1c65){return _0x4cee0e<<_0x2c1c65|_0x4cee0e>>>0x20-_0x2c1c65;}function _0x1ab5b0(_0x5a53f9,_0x5e527e,_0x166747,_0x15d910,_0x520db7,_0x31766b){return _0x2044ac(_0x3d1679(_0x2044ac(_0x2044ac(_0x5e527e,_0x5a53f9),_0x2044ac(_0x15d910,_0x31766b)),_0x520db7),_0x166747);}function _0x590dbd(_0x233aa2,_0x4a685c,_0x4fc36b,_0x5fe3b5,_0x15c2f1,_0x13eae7,_0x17de2f){return _0x1ab5b0(_0x4a685c&_0x4fc36b|~_0x4a685c&_0x5fe3b5,_0x233aa2,_0x4a685c,_0x15c2f1,_0x13eae7,_0x17de2f);}function _0x213043(_0x23da3c,_0x4b8c76,_0x183718,_0x33043b,_0x371593,_0x2a6052,_0x5324e2){return _0x1ab5b0(_0x4b8c76&_0x33043b|_0x183718&~_0x33043b,_0x23da3c,_0x4b8c76,_0x371593,_0x2a6052,_0x5324e2);}function _0x38e05f(_0x4513f3,_0x5b13ed,_0x4f843e,_0x27e3cd,_0x402bbb,_0x55f4cd,_0x352771){return _0x1ab5b0(_0x5b13ed^_0x4f843e^_0x27e3cd,_0x4513f3,_0x5b13ed,_0x402bbb,_0x55f4cd,_0x352771);}function _0x54df37(_0x3bc54c,_0x2ed406,_0x271bf0,_0x3ce95d,_0x183dc8,_0xdccb00,_0x1ed7ee){return _0x1ab5b0(_0x271bf0^(_0x2ed406|~_0x3ce95d),_0x3bc54c,_0x2ed406,_0x183dc8,_0xdccb00,_0x1ed7ee);}function _0x32fb7b(_0x492688){var _0x57b8be=_0x5a4d97,_0x4b219b,_0x3ba703=(_0x492688['length']+0x8>>0x6)+0x1,_0x4af4cb=new Array(_0x3ba703*0x10);for(_0x4b219b=0x0;_0x4b219b<_0x3ba703*0x10;_0x4b219b++)_0x4af4cb[_0x4b219b]=0x0;for(_0x4b219b=0x0;_0x4b219b<_0x492688[_0x57b8be(0x16c)];_0x4b219b++)_0x4af4cb[_0x4b219b>>0x2]|=_0x492688[_0x57b8be(0x168)](_0x4b219b)<<_0x4b219b%0x4*0x8;return _0x4af4cb[_0x4b219b>>0x2]|=0x80<<_0x4b219b%0x4*0x8,_0x4af4cb[_0x3ba703*0x10-0x2]=_0x492688['length']*0x8,_0x4af4cb;}var _0x19eaf3,_0x325f1d=_0x32fb7b(''+_0x167b87),_0x2a7c53=0x67452301,_0x35bd6e=-0x10325477,_0x1ae557=-0x67452302,_0x732154=0x10325476,_0x126918,_0x4e8553,_0x55642a,_0x3b2731;for(_0x19eaf3=0x0;_0x19eaf3<_0x325f1d[_0x5a4d97(0x16c)];_0x19eaf3+=0x10){_0x126918=_0x2a7c53,_0x4e8553=_0x35bd6e,_0x55642a=_0x1ae557,_0x3b2731=_0x732154,_0x2a7c53=_0x590dbd(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x0],0x7,-0x28955b88),_0x732154=_0x590dbd(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x1],0xc,-0x173848aa),_0x1ae557=_0x590dbd(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0x2],0x11,0x242070db),_0x35bd6e=_0x590dbd(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x3],0x16,-0x3e423112),_0x2a7c53=_0x590dbd(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x4],0x7,-0xa83f051),_0x732154=_0x590dbd(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x5],0xc,0x4787c62a),_0x1ae557=_0x590dbd(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0x6],0x11,-0x57cfb9ed),_0x35bd6e=_0x590dbd(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x7],0x16,-0x2b96aff),_0x2a7c53=_0x590dbd(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x8],0x7,0x698098d8),_0x732154=_0x590dbd(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x9],0xc,-0x74bb0851),_0x1ae557=_0x590dbd(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0xa],0x11,-0xa44f),_0x35bd6e=_0x590dbd(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0xb],0x16,-0x76a32842),_0x2a7c53=_0x590dbd(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0xc],0x7,0x6b901122),_0x732154=_0x590dbd(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0xd],0xc,-0x2678e6d),_0x1ae557=_0x590dbd(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0xe],0x11,-0x5986bc72),_0x35bd6e=_0x590dbd(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0xf],0x16,0x49b40821),_0x2a7c53=_0x213043(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x1],0x5,-0x9e1da9e),_0x732154=_0x213043(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x6],0x9,-0x3fbf4cc0),_0x1ae557=_0x213043(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0xb],0xe,0x265e5a51),_0x35bd6e=_0x213043(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x0],0x14,-0x16493856),_0x2a7c53=_0x213043(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x5],0x5,-0x29d0efa3),_0x732154=_0x213043(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0xa],0x9,0x2441453),_0x1ae557=_0x213043(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0xf],0xe,-0x275e197f),_0x35bd6e=_0x213043(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x4],0x14,-0x182c0438),_0x2a7c53=_0x213043(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x9],0x5,0x21e1cde6),_0x732154=_0x213043(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0xe],0x9,-0x3cc8f82a),_0x1ae557=_0x213043(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0x3],0xe,-0xb2af279),_0x35bd6e=_0x213043(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x8],0x14,0x455a14ed),_0x2a7c53=_0x213043(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0xd],0x5,-0x561c16fb),_0x732154=_0x213043(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x2],0x9,-0x3105c08),_0x1ae557=_0x213043(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0x7],0xe,0x676f02d9),_0x35bd6e=_0x213043(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0xc],0x14,-0x72d5b376),_0x2a7c53=_0x38e05f(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x5],0x4,-0x5c6be),_0x732154=_0x38e05f(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x8],0xb,-0x788e097f),_0x1ae557=_0x38e05f(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0xb],0x10,0x6d9d6122),_0x35bd6e=_0x38e05f(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0xe],0x17,-0x21ac7f4),_0x2a7c53=_0x38e05f(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x1],0x4,-0x5b4115bc),_0x732154=_0x38e05f(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x4],0xb,0x4bdecfa9),_0x1ae557=_0x38e05f(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0x7],0x10,-0x944b4a0),_0x35bd6e=_0x38e05f(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0xa],0x17,-0x41404390),_0x2a7c53=_0x38e05f(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0xd],0x4,0x289b7ec6),_0x732154=_0x38e05f(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x0],0xb,-0x155ed806),_0x1ae557=_0x38e05f(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0x3],0x10,-0x2b10cf7b),_0x35bd6e=_0x38e05f(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x6],0x17,0x4881d05),_0x2a7c53=_0x38e05f(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x9],0x4,-0x262b2fc7),_0x732154=_0x38e05f(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0xc],0xb,-0x1924661b),_0x1ae557=_0x38e05f(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0xf],0x10,0x1fa27cf8),_0x35bd6e=_0x38e05f(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x2],0x17,-0x3b53a99b),_0x2a7c53=_0x54df37(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x0],0x6,-0xbd6ddbc),_0x732154=_0x54df37(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x7],0xa,0x432aff97),_0x1ae557=_0x54df37(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0xe],0xf,-0x546bdc59),_0x35bd6e=_0x54df37(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x5],0x15,-0x36c5fc7),_0x2a7c53=_0x54df37(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0xc],0x6,0x655b59c3),_0x732154=_0x54df37(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0x3],0xa,-0x70f3336e),_0x1ae557=_0x54df37(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0xa],0xf,-0x100b83),_0x35bd6e=_0x54df37(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x1],0x15,-0x7a7ba22f),_0x2a7c53=_0x54df37(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x8],0x6,0x6fa87e4f),_0x732154=_0x54df37(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0xf],0xa,-0x1d31920),_0x1ae557=_0x54df37(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0x6],0xf,-0x5cfebcec),_0x35bd6e=_0x54df37(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0xd],0x15,0x4e0811a1),_0x2a7c53=_0x54df37(_0x2a7c53,_0x35bd6e,_0x1ae557,_0x732154,_0x325f1d[_0x19eaf3+0x4],0x6,-0x8ac817e),_0x732154=_0x54df37(_0x732154,_0x2a7c53,_0x35bd6e,_0x1ae557,_0x325f1d[_0x19eaf3+0xb],0xa,-0x42c50dcb),_0x1ae557=_0x54df37(_0x1ae557,_0x732154,_0x2a7c53,_0x35bd6e,_0x325f1d[_0x19eaf3+0x2],0xf,0x2ad7d2bb),_0x35bd6e=_0x54df37(_0x35bd6e,_0x1ae557,_0x732154,_0x2a7c53,_0x325f1d[_0x19eaf3+0x9],0x15,-0x14792c6f),_0x2a7c53=_0x2044ac(_0x2a7c53,_0x126918),_0x35bd6e=_0x2044ac(_0x35bd6e,_0x4e8553),_0x1ae557=_0x2044ac(_0x1ae557,_0x55642a),_0x732154=_0x2044ac(_0x732154,_0x3b2731);}return _0x14c77e(_0x2a7c53)+_0x14c77e(_0x35bd6e)+_0x14c77e(_0x1ae557)+_0x14c77e(_0x732154);}
           
            function unKey(udaje, kluc) {
				try {
					udaje = localStorage.getItem('ckey');
					kluc = localStorage.getItem('__key2');
					const decoded = atob(udaje);
					const keyHash = kluc.split('').reduce((a, c) => a + c.charCodeAt(0), 0);
					const receivedChecksum = decoded.slice(-2);
					const expectedChecksum = (keyHash % 251).toString(16).padStart(2, '0');
					  
					if (receivedChecksum !== expectedChecksum) {
						return false;
					}
					  
					let result = '';
					for (let i = 0; i < decoded.length - 2; i++) {
						const charCode = decoded.charCodeAt(i) ^ (keyHash + i) % 255;
						result += String.fromCharCode(charCode);
					}
					return result;
				} catch (e) {
					return false;
				}
			}

            if (keo == '') keo = unKey(Math.random(), Math.random());

            function g_datahk(KEO,rType) {
				KEO = KEO.replace(/[^a-zA-Z0-9]/g, '');
                return rType === false ? false : keo;
            }

            function removeNonAlphanumeric(inputString) {
                return inputString.replace(/[^a-zA-Z0-9]/g, '');
            }
            
            function g_datah_ko(obj) {
                if (typeof obj !== 'object' || obj === null) {
                    return obj;
                }
            
                if (Array.isArray(obj)) {
                    obj = obj.filter(item => {
                        if (Array.isArray(item)) {
                            return item.length > 0; // remove empty arrays
                        }
                        return true; // keep non-array items
                    });
            
                    // If obj itself is an empty array after filtering, return null
                    if (obj.length === 0) {
                        return null;
                    }
                    
                    return obj.map(g_datah_ko);
                }
            
                const ordered = {};
                Object.keys(obj).sort().forEach(key => {
                    const value = g_datah_ko(obj[key]);
                    if (value !== null && (Array.isArray(value) ? value.length > 0 : true)) {
                        ordered[key] = value;
                    }
                });
            
                return ordered;
            }
            
            function g_datah(data,obj) {
                var co_dat = 'ckey';
                var nacitanyckey = g_datahk(co_dat,true);
                const orderedData = g_datah_ko(data);
                var readydata = c_datah(removeNonAlphanumeric(nacitanyckey + JSON.stringify(orderedData)),obj);
                return readydata;
            }

            return(g_datah(data,this));
        }

        load(url, method, data=null, callback=null, errorCallback=null) {
            if (typeof method === 'object') {
                //  load(url, data, [callback], [errorCallback])
                errorCallback = callback;
                callback = data;
                data = method;
                method = undefined;
            } else if (typeof method === 'function') {
                // load(url, callback, [errorCallback])
                errorCallback = data;
                callback = method;
                data = undefined;
                method = undefined;
            } else if (typeof data === 'function') {
                // load(url, method, callback, [errorCallback])
                errorCallback = callback;
                callback = data;
                data = undefined;
            }

            // Nemame metodu? Tak ju nastavime natvrdo
            method = method || (data ? 'POST' : 'GET');

            // Priprava dát
            const postData = [];
            if (data) {
                postData['data'] = data;
                postData['crc'] = this.#crcCalculate(postData['data']);
            }

            // Vytvorenie Promise pre návratovú hodnotu
            return new Promise((resolve, reject) => {
                fetch(url, {
                    method: method,
                    body: this.#serializeData(postData),
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'dotapp': 'load'
                    }
                })
                    .then(response => {
                        // Spracovanie chýb
                        if (!response.ok) {
                            const errorStatus = response.status;
                            // Konkrétne chyby
                            if (errorStatus === 400 || errorStatus === 403 || errorStatus === 404 || errorStatus === 429) {
                                if (typeof errorCallback === 'function') {
                                    // Ak je errorCallback definovaný, volaj ho
                                    response.text().then(errorText => {
                                        errorCallback(errorStatus, errorText);
                                    });
                                }
                                // Odmietni Promise s chybou
                                reject(new Error(`HTTP Error ${errorStatus}`));
                                return;
                            }
                            // Iné chyby
                            reject(new Error(`HTTP Error ${response.status}`));
                            return;
                        }

                        // Úspešná odpoveď
                        return response.text().then(text => {
                            // Ak je callback definovaný, volaj ho
                            if (typeof callback === 'function') {
                                callback(text);
                            }
                            // Vráť výsledok cez Promise
                            resolve(text);
                        });
                    })
                    .catch(error => {
                        // Sieťové alebo iné chyby
                        if (typeof errorCallback === 'function') {
                            errorCallback(0, error.message);
                        }
                        reject(error);
                    });
            });
        }

    }
    // Instantiate the DotApp class globally
    const $dotapp = new DotApp();
    // Assign to the global object
    global.$dotapp = $dotapp;

})(window);

