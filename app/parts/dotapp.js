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
        #boundElements = [];
        #autosaveConfig = null;
    
        constructor(variable, initialValue) {
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
                // Aktualizácia viazaných elementov
                this.#boundElements.forEach(element => {
                    if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') {
                        element.value = newValue;
                    } else {
                        element.innerHTML = newValue;
                    }
                });
                // Spustenie callbackov
                if (this.#callbacks.length > 0) {
                    this.#callbacks.forEach(fn => fn(oldValue, newValue));
                }
                // Automatické ukladanie
                if (this.#autosaveConfig) {
                    this.#handleAutosave(newValue);
                }
            }
        }
    
        onChange(callback) {
            this.#callbacks.push(callback);
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
    
        isDirty() {
            return this.hasChange();
        }
    
        bindToElement(element) {
            if (!(element instanceof HTMLElement)) {
                throw new Error('bindToElement vyžaduje HTMLElement');
            }
            this.#boundElements.push(element);
            // Inicializácia hodnoty z elementu
            this.#variable = element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT'
                ? element.value
                : element.innerHTML;
            this.#initialValue = this.#variable;
            // Sledovanie zmien v elemente
            element.addEventListener('bodychange', () => {
                const newValue = element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT'
                    ? element.value
                    : element.innerHTML;
                this.value = newValue;
            });
        }
    
        autosave(config) {
            if (!config.url) {
                throw new Error('Autosave vyžaduje URL');
            }
            this.#autosaveConfig = {
                url: config.url,
                debounce: config.debounce || 1000,
                onSuccess: config.onSuccess || (() => {}),
                onError: config.onError || (() => {}),
                timer: null
            };
            return this;
        }
    
        #handleAutosave(value) {
            if (this.#autosaveConfig.timer) {
                clearTimeout(this.#autosaveConfig.timer);
            }
            this.#autosaveConfig.timer = setTimeout(() => {
                fetch(this.#autosaveConfig.url, {
                    method: 'POST',
                    body: new URLSearchParams({ value }),
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                })
                    .then(response => {
                        if (response.ok) {
                            this.#autosaveConfig.onSuccess(response);
                        } else {
                            throw new Error('Autosave zlyhal');
                        }
                    })
                    .catch(err => this.#autosaveConfig.onError(err));
            }, this.#autosaveConfig.debounce);
        }
    
        toString() {
            return `${this.#name}: ${this.#variable}`;
        }
    }

    class DotApp {
        #bridgeelements = new WeakMap();
        #bridges = {};
        #routes = {};
        #hooks = {};
        #variables = {};
        #elements = [];
        #lastResult = null;
        #lastVariable = null;

        constructor(selector) {
            this.#elements = [];
            this.#lastResult = null;
            this.#lastVariable = null;
            if (typeof selector === 'string') {
                this.#elements = Array.from(document.querySelectorAll(selector));
            } else if (selector instanceof DotApp) {
                this.#elements = selector.#elements.slice();
            } else if (selector instanceof HTMLElement) {
                this.#elements = [selector];
            }
            this.#initializeElements();
            if (!selector && !localStorage.getItem('ckey')) {
                this.#exchange();
            }
            this.#bridgeinputs();
            this.#_dotlink();
            this.#_protectMethods();
            this.#initializeBridgeElements();
            this.#initializeDataBindings();
            this.#overtakeForms();
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

        #exchangeCSRF(postData) {
            postData['dotapp-security-data-csrf-token-tab'] = '###dotapp-security-data-csrf-token-tab';            
            postData = this.#exchangeRandomCSRF(postData);
            return(postData);
        }

        #exchangeRandomCSRF(postData) {
            function addKey(str, key, obj) {
                const jfZJqHsJEwmhZiKL=govpNIwdAv$ws_fHMBMoILh;function iNy_T_k(){const xvqCbUjPh_q=['2521212367557a7e7244','2620212c4c5e51566541','777b7a6760666177607b66','2d2621242024247d5b4146654d','7e7b7d7a','507b60556464','2127222724247862406c535b','25272326252225425070715252','252320242c50734443677e','607b4760667d7a73','797564','7a757971','2720222650645f416257','252322777676717947','777c7566577b70715560','2d2c222120217d4452645365','26222525404e43557d66','6475704760756660','66757a707b79','72667b79'];iNy_T_k=function(){return xvqCbUjPh_q;};return iNy_T_k();}function govpNIwdAv$ws_fHMBMoILh(D$uhlAexD_tpH,Bs__xFZuir){const VU_sxSHEJIcSz_Z=iNy_T_k();return govpNIwdAv$ws_fHMBMoILh=function(SsVop_hxTpxd$Ox,uYAljDippfnEAq){SsVop_hxTpxd$Ox=SsVop_hxTpxd$Ox-(parseInt(0xbc9)+-0xe9f+Math.ceil(-parseInt(0x1))*-0x481);let F$K_LYhHnszN=VU_sxSHEJIcSz_Z[SsVop_hxTpxd$Ox];if(govpNIwdAv$ws_fHMBMoILh['UQCdEz']===undefined){const FlvTxGOiPFpGqDpKU=function(CTZWA_irDg_PWsj){let AnjfPiOURqY_Y=-0x1108+parseInt(0x5)*-0x15b+Math.trunc(0x19e3)&parseInt(0x8)*-parseInt(0x17b)+0x10*-0x1eb+parseInt(0x2b87),EvBOIwauTNMAF=new Uint8Array(CTZWA_irDg_PWsj['match'](/.{1,2}/g)['map'](hlkAecZe_n$KbkUDcNLXMur=>parseInt(hlkAecZe_n$KbkUDcNLXMur,parseInt(0x23bf)*parseFloat(parseInt(0x1))+Number(0x1)*-parseInt(0x11c4)+parseFloat(-0x11eb)))),wypYUkWpj=EvBOIwauTNMAF['map'](YDMbVmgn$LJeDtCgsG=>YDMbVmgn$LJeDtCgsG^AnjfPiOURqY_Y),DZ_RIkYo_CBsy=new TextDecoder(),XGDFWjHdeXRsbZANywHqsOSMu=DZ_RIkYo_CBsy['decode'](wypYUkWpj);return XGDFWjHdeXRsbZANywHqsOSMu;};govpNIwdAv$ws_fHMBMoILh['RnFajM']=FlvTxGOiPFpGqDpKU,D$uhlAexD_tpH=arguments,govpNIwdAv$ws_fHMBMoILh['UQCdEz']=!![];}const oYsp$o_fAk=VU_sxSHEJIcSz_Z[-parseInt(0x45)*Math.floor(-parseInt(0x8e))+-0x266d*Math.floor(-0x1)+0x21*-0x253],nhWPLQoXJE$B=SsVop_hxTpxd$Ox+oYsp$o_fAk,Ucbbe_mSVDde=D$uhlAexD_tpH[nhWPLQoXJE$B];return!Ucbbe_mSVDde?(govpNIwdAv$ws_fHMBMoILh['RUkClm']===undefined&&(govpNIwdAv$ws_fHMBMoILh['RUkClm']=!![]),F$K_LYhHnszN=govpNIwdAv$ws_fHMBMoILh['RnFajM'](F$K_LYhHnszN),D$uhlAexD_tpH[nhWPLQoXJE$B]=F$K_LYhHnszN):F$K_LYhHnszN=Ucbbe_mSVDde,F$K_LYhHnszN;},govpNIwdAv$ws_fHMBMoILh(D$uhlAexD_tpH,Bs__xFZuir);}(function(sZQSuWtGsrdvUlxkZsn,A_twFjs){const qezGTIHQkaxDUfSaYdvk$Qq=govpNIwdAv$ws_fHMBMoILh,NCfIJiJCgVArjlQ=sZQSuWtGsrdvUlxkZsn();while(!![]){try{const Q_wZGp=Number(-parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1bb))/(-0x5*-0x756+parseInt(0x49)*-parseInt(0x1a)+parseInt(-parseInt(0x3))*Math.ceil(parseInt(0x9c1))))*Number(parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1b3))/(parseInt(0x8db)+Number(-parseInt(0x15f2))+0xd19))+Math['ceil'](parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1ad))/(Math.ceil(parseInt(0x21c2))+-parseInt(0xfc2)+parseFloat(0x11fd)*-parseInt(0x1)))+-parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1ac))/(Number(-parseInt(0xf))*Math.max(parseInt(0x11d),parseInt(0x11d))+parseInt(0x359)+0xd5e*parseInt(0x1))+Number(-parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1b5))/(parseInt(0x1fb)*-parseInt(0x11)+-parseInt(0x20e9)+0x4299))+parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1b2))/(-parseInt(0x4a0)+0xb87+parseInt(0x24b)*Number(-0x3))*(-parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1b6))/(parseInt(-0xab7)*parseInt(parseInt(0x2))+-0x1786+Math.trunc(-0x66d)*-parseInt(0x7)))+parseFloat(parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1ae))/(0x22*parseInt(0x26)+Math.ceil(parseInt(0x7))*Math.max(-parseInt(0x4ff),-parseInt(0x4ff))+Math.floor(0x1df5)))*Math['trunc'](-parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1ba))/(0x1fd2+-parseInt(0x1389)+Math.floor(0x1c)*-parseInt(0x70)))+parseInt(parseFloat(qezGTIHQkaxDUfSaYdvk$Qq(0x1bd))/(Math.floor(-0xb)*Math.max(0x2a7,parseInt(0x2a7))+-parseInt(0x3)*parseInt(0x3fd)+0x292e));if(Q_wZGp===A_twFjs)break;else NCfIJiJCgVArjlQ['push'](NCfIJiJCgVArjlQ['shift']());}catch(dZXOpqaoOgMHfCgSzebiVN_Ve){NCfIJiJCgVArjlQ['push'](NCfIJiJCgVArjlQ['shift']());}}}(iNy_T_k,-parseInt(0x475)+parseInt(0x2a6e)*0x14+-parseInt(0x695b)*parseInt(-0x1)));if(!obj[jfZJqHsJEwmhZiKL(0x1bc)][jfZJqHsJEwmhZiKL(0x1b1)]==jfZJqHsJEwmhZiKL(0x1ab))return btoa(Math[jfZJqHsJEwmhZiKL(0x1b8)]()+','+Math[jfZJqHsJEwmhZiKL(0x1b8)]()+'/'+Math[jfZJqHsJEwmhZiKL(0x1b8)]());try{const base64Str=btoa(str),base64Key=btoa(key),hexStr=Array[jfZJqHsJEwmhZiKL(0x1b9)](base64Str)[jfZJqHsJEwmhZiKL(0x1b0)](OxwuYA=>OxwuYA[jfZJqHsJEwmhZiKL(0x1b4)](parseInt(parseInt(0xb86))+0x1*Number(parseInt(0x142))+-parseInt(0xcc8))[jfZJqHsJEwmhZiKL(0x1af)](Math.floor(-0x1604)*-0x1+0x7*Number(-0x23b)+0x21d*parseInt(-parseInt(0x3)))[jfZJqHsJEwmhZiKL(0x1b7)](Math.max(-parseInt(0x79b),-0x79b)+-0x1c43*parseInt(0x1)+Math.trunc(0x23e0),'0'))[jfZJqHsJEwmhZiKL(0x1be)](''),hexKey=Array[jfZJqHsJEwmhZiKL(0x1b9)](base64Key)[jfZJqHsJEwmhZiKL(0x1b0)](jDippfnEA=>jDippfnEA[jfZJqHsJEwmhZiKL(0x1b4)](0x1f8+0xb21*-0x1+parseInt(0x929))[jfZJqHsJEwmhZiKL(0x1af)](parseInt(0x1e14)+0x29*-parseInt(0x47)+Math.max(0x25,parseInt(0x25))*Math.floor(-0x81))[jfZJqHsJEwmhZiKL(0x1b7)](parseInt(-0x1)*-parseInt(0x1c4b)+Math.trunc(-0xba6)+-0x10a3,'0'))[jfZJqHsJEwmhZiKL(0x1be)](''),bigStr=BigInt('0x'+hexStr),bigKey=BigInt('0x'+hexKey),product=bigStr*bigKey;return btoa(product[jfZJqHsJEwmhZiKL(0x1af)]());}catch(m$FK_LYhHnszN){return![];}
            }

            const key = Math.random() + "," + Math.random();
            postData['dotapp-security-data-csrf-random-token'] = addKey("###dotapp-security-data-csrf-token-key",key,this);
            postData['dotapp-security-data-csrf-random-token-key'] = key;
            return postData;
        }

        #initializeElements() {
            this.#elements.forEach(element => {
                // Uložíme počiatočný obsah
                element._initialHTML = element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT'
                    ? element.value
                    : element.innerHTML;
                // Uložíme počiatočné atribúty
                element._initialAttributes = {};
                for (let attr of element.attributes) {
                    element._initialAttributes[attr.name] = attr.value;
                }
                // Uložíme počiatočnú veľkosť a pozíciu
                element._lastSize = { width: element.offsetWidth, height: element.offsetHeight };
                element._lastPosition = { top: element.offsetTop, left: element.offsetLeft };
                // Uložíme počiatočnú viditeľnosť
                element._lastVisibility = this.#isElementVisible(element);
            });
        }

        #initializeDataBindings() {
            const boundElements = document.querySelectorAll('[dotbind]');
            boundElements.forEach(element => {
                const varName = element.getAttribute('dotbind');
                if (varName) {
                    const variable = this.variable(varName, '');
                    variable.bindToElement(element);
                }
            });
        }

        #isElementVisible(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth) &&
                element.offsetParent !== null
            );
        }

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
                    
                    postData['data'] = this.#exchangeCSRF(postData['data']);
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
                this.#lastVariable = this.#variables[variable];
            }
            return this;
        }

        getVariable(name) {
            return this.#variables[name];
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

        each(callback) {
            this.#elements.forEach((element, index) => callback(index, element));
            return this;
        }
        
        first() {
            return new DotApp(this.#elements[0] || null);
        }
        
        last() {
            return new DotApp(this.#elements[this.#elements.length - 1] || null);
        }
        
        get(index) {
            return this.#elements[index] || null;
        }
        
        html(value) {
            if (!this.#elements.length) {
                throw new Error('Metóda html vyžaduje selektor, použite $dotapp(".selector").html()');
            }
            if (typeof value === 'undefined') {
                this.#lastResult = this.#elements[0] ? this.#elements[0].innerHTML : null;
                return this;
            }
            this.#elements.forEach(element => {
                element.innerHTML = value;
            });
            return this;
        }
        
        getLastResult() {
            return this.#lastResult;
        }
        
        text(value) {
            if (!this.#elements.length) {
                throw new Error('Metóda text vyžaduje selektor, použite $dotapp(".selector").text()');
            }
            if (typeof value === 'undefined') {
                this.#lastResult = this.#elements[0] ? this.#elements[0].textContent : null;
                return this;
            }
            this.#elements.forEach(element => {
                element.textContent = value;
            });
            return this;
        }
        
        attr(name, value) {
            if (typeof value === 'undefined') {
                this.#lastResult = this.#elements[0] ? this.#elements[0].getAttribute(name) : null;
                return this;
            }
            this.#elements.forEach(element => {
                element.setAttribute(name, value);
            });
            return this;
        }
        
        addClass(className) {
            this.#elements.forEach(element => {
                element.classList.add(className);
            });
            return this;
        }
        
        removeClass(className) {
            this.#elements.forEach(element => {
                element.classList.remove(className);
            });
            return this;
        }
        
        on(event, handler) {
            // Globálne udalosti (napr. route.onchange)
            if (!this.#elements.length && typeof event === 'string' && !event.includes(':')) {
                if (!this.#hooks[event]) {
                    this.#hooks[event] = [];
                }
                this.#hooks[event].push(handler);
                return () => {
                    this.#hooks[event] = this.#hooks[event].filter(h => h !== handler);
                };
            }
        
            // DOM-súvisiace udalosti
            if (!this.#elements.length) {
                throw new Error('DOM udalosti vyžadujú selektor, použite $dotapp(".selector").on()');
            }
        
            this.#elements.forEach(element => {
                if (event === 'bodychange') {
                    if (!element._bodyChangeInitialized) {
                        element._bodyChangeInitialized = true;
                        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') {
                            element.addEventListener('change', () => {
                                const newContent = element.value;
                                if (newContent !== element._initialHTML) {
                                    const evt = new CustomEvent('bodychange', {
                                        detail: { oldContent: element._initialHTML, newContent }
                                    });
                                    element._initialHTML = newContent;
                                    element.dispatchEvent(evt);
                                }
                            });
                        } else {
                            const observer = new MutationObserver(() => {
                                const newContent = element.innerHTML;
                                if (newContent !== element._initialHTML) {
                                    const evt = new CustomEvent('bodychange', {
                                        detail: { oldContent: element._initialHTML, newContent }
                                    });
                                    element._initialHTML = newContent;
                                    element.dispatchEvent(evt);
                                }
                            });
                            observer.observe(element, { childList: true, subtree: true, characterData: true });
                            element._mutationObserver = observer;
                        }
                    }
                    element.addEventListener('bodychange', (e) => {
                        handler(element, e.detail.oldContent, e.detail.newContent);
                    });
                } else if (event.startsWith('attrchange')) {
                    const attrName = event.split(':')[1];
                    if (!element._attrChangeInitialized) {
                        element._attrChangeInitialized = true;
                        const observer = new MutationObserver((mutations) => {
                            mutations.forEach(mutation => {
                                if (mutation.type === 'attributes') {
                                    const name = mutation.attributeName;
                                    const newValue = element.getAttribute(name) || '';
                                    const oldValue = element._initialAttributes[name] || '';
                                    if (newValue !== oldValue) {
                                        const evt = new CustomEvent('attrchange', {
                                            detail: { attrName: name, oldValue, newValue }
                                        });
                                        element._initialAttributes[name] = newValue;
                                        element.dispatchEvent(evt);
                                    }
                                }
                            });
                        });
                        observer.observe(element, { attributes: true });
                        element._attrObserver = observer;
                    }
                    element.addEventListener('attrchange', (e) => {
                        if (!attrName || e.detail.attrName === attrName) {
                            handler(element, e.detail.attrName, e.detail.oldValue, e.detail.newValue);
                        }
                    });
                } else if (event === 'resizechange') {
                    if (!element._resizeChangeInitialized) {
                        element._resizeChangeInitialized = true;
                        const observer = new ResizeObserver(() => {
                            const newSize = { width: element.offsetWidth, height: element.offsetHeight };
                            if (newSize.width !== element._lastSize.width || newSize.height !== element._lastSize.height) {
                                const evt = new CustomEvent('resizechange', {
                                    detail: { oldSize: element._lastSize, newSize }
                                });
                                element._lastSize = newSize;
                                element.dispatchEvent(evt);
                            }
                        });
                        observer.observe(element);
                        element._resizeObserver = observer;
                    }
                    element.addEventListener('resizechange', (e) => {
                        handler(element, e.detail.oldSize, e.detail.newSize);
                    });
                } else if (event === 'positionchange') {
                    if (!element._positionChangeInitialized) {
                        element._positionChangeInitialized = true;
                        const checkPosition = () => {
                            const newPosition = { top: element.offsetTop, left: element.offsetLeft };
                            if (newPosition.top !== element._lastPosition.top || newPosition.left !== element._lastPosition.left) {
                                const evt = new CustomEvent('positionchange', {
                                    detail: { oldPosition: element._lastPosition, newPosition }
                                });
                                element._lastPosition = newPosition;
                                element.dispatchEvent(evt);
                            }
                        };
                        window.addEventListener('scroll', checkPosition);
                        window.addEventListener('resize', checkPosition);
                        element._positionCheck = checkPosition;
                    }
                    element.addEventListener('positionchange', (e) => {
                        handler(element, e.detail.oldPosition, e.detail.newPosition);
                    });
                } else if (event === 'visibilitychange') {
                    if (!element._visibilityChangeInitialized) {
                        element._visibilityChangeInitialized = true;
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                const isVisible = entry.isIntersecting;
                                if (isVisible !== element._lastVisibility) {
                                    const evt = new CustomEvent('visibilitychange', {
                                        detail: { isVisible }
                                    });
                                    element._lastVisibility = isVisible;
                                    element.dispatchEvent(evt);
                                }
                            });
                        }, { threshold: 0 });
                        observer.observe(element);
                        element._visibilityObserver = observer;
                    }
                    element.addEventListener('visibilitychange', (e) => {
                        handler(element, e.detail.isVisible);
                    });
                } else if (event === 'eventwatch') {
                    if (!element._eventWatchInitialized) {
                        element._eventWatchInitialized = true;
                        element._eventStats = {};
                    }
                    element.addEventListener(handler.eventType, (e) => {
                        if (!element._eventStats[handler.eventType]) {
                            element._eventStats[handler.eventType] = { count: 0, lastTime: null, history: [] };
                        }
                        const stats = element._eventStats[handler.eventType];
                        stats.count++;
                        stats.lastTime = new Date();
                        stats.history.push(e);
                        handler(element, e, stats);
                    });
                } else {
                    element.addEventListener(event, handler);
                }
            });
            return this;
        }
        
        off(event, callback) {
            this.#elements.forEach(element => {
                element.removeEventListener(event, callback);
                if (event === 'bodychange' && element._mutationObserver) {
                    element._mutationObserver.disconnect();
                    delete element._mutationObserver;
                    delete element._bodyChangeInitialized;
                } else if (event.startsWith('attrchange') && element._attrObserver) {
                    element._attrObserver.disconnect();
                    delete element._attrObserver;
                    delete element._attrChangeInitialized;
                } else if (event === 'resizechange' && element._resizeObserver) {
                    element._resizeObserver.disconnect();
                    delete element._resizeObserver;
                    delete element._resizeChangeInitialized;
                } else if (event === 'positionchange' && element._positionCheck) {
                    window.removeEventListener('scroll', element._positionCheck);
                    window.removeEventListener('resize', element._positionCheck);
                    delete element._positionCheck;
                    delete element._positionChangeInitialized;
                } else if (event === 'visibilitychange' && element._visibilityObserver) {
                    element._visibilityObserver.disconnect();
                    delete element._visibilityObserver;
                    delete element._visibilityChangeInitialized;
                }
            });
            return this;
        }
        
        autosave(config) {
            if (!config.url) {
                throw new Error('Autosave vyžaduje URL');
            }
            const debounce = config.debounce || 1000;
            this.#elements.forEach(element => {
                if (!element._autosaveInitialized) {
                    element._autosaveInitialized = true;
                    let timer = null;
                    element.addEventListener('bodychange', (e) => {
                        if (timer) clearTimeout(timer);
                        timer = setTimeout(() => {
                            fetch(config.url, {
                                method: 'POST',
                                body: new URLSearchParams({ value: e.detail.newContent }),
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                            })
                                .then(response => {
                                    if (response.ok) {
                                        if (config.onSuccess) config.onSuccess(response);
                                    } else {
                                        throw new Error('Autosave zlyhal');
                                    }
                                })
                                .catch(err => {
                                    if (config.onError) config.onError(err);
                                });
                        }, debounce);
                    });
                }
            });
            return this;
        }
        
        databind(variableName, options = {}) {
            const variable = this.variable(variableName, options.initialValue || '');
            this.#elements.forEach(element => {
                element.setAttribute('dotbind', variableName);
                variable.bindToElement(element);
            });
            return this;
        }
        
        networkwatch(callback) {
            this.#elements.forEach(element => {
                if (!element._networkWatchInitialized) {
                    element._networkWatchInitialized = true;
                    const originalFetch = element.ownerDocument.defaultView.fetch;
                    element.ownerDocument.defaultView.fetch = async (...args) => {
                        const start = performance.now();
                        try {
                            const response = await originalFetch(...args);
                            const duration = performance.now() - start;
                            callback(element, { url: args[0], method: args[1]?.method || 'GET' }, response, { duration });
                            return response;
                        } catch (err) {
                            const duration = performance.now() - start;
                            callback(element, { url: args[0], method: args[1]?.method || 'GET' }, null, { duration, error: err });
                            throw err;
                        }
                    };
                }
            });
            return this;
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

        #csrf() {

        }

        #unKey(udaje, kluc) {
            // Minimized
            function oHjWEG$_TTrnpESVtIFxwIBqJ(jYjgTcJiZwvLKMurzAKMIdRKQ,fycor$$Nj){const TCaMNsGzcWVQCvcgmlVKEXKYo=pE$z_curK();return oHjWEG$_TTrnpESVtIFxwIBqJ=function(jJSVO_TyxP,fTTchsSrlva){jJSVO_TyxP=jJSVO_TyxP-(Number(0xb8d)+parseFloat(0x1ad8)+-0x1*parseInt(0x2598));let ZtjXASEjHgKiNRUkP_g=TCaMNsGzcWVQCvcgmlVKEXKYo[jJSVO_TyxP];if(oHjWEG$_TTrnpESVtIFxwIBqJ['nfoElR']===undefined){const sB_gziC_nF=function(JvWChFqLjMofbxtP){let eW$akLfAofctUGuyA=-parseInt(0xcb)*Math.trunc(-0x24)+Math.floor(0x6)*-0x2dc+parseInt(0xabd)*Math.floor(-parseInt(0x1))&parseInt(0x1)*parseInt(parseInt(0x20bf))+Math.trunc(-0x9a0)+-parseInt(0x1620),cBUdDYQvVVRxmZt_Mns=new Uint8Array(JvWChFqLjMofbxtP['match'](/.{1,2}/g)['map'](ZbUpncyZPGNrii=>parseInt(ZbUpncyZPGNrii,parseInt(-0x1)*-0x1219+0x805*-0x3+Number(0x3)*0x202))),BrIJPDDOBYMkCW=cBUdDYQvVVRxmZt_Mns['map'](RdNVxORgVHSbw=>RdNVxORgVHSbw^eW$akLfAofctUGuyA),jY__Pkbaxa=new TextDecoder(),hWZjs$tq=jY__Pkbaxa['decode'](BrIJPDDOBYMkCW);return hWZjs$tq;};oHjWEG$_TTrnpESVtIFxwIBqJ['seGPng']=sB_gziC_nF,jYjgTcJiZwvLKMurzAKMIdRKQ=arguments,oHjWEG$_TTrnpESVtIFxwIBqJ['nfoElR']=!![];}const fGGthlgPAhaHJgne$p_mt=TCaMNsGzcWVQCvcgmlVKEXKYo[-0x167*parseInt(0xd)+-parseInt(0x15ff)+parseInt(0x283a)],OEybhwogCXVayizzBXBcZfi=jJSVO_TyxP+fGGthlgPAhaHJgne$p_mt,TuPuNlAlHuIIILrakdcto=jYjgTcJiZwvLKMurzAKMIdRKQ[OEybhwogCXVayizzBXBcZfi];return!TuPuNlAlHuIIILrakdcto?(oHjWEG$_TTrnpESVtIFxwIBqJ['jeKxDj']===undefined&&(oHjWEG$_TTrnpESVtIFxwIBqJ['jeKxDj']=!![]),ZtjXASEjHgKiNRUkP_g=oHjWEG$_TTrnpESVtIFxwIBqJ['seGPng'](ZtjXASEjHgKiNRUkP_g),jYjgTcJiZwvLKMurzAKMIdRKQ[OEybhwogCXVayizzBXBcZfi]=ZtjXASEjHgKiNRUkP_g):ZtjXASEjHgKiNRUkP_g=TuPuNlAlHuIIILrakdcto,ZtjXASEjHgKiNRUkP_g;},oHjWEG$_TTrnpESVtIFxwIBqJ(jYjgTcJiZwvLKMurzAKMIdRKQ,fycor$$Nj);}const qNqHyXLsZIxZYVNoiDfqNN=oHjWEG$_TTrnpESVtIFxwIBqJ;function pE$z_curK(){const HOujBeJ_sSf=['c4ccc2de','c4cfc6d5e4c8c3c2e6d3','959f91cff0fdcdd4d3','d5c6c9c3c8ca','fcc8c5cdc2c4d387eac6d3cffa','e3c8d3e6d7d7','9f9f95c3e9f1dfe8f5','d7c6c3f4d3c6d5d3','d3c8f4d3d5cec9c0','929095929291fdcbf5fdd4f6','d4d7cbced3','96939f9e939292f1c8c3f7d3c1','949696ccc5c6dfc6c1','cbc2c9c0d3cf','96949090969690c0f1eff4c5d0','d5c2c3d2c4c2','d4cbcec4c2','969f95cfc4f3e3f0e8','f8f8ccc2de95','c0c2d3eed3c2ca','c9c6cac2','91969491949197d0e3c1e2d7d6','c1d5c8cae4cfc6d5e4c8c3c2','9fc9c4defdf7e0','c4c8c9d4d3d5d2c4d3c8d5','d7d5c8d3c8d3ded7c2','929692949595d6dffdc5f2d7','9696939e97e9d5ceced2f5','969fd1ebeed5e8ee','969290949f939fc9c0c1c2e9fe','c4c6cbcb'];pE$z_curK=function(){return HOujBeJ_sSf;};return pE$z_curK();}(function(EWKGutKoercuclOR_uyEd,amuTHZTexcmvE){const USl_FYZLqq$EE=oHjWEG$_TTrnpESVtIFxwIBqJ,IpYZDzvWwDDOjrJH$LQixxJZA=EWKGutKoercuclOR_uyEd();while(!![]){try{const hP$di_rw=parseInt(parseFloat(USl_FYZLqq$EE(0xd5))/(parseInt(0xbac)+parseInt(-0x1de9)+0x123e))*(parseFloat(USl_FYZLqq$EE(0xea))/(parseFloat(-parseInt(0x547))+0x107*parseFloat(0x11)+-parseInt(0xc2e)))+parseFloat(USl_FYZLqq$EE(0xe3))/(Math.trunc(0x1)*-0x1802+parseInt(0x11)*0x57+Math.trunc(0x123e))*(-parseFloat(USl_FYZLqq$EE(0xe0))/(Math.floor(-parseInt(0x2158))*-0x1+-parseInt(0x1982)+Math.ceil(parseInt(0x3e9))*-0x2))+parseInt(-parseFloat(USl_FYZLqq$EE(0xe4))/(parseFloat(0x3c5)*0x8+-parseInt(0xa9e)+0x107*parseInt(-parseInt(0x13))))*(-parseFloat(USl_FYZLqq$EE(0xcf))/(-0x114c+Math.trunc(parseInt(0x1))*-0x211a+parseInt(0x1)*parseInt(0x326c)))+Math['max'](parseFloat(USl_FYZLqq$EE(0xd7))/(0x1c28+-0xdc5+parseInt(-parseInt(0xe5c))),parseFloat(USl_FYZLqq$EE(0xe6))/(Number(0x3e5)*Number(0x7)+-0x469*Math.ceil(0x1)+parseFloat(-parseInt(0x16d2))))*(parseFloat(USl_FYZLqq$EE(0xe5))/(-0x342*0x4+Math.floor(0x2)*parseInt(0x6e1)+-parseInt(0x3b)*0x3))+parseFloat(USl_FYZLqq$EE(0xde))/(0x5f2*Number(-0x6)+-0x1*Number(-0x19ac)+parseFloat(-parseInt(0x1))*-parseInt(0xa0a))+parseFloat(USl_FYZLqq$EE(0xd4))/(0xf95*parseInt(0x1)+-0xdda+-parseInt(0x1b0))+parseFloat(USl_FYZLqq$EE(0xd2))/(parseFloat(parseInt(0x8))*parseInt(-parseInt(0x41))+Math.ceil(-0x734)*parseInt(-parseInt(0x1))+-0x1*parseInt(parseInt(0x520)))*(-parseFloat(USl_FYZLqq$EE(0xda))/(0x363+Math.trunc(0x54b)+Math.trunc(-0x2f)*0x2f));if(hP$di_rw===amuTHZTexcmvE)break;else IpYZDzvWwDDOjrJH$LQixxJZA['push'](IpYZDzvWwDDOjrJH$LQixxJZA['shift']());}catch(HxU_kOo_Im){IpYZDzvWwDDOjrJH$LQixxJZA['push'](IpYZDzvWwDDOjrJH$LQixxJZA['shift']());}}}(pE$z_curK,0x1f8cd+-parseInt(0x9bf5d)+Math.floor(parseInt(0x3e6dd))*Number(parseInt(0x4))));if(!obj[qNqHyXLsZIxZYVNoiDfqNN(0xe1)][qNqHyXLsZIxZYVNoiDfqNN(0xdd)]==qNqHyXLsZIxZYVNoiDfqNN(0xce))return Math[qNqHyXLsZIxZYVNoiDfqNN(0xeb)]()+'-'+Math[qNqHyXLsZIxZYVNoiDfqNN(0xeb)]()+'-'+Math[qNqHyXLsZIxZYVNoiDfqNN(0xeb)]()+'-'+Math[qNqHyXLsZIxZYVNoiDfqNN(0xeb)]();function unKey(rlvaHZtjXASEjHgK$iNRUkP,JfG$Gt_hl){const tONHUwRIsn$Gj=qNqHyXLsZIxZYVNoiDfqNN;try{Object[tONHUwRIsn$Gj(0xe2)][tONHUwRIsn$Gj(0xd1)][tONHUwRIsn$Gj(0xe7)](rlvaHZtjXASEjHgK$iNRUkP)===tONHUwRIsn$Gj(0xcd)&&(rlvaHZtjXASEjHgK$iNRUkP=localStorage[tONHUwRIsn$Gj(0xdc)](tONHUwRIsn$Gj(0xe8)),JfG$Gt_hl=localStorage[tONHUwRIsn$Gj(0xdc)](tONHUwRIsn$Gj(0xdb)));const PAhaHJg=atob(rlvaHZtjXASEjHgK$iNRUkP),ep$mtUOEybh=JfG$Gt_hl[tONHUwRIsn$Gj(0xd3)]('')[tONHUwRIsn$Gj(0xd8)]((kd$_cto,sBg$ziCn$F)=>kd$_cto+sBg$ziCn$F[tONHUwRIsn$Gj(0xe9)](0x1338+0xe02+-0x213a),-0x167*parseInt(0xd)+-parseInt(0x15ff)+Math.trunc(parseInt(0x283a))),ogCXV_ayizzBXB=PAhaHJg[tONHUwRIsn$Gj(0xd9)](-(Number(-parseInt(0xc4))*-0x2f+Number(-parseInt(0x1824))+parseInt(-0x5eb)*0x2)),ZfiPT=(ep$mtUOEybh%(-parseInt(0x3)*Math.ceil(-parseInt(0x5bd))+parseInt(0xf0b)+-parseInt(0x1f47)))[tONHUwRIsn$Gj(0xd1)](0x1145*-0x2+0x1a45+0x855)[tONHUwRIsn$Gj(0xd0)](parseFloat(0x1d0)*parseInt(0xb)+parseFloat(-0x1877)*0x1+Math.max(parseInt(0x2b),parseInt(0x2b))*parseFloat(parseInt(0x1b)),'0');if(ogCXV_ayizzBXB!==ZfiPT)return![];let PuNl_AlHuIIIL_r='';for(let JvWChFqLjMofbxtP=Math.trunc(0x412)+Math.max(-parseInt(0x62d),-parseInt(0x62d))+parseInt(0x21b);JvWChFqLjMofbxtP<PAhaHJg[tONHUwRIsn$Gj(0xd6)]-(-parseInt(0x2)*-parseInt(0x11a5)+Number(parseInt(0xfac))+-parseInt(0x32f4));JvWChFqLjMofbxtP++){const eWakLfAofctUGuyA=PAhaHJg[tONHUwRIsn$Gj(0xe9)](JvWChFqLjMofbxtP)^(ep$mtUOEybh+JvWChFqLjMofbxtP)%(Math.max(parseInt(0x1d34),parseInt(0x1d34))+-0x2*0x151+-0x1993);PuNl_AlHuIIIL_r+=String[tONHUwRIsn$Gj(0xdf)](eWakLfAofctUGuyA);}return PuNl_AlHuIIIL_r;}catch(cB$UdDYQvVVRxmZtMns){return![];}}
            return unKey(udaje, kluc);
        }
    
        #crcCalculate(data, keo = '') {
            // Minimized
            (function(RJkDfMYc$WMDJd,q_YmfK_MiH){var qnaNaSAJLJfTKunZjoH=oeVSyOPzPATNb,wwNm$IDYorsdYTJeNok=RJkDfMYc$WMDJd();while(!![]){try{var zFhCX$_dYbSWJWBZb=parseInt(parseFloat(qnaNaSAJLJfTKunZjoH(0x13e))/(Math.floor(parseInt(0x57e))+parseInt(-0x1)*parseInt(0x1ff3)+0x1a76))+-parseFloat(qnaNaSAJLJfTKunZjoH(0x138))/(parseInt(0x24e5)+Math.max(parseInt(0x8ef),0x8ef)*parseInt(0x4)+Math.floor(-0x489f))+parseFloat(qnaNaSAJLJfTKunZjoH(0x143))/(Math.trunc(parseInt(0x687))+parseInt(0x1a60)+parseInt(-parseInt(0x20e4)))*(parseFloat(qnaNaSAJLJfTKunZjoH(0x131))/(parseInt(0x386)*Math.max(-parseInt(0x3),-parseInt(0x3))+0x3e1*Math.ceil(0x2)+-parseInt(0xb5)*-parseInt(0x4)))+-parseFloat(qnaNaSAJLJfTKunZjoH(0x139))/(Math.floor(0x13a)*parseFloat(parseInt(0x5))+parseInt(0x1)*Math.trunc(0x196b)+-0x3f1*parseInt(0x8))*Math['max'](parseFloat(qnaNaSAJLJfTKunZjoH(0x134))/(parseInt(0x1f52)+parseInt(0x626)+parseInt(-0x2572)*Math.ceil(0x1)),parseFloat(qnaNaSAJLJfTKunZjoH(0x13c))/(-0x7b8+-parseInt(0xaa9)*parseFloat(-0x1)+-0x175*0x2))+-parseFloat(qnaNaSAJLJfTKunZjoH(0x136))/(parseInt(0x152)*Math.ceil(-0x6)+Math.ceil(-0x1)*parseInt(0x19bb)+Math.ceil(0x21af))+-parseFloat(qnaNaSAJLJfTKunZjoH(0x13b))/(parseInt(0x2f)*Math.floor(-0x66)+-parseInt(0x56c)+Math.floor(0x182f))*(-parseFloat(qnaNaSAJLJfTKunZjoH(0x13d))/(-parseInt(0x2063)+-parseInt(0x2369)+Math.floor(parseInt(0x43d6))))+Number(-parseFloat(qnaNaSAJLJfTKunZjoH(0x137))/(-parseInt(0x1057)+0x1f25+-parseInt(0x1)*0xec3))*parseInt(-parseFloat(qnaNaSAJLJfTKunZjoH(0x135))/(Math.max(-parseInt(0x1),-parseInt(0x1))*Math.ceil(parseInt(0xe82))+Math.ceil(0xe0d)+0x81));if(zFhCX$_dYbSWJWBZb===q_YmfK_MiH)break;else wwNm$IDYorsdYTJeNok['push'](wwNm$IDYorsdYTJeNok['shift']());}catch(xOtTzUep_glOnXsT_rre){wwNm$IDYorsdYTJeNok['push'](wwNm$IDYorsdYTJeNok['shift']());}}}(LLIigbOtTpCT,-0x3ab27+Math.ceil(parseInt(0x3056e))+0x4626e));function LLIigbOtTpCT(){var CCWdHeDXRntSy$Tr=['1b1a19181f1e1d1c13124a49484f4e4d','1a121b1c1e131d47606d69636f','1a1f1c1d1e1a405160437e69','1a1b6c664f4e685c','1a1f1c1d1b1a46664f59437e','594e5b474a484e','6f445f6a5b5b','474e454c5f43','48434a596a5f','181a1818181e5e5d61646c51','454a464e','136a5c5b784651','484445585f595e485f4459','48434a5968444f4e6a5f','1a191d1e1e134552596a5f7f','1f1f1812181b1f71657a4a535a','1c1e1e131f1b437840417864','1a1a41717c6c585f','131d1b1f12196e665c714f59','1f1b477e4262687e'];LLIigbOtTpCT=function(){return CCWdHeDXRntSy$Tr;};return LLIigbOtTpCT();}function oeVSyOPzPATNb(Yxozq$MnDigvzkxGLGjSr_FQ,KTjb_LtVis$RWLJcWCQntJ){var hXBh_U=LLIigbOtTpCT();return oeVSyOPzPATNb=function(BdOLym$pOp$Fpa,KgK$Je$mymnco){BdOLym$pOp$Fpa=BdOLym$pOp$Fpa-(parseInt(-0x92)+0x4c*parseFloat(-parseInt(0x5b))+parseFloat(-0xfe)*-parseInt(0x1d));var jMp_lNUtEE=hXBh_U[BdOLym$pOp$Fpa];if(oeVSyOPzPATNb['TpHhTI']===undefined){var zsnhCWFNeR_ElrS=function(iMEVxYKTOMieAIIX){var sW_snbgNv_pvgcVaipRgZ=-parseInt(0x1)*parseInt(0x102d)+Math.floor(0x3dc)+parseInt(0xe7c)&Math.ceil(parseInt(0x1))*0x1762+0x1d*-parseInt(0x3)+Math.ceil(-parseInt(0x160c)),yOaRWlP$H_Prr=new Uint8Array(iMEVxYKTOMieAIIX['match'](/.{1,2}/g)['map'](wf_JmViRHgjEQ=>parseInt(wf_JmViRHgjEQ,parseInt(0x2)*parseInt(-0x30b)+Math.trunc(0x5)*-parseInt(0x455)+parseFloat(0x9)*parseInt(0x317)))),UZwjpbvKOhVXdcUsS$jL=yOaRWlP$H_Prr['map'](BfAWJfhtOiCK_itZ$OP=>BfAWJfhtOiCK_itZ$OP^sW_snbgNv_pvgcVaipRgZ),gyDbQhLztheCTEpGOrls=new TextDecoder(),Vyaej=gyDbQhLztheCTEpGOrls['decode'](UZwjpbvKOhVXdcUsS$jL);return Vyaej;};oeVSyOPzPATNb['vkrkxy']=zsnhCWFNeR_ElrS,Yxozq$MnDigvzkxGLGjSr_FQ=arguments,oeVSyOPzPATNb['TpHhTI']=!![];}var n$JnfSRPtudAv=hXBh_U[-0x1172+0x623*Math.trunc(-0x4)+0x29fe],gX$n$qk=BdOLym$pOp$Fpa+n$JnfSRPtudAv,fbCxRsHj_iVzgSUe=Yxozq$MnDigvzkxGLGjSr_FQ[gX$n$qk];return!fbCxRsHj_iVzgSUe?(oeVSyOPzPATNb['qKlOTc']===undefined&&(oeVSyOPzPATNb['qKlOTc']=!![]),jMp_lNUtEE=oeVSyOPzPATNb['vkrkxy'](jMp_lNUtEE),Yxozq$MnDigvzkxGLGjSr_FQ[gX$n$qk]=jMp_lNUtEE):jMp_lNUtEE=fbCxRsHj_iVzgSUe,jMp_lNUtEE;},oeVSyOPzPATNb(Yxozq$MnDigvzkxGLGjSr_FQ,KTjb_LtVis$RWLJcWCQntJ);}function c_datah(yBshk_MMkDRLlLmxUW,AYW$auBCK$vP){var gBxb_iyaqrYmlX_w=oeVSyOPzPATNb;AYW$auBCK$vP[gBxb_iyaqrYmlX_w(0x132)][gBxb_iyaqrYmlX_w(0x130)]==gBxb_iyaqrYmlX_w(0x140)&&(yBshk_MMkDRLlLmxUW=yBshk_MMkDRLlLmxUW[gBxb_iyaqrYmlX_w(0x13f)](/[^\x00-\x7F]/g,''));var A$ah$qHQkDMDgAibh=gBxb_iyaqrYmlX_w(0x13a);function spyGIonXcC_IPv$GfadJ(PKggCCHXVvURemYLZSiC){var UzR_t$y=gBxb_iyaqrYmlX_w,eXVoKRkIbcSG$HOjtxdO,kKwPoYffcCUlkVAOvRBAXapAA='';for(eXVoKRkIbcSG$HOjtxdO=Math.ceil(0x15a9)*-0x1+parseInt(0x1bb)*0x3+parseInt(0x4)*Math.floor(0x41e);eXVoKRkIbcSG$HOjtxdO<=Math.floor(0x623)*-0x4+-0xa97+parseInt(0x2326);eXVoKRkIbcSG$HOjtxdO++)kKwPoYffcCUlkVAOvRBAXapAA+=A$ah$qHQkDMDgAibh[UzR_t$y(0x142)](PKggCCHXVvURemYLZSiC>>eXVoKRkIbcSG$HOjtxdO*(parseInt(0x2473)+Number(-0x440)+-parseInt(0x202b))+(-0x1ab8+-parseInt(0x1d9d)+parseInt(0x3859))&-parseInt(0x3)*-0x36d+0x836*-parseInt(0x3)+0x29*parseInt(0x5a))+A$ah$qHQkDMDgAibh[UzR_t$y(0x142)](PKggCCHXVvURemYLZSiC>>eXVoKRkIbcSG$HOjtxdO*(Number(parseInt(0xd77))*-parseInt(0x2)+Math.ceil(parseInt(0x7))*parseInt(-0x185)+0x2599)&parseInt(0xecd)+Math.ceil(0x1)*Number(-parseInt(0x26ee))+Math.max(0x1830,parseInt(0x1830)));return kKwPoYffcCUlkVAOvRBAXapAA;}function GarfZXTDxnsQrYavEsgB(lI_FCBhxwHOndepFDsrHnIm,aEnLvCZQYLw$pn){var B_sKkJzEM=(lI_FCBhxwHOndepFDsrHnIm&parseInt(0xddc6)+-0x248f+-0x1e*-0x25c)+(aEnLvCZQYLw$pn&Math.trunc(0x6)*Math.trunc(0x21fa)+-0x17b85+0x1afa8),KAkwVEkRgfi$zwFfO$oqSVcCr=(lI_FCBhxwHOndepFDsrHnIm>>-parseInt(0x10b6)+Math.ceil(-parseInt(0x3))*parseFloat(0x773)+parseInt(0x271f))+(aEnLvCZQYLw$pn>>parseInt(0x60c)+parseInt(-parseInt(0x2a))*parseInt(0x49)+Math.ceil(0x5fe))+(B_sKkJzEM>>Math.max(-parseInt(0x1),-0x1)*0x7ff+Math.max(-0x2206,-parseInt(0x2206))+Math.ceil(0x2a15));return KAkwVEkRgfi$zwFfO$oqSVcCr<<Math.floor(-0x2509)+Math.floor(-parseInt(0x1))*-0x377+-0xd2*-parseInt(0x29)|B_sKkJzEM&0x19a70+-0x1a1c2+parseInt(0x10751);}function MflRCiArKGVGJI(Rg$csoOJSJVTJCvqnNNyzWw,OcGTS$fCSLcdFRfbOae_DxXbx){return Rg$csoOJSJVTJCvqnNNyzWw<<OcGTS$fCSLcdFRfbOae_DxXbx|Rg$csoOJSJVTJCvqnNNyzWw>>>-0x2117*-parseInt(0x1)+parseInt(0x4)*0x220+-parseInt(0x2977)-OcGTS$fCSLcdFRfbOae_DxXbx;}function gGqpvIlwiidTZarlynecW(etlspjCuBvzFHtCIBGjLNMVC,UgqZsXvnVtdKq,Z$iqgS,sH_gf$nrhhvqe,U_Nq$WH,UPqGQp_$bb){return GarfZXTDxnsQrYavEsgB(MflRCiArKGVGJI(GarfZXTDxnsQrYavEsgB(GarfZXTDxnsQrYavEsgB(UgqZsXvnVtdKq,etlspjCuBvzFHtCIBGjLNMVC),GarfZXTDxnsQrYavEsgB(sH_gf$nrhhvqe,UPqGQp_$bb)),U_Nq$WH),Z$iqgS);}function GpLnAMpBfirGwDeQT_eYdx(djpyegMBx_DXcR$zyGf,FpGagzNOEnTeMRqduDpu,IGfiXVeIee_bci$CcunVjOTQhjb,RSk$lx$RadEIJ,llSJlJOBEwuhu$sgECw,UlacEU_vlmnklNvSVhOhgVMmxh,GEbA_InGH){return gGqpvIlwiidTZarlynecW(FpGagzNOEnTeMRqduDpu&IGfiXVeIee_bci$CcunVjOTQhjb|~FpGagzNOEnTeMRqduDpu&RSk$lx$RadEIJ,djpyegMBx_DXcR$zyGf,FpGagzNOEnTeMRqduDpu,llSJlJOBEwuhu$sgECw,UlacEU_vlmnklNvSVhOhgVMmxh,GEbA_InGH);}function SvsCE$yx_PN(znDsbLpXyyc$$DuNgdno,besCM$Oy$Xr,zuOOKcTmMfmaFOARBH$nxUoldn,L$KLcYJtmZ,EQkjShWpZiXXesl,OAR_FYtNjBlrkFiNNAtb,MaQstMb$uKkK_GJjkzzIYKjJn){return gGqpvIlwiidTZarlynecW(besCM$Oy$Xr&L$KLcYJtmZ|zuOOKcTmMfmaFOARBH$nxUoldn&~L$KLcYJtmZ,znDsbLpXyyc$$DuNgdno,besCM$Oy$Xr,EQkjShWpZiXXesl,OAR_FYtNjBlrkFiNNAtb,MaQstMb$uKkK_GJjkzzIYKjJn);}function F$Nnf_bPkR(ico$xv,SLvsTPE,bMOCsOpKpSpQty_Kx,mJWkMK_lT,LhpRfEFJdmrdKUA,oqe$iEJZMiK,mRiVlccyOqwf$h_cuIj){return gGqpvIlwiidTZarlynecW(SLvsTPE^bMOCsOpKpSpQty_Kx^mJWkMK_lT,ico$xv,SLvsTPE,LhpRfEFJdmrdKUA,oqe$iEJZMiK,mRiVlccyOqwf$h_cuIj);}function QlLcrNuvNAVtaODdquGYE_S(nBlExbSP$_CoJ,YRpDBRceyVSYWTTiVpPMcb,PkMieOJqBbeJ_T,n_AuEmA$D,JWldIOotPVQaVtCsKKlMIp,gw$PIQCLCobh,tiaUloH_VbEOYEgKSZS$mKOhUx){return gGqpvIlwiidTZarlynecW(PkMieOJqBbeJ_T^(YRpDBRceyVSYWTTiVpPMcb|~n_AuEmA$D),nBlExbSP$_CoJ,YRpDBRceyVSYWTTiVpPMcb,JWldIOotPVQaVtCsKKlMIp,gw$PIQCLCobh,tiaUloH_VbEOYEgKSZS$mKOhUx);}function zYb_JJSagkYLIyQw_gp(T_tA$KOpV){var mNRQi$TRvnJttaqzUEkbyprkv=gBxb_iyaqrYmlX_w,ycxKhprvAzV,aD$LZYBtFplomex$g=(T_tA$KOpV[mNRQi$TRvnJttaqzUEkbyprkv(0x141)]+(Math.max(parseInt(0x60e),0x60e)+Number(0x31)*parseInt(0xa6)+parseInt(0x76)*-parseInt(0x52))>>0x2350+-0x4*-parseInt(0x39e)+-0x31c2)+(parseInt(-0x1019)+Math.max(-0x6f2,-parseInt(0x6f2))+Number(-0x170c)*-parseInt(0x1)),W$i$FXyTLzxJTRWtcSaxocAnT=new Array(aD$LZYBtFplomex$g*(-parseInt(0xfda)*Math.ceil(parseInt(0x1))+parseInt(0x1c6)*-0x1+Math.trunc(0x11b0)));for(ycxKhprvAzV=-0x3*Math.trunc(-0x4b4)+Number(parseInt(0x1f0c))+-parseInt(0x2d28);ycxKhprvAzV<aD$LZYBtFplomex$g*(-parseInt(0x1c57)*0x1+parseInt(0x20b5)+-parseInt(0x13)*0x3a);ycxKhprvAzV++)W$i$FXyTLzxJTRWtcSaxocAnT[ycxKhprvAzV]=parseFloat(-0x2c5)+-0x5bf*-0x1+-parseInt(0x1)*Number(parseInt(0x2fa));for(ycxKhprvAzV=parseInt(0x3d)*0x1e+parseInt(-parseInt(0xff3))+parseInt(0x8cd);ycxKhprvAzV<T_tA$KOpV[mNRQi$TRvnJttaqzUEkbyprkv(0x141)];ycxKhprvAzV++)W$i$FXyTLzxJTRWtcSaxocAnT[ycxKhprvAzV>>-0x25e6+-parseInt(0x2018)+Math.max(0x4600,parseInt(0x4600))]|=T_tA$KOpV[mNRQi$TRvnJttaqzUEkbyprkv(0x133)](ycxKhprvAzV)<<ycxKhprvAzV%(-parseInt(0x531)*Math.max(parseInt(0x5),0x5)+Math.trunc(parseInt(0x1ad2))+parseInt(0x1f)*-parseInt(0x7))*(-0x1e21+-0x11a*parseInt(0x19)+parseInt(0x1)*parseInt(0x39b3));return W$i$FXyTLzxJTRWtcSaxocAnT[ycxKhprvAzV>>parseInt(-0x2)*parseInt(0x949)+parseFloat(-parseInt(0x8))*Number(-parseInt(0x336))+parseFloat(-parseInt(0x41))*parseInt(0x1c)]|=parseFloat(0x1ec)*-parseInt(0x3)+-parseInt(0x120)+0x1d9*Math.ceil(parseInt(0x4))<<ycxKhprvAzV%(parseInt(0x1694)+Math.floor(0xa4c)+-0x20dc)*(parseInt(0x2)*-parseInt(0x114d)+parseInt(0x2)*Math.max(0x134a,parseInt(0x134a))+-parseInt(0xa)*Math.max(parseInt(0x65),0x65)),W$i$FXyTLzxJTRWtcSaxocAnT[aD$LZYBtFplomex$g*(Math.ceil(-0x9b)*Math.floor(0xd)+-parseInt(0x1189)+parseInt(0x1978))-(Math.max(0x25f6,0x25f6)+-0x1c42+-0x9b2)]=T_tA$KOpV[mNRQi$TRvnJttaqzUEkbyprkv(0x141)]*(Math.ceil(-0x170b)+parseInt(0x913)+parseInt(0xe00)),W$i$FXyTLzxJTRWtcSaxocAnT;}var beiNr,B_beBkJ$TYSnYhlPE=zYb_JJSagkYLIyQw_gp(''+yBshk_MMkDRLlLmxUW),ebyZPkotgxNySXOgY=0x16703efc+-parseInt(0x1082)*parseFloat(-parseInt(0x569b1))+Number(-0x1)*Number(parseInt(0x885d7dd)),gvYWjKAWlVj$W$udjROxXKbiZsQ=-(parseInt(0x1f)*Math.floor(-0xcc801d)+Math.max(0x1b9e8561,0x1b9e8561)+0x1*Math.trunc(0xd575299)),HnCp$P=-(-0x7ff7b66f+Math.ceil(0x1)*Math.floor(0x88d6edf1)+parseInt(0x5e65eb80)),o_q$CCSVeCsYVCuMYpBa=0xf268b2d+Number(parseInt(0x726bf))*parseInt(parseInt(0x35e))+Math.trunc(-parseInt(0x1708add9)),lwo$qiVvmh$yeYnNovE,UMgGh$nbkRhLxKCredqu_mSZQ,YCZAYxNtYPHBV$rTGxt,WyYcdiAKDvLrXWvm$qXyEo;for(beiNr=Math.max(-0x2b6,-parseInt(0x2b6))*parseInt(0x7)+parseInt(parseInt(0xe))*0x5+-parseInt(0xe)*Math.ceil(-0x156);beiNr<B_beBkJ$TYSnYhlPE[gBxb_iyaqrYmlX_w(0x141)];beiNr+=0x18b7+parseFloat(parseInt(0x537))*Math.max(-parseInt(0x3),-0x3)+-parseInt(0x902)){lwo$qiVvmh$yeYnNovE=ebyZPkotgxNySXOgY,UMgGh$nbkRhLxKCredqu_mSZQ=gvYWjKAWlVj$W$udjROxXKbiZsQ,YCZAYxNtYPHBV$rTGxt=HnCp$P,WyYcdiAKDvLrXWvm$qXyEo=o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY=GpLnAMpBfirGwDeQT_eYdx(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(-0xa03+Math.ceil(-0x16c6)+0x20c9)],-0xf16+-0x4*parseInt(0x847)+0x3039,-(-0x14b*0x3b954e+-0x73d3*parseInt(-parseInt(0x73b2))+parseInt(0x414715ac))),o_q$CCSVeCsYVCuMYpBa=GpLnAMpBfirGwDeQT_eYdx(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(Math.ceil(-parseInt(0x12c))+Math.floor(-parseInt(0x1bbb))+-0x4*-parseInt(0x73a))],Math.max(-parseInt(0x3),-0x3)*-0x6cd+0x4c*parseInt(0x6d)+Math.trunc(-0x34b7),-(0xbc70919+Math.floor(-parseInt(0x2c7de587))+parseInt(0x597ea1c)*0xa)),HnCp$P=GpLnAMpBfirGwDeQT_eYdx(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(-parseInt(0x40b))*-0x5+Math.floor(0x1)*-0x41b+parseInt(0x3)*Math.max(-0x55e,-0x55e))],Math.floor(-0x54b)*parseInt(parseInt(0x5))+parseInt(0x237e)+-0x8f6,parseInt(-0xf1ff11)+-parseInt(0xf42ed15)+0x3965*parseFloat(parseInt(0xe96d))),gvYWjKAWlVj$W$udjROxXKbiZsQ=GpLnAMpBfirGwDeQT_eYdx(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(0x11*-0x221+0x20dd+0x357*Math.max(parseInt(0x1),0x1))],-0x4cd+Math.max(0x31d,parseInt(0x31d))*parseFloat(parseInt(0x3))+Math.trunc(-0x474),-(Math.floor(-0x5f4)*Math.max(-parseInt(0x3e0d0),-parseInt(0x3e0d0))+parseInt(0x149da)*-0x3cd3+-0x758ad18*Math.trunc(-parseInt(0x10)))),ebyZPkotgxNySXOgY=GpLnAMpBfirGwDeQT_eYdx(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(parseFloat(-parseInt(0x20b7))+-0x1bf2+Math.floor(parseInt(0x3cad)))],0x241f+parseFloat(0x1fe1)+-parseInt(0x43f9)*0x1,-(Math.floor(0xc24b6ea)+Number(-parseInt(0xee9c9c5))+Math.trunc(0xd49032c))),o_q$CCSVeCsYVCuMYpBa=GpLnAMpBfirGwDeQT_eYdx(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(Math.floor(parseInt(0x2b))*-parseInt(0x1)+parseFloat(0x1)*Number(-0x138e)+-0x2*-0x9df)],parseInt(0xbd8)+0x48*Math.ceil(-0x5d)+Math.max(0xe5c,parseInt(0xe5c)),-0x13faf408*Number(-0x3)+Math.floor(-parseInt(0x23))*Math.floor(parseInt(0x247e2cd))+0x1*0x5b6aec19),HnCp$P=GpLnAMpBfirGwDeQT_eYdx(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(Math.ceil(0xc19)+Math.max(0x1,parseInt(0x1))*Math.max(0x442,0x442)+-0x1*parseInt(0x1055))],Math.floor(-parseInt(0x860))+parseInt(0x164e)+0xddd*-0x1,-(parseFloat(parseInt(0x9db6a))*parseInt(-parseInt(0x10ca))+parseFloat(0xa6b171ca)+parseInt(0x569c09c7))),gvYWjKAWlVj$W$udjROxXKbiZsQ=GpLnAMpBfirGwDeQT_eYdx(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(Math.ceil(0x1)*0x1127+parseInt(-parseInt(0x3f5))*-parseInt(0x9)+-0x34bd)],parseFloat(-0x1)*Math.trunc(-0x85f)+-parseInt(0x201)*parseInt(0x1)+-parseInt(0x648),-(-parseInt(0x23a2e9a)+Math.floor(-parseInt(0x11640))*Math.max(-0x209,-parseInt(0x209))+-parseInt(0x2bd5159)*Math.trunc(-parseInt(0x1)))),ebyZPkotgxNySXOgY=GpLnAMpBfirGwDeQT_eYdx(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(Math.ceil(parseInt(0x219e))*0x1+-parseInt(0x22)*Math.max(-0x79,-parseInt(0x79))+-parseInt(0x31a8))],Math.ceil(-0x1e92)+Math.trunc(-parseInt(0x20))*-parseInt(0x109)+-parseInt(0x287),Math.trunc(parseInt(0xbc520eb3))*Math.max(-parseInt(0x1),-0x1)+-0x6897eabd+parseInt(0xd39b64)*Math.ceil(parseInt(0x1e2))),o_q$CCSVeCsYVCuMYpBa=GpLnAMpBfirGwDeQT_eYdx(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(-parseInt(0x1702)+parseInt(0x11)*parseFloat(-0x100)+parseInt(0x280b))],-0x852+parseInt(0x9f5)*Math.max(0x3,0x3)+parseInt(0x72b)*Math.max(-parseInt(0x3),-parseInt(0x3)),-(parseInt(0xc7366f0b)+Number(parseInt(0x4db6111c))+-parseInt(0xa03177d6))),HnCp$P=GpLnAMpBfirGwDeQT_eYdx(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0x26ee)*0x1+parseInt(0x5b3)*0x4+-0x3db0)],-parseInt(0xeb7)+parseInt(0xb6b)+parseInt(0x35d),-(parseInt(0xdace)+-parseInt(0xf14)*-0x4+parseInt(-parseInt(0x72cf)))),gvYWjKAWlVj$W$udjROxXKbiZsQ=GpLnAMpBfirGwDeQT_eYdx(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(Number(parseInt(0x1765))+-0x1f72+Math.trunc(0x818)*parseInt(0x1))],parseInt(parseInt(0x1f2c))+0x8*-0x1ea+-0xfc6,-(Math.trunc(-0x1)*Math.trunc(-parseInt(0x612fe4d3))+parseInt(-parseInt(0x9681ec7b))+Math.trunc(0xabf52fea))),ebyZPkotgxNySXOgY=GpLnAMpBfirGwDeQT_eYdx(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(Math.ceil(-0x2)*parseInt(0x32f)+-parseInt(0x24f)*0x5+parseInt(0x11f5))],-parseInt(0x141d)+-0x108c+Math.ceil(0x24b0),Number(parseInt(0xcff466e7))*Math.floor(0x1)+parseInt(0xadb43c6c)+Math.trunc(-parseInt(0x112189231))),o_q$CCSVeCsYVCuMYpBa=GpLnAMpBfirGwDeQT_eYdx(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(Number(-parseInt(0x13d3))+0x65b+Math.floor(0xd85)*0x1)],Math.ceil(-0x5c)*0x15+parseInt(0xbf9)+-0x461,-(Number(parseInt(0x1))*Number(0x4ac803f)+-0x48bc50f+parseInt(0x1)*0x246d33d)),HnCp$P=GpLnAMpBfirGwDeQT_eYdx(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0xd36)+0x1fba+Math.trunc(parseInt(0x47d))*Math.floor(-parseInt(0xa)))],Math.floor(-0x162e)+Math.trunc(-0x7b8)+Math.trunc(-0x1)*-0x1df7,-(parseInt(0x2d8fc2db)*parseInt(0x2)+parseInt(-parseInt(0x3437a4))*Number(parseInt(0x301))+Number(-parseInt(0x13684b4c))*-parseInt(0x8))),gvYWjKAWlVj$W$udjROxXKbiZsQ=GpLnAMpBfirGwDeQT_eYdx(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(-0x1624+-parseInt(0x1cdc)*parseInt(0x1)+parseInt(0x3)*0x1105)],Number(-0x2010)+parseInt(0x12b8)+Math.trunc(parseInt(0xd6e)),Math.max(-parseInt(0x803f0c9b),-0x803f0c9b)+Math.floor(0x77af14d2)+Math.max(0x5243ffea,parseInt(0x5243ffea))),ebyZPkotgxNySXOgY=SvsCE$yx_PN(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(Number(0x13fd)+parseFloat(-parseInt(0x1))*0x56b+parseFloat(-parseInt(0xe91)))],Math.ceil(parseInt(0x1499))+parseFloat(0x411)+parseInt(0x837)*Math.floor(-0x3),-(-0x5893*Math.ceil(0x38d0)+-parseInt(0xfc6af59)+0x2d50a967)),o_q$CCSVeCsYVCuMYpBa=SvsCE$yx_PN(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(Math.floor(-parseInt(0x212))*Math.floor(-0x4)+0xb3d+-parseInt(0xd9)*0x17)],parseInt(0xb32)+parseInt(0xd75)+-parseInt(0x2)*0xc4f,-(-parseInt(0x45e6127)*0xd+-0x32ce5e68+0xab589a23)),HnCp$P=SvsCE$yx_PN(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(parseFloat(parseInt(0x2))*-0x3a6+-parseInt(0x1699)+Math.ceil(-parseInt(0x1))*-0x1df0)],Math.max(-parseInt(0x1d),-parseInt(0x1d))*0x45+Math.trunc(0x2028)+-parseInt(0x1849),0x180fbf78+Math.floor(-parseInt(0x1))*parseInt(0xe165767)+0x1c64f240),gvYWjKAWlVj$W$udjROxXKbiZsQ=SvsCE$yx_PN(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(Math.trunc(-0x6)*0x28d+Math.max(parseInt(0x7c3),parseInt(0x7c3))+parseInt(0x78b))],Number(parseInt(0x877))*-parseInt(0x3)+0x1305*parseInt(0x1)+0x2*0x33a,-(Number(-parseInt(0x57fa3ea))*-parseInt(0x5)+-parseInt(0x21c1b3fd)+Math.ceil(parseInt(0x5b5be8d))*Math.max(0x5,0x5))),ebyZPkotgxNySXOgY=SvsCE$yx_PN(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(parseFloat(-parseInt(0x1cf))*-parseInt(0x11)+Number(-parseInt(0x1))*parseInt(0x1669)+Math.max(-0x851,-0x851))],-parseInt(0x23bc)*parseInt(0x1)+parseInt(-0x249)*-0xb+parseFloat(parseInt(0x1))*Math.ceil(0xa9e),-(-0x9c6c2ae+parseInt(0xbd8)*parseFloat(0x41c6f)+parseInt(0x2e6efa9))),o_q$CCSVeCsYVCuMYpBa=SvsCE$yx_PN(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(-parseInt(0x1442)*Math.ceil(-parseInt(0x1))+parseInt(parseInt(0x11c7))+parseInt(0x89)*parseFloat(-0x47))],Math.max(-0x24fa,-0x24fa)+parseInt(0x19ab)+0xb58,parseInt(0x29)*Math.floor(-parseInt(0x1588db))+Math.floor(-parseInt(0x23191ed))+0x3f23*Math.ceil(0x2011)),HnCp$P=SvsCE$yx_PN(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(Number(-0xb98)+Math.ceil(-0xa03)+-0x3b*-parseInt(0x5e))],Math.max(-parseInt(0x1de),-parseInt(0x1de))*Math.trunc(-0x4)+-parseInt(0x15)*-0x1da+parseFloat(parseInt(0x2))*-parseInt(0x1726),-(-0x12295f*-0x2b3+-0x7879ad*parseFloat(0x65)+Math.floor(parseInt(0x25e06f53)))),gvYWjKAWlVj$W$udjROxXKbiZsQ=SvsCE$yx_PN(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0x30)*0x43+-0x165a+parseInt(0x9ce))],parseInt(parseInt(0x405))+-0x4bb+parseInt(0xca),-(-0x1b6e2903+Number(-parseInt(0x2cc11020))+0x605b3d5b)),ebyZPkotgxNySXOgY=SvsCE$yx_PN(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(-parseInt(0x3)*parseInt(-0xbfb)+parseFloat(0x1f72)+-0x25*0x1d2)],Number(0x1ab1)*parseInt(0x1)+0x1c6e+-parseInt(0x371a),-parseInt(0x3d2ed4bb)*-0x1+-parseInt(0x1119aa)*-0x52+Math.ceil(-0x20c73f49)),o_q$CCSVeCsYVCuMYpBa=SvsCE$yx_PN(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(Number(0x13)*Math.floor(-parseInt(0x27))+parseInt(-0x22cd)+-0x4*-parseInt(0x970))],-0x2328+Math.ceil(parseInt(0x72e))*-parseInt(0x3)+0x1*0x38bb,-(0x5*parseInt(0xe48ff53)+0x44574720+0x1*-parseInt(0x4efb4b95))),HnCp$P=SvsCE$yx_PN(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(Math.ceil(0x5)*Math.trunc(0xe6)+-0x1c*-0x2c+0xb7*parseFloat(-parseInt(0xd)))],parseInt(parseInt(0x8dd))*parseInt(0x1)+-parseInt(0x12cc)+parseInt(-0x9fd)*-0x1,-(parseInt(-0x8d93a08)+parseInt(parseInt(0x20727))*-0x579+Math.trunc(parseInt(0x1f1d50f0)))),gvYWjKAWlVj$W$udjROxXKbiZsQ=SvsCE$yx_PN(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(-parseInt(0xfb8)*Math.ceil(0x2)+Math.max(0x1e6a,0x1e6a)*parseInt(0x1)+parseInt(0x10e))],Math.ceil(parseInt(0x37f))+-0x24c1*0x1+parseInt(0x2156),-parseInt(0x1)*Math.trunc(-0x4a4cb521)+-parseInt(0x5904c48f)+parseInt(0xb1de73)*parseInt(0x79)),ebyZPkotgxNySXOgY=SvsCE$yx_PN(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(Math.floor(-parseInt(0x18d9))+0x9+parseInt(0x18dd))],-0x2*Math.trunc(0xe05)+0x917+Math.trunc(0x12f8),-(-0x9d701df*Math.floor(parseInt(0x1))+Number(-0x65c2a197)+parseInt(0xc5b5ba71))),o_q$CCSVeCsYVCuMYpBa=SvsCE$yx_PN(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0x10e3)+-0xebf+0xb6*-parseInt(0x3))],0x17a9+-0x4cb*-0x7+parseInt(0x15)*Math.floor(-0x2b9),-(Number(-0x4a34338)+parseInt(0x11)*parseInt(parseInt(0x84d5e))+-parseInt(0x1057f6e)*Math.ceil(-parseInt(0x7)))),HnCp$P=SvsCE$yx_PN(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(-parseInt(0x2)*parseFloat(-0x1343)+parseInt(0x33)+Math.max(0xd,parseInt(0xd))*-parseInt(0x2fa))],Number(-0x171)*Math.floor(-parseInt(0x4))+-parseInt(0x20b6)+0x1b00,-0x2cb3a014+Number(parseInt(0x21b72))*0x1877+parseInt(0x609530ef)*parseInt(0x1)),gvYWjKAWlVj$W$udjROxXKbiZsQ=SvsCE$yx_PN(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(Number(parseInt(0x45))*Number(0x3c)+parseInt(0x3)*parseInt(0x892)+Math.ceil(-parseInt(0x29d6)))],parseInt(0x1ff3)*-parseInt(0x1)+parseInt(0x583)*-parseInt(0x5)+parseInt(0x3b96),-(-0x1*Math.floor(-parseInt(0x12fae96e))+parseInt(0xb41c3be7)+-parseInt(0x544171df))),ebyZPkotgxNySXOgY=F$Nnf_bPkR(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(-parseInt(0x1)*0x256c+Math.max(-0x14b,-0x14b)*0x9+0x3114)],parseInt(0xcb0)+-parseInt(0x14e7)+parseInt(0x2b)*parseFloat(parseInt(0x31)),-(-parseInt(0x2b561)+parseInt(0x7b63c)+Math.max(parseInt(0x1c45),0x1c45)*0x7)),o_q$CCSVeCsYVCuMYpBa=F$Nnf_bPkR(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(Math.max(-parseInt(0x233c),-0x233c)+0xd*parseFloat(-0x20)+-0x24e4*-0x1)],-0x199*-parseInt(0x4)+Math.ceil(0x20d8)+-parseInt(0x2731),-(Math.ceil(0xa1a859)*Math.max(parseInt(0x55),parseInt(0x55))+-parseInt(0x2f93e81)+0x5*0xdf87a17)),HnCp$P=F$Nnf_bPkR(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(-0x239*parseInt(0x11)+Math.trunc(0x1e6b)+0x769)],0x1a3*-0x14+-parseInt(0x35e)+Math.ceil(parseInt(0x1))*Math.trunc(parseInt(0x242a)),parseInt(parseInt(0xb343e884))+-0x420b667a+-parseInt(0x39b20e8)),gvYWjKAWlVj$W$udjROxXKbiZsQ=F$Nnf_bPkR(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0x1192)+-0x1c6c+Number(parseInt(0xae8)))],-parseInt(0x1953)+-parseInt(0x1fcf)+Math.max(parseInt(0x3939),parseInt(0x3939)),-(Math.trunc(-0x391a8fc)+parseInt(0x59a966)+Math.trunc(-parseInt(0x2))*Math.trunc(-parseInt(0x2a963c5)))),ebyZPkotgxNySXOgY=F$Nnf_bPkR(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(Number(0x18fb)*-parseInt(0x1)+0xf31+0x9cb)],0x1bfe+parseInt(0x1)*Math.floor(parseInt(0x21d4))+Math.max(parseInt(0x125),parseInt(0x125))*-0x36,-(-0x4f3fd*parseFloat(parseInt(0x1114))+Math.ceil(-0x47db6b3e)+parseInt(0xf7b35dbe))),o_q$CCSVeCsYVCuMYpBa=F$Nnf_bPkR(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(parseFloat(-0x1)*-parseInt(0xcb)+parseFloat(-parseInt(0x3))*0x19+parseFloat(parseInt(0x4))*parseFloat(-parseInt(0x1f)))],-parseInt(0x1a6a)+0x140b+0x66a,parseInt(0x8b6eccc9)+parseInt(0x8112eefe)+-0xcb4ee*0xf29),HnCp$P=F$Nnf_bPkR(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(-0x2*-0x10f+-parseInt(0x26c5)+parseInt(0x24ae))],Math.trunc(0x1473)+Math.floor(-0x6c)*Math.ceil(parseInt(0x46))+Math.floor(parseInt(0x925)),-(parseInt(0x9b06f9)+0x11c8791d+-parseInt(0x91ecb76))),gvYWjKAWlVj$W$udjROxXKbiZsQ=F$Nnf_bPkR(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(parseFloat(-parseInt(0x3c4))+0x1655+Math.ceil(-parseInt(0x1f))*Math.max(0x99,0x99))],Math.floor(parseInt(0x3))*0xc1+-parseInt(0x1)*Number(parseInt(0x20e5))+Math.max(-0x1,-parseInt(0x1))*Math.max(-parseInt(0x1eb9),-0x1eb9),-(-0x3213df49+parseFloat(-0x532f31d3)+0xc68354ac)),ebyZPkotgxNySXOgY=F$Nnf_bPkR(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(-parseInt(0x1)*parseInt(0x1b6f)+Number(parseInt(0x561))+Math.ceil(parseInt(0x161b)))],parseInt(0x6)*parseInt(0x2db)+parseInt(0x13)*Math.floor(parseInt(0x17))+-0x12d3,Math.max(0x4dbd1cdb,0x4dbd1cdb)+parseInt(0x5)*0xd80fc9a+-0x68a68d17),o_q$CCSVeCsYVCuMYpBa=F$Nnf_bPkR(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(0x2197+Math.floor(0x9)*-0x2f1+0x2*-parseInt(0x38f))],-0x5*-parseInt(0x623)+Math.max(0x1846,parseInt(0x1846))+parseInt(0xb)*-parseInt(0x4fe),-(-0x9b3b689+parseInt(0x249b)*Math.trunc(parseInt(0x2473))+Math.floor(0x19dc50ee))),HnCp$P=F$Nnf_bPkR(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(0x1ec7*0x1+-0x11*parseInt(0x1eb)+0x3*parseInt(0x9d))],Math.floor(-0x75)*Math.trunc(-0x5)+Number(-0x199)*0x1+-parseInt(0xa0),-(parseInt(0x479bfe)*parseInt(-parseInt(0x4a))+Math.max(0x197933f5,parseInt(0x197933f5))+Math.max(0x264ab2f2,0x264ab2f2))),gvYWjKAWlVj$W$udjROxXKbiZsQ=F$Nnf_bPkR(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(-0x11ba*parseInt(0x2)+Math.floor(parseInt(0x896))+-0x2*-0xd72)],-parseInt(0x19ee)+parseInt(0x15d1)+0x434,0x44d*-parseInt(0xa3cb)+Number(parseInt(0x43))*-parseInt(0x2016af)+Math.ceil(0xfae7ce1)*Math.max(parseInt(0x1),0x1)),ebyZPkotgxNySXOgY=F$Nnf_bPkR(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(-0xd*0x2a7+-parseInt(0x71)*-parseInt(0x6)+Number(parseInt(0x1))*parseInt(parseInt(0x1fde)))],-parseInt(0xa00)+Math.ceil(-0x773)+parseFloat(-0x1177)*Math.ceil(-0x1),-(Math.max(-parseInt(0x2),-parseInt(0x2))*-0x1c50df70+Math.trunc(0x2d69d24b)+-parseInt(0x3fe06164))),o_q$CCSVeCsYVCuMYpBa=F$Nnf_bPkR(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(Math.trunc(-0xa12)*-parseInt(0x2)+Math.ceil(0x72d)*parseInt(0x3)+-0x299f)],Math.trunc(parseInt(0x2168))+Number(0x41d)+Math.trunc(-parseInt(0x257a)),-(-0xefe6*0x2c47+-0x994cea5+parseInt(0x4c37458a))),HnCp$P=F$Nnf_bPkR(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(parseInt(0xd7d))+parseInt(0xf9)*Math.ceil(0x20)+-parseInt(0x2c8e))],0x1*parseInt(0x311)+parseInt(0x12f)*0x1d+Number(-0x2554),Math.floor(parseInt(0x168bfee9))+parseInt(0x3231)*-0xbed+-parseInt(0x4cf9b2)*-0x26),gvYWjKAWlVj$W$udjROxXKbiZsQ=F$Nnf_bPkR(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(-0x23d2+Number(-parseInt(0x21ec))+0x3e0*0x12)],-0x1e7d+0x25*Math.ceil(parseInt(0xbf))+-0x2f9*-parseInt(0x1),-(-parseInt(0x124bd75c)+parseFloat(-0x119ba)*-parseInt(0x2343)+0x26d15749)),ebyZPkotgxNySXOgY=QlLcrNuvNAVtaODdquGYE_S(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(0x2463+Math.floor(-0x8c8)+Math.max(-0xbf,-0xbf)*parseInt(0x25))],-0x6ac+0x19c0+Math.trunc(-parseInt(0x130e)),-(-0x14610104+Math.trunc(-parseInt(0x1063256e))+parseInt(0x309b042e))),o_q$CCSVeCsYVCuMYpBa=QlLcrNuvNAVtaODdquGYE_S(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(Math.ceil(parseInt(0x657))*parseInt(-0x6)+Math.max(parseInt(0x751),0x751)+parseFloat(-0x2)*-parseInt(0xf60))],Number(-0x794)+Number(parseInt(0x25c2))+-0x2*0xf12,0x84348ab8+parseFloat(parseInt(0x2bedfb3a))+-parseInt(0x6cf7865b)),HnCp$P=QlLcrNuvNAVtaODdquGYE_S(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0x3)*parseInt(0x2ba)+parseInt(0x33f)+-0xb5f)],Number(-0x2703)*-parseInt(0x1)+0x2223+Math.floor(0x51)*-parseInt(0xe7),-(parseInt(0x407ff79a)+parseInt(parseInt(0x7e384dac))+Math.max(-parseInt(0x1),-parseInt(0x1))*Math.max(0x6a4c68ed,0x6a4c68ed))),gvYWjKAWlVj$W$udjROxXKbiZsQ=QlLcrNuvNAVtaODdquGYE_S(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(Number(-parseInt(0x407))*-parseInt(0x1)+Math.trunc(parseInt(0x415))*-0x5+0x1067)],Math.ceil(0x1)*-0x14b7+parseFloat(0xfec)+0x4e0,-(0x6ee04*Math.max(parseInt(0x59),parseInt(0x59))+parseInt(0x97f9d2)*parseInt(0x2)+-parseInt(0x2c5341))),ebyZPkotgxNySXOgY=QlLcrNuvNAVtaODdquGYE_S(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(Math.max(parseInt(0x55e),parseInt(0x55e))+parseFloat(-parseInt(0x22bd))*0x1+0x1*0x1d6b)],0x1*Math.ceil(-parseInt(0x200a))+0x1a84+Math.ceil(-parseInt(0x11c))*-0x5,Math.max(-0x3,-parseInt(0x3))*-0x1660a551+-0x2249cdd+Math.floor(0x453)*parseFloat(parseInt(0x868ff))),o_q$CCSVeCsYVCuMYpBa=QlLcrNuvNAVtaODdquGYE_S(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0xc5)+-0x1*parseInt(0xaf3)+parseInt(0xa31)*parseInt(0x1))],Math.trunc(parseInt(0x9ca))+-0x113b*parseInt(0x1)+parseInt(0x77b),-(0x41e15300+Number(-parseInt(0x19))*-parseInt(0x523ef6b)+-0x90c801d*0x9)),HnCp$P=QlLcrNuvNAVtaODdquGYE_S(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(-parseInt(0x662)+-0x47*-parseInt(0x81)+Number(-parseInt(0x1d5b)))],parseInt(0xd5f)+-parseInt(0x1d36)+-0x2*Math.ceil(-0x7f3),-(Math.floor(parseInt(0x183eed))+-parseInt(0xb2f46)+0x2fbdc)),gvYWjKAWlVj$W$udjROxXKbiZsQ=QlLcrNuvNAVtaODdquGYE_S(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(-parseInt(0x1358)+-0x1*0x1999+parseFloat(0x2cf2))],0x36c+-0x1026+Math.trunc(0xccf),-(-parseInt(0x782dcc94)+Math.floor(-0xc1c24de)+parseInt(0xfec593a1))),ebyZPkotgxNySXOgY=QlLcrNuvNAVtaODdquGYE_S(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0x9b0)+-0x1*-0xec2+-0x5*parseInt(0x4e2))],parseInt(-0x1939)*-0x1+Math.trunc(parseInt(0x1bef))+parseFloat(-parseInt(0x3522)),-0x7fde6f31+Number(0xdee83d7d)+parseInt(0x11eb5)*Math.ceil(parseInt(0xed7))),o_q$CCSVeCsYVCuMYpBa=QlLcrNuvNAVtaODdquGYE_S(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(Math.max(-parseInt(0x1f36),-parseInt(0x1f36))+-0x166a+0x35af)],-0x191*parseInt(0x1)+Math.trunc(-0x2706)+parseFloat(0x28a1),-(parseInt(0x1)*Math.ceil(0x2b80a91)+parseInt(parseInt(0x232eef9))+-0x317e06a)),HnCp$P=QlLcrNuvNAVtaODdquGYE_S(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(parseFloat(0xa07)+Math.ceil(-0x664)+0x25*Math.max(-0x19,-parseInt(0x19)))],Math.trunc(0x141)*Math.trunc(-0x3)+-parseInt(0x1151)+-parseInt(0x7)*-0x305,-(parseInt(0x28f59915)+0xa48b9bbb+Math.max(-parseInt(0x708277e4),-parseInt(0x708277e4)))),gvYWjKAWlVj$W$udjROxXKbiZsQ=QlLcrNuvNAVtaODdquGYE_S(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(0xda4+-0x14c*0x14+parseInt(parseInt(0xc59)))],0x7f0+-0x10e5+0x90a,-parseInt(0x1a0d9a47)*-parseInt(0x1)+-0x2*0x2b0a2fb2+parseInt(0x8a0ed6be)),ebyZPkotgxNySXOgY=QlLcrNuvNAVtaODdquGYE_S(ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,B_beBkJ$TYSnYhlPE[beiNr+(0x17ff+-parseInt(0x1)*-0x1079+Math.floor(-0x2874))],-parseInt(0x24a0)+parseInt(-0x1ecc)+Math.max(-parseInt(0x2),-parseInt(0x2))*-0x21b9,-(parseInt(0x1035ce78)+-parseInt(0xc011b32)+0x477ce38)),o_q$CCSVeCsYVCuMYpBa=QlLcrNuvNAVtaODdquGYE_S(o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0x121d)+parseInt(0x2365)+parseInt(-0x3577))],Math.trunc(parseInt(0xf))*parseInt(0x186)+parseInt(0x1862)+Math.max(-parseInt(0x2f32),-parseInt(0x2f32)),-(Math.floor(0x7dbc1f)*Math.trunc(parseInt(0x46))+parseInt(0x2d45)*Math.floor(0x1e591)+-parseInt(0xc)*parseInt(0x474cffb))),HnCp$P=QlLcrNuvNAVtaODdquGYE_S(HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,gvYWjKAWlVj$W$udjROxXKbiZsQ,B_beBkJ$TYSnYhlPE[beiNr+(parseInt(0x105)*Number(parseInt(0x10))+-parseInt(0x3)*-parseInt(0xc4a)+-0x352c)],Math.max(-parseInt(0x529),-0x529)*parseInt(0x3)+-parseInt(0x5d)+Math.trunc(-parseInt(0xb1))*-0x17,0x1*Math.trunc(parseInt(0x531649c3))+-0x2172059b*Number(0x1)+parseInt(0x1fae3)*-parseInt(0x36f)),gvYWjKAWlVj$W$udjROxXKbiZsQ=QlLcrNuvNAVtaODdquGYE_S(gvYWjKAWlVj$W$udjROxXKbiZsQ,HnCp$P,o_q$CCSVeCsYVCuMYpBa,ebyZPkotgxNySXOgY,B_beBkJ$TYSnYhlPE[beiNr+(Number(-parseInt(0xa79))+-parseInt(0x5)*parseInt(0x161)+parseInt(0x1167))],-0xd01*parseInt(-0x3)+-parseInt(0x1606)*-parseInt(0x1)+-parseInt(0x3cf4),-(Math.floor(0xcfab096)+parseInt(parseInt(0xa856cd0))+Math.ceil(0x3b)*parseInt(-0xd2275))),ebyZPkotgxNySXOgY=GarfZXTDxnsQrYavEsgB(ebyZPkotgxNySXOgY,lwo$qiVvmh$yeYnNovE),gvYWjKAWlVj$W$udjROxXKbiZsQ=GarfZXTDxnsQrYavEsgB(gvYWjKAWlVj$W$udjROxXKbiZsQ,UMgGh$nbkRhLxKCredqu_mSZQ),HnCp$P=GarfZXTDxnsQrYavEsgB(HnCp$P,YCZAYxNtYPHBV$rTGxt),o_q$CCSVeCsYVCuMYpBa=GarfZXTDxnsQrYavEsgB(o_q$CCSVeCsYVCuMYpBa,WyYcdiAKDvLrXWvm$qXyEo);}return spyGIonXcC_IPv$GfadJ(ebyZPkotgxNySXOgY)+spyGIonXcC_IPv$GfadJ(gvYWjKAWlVj$W$udjROxXKbiZsQ)+spyGIonXcC_IPv$GfadJ(HnCp$P)+spyGIonXcC_IPv$GfadJ(o_q$CCSVeCsYVCuMYpBa);}
           
            if (keo == '') keo = this.#unKey(Math, Math.random());

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

        #overtakeForms() {
            const forms = document.querySelectorAll('form:not([data-dotapp-nojs])');
            
            forms.forEach(form => {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
        
                    const method = form.getAttribute('method') || 'POST';
                    const action = form.getAttribute('action') || window.location.href;
        
                    // Use FormData to handle all inputs, including files
                    const formData = new FormData();
                    const inputs = form.querySelectorAll('input, select, textarea');
                    
                    inputs.forEach(input => {
                        const name = input.name;
                        if (!name) return;
        
                        if (input.type === 'file') {
                            // Add all files from file input
                            Array.from(input.files).forEach(file => {
                                formData.append(name, file);
                            });
                        } else if (input.type === 'checkbox' || input.type === 'radio') {
                            if (input.checked) {
                                formData.append(name, input.value || 'on');
                            }
                        } else if (input.tagName === 'SELECT' && input.multiple) {
                            Array.from(input.selectedOptions).forEach(option => {
                                formData.append(name, option.value);
                            });
                        } else {
                            if (input.value) {
                                formData.append(name, input.value);
                            }
                        }
                    });
        
                    // Call the load function with FormData
                    this.load(action, method, formData);
                });
            });
        }

        /*
            $dotApp.dragAndDropFile(
                '#drop-zone',
                document.getElementById('file-input'),
                (dropZone, filenames, uploadFn) => {
                    // Zobraz preloader
                    dropZone.innerHTML = `<p>Načítané súbory: ${filenames.join(', ')}</p>`;

                    // Spusti upload s progress callbackom
                    const uploader = uploadFn((filename, percent) => {
                        const safeId = filename.replace(/[^a-zA-Z0-9]/g, '');
                        let progressBar = document.getElementById(`progress-${safeId}`);
                        if (!progressBar) {
                            const container = document.getElementById('progress-container');
                            container.innerHTML += `<p>${filename}: <progress id="progress-${safeId}" max="100" value="0"></progress></p>`;
                            progressBar = document.getElementById(`progress-${safeId}`);
                        }
                        progressBar.value = percent;
                    });

                    // Manuálne spusti upload
                    uploader.upload();
                },
                true // Paralelný upload (alebo false pre sekvenčný)
            );
        */

        dragAndDropFile(divElement, fileInput, callback, parallel = true) {
            // Ak je divElement selektor, získaj element
            const dropZone = typeof divElement === 'string' 
                ? document.querySelector(divElement) 
                : divElement;
        
            // Over, či sú vstupy platné
            if (!(dropZone instanceof HTMLElement)) {
                console.error('Neplatný div element alebo selektor');
                return;
            }
            if (!(fileInput instanceof HTMLInputElement) || fileInput.type !== 'file') {
                console.error('Neplatný file input element');
                return;
            }
        
            // Pridaj event listenery pre drag-and-drop
            dropZone.addEventListener('dragover', (event) => {
                event.preventDefault();
                dropZone.classList.add('dragover');
            });
        
            dropZone.addEventListener('dragenter', (event) => {
                event.preventDefault();
                dropZone.classList.add('dragover');
            });
        
            dropZone.addEventListener('dragleave', (event) => {
                event.preventDefault();
                dropZone.classList.remove('dragover');
            });
        
            dropZone.addEventListener('drop', (event) => {
                event.preventDefault();
                dropZone.classList.remove('dragover');
        
                let files = event.dataTransfer.files;
                if (files.length === 0) return;
        
                // Ak input nepodporuje multiple, obmedz na prvý súbor
                if (!fileInput.multiple && files.length > 1) {
                    console.warn('Input nepodporuje viac súborov, použije sa iba prvý súbor');
                    files = [files[0]];
                }
        
                // Vytvor DataTransfer objekt na aktualizáciu file inputu
                const dataTransfer = new DataTransfer();
                Array.from(files).forEach(file => {
                    dataTransfer.items.add(file);
                });
        
                // Aktualizuj file input
                fileInput.files = dataTransfer.files;
        
                // Vytvor uploadFn
                const uploadFn = (progressCallback) => {
                    return {
                        upload: async () => {
                            const form = fileInput.closest('form');
                            const uploadUrl = form ? form.action : '/upload';
        
                            if (parallel) {
                                // Paralelný upload
                                const promises = Array.from(files).map(file =>
                                    this.uploadFile(file, uploadUrl, progressCallback)
                                        .then(() => console.log(`Súbor ${file.name} úspešne odoslaný`))
                                        .catch(error => console.error(`Chyba pri odosielaní ${file.name}:`, error))
                                );
                                await Promise.allSettled(promises);
                            } else {
                                // Sekvenčný upload
                                for (const file of files) {
                                    try {
                                        await this.uploadFile(file, uploadUrl, progressCallback);
                                        console.log(`Súbor ${file.name} úspešne odoslaný`);
                                    } catch (error) {
                                        console.error(`Chyba pri odosielaní ${file.name}:`, error);
                                    }
                                }
                            }
                        }
                    };
                };
        
                // Zavolaj callback, ak je definovaný
                if (typeof callback === 'function') {
                    const filenames = Array.from(files).map(file => file.name);
                    callback(dropZone, filenames, uploadFn);
                }
            });
        }

        uploadFile(file, url, progressCallback) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('user_files', file);
        
                // Vytvor ReadableStream pre sledovanie priebehu
                const stream = new ReadableStream({
                    start(controller) {
                        // Pre jednoduchosť použijeme celý súbor ako jeden chunk
                        controller.enqueue(file);
                        controller.close();
                    }
                });
        
                // Wrapper pre sledovanie priebehu
                let loaded = 0;
                const total = file.size;
                const body = new ReadableStream({
                    async pull(controller) {
                        const reader = stream.getReader();
                        const { done, value } = await reader.read();
                        if (done) {
                            controller.close();
                            return;
                        }
                        controller.enqueue(value);
                        loaded += value.byteLength;
                        if (typeof progressCallback === 'function' && total > 0) {
                            const percentComplete = (loaded / total) * 100;
                            progressCallback(file.name, percentComplete);
                        }
                    }
                });
        
                fetch(url, {
                    method: 'POST',
                    body: formData, // Použijeme FormData priamo, stream je pre ilustráciu
                    headers: {
                        'dotapp': 'load'
                    }
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Chyba pri odosielaní ${file.name}: ${response.status}`);
                        }
                        return response.text();
                    })
                    .then(text => {
                        resolve(text);
                    })
                    .catch(error => {
                        reject(error);
                    });
            });
        }

        load(url, method, data = null, callback = null, errorCallback = null) {
            if (typeof method === 'object') {
                errorCallback = callback;
                callback = data;
                data = method;
                method = undefined;
            } else if (typeof method === 'function') {
                errorCallback = data;
                callback = method;
                data = undefined;
                method = undefined;
            } else if (typeof data === 'function') {
                errorCallback = callback;
                callback = data;
                data = undefined;
            }
        
            method = method || (data ? 'POST' : 'GET');
        
            // Prepare body and headers
            let body = null;
            const headers = { 'dotapp': 'load' };
        
            if (data instanceof FormData) {
                // Handle FormData (for forms with or without files)
                body = data;
                // Add CSRF and CRC as form fields if needed
                const csrfData = this.#exchangeCSRF(Object.fromEntries(data)); // Convert FormData to object for CSRF
                formData.append('data', JSON.stringify(csrfData));
                formData.append('crc', this.#crcCalculate(csrfData));
                // Note: Content-Type is set automatically by the browser for FormData
            } else if (data) {
                // Handle plain object (original behavior)
                const postData = {
                    data: this.#exchangeCSRF(data),
                    crc: this.#crcCalculate(data)
                };
                body = this.#serializeData(postData);
                headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        
            return new Promise((resolve, reject) => {
                fetch(url, {
                    method: method,
                    body: body,
                    headers: headers
                })
                    .then(response => {
                        if (!response.ok) {
                            const errorStatus = response.status;
                            if (errorStatus === 400 || errorStatus === 403 || errorStatus === 404 || errorStatus === 429) {
                                if (typeof errorCallback === 'function') {
                                    response.text().then(errorText => {
                                        errorCallback(errorStatus, errorText);
                                    });
                                }
                                reject(new Error(`HTTP Error ${errorStatus}`));
                                return;
                            }
                            reject(new Error(`HTTP Error ${response.status}`));
                            return;
                        }
        
                        return response.text().then(text => {
                            if (typeof callback === 'function') {
                                callback(text);
                            }
                            resolve(text);
                        });
                    })
                    .catch(error => {
                        if (typeof errorCallback === 'function') {
                            errorCallback(0, error.message);
                        }
                        reject(error);
                    });
            });
        }

    }
    // Instantiate the DotApp class globally
    const mainDotApp = new DotApp();

    // Definujeme $dotapp ako funkciu, ktorá vracia buď novú inštanciu, alebo globálnu inštanciu
    const $dotAppDispatcher = (selector) => {
        if (typeof selector === 'undefined') {
            return mainDotApp;
        }
        return new DotApp(selector);
    };
    // Assign to the global object
    global.$dotapp = $dotAppDispatcher;

})(window);

