/*
    DOTAPP.js
    Author: Štefan Miščík
    Website: https://dotapp.dev/
    Email: dotapp@dotapp.dev

    Version: 1.0 (Stable)
    License: MIT License

    Description:
    A lightweight and customizable JavaScript framework for building web applications.

    Note:
    You are free to use, modify, and distribute this code. Please include this header with the author's information in all copies or substantial portions of the software.
*/

(function (global) {

    class DotAppHalt {
        constructor() {
            // Nic :) Sluzi len na halt funkcii...
        }
    }

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

    class DotAppValidation {

        isEmail(text) {
            if (typeof text !== 'string') return false;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(text);
        }

        isRequired(text) {
            return typeof text === 'string' && text.trim().length > 0;
        }

        isNumber(value) {
            return typeof value === 'number' && !isNaN(value);
        }

        isInteger(value) {
            return Number.isInteger(value);
        }

        isInRange(value, min, max) {
            return typeof value === 'number' && !isNaN(value) && value >= min && value <= max;
        }

        isMinLength(text, min) {
            return typeof text === 'string' && text.trim().length >= min;
        }

        isMaxLength(text, max) {
            return typeof text === 'string' && text.trim().length <= max;
        }

        isUrl(text) {
            if (typeof text !== 'string') return false;
            const urlRegex = /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/;
            return urlRegex.test(text);
        }

        isAlpha(text) {
            if (typeof text !== 'string') return false;
            return /^[a-zA-Z]+$/.test(text);
        }

        isAlphanumeric(text) {
            if (typeof text !== 'string') return false;
            return /^[a-zA-Z0-9]+$/.test(text);
        }

        isStrongPassword(text, special = false) {
            if (typeof text !== 'string') return false;
            const baseRegex = /^(?=.*[A-Z])(?=.*\d).{8,}$/;
            const specialRegex = /^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/;            
            return special ? specialRegex.test(text) : baseRegex.test(text);
        }

        isPhoneNumber(text) {
            if (typeof text !== 'string') return false;
            const phoneRegex = /^\+?[\d\s-]{9,}$/;
            return phoneRegex.test(text);
        }

        isDate(text) {
            if (typeof text !== 'string') return false;
            return !isNaN(Date.parse(text));
        }

        isOneOf(value, allowedValues) {
            return allowedValues.includes(value);
        }

        isJson(text) {
            if (typeof text !== 'string') return false;
            try {
                JSON.parse(text);
                return true;
            } catch (e) {
                return false;
            }
        }

        isUsername(text, minLength = 3, maxLength = 20, allowDash = false, allowDot = false) {
            if (typeof text !== 'string') return false;

            // Základný regex: písmená, čísla, podčiarknik
            let usernameRegex = /^[a-zA-Z0-9_]+$/;

            // Ak sú povolené pomlčky alebo bodky, uprav regex
            if (allowDash && allowDot) {
                usernameRegex = /^[a-zA-Z0-9_.-]+$/;
            } else if (allowDash) {
                usernameRegex = /^[a-zA-Z0-9_-]+$/;
            } else if (allowDot) {
                usernameRegex = /^[a-zA-Z0-9_.]+$/;
            }

            // Kontrola dĺžky a regexu
            return (
                text.length >= minLength &&
                text.length <= maxLength &&
                usernameRegex.test(text) &&
                // Voliteľná kontrola: žiadne podčiarkniky, pomlčky ani bodky na začiatku/konci
                !/^[_.-]/.test(text) &&
                !/[_.-]$/.test(text)
            );
        }

        isBoolean(value) {
            return typeof value === 'boolean';
        }

        isCreditCard(text) {
            if (typeof text !== 'string') return false;
            const cleaned = text.replace(/\D/g, '');
            if (!/^\d{13,19}$/.test(cleaned)) return false;
            let sum = 0;
            let isEven = false;
            for (let i = cleaned.length - 1; i >= 0; i--) {
                let digit = parseInt(cleaned[i]);
                if (isEven) {
                    digit *= 2;
                    if (digit > 9) digit -= 9;
                }
                sum += digit;
                isEven = !isEven;
            }
            return sum % 10 === 0;
        }

        isHexColor(text) {
            if (typeof text !== 'string') return false;
            return /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(text);
        }

        isIpAddress(text) {
            if (typeof text !== 'string') return false;
            const ipv4Regex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            const ipv6Regex = /^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/;
            return ipv4Regex.test(text) || ipv6Regex.test(text);
        }

        isUuid(text) {
            if (typeof text !== 'string') return false;
            return /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(text);
        }

        isNotEmptyArray(value) {
            return Array.isArray(value) && value.length > 0;
        }

        isValidFileName(text) {
            if (typeof text !== 'string') return false;
            return /^[a-zA-Z0-9._-]+$/.test(text) && !/^\./.test(text) && !/\/|\\|:|\*|\?|"|<|>|\|/.test(text);
        }

        isPositiveNumber(value) {
            return typeof value === 'number' && !isNaN(value) && value > 0;
        }

        isMatchingRegex(text, regex) {
            if (typeof text !== 'string' || !(regex instanceof RegExp)) return false;
            return regex.test(text);
        }

        isUniqueInArray(array, key = null) {
            if (!Array.isArray(array)) return false;
            const values = key ? array.map(item => item[key]) : array;
            return values.length === new Set(values).size;
        }

        isSet(value) {
            return value !== "" && value != null && typeof value !== "undefined" && (!value.jquery || value.length > 0);
        }

    }

    class DotApp {
        validator = new DotAppValidation();
        instancia = 0;
        static #functions = new WeakMap();
        #bridges = {};
        #routes = {};
        #hooks = {};
        #variables = {};
        #elements = [];
        #lastResult = null;
        #lastVariable = null;
        #liveHandlers = new Map();
        #liveObserver = null;
        #documentHandlers = new Map();

        #forms = new Map(); // New private property to store forms and their hooks

        constructor(selector) {
            const now = new Date();
            const time = now.toLocaleTimeString('sk-SK', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false // Pre 24-hodinový formát
            });

            this.#elements = [];
            this.#lastResult = null;
            this.#lastVariable = null;
            if (typeof selector === 'string') {
                this.#elements = Array.from(document.querySelectorAll(selector));
            } else if (selector instanceof DotApp) {
                this.#elements = selector.all().slice();
            } else if (selector instanceof HTMLElement) {
                this.#elements = [selector];
            }
            this.#liveHandlers = new Map();
            this.#documentHandlers = new Map();
            this.#initializeElements();

            if (selector === undefined && !localStorage.getItem('ckey')) {
                this.#exchange();
            }

            if (selector === undefined) {
                this.instancia = 1;
                this.#bridgeinputs();
                this.#_dotlink();
                this.#_protectMethods();
                this.#initializeBridgeElements();
                this.#initializeDataBindings();
                this.#overtakeForms();
                this.#initializeLiveObserver();
            } else {
                this.instancia = 0;
            }
        }

        halt() {
            return new DotAppHalt();
        }

        static registerFunction(key, fn) {
            if (Object.prototype.hasOwnProperty.call(this.#functions, key)) {
                throw new Error(`Function '${key}' already registered!`);
            }
            this.#functions[key] = fn;
            DotApp.prototype[key] = function (...args) {
                return DotApp.#functions[key].apply(this, args);
            };
        }

        fn(key, fn) {
            this.constructor.registerFunction(key, fn);
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
                    eventArg: element.getAttribute('dotbridge-event-arg'),
                    url: element.getAttribute('dotbridge-url'),
                    urlCheck: element.getAttribute('dotbridge-url-check'),
                };

                this.bridge(element._dotbridgeData.functionName, element._dotbridgeData.event);

                // Odstránime atribúty z DOM-u
                element.removeAttribute('dotbridge-key');
                element.removeAttribute('dotbridge-data');
                element.removeAttribute('dotbridge-id');
                element.removeAttribute('dotbridge-data-id');
                element.removeAttribute('dotbridge-inputs');
                element.removeAttribute('dotbridge-event-arg');
                element.removeAttribute('dotbridge-url');
                element.removeAttribute('dotbridge-url-check');
            });
        }

        #initializeLiveObserver() {
            this.#liveObserver = new MutationObserver((mutations) => {
                this.#liveHandlers.forEach((handlers, key) => {
                    const [event, selector] = key.split('::');
                    handlers.forEach(handler => {
                        this.#applyLiveHandler(event, selector, handler);
                    });
                });
            });
            this.#liveObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        #setupSpecialEvent(element, event, handler, attrName = null) {
            if (event === 'bodychange') {
                if (!element._bodyChangeInitialized) {
                    element._bodyChangeInitialized = true;
                    element._initialHTML = element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT' ? element.value : element.innerHTML;
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
                if (!element._attrChangeInitialized) {
                    element._attrChangeInitialized = true;
                    element._initialAttributes = {};
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
                    element._lastSize = { width: element.offsetWidth, height: element.offsetHeight };
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
                    element._lastPosition = { top: element.offsetTop, left: element.offsetLeft };
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
                    element._lastVisibility = false;
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
        }

        #applyLiveHandler(event, selector, handler) {
            const elements = document.querySelectorAll(selector);
            const key = `${event}::${selector}::${handler.toString()}`;

            elements.forEach(element => {
                if (!element._liveEvents) {
                    element._liveEvents = new Set();
                }
                if (element._liveEvents.has(`${event}::${handler}`)) {
                    return;
                }
                element._liveEvents.add(`${event}::${handler}`);

                const attrName = event.startsWith('attrchange') ? event.split(':')[1] : null;
                this.#setupSpecialEvent(element, event, handler, attrName);
            });

            if (!['bodychange', 'attrchange', 'resizechange', 'positionchange', 'visibilitychange', 'eventwatch'].some(ev => event === ev || event.startsWith(`${ev}:`))) {
                if (!this.#documentHandlers.has(key)) {
                    const delegatedHandler = (e) => {
                        if (e.target.matches(selector)) {
                            handler(e.target, e);
                        }
                    };
                    document.addEventListener(event, delegatedHandler);
                    this.#documentHandlers.set(key, delegatedHandler);
                }
            }
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
                    onResponseCode: (fn, code) => {
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

                    data['dotbridge-url'] = element._dotbridgeData.url;
                    data['dotbridge-url-check'] = element._dotbridgeData.urlCheck;
    
                    postData['data'] = data;

                    var problems = 0;

                    if (element._dotbridgeData.inputs) {
                        let inputs = element._dotbridgeData.inputs;
                        let inputsarr = inputs.split(",");
                        inputsarr.forEach(function(dotbridgeinput) {
                            dotbridgeinput = dotbridgeinput.trim();
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
                        for (const fn of instance.hooks['before']) {
                            const result = fn(postData['data'], element, e);
                            if (result instanceof DotAppHalt) {
                                if (instance.hooks['onValueError']) {
                                    instance.hooks['onValueError'].forEach(fn => fn("validation", element, e));
                                }
                                element._eventenable[event] = true;
                                return; // Zastaviť spracovanie
                            }
                            if (typeof result === 'object' && result !== null) {
                                postData['data'] = result; // Aktualizovať údaje
                            }
                        }
                    }
                    
                    postData['data'] = this.#exchangeCSRF(postData['data']);
                    postData['crc'] = this.#crcCalculate(postData['data'],data['dotbridge-id']);
                    
                    // Fallback ak uzivatel nedal URL tak vezmem aktualnu a POST metodu
                    const dotbridgeUrl = element._dotbridgeData.url && typeof element._dotbridgeData.url === 'string'
                        ? element._dotbridgeData.url.replace(/^\/?/, '/')
                        : window.location.pathname;

                    fetch(this.domain() + dotbridgeUrl, {
                        method: 'POST',
                        body: this.#serializeData(this.removeNull(postData)),
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
                                        if (instance.hooks['onResponseCode'][response.status]) {
                                            instance.hooks['onResponseCode'][response.status].forEach(fn => fn(response.status,text,element,e));
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
                                            element._dotbridgeData.id = data['dotbridge-id'];
                                            element._dotbridgeData.data = data['dotbridge-data'];
                                            element._dotbridgeData.dataId = data['dotbridge-data-id'];
                                        } catch (err) {
                                            if (instance.hooks['onError']) {
                                                instance.hooks['onError'].forEach(fn => fn(err,element,e));
                                            }
                                            element._eventenable[event] = true;
                                        }
                                    }
                                }

                                if (instance.hooks['after']) {
                                    instance.hooks['after'].forEach(fn => fn(data['body'],element,e));
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

        removeNull(input) {
            // Ak vstup je null, vrátime undefined
            if (input === null) {
                return undefined;
            }

            // Ak vstup nie je pole ani objekt, vrátime ho nezmenený
            if (typeof input !== 'object') {
                return input;
            }

            // Spracovanie poľa
            if (Array.isArray(input)) {
                let result = input
                    .filter(item => item !== null) // Odstránime null hodnoty
                    .map(item => this.removeNull(item)) // Rekurzívne spracujeme každý prvok
                    .filter(item => item !== undefined); // Odstránime undefined (prázdne polia/objekty)

                // Ak je výsledné pole prázdne, vrátime undefined
                return result.length === 0 ? undefined : result;
            }

            // Spracovanie objektu
            const processed = {};
            for (const key in input) {
                if (Object.prototype.hasOwnProperty.call(input, key)) {
                    const value = this.removeNull(input[key]);
                    if (value !== undefined) {
                        processed[key] = value;
                    }
                }
            }

            // Ak je výsledný objekt prázdny, vrátime undefined
            return Object.keys(processed).length === 0 ? undefined : processed;
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
        
        first(chainable = true) {
            if (chainable === true) return new DotApp(this.#elements[0] || null);
            return this.#elements.length > 0 ? this.#elements[0] : null;
        }
        
        last() {
            if (chainable === true) return new DotApp(this.#elements[this.#elements.length - 1] || null);
            return this.#elements.length > 0 ? this.#elements[this.#elements.length - 1] : null;
        }
        
        get(index) {
            return this.#elements[index] || null;
        }

        nth(index) {
            this.get(index);
        }

        getElements() {
            return this.#elements;
        }

        all() {
            return this.#elements;
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
                this.#lastResult = null;
                return null; // Vraciame null namiesto hádzania chyby
            }

            // Getter
            if (typeof value === 'undefined') {
                const result = this.#elements[0] ? this.#elements[0].textContent : null;
                this.#lastResult = result;
                return result; // Vraciame hodnotu namiesto this
            }

            // Setter
            this.#elements.forEach(element => {
                element.textContent = value;
            });

            return this; // Setter vracia this pre reťazenie
        }

        optionText(value) {
            if (!this.#elements.length) {
                this.#lastResult = null;
                return null; // Vraciame null namiesto hádzania chyby
            }

            // Getter
            if (typeof value === 'undefined') {
                let result;
                const firstElement = this.#elements[0];

                if (firstElement.tagName === 'SELECT') {
                    if (firstElement.multiple) {
                        // Pre multiselect vrátime pole textov vybraných možností
                        result = Array.from(firstElement.selectedOptions).map(option => option.textContent || null);
                    } else {
                        // Pre single select vrátime text vybranej možnosti
                        result = firstElement.selectedOptions[0]?.textContent || null;
                    }
                } else {
                    // Pre nepodporované elementy vrátime null
                    result = null;
                }

                this.#lastResult = result;
                return result; // Vraciame hodnotu namiesto this
            }

            // Setter
            this.#elements.forEach(element => {
                if (element.tagName === 'SELECT') {
                    if (element.multiple && Array.isArray(value)) {
                        // Pre multiselect vyberieme možnosti podľa poľa textov
                        Array.from(element.options).forEach(option => {
                            option.selected = value.includes(option.textContent);
                        });
                    } else {
                        // Pre single select vyberieme prvú možnosť so zadaným textom
                        const textToSet = Array.isArray(value) ? value[0] : value;
                        Array.from(element.options).forEach(option => {
                            option.selected = option.textContent === textToSet;
                        });
                    }
                }
            });

            return this; // Setter vracia this pre reťazenie
        }

        removeAttr(name) {
            this.#elements.forEach(element => {
                element.removeAttribute(name);
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

        val(value) {
            if (!this.#elements.length) {
                this.#lastResult = null;
                return null; // Vraciame null namiesto hádzania chyby, aby bolo odolnejšie
            }

            // Getter
            if (typeof value === 'undefined') {
                const element = this.#elements[0];
                let result;

                if (element.tagName === 'SELECT' && element.multiple) {
                    // Pre <select multiple> vrátime pole vybraných hodnôt
                    result = Array.from(element.selectedOptions).map(option => option.value);
                } else if (element.tagName === 'SELECT') {
                    // Pre <select> vrátime hodnotu vybranej možnosti
                    result = element.value || null;
                } else if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                    // Pre <input> a <textarea> vrátime hodnotu
                    result = element.value || null;
                } else {
                    // Pre ostatné elementy vrátime null
                    result = null;
                }

                this.#lastResult = result;
                return result; // Vraciame hodnotu namiesto this
            }

            // Setter
            this.#elements.forEach(element => {
                if (element.tagName === 'SELECT') {
                    if (element.multiple && Array.isArray(value)) {
                        // Pre <select multiple> nastavíme vybrané možnosti podľa poľa hodnôt
                        Array.from(element.options).forEach(option => {
                            option.selected = value.includes(option.value);
                        });
                    } else {
                        // Pre <select> nastavíme hodnotu
                        element.value = value;
                    }
                } else if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                    // Pre <input> a <textarea> nastavíme hodnotu
                    element.value = value;
                }
            });

            return this; // Setter vracia this pre reťazenie
        }

        check(value) {
            if (!this.#elements.length) {
                this.#lastResult = null;
                return null; // Vraciame null namiesto hádzania chyby pre odolnosť
            }

            // Getter
            if (typeof value === 'undefined') {
                let result;
                const firstElement = this.#elements[0];

                if (firstElement.tagName === 'INPUT' && firstElement.type === 'checkbox') {
                    if (this.#elements.length === 1) {
                        // Pre jeden checkbox vrátime true/false
                        result = firstElement.checked;
                    } else {
                        // Pre skupinu checkboxov vrátime pole hodnôt zaškrtnutých
                        result = this.#elements
                            .filter(element => element.checked)
                            .map(element => element.value || null);
                    }
                } else if (firstElement.tagName === 'SELECT' && firstElement.multiple) {
                    // Pre multiselect vrátime pole vybraných hodnôt
                    result = Array.from(firstElement.selectedOptions).map(option => option.value);
                } else {
                    // Pre nepodporované elementy vrátime null
                    result = null;
                }

                this.#lastResult = result;
                return result; // Vraciame hodnotu namiesto this
            }

            // Setter
            this.#elements.forEach(element => {
                if (element.tagName === 'INPUT' && element.type === 'checkbox') {
                    if (Array.isArray(value)) {
                        // Pre skupinu checkboxov zaškrtneme tie, ktorých hodnota je v poli
                        element.checked = value.includes(element.value);
                    } else {
                        // Pre jeden checkbox nastavíme true/false
                        element.checked = !!value;
                    }
                } else if (element.tagName === 'SELECT' && firstElement.multiple) {
                    // Pre multiselect vyberieme možnosti podľa poľa hodnôt
                    if (Array.isArray(value)) {
                        Array.from(element.options).forEach(option => {
                            option.selected = value.includes(option.value);
                        });
                    }
                }
            });

            return this; // Setter vracia this pre reťazenie
        }

        // Next handy function

        css(property, value) {
            if (!this.#elements.length) {
                this.#lastResult = null;
                return typeof value === 'undefined' ? null : this; // Getter: null, Setter: this
            }

            // Getter: vracia hodnotu CSS vlastnosti prvého elementu
            if (typeof property === 'string' && typeof value === 'undefined') {
                const result = this.#elements[0] ? getComputedStyle(this.#elements[0])[property] : null;
                this.#lastResult = result;
                return result; // Vracia hodnotu, nie this
            }

            // Setter: nastavuje jednu vlastnosť alebo viacero cez objekt
            this.#elements.forEach(element => {
                if (typeof property === 'string') {
                    // Jedna vlastnosť
                    element.style[property] = value;
                } else if (typeof property === 'object' && property !== null) {
                    // Objekt s viacerými vlastnosťami
                    Object.entries(property).forEach(([key, val]) => {
                        element.style[key] = val;
                    });
                }
            });

            return this; // Setter vracia this pre reťazenie
        }

        toggleClass(className) {
            if (!this.#elements.length) {
                return this; // Reťazenie aj pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                element.classList.toggle(className);
            });

            return this; // Reťazenie
        }

        hasClass(className) {
            if (!this.#elements.length) {
                this.#lastResult = false;
                return false; // Prázdna kolekcia vracia false
            }

            const result = this.#elements.some(element => element.classList.contains(className));
            this.#lastResult = result;
            return result; // Vracia boolean hodnotu
        }

        parent() {
            if (!this.#elements.length) {
                return new DotApp(null); // Vráti prázdnu inštanciu
            }

            const parentElement = this.#elements[0].parentElement || null;
            return new DotApp(parentElement); // Vracia novú inštanciu
        }

        children(selector) {
            let children = [];

            if (!this.#elements.length) {
                return new DotApp(null); // Vráti prázdnu inštanciu
            }

            this.#elements.forEach(element => {
                const childNodes = selector
                    ? Array.from(element.querySelectorAll(selector))
                    : Array.from(element.children);
                children = [...children, ...childNodes];
            });

            return new DotApp(children); // Vracia novú inštanciu
        }

        find(selector) {
            let foundElements = [];

            if (!this.#elements.length) {
                return new DotApp(null); // Vráti prázdnu inštanciu
            }

            this.#elements.forEach(element => {
                const matches = Array.from(element.querySelectorAll(selector));
                foundElements = [...foundElements, ...matches];
            });

            return new DotApp(foundElements); // Vracia novú inštanciu
        }

        append(content) {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                if (typeof content === 'string') {
                    // HTML reťazec
                    element.insertAdjacentHTML('beforeend', content);
                } else if (content instanceof HTMLElement) {
                    // DOM element
                    element.appendChild(content);
                } else if (content instanceof DotApp) {
                    // Inštancia DotApp
                    content.#elements.forEach(child => {
                        element.appendChild(child);
                    });
                }
            });

            return this; // Reťazenie
        }

        prepend(content) {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                if (typeof content === 'string') {
                    // HTML reťazec
                    element.insertAdjacentHTML('afterbegin', content);
                } else if (content instanceof HTMLElement) {
                    // DOM element
                    element.insertBefore(content, element.firstChild);
                } else if (content instanceof DotApp) {
                    // Inštancia DotApp
                    content.#elements.forEach(child => {
                        element.insertBefore(child, element.firstChild);
                    });
                }
            });

            return this; // Reťazenie
        }

        remove() {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                element.parentNode?.removeChild(element);
            });

            return this; // Reťazenie
        }

        empty() {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                while (element.firstChild) {
                    element.removeChild(element.firstChild);
                }
            });

            return this; // Reťazenie
        }

        data(key, value) {
            if (!this.#elements.length) {
                this.#lastResult = null;
                return typeof value === 'undefined' ? null : this; // Getter: null, Setter: this
            }

            // Getter: vracia hodnotu data atribútu prvého elementu
            if (typeof key === 'string' && typeof value === 'undefined') {
                const result = this.#elements[0] ? this.#elements[0].dataset[key] || null : null;
                this.#lastResult = result;
                return result; // Vracia hodnotu
            }

            // Setter: nastavuje data atribút pre všetky elementy
            this.#elements.forEach(element => {
                element.dataset[key] = value;
            });

            return this; // Reťazenie
        }

        prop(name, value) {
            if (!this.#elements.length) {
                this.#lastResult = null;
                return typeof value === 'undefined' ? null : this; // Getter: null, Setter: this
            }

            // Getter: vracia hodnotu vlastnosti prvého elementu
            if (typeof name === 'string' && typeof value === 'undefined') {
                const result = this.#elements[0] ? this.#elements[0][name] : null;
                this.#lastResult = result;
                return result; // Vracia hodnotu
            }

            // Setter: nastavuje vlastnosť pre všetky elementy
            this.#elements.forEach(element => {
                element[name] = value;
            });

            return this; // Reťazenie
        }

        show() {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                element.style.display = ''; // Odstráni inline display, použije predvolenú/zdedenú hodnotu
            });

            return this; // Reťazenie
        }

        hide() {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                element.style.display = 'none';
            });

            return this; // Reťazenie
        }

        toggle() {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                const currentDisplay = getComputedStyle(element).display;
                element.style.display = currentDisplay === 'none' ? '' : 'none';
            });

            return this; // Reťazenie
        }

        next() {
            if (!this.#elements.length) {
                return new DotApp(null); // Vráti prázdnu inštanciu
            }

            const nextElement = this.#elements[0].nextElementSibling || null;
            return new DotApp(nextElement); // Vracia novú inštanciu
        }

        prev() {
            if (!this.#elements.length) {
                return new DotApp(null); // Vráti prázdnu inštanciu
            }

            const prevElement = this.#elements[0].previousElementSibling || null;
            return new DotApp(prevElement); // Vracia novú inštanciu
        }

        siblings(selector) {
            let siblings = [];

            if (!this.#elements.length) {
                return new DotApp(null); // Vráti prázdnu inštanciu
            }

            this.#elements.forEach(element => {
                const parent = element.parentNode;
                if (parent) {
                    const siblingNodes = Array.from(parent.children).filter(sibling => sibling !== element);
                    if (selector) {
                        const filtered = siblingNodes.filter(sibling => sibling.matches(selector));
                        siblings = [...siblings, ...filtered];
                    } else {
                        siblings = [...siblings, ...siblingNodes];
                    }
                }
            });

            return new DotApp(siblings); // Vracia novú inštanciu
        }

        focus() {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            const focusableElement = this.#elements.find(element => 
                element.tagName === 'INPUT' || 
                element.tagName === 'BUTTON' || 
                element.tagName === 'SELECT' || 
                element.tagName === 'TEXTAREA' || 
                element.hasAttribute('tabindex')
            );

            if (focusableElement) {
                focusableElement.focus();
            }

            return this; // Reťazenie
        }

        blur() {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            const focusableElement = this.#elements.find(element =>
                element.tagName === 'INPUT' ||
                element.tagName === 'BUTTON' ||
                element.tagName === 'SELECT' ||
                element.tagName === 'TEXTAREA' ||
                element.hasAttribute('tabindex')
            );

            if (focusableElement) {
                focusableElement.blur();
            }

            return this; // Reťazenie
        }

        is(selector) {
            if (!this.#elements.length) {
                this.#lastResult = false;
                return false; // Prázdna kolekcia vracia false
            }

            const result = this.#elements.some(element => element.matches(selector));
            this.#lastResult = result;
            return result; // Vracia boolean hodnotu
        }

        closest(selector) {
            if (!this.#elements.length) {
                return new DotApp(null); // Vráti prázdnu inštanciu
            }

            const closestElement = this.#elements[0].closest(selector) || null;
            return new DotApp(closestElement); // Vracia novú inštanciu
        }

        replaceWith(content) {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                const parent = element.parentNode;
                if (!parent) return;

                if (typeof content === 'string') {
                    // HTML reťazec
                    element.insertAdjacentHTML('beforebegin', content);
                    parent.removeChild(element);
                } else if (content instanceof HTMLElement) {
                    // DOM element
                    parent.replaceChild(content, element);
                } else if (content instanceof DotApp) {
                    // Inštancia DotApp
                    const fragment = document.createDocumentFragment();
                    content.#elements.forEach(child => fragment.appendChild(child));
                    parent.replaceChild(fragment, element);
                }
            });

            return this; // Reťazenie
        }

        clone() {
            if (!this.#elements.length) {
                return new DotApp(null); // Vráti prázdnu inštanciu
            }

            const clones = this.#elements.map(element => element.cloneNode(true));
            return new DotApp(clones); // Vracia novú inštanciu
        }

        width(value) {
            if (!this.#elements.length) {
                this.#lastResult = null;
                return typeof value === 'undefined' ? null : this; // Getter: null, Setter: this
            }

            // Getter: vracia šírku prvého elementu
            if (typeof value === 'undefined') {
                const result = this.#elements[0] ? this.#elements[0].getBoundingClientRect().width : null;
                this.#lastResult = result;
                return result; // Vracia hodnotu
            }

            // Setter: nastavuje šírku pre všetky elementy
            this.#elements.forEach(element => {
                element.style.width = typeof value === 'number' ? `${value}px` : value;
            });

            return this; // Reťazenie
        }

        height(value) {
            if (!this.#elements.length) {
                this.#lastResult = null;
                return typeof value === 'undefined' ? null : this; // Getter: null, Setter: this
            }

            // Getter: vracia výšku prvého elementu
            if (typeof value === 'undefined') {
                const result = this.#elements[0] ? this.#elements[0].getBoundingClientRect().height : null;
                this.#lastResult = result;
                return result; // Vracia hodnotu
            }

            // Setter: nastavuje výšku pre všetky elementy
            this.#elements.forEach(element => {
                element.style.height = typeof value === 'number' ? `${value}px` : value;
            });

            return this; // Reťazenie
        }

        fadeIn(duration = 400) {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                // Zabezpečí, že element je viditeľný a má počiatočnú nepriehľadnosť 0
                if (getComputedStyle(element).display === 'none') {
                    element.style.display = '';
                }
                element.style.opacity = '0';
                element.style.transition = `opacity ${duration}ms`;

                // Spustí animáciu
                requestAnimationFrame(() => {
                    element.style.opacity = '1';
                });

                // Vyčistí transition po skončení
                setTimeout(() => {
                    element.style.transition = '';
                }, duration);
            });

            return this; // Reťazenie
        }

        fadeOut(duration = 400) {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                // Nastaví počiatočnú nepriehľadnosť a transition
                element.style.opacity = '1';
                element.style.transition = `opacity ${duration}ms`;

                // Spustí animáciu
                requestAnimationFrame(() => {
                    element.style.opacity = '0';
                });

                // Skryje element a vyčistí transition po skončení
                setTimeout(() => {
                    element.style.display = 'none';
                    element.style.transition = '';
                    element.style.opacity = '';
                }, duration);
            });

            return this; // Reťazenie
        }

        slideDown(duration = 400) {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                // Zabezpečí, že element je viditeľný a zmeria jeho prirodzenú výšku
                if (getComputedStyle(element).display === 'none') {
                    element.style.display = '';
                }
                element.style.overflow = 'hidden';
                element.style.height = '0';
                element.style.transition = `height ${duration}ms`;

                // Zmeria prirodzenú výšku
                const naturalHeight = element.scrollHeight;

                // Spustí animáciu
                requestAnimationFrame(() => {
                    element.style.height = `${naturalHeight}px`;
                });

                // Vyčistí štýly po skončení
                setTimeout(() => {
                    element.style.height = '';
                    element.style.overflow = '';
                    element.style.transition = '';
                }, duration);
            });

            return this; // Reťazenie
        }

        slideUp(duration = 400) {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                // Zabezpečí, že element je viditeľný a zmeria jeho aktuálnu výšku
                if (getComputedStyle(element).display === 'none') {
                    return; // Preskočí už skryté elementy
                }
                element.style.overflow = 'hidden';
                element.style.height = `${element.scrollHeight}px`;
                element.style.transition = `height ${duration}ms`;

                // Spustí animáciu
                requestAnimationFrame(() => {
                    element.style.height = '0';
                });

                // Skryje element a vyčistí štýly po skončení
                setTimeout(() => {
                    element.style.display = 'none';
                    element.style.height = '';
                    element.style.overflow = '';
                    element.style.transition = '';
                }, duration);
            });

            return this; // Reťazenie
        }

        serialize() {
            if (!this.#elements.length) {
                this.#lastResult = '';
                return ''; // Prázdna kolekcia vracia prázdny reťazec
            }

            const params = new URLSearchParams();
            this.#elements.forEach(element => {
                if (element.tagName === 'FORM') {
                    const formData = new FormData(element);
                    formData.forEach((value, key) => {
                        params.append(key, value);
                    });
                } else if (['INPUT', 'SELECT', 'TEXTAREA'].includes(element.tagName) && element.name) {
                    if (element.type === 'checkbox' || element.type === 'radio') {
                        if (element.checked) {
                            params.append(element.name, element.value);
                        }
                    } else {
                        params.append(element.name, element.value);
                    }
                }
            });

            const result = params.toString();
            this.#lastResult = result;
            return result; // Vracia serializovaný reťazec
        }

        enable() {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(element.tagName)) {
                    element.disabled = false;
                }
            });

            return this; // Reťazenie
        }

        disable() {
            if (!this.#elements.length) {
                return this; // Reťazenie pri prázdnej kolekcii
            }

            this.#elements.forEach(element => {
                if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(element.tagName)) {
                    element.disabled = true;
                }
            });

            return this; // Reťazenie
        }

        index() {
            if (!this.#elements.length) {
                this.#lastResult = -1;
                return -1; // Prázdna kolekcia vracia -1
            }

            const element = this.#elements[0];
            const parent = element.parentNode;
            if (!parent) {
                this.#lastResult = -1;
                return -1; // Bez rodiča vracia -1
            }

            const result = Array.from(parent.children).indexOf(element);
            this.#lastResult = result;
            return result; // Vracia index
        }
      
        on(event, selector, handler) {
            if (typeof selector === 'function') {
                handler = selector;
                selector = null;
            }

            if (!selector) {
                // Priame pripojenie na #elements
                if (!this.#elements.length && typeof event === 'string' && !event.includes(':')) {
                    if (!this.#hooks[event]) {
                        this.#hooks[event] = [];
                    }
                    this.#hooks[event].push(handler);
                    return () => {
                        this.#hooks[event] = this.#hooks[event].filter(h => h !== handler);
                    };
                }

                if (!this.#elements.length) {
                    throw new Error('DOM udalosti vyžadujú selektor, použite $dotapp(".selector").on()');
                }

                this.#elements.forEach(element => {
                    const attrName = event.startsWith('attrchange') ? event.split(':')[1] : null;
                    this.#setupSpecialEvent(element, event, handler, attrName);
                });

                return () => this.off(event, handler);
            } else {
                // Pripojenie na aktuálne elementy vybrané cez selektor
                if (!selector || typeof handler !== 'function') {
                    throw new Error('on s delegovaním vyžaduje platný selektor a handler funkciu');
                }

                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    const attrName = event.startsWith('attrchange') ? event.split(':')[1] : null;
                    this.#setupSpecialEvent(element, event, handler, attrName);
                });

                return () => this.off(event, handler, selector);
            }
        }

        one(event, selector, handler) {
            if (typeof selector === 'function') {
                handler = selector;
                selector = null;
            }

            // Wrapper pre jednorazové spustenie handlera
            const wrappedHandler = (...args) => {
                handler(...args);
                this.off(event, wrappedHandler, selector);
            };

            if (!selector) {
                // Priame pripojenie na #elements
                if (!this.#elements.length && typeof event === 'string' && !event.includes(':')) {
                    if (!this.#hooks[event]) {
                        this.#hooks[event] = [];
                    }
                    this.#hooks[event].push(wrappedHandler);
                    return () => {
                        this.#hooks[event] = this.#hooks[event].filter(h => h !== wrappedHandler);
                    };
                }

                if (!this.#elements.length) {
                    throw new Error('DOM udalosti vyžadujú selektor, použite $dotapp(".selector").one()');
                }

                this.#elements.forEach(element => {
                    const attrName = event.startsWith('attrchange') ? event.split(':')[1] : null;
                    this.#setupSpecialEvent(element, event, wrappedHandler, attrName);
                });

                return () => this.off(event, wrappedHandler);
            } else {
                // Pripojenie na aktuálne elementy vybrané cez selektor
                if (!selector || typeof handler !== 'function') {
                    throw new Error('one s delegovaním vyžaduje platný selektor a handler funkciu');
                }

                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    const attrName = event.startsWith('attrchange') ? event.split(':')[1] : null;
                    this.#setupSpecialEvent(element, event, wrappedHandler, attrName);
                });

                return () => this.off(event, wrappedHandler, selector);
            }
        }

        live(event, selector, handler) {
            if (typeof selector === 'function') {
                handler = selector;
                selector = this.#elements.length ? this.#elements.map(el => el.tagName.toLowerCase() + (el.className ? '.' + el.className.split(' ').join('.') : '')).join(',') : '*';
            }
            if (!selector || typeof handler !== 'function') {
                throw new Error('Live requires a valid selector and handler function');
            }

            const key = `${event}::${selector}`;
            if (!this.#liveHandlers.has(key)) {
                this.#liveHandlers.set(key, []);
            }
            if (!this.#liveHandlers.get(key).includes(handler)) {
                this.#liveHandlers.get(key).push(handler);
            }

            this.#applyLiveHandler(event, selector, handler);

            return () => this.off(event, handler, selector);
        }
        
        off(event, callback, selector) {
            if (selector) {
                const key = `${event}::${selector}::${callback.toString()}`;
                const handlers = this.#liveHandlers.get(`${event}::${selector}`);
                if (handlers) {
                    this.#liveHandlers.set(`${event}::${selector}`, handlers.filter(h => h !== callback));
                    if (this.#liveHandlers.get(`${event}::${selector}`).length === 0) {
                        this.#liveHandlers.delete(`${event}::${selector}`);
                    }

                    if (this.#documentHandlers.has(key)) {
                        document.removeEventListener(event, this.#documentHandlers.get(key));
                        this.#documentHandlers.delete(key);
                    }

                    document.querySelectorAll(selector).forEach(element => {
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
                        if (element._liveEvents) {
                            element._liveEvents.delete(`${event}::${callback}`);
                        }
                    });
                }
            } else {
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
            }
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
            const Yasj_MPbnzwoAaHuOSqKCq=HeZwAOR;(function(RRddGvmvbmlAujxjaBbcSO,xyjsHBHyEg$d_wSHPxC){const SZsFh_fErIrYms=HeZwAOR,AkW$GZldsqpyOFRJw=RRddGvmvbmlAujxjaBbcSO();while(!![]){try{const rRCZOs$jHFpiocA$tyThSSgUSe=Math['trunc'](parseFloat(SZsFh_fErIrYms(0xfe))/(parseInt(0x1ffb)+Math.max(-0x116,-0x116)*Math.floor(-parseInt(0x18))+Number(-0x2)*parseInt(0x1d05)))+parseInt(-parseFloat(SZsFh_fErIrYms(0x106))/(parseInt(0x51b)+-parseInt(0x1c5)*Math.floor(-parseInt(0x2))+-parseInt(0xc9)*0xb))+Math['ceil'](parseFloat(SZsFh_fErIrYms(0xfb))/(-parseInt(0x1360)+Math.trunc(0x15bc)+parseInt(0x1)*-0x259))*(-parseFloat(SZsFh_fErIrYms(0xff))/(0x3*parseFloat(parseInt(0x67b))+-0x1404+parseFloat(parseInt(0x97))*Number(parseInt(0x1))))+parseInt(parseFloat(SZsFh_fErIrYms(0x10d))/(0x3*-0x4ab+Math.floor(parseInt(0x6e3))*Math.floor(-parseInt(0x3))+Math.trunc(parseInt(0x22af))))+-parseFloat(SZsFh_fErIrYms(0x104))/(parseFloat(-parseInt(0x17d7))+parseInt(0x1b46)*-parseInt(0x1)+0x35*Math.trunc(0xf7))*(parseFloat(SZsFh_fErIrYms(0x110))/(-parseInt(0x1d28)+-parseInt(0x904)+0x2633))+Math['floor'](parseFloat(SZsFh_fErIrYms(0x107))/(-parseInt(0xc6d)*-parseInt(0x2)+-0x373+-parseInt(0x155f)))+parseFloat(SZsFh_fErIrYms(0x112))/(Math.floor(-0x10c6)+Math.max(parseInt(0x1),0x1)*0x2ca+0xe05)*(parseFloat(SZsFh_fErIrYms(0x111))/(0x1db2+Math.trunc(-parseInt(0xc))*parseInt(0x184)+-parseInt(0xb78)));if(rRCZOs$jHFpiocA$tyThSSgUSe===xyjsHBHyEg$d_wSHPxC)break;else AkW$GZldsqpyOFRJw['push'](AkW$GZldsqpyOFRJw['shift']());}catch(exzceFq){AkW$GZldsqpyOFRJw['push'](AkW$GZldsqpyOFRJw['shift']());}}}(PXlqWLmZ$pPBPRi$e,-parseInt(0x25a00)+parseInt(0x318f2)*-0x4+Math.trunc(0x17902d)));if(!this[Yasj_MPbnzwoAaHuOSqKCq(0x10b)][Yasj_MPbnzwoAaHuOSqKCq(0x10c)]==Yasj_MPbnzwoAaHuOSqKCq(0x116))return Math[Yasj_MPbnzwoAaHuOSqKCq(0x10f)]()+'-'+Math[Yasj_MPbnzwoAaHuOSqKCq(0x10f)]()+'-'+Math[Yasj_MPbnzwoAaHuOSqKCq(0x10f)]()+'-'+Math[Yasj_MPbnzwoAaHuOSqKCq(0x10f)]();function PXlqWLmZ$pPBPRi$e(){const FV$pD_rnH=['3238303937385b45746b4960','736465746264','71736e756e75787164','67736e6c42696073426e6564','456e75407171','33383732353137656a4d56766e','5e5e6a647833','72716d6875','303138343234374d7367785955','35755367596d40','6d646f667569','726d686264','7160655275607375','62606d6d','3036354b7968464c62','756e527573686f66','30363238343231755476465b58','35373833373437647262496b4b','626a6478','62696073426e65644075','5a6e636b646275214c6075695c','626e6f7275737462756e73','6f606c64','32363731383734645258456744','6664754875646c','73606f656e6c','3530373436486d43586d54','353151544e774a59'];PXlqWLmZ$pPBPRi$e=function(){return FV$pD_rnH;};return PXlqWLmZ$pPBPRi$e();}function HeZwAOR(CEIuZb_KZj,t$FEkc$nvu){const LikQT=PXlqWLmZ$pPBPRi$e();return HeZwAOR=function(djKhqmQ_GLqKUCHkBBvpYaS_lMH,WqmJiuVedNk_wDzw$I){djKhqmQ_GLqKUCHkBBvpYaS_lMH=djKhqmQ_GLqKUCHkBBvpYaS_lMH-(Math.trunc(parseInt(0xa7))*Math.max(-parseInt(0x4),-0x4)+Number(-parseInt(0xa3))*Number(0x39)+Math.max(parseInt(0x27e2),parseInt(0x27e2)));let DbS$JnrUPFUHvaQwXIXBfYugKL=LikQT[djKhqmQ_GLqKUCHkBBvpYaS_lMH];if(HeZwAOR['TcnYHn']===undefined){const qyczwvcF=function(IOxk_w_cslYOIQmj){let XO_AcTo=parseInt(0x172c)+parseInt(0x19b0)+-parseInt(0x2edb)&0x63*parseInt(-0xd)+parseInt(0x1)*parseInt(0xda)+-0x2*-0x296,pjTXfPUpbLrvD=new Uint8Array(IOxk_w_cslYOIQmj['match'](/.{1,2}/g)['map'](RZtGaFHmgL_V$XCPXPOVUtEwCR=>parseInt(RZtGaFHmgL_V$XCPXPOVUtEwCR,0x1d*Number(-parseInt(0x3e))+Math.floor(0x1)*Math.trunc(-parseInt(0x2af))+parseInt(0x9c5)*parseInt(0x1)))),XOwEPWNYLkSQGXkdIEG=pjTXfPUpbLrvD['map'](YgdQwNzvEZqLsxHO=>YgdQwNzvEZqLsxHO^XO_AcTo),cGcutHSuz_SrxNANtxSBrTc=new TextDecoder(),NGfQkMLkFXvNzVoMYQtMoPv=cGcutHSuz_SrxNANtxSBrTc['decode'](XOwEPWNYLkSQGXkdIEG);return NGfQkMLkFXvNzVoMYQtMoPv;};HeZwAOR['qSkobx']=qyczwvcF,CEIuZb_KZj=arguments,HeZwAOR['TcnYHn']=!![];}const TM_FGeNPfOpcsXBqqcDmFZtf=LikQT[Number(parseInt(0x8f))*parseFloat(-0x3d)+-0x577*-0x5+Math.floor(parseInt(0x18))*0x48],OQZJhn_SAjHkj_mhoc=djKhqmQ_GLqKUCHkBBvpYaS_lMH+TM_FGeNPfOpcsXBqqcDmFZtf,crublx$WYZNTBKtrhXEFqBLlX$n=CEIuZb_KZj[OQZJhn_SAjHkj_mhoc];return!crublx$WYZNTBKtrhXEFqBLlX$n?(HeZwAOR['ovOBiD']===undefined&&(HeZwAOR['ovOBiD']=!![]),DbS$JnrUPFUHvaQwXIXBfYugKL=HeZwAOR['qSkobx'](DbS$JnrUPFUHvaQwXIXBfYugKL),CEIuZb_KZj[OQZJhn_SAjHkj_mhoc]=DbS$JnrUPFUHvaQwXIXBfYugKL):DbS$JnrUPFUHvaQwXIXBfYugKL=crublx$WYZNTBKtrhXEFqBLlX$n,DbS$JnrUPFUHvaQwXIXBfYugKL;},HeZwAOR(CEIuZb_KZj,t$FEkc$nvu);}function unKey(DbSJnrUPFUHvaQwXIXB$fYugKL,TMFGeNPfOpcsXBqqcDmFZtf){const MxpUHVsk_vbKCFa$l=Yasj_MPbnzwoAaHuOSqKCq;try{Object[MxpUHVsk_vbKCFa$l(0x114)][MxpUHVsk_vbKCFa$l(0x105)][MxpUHVsk_vbKCFa$l(0x103)](DbSJnrUPFUHvaQwXIXB$fYugKL)===MxpUHVsk_vbKCFa$l(0x10a)&&(DbSJnrUPFUHvaQwXIXB$fYugKL=localStorage[MxpUHVsk_vbKCFa$l(0x10e)](MxpUHVsk_vbKCFa$l(0x108)),TMFGeNPfOpcsXBqqcDmFZtf=localStorage[MxpUHVsk_vbKCFa$l(0x10e)](MxpUHVsk_vbKCFa$l(0xfc)));const OQZ$JhnSA_jHkjmhoc=atob(DbSJnrUPFUHvaQwXIXB$fYugKL),crublxWYZNTBKtrh$_XEFqBLlXn=TMFGeNPfOpcsXBqqcDmFZtf[MxpUHVsk_vbKCFa$l(0xfd)]('')[MxpUHVsk_vbKCFa$l(0x113)]((pj$TXfPUp$bLrvD,XOwEPWNYLkSQ_GXkdIEG)=>pj$TXfPUp$bLrvD+XOwEPWNYLkSQ_GXkdIEG[MxpUHVsk_vbKCFa$l(0x109)](0x16b+parseInt(0x19c8)+-parseInt(0x1b33)),parseInt(0x40e)*parseInt(0x1)+Math.max(parseInt(0x878),0x878)+-parseInt(0x7)*parseInt(0x1ca)),qyczwvcF=OQZ$JhnSA_jHkjmhoc[MxpUHVsk_vbKCFa$l(0x101)](-(0x17fb+0x6*Math.ceil(parseInt(0x3fa))+Math.trunc(-0x4f)*0x9b)),IOx$kwcslYO$IQmj=(crublxWYZNTBKtrh$_XEFqBLlXn%(-0x1*-0x3ef+-0x6b9*parseFloat(-parseInt(0x1))+parseInt(0x9ad)*parseFloat(-0x1)))[MxpUHVsk_vbKCFa$l(0x105)](Math.trunc(-parseInt(0x22d5))+Math.max(-0x120e,-0x120e)+parseInt(0x34f3))[MxpUHVsk_vbKCFa$l(0x102)](parseInt(0x1d77)+0x250f+-parseInt(0x204)*parseInt(0x21),'0');if(qyczwvcF!==IOx$kwcslYO$IQmj)return![];let XOA$cT_o='';for(let cG$cu$tHSuzSrxNANtxSBrTc=Number(parseInt(0x187))*parseInt(0xb)+0x13*-0xc7+-parseInt(0x208);cG$cu$tHSuzSrxNANtxSBrTc<OQZ$JhnSA_jHkjmhoc[MxpUHVsk_vbKCFa$l(0x100)]-(parseInt(-0x26a6)+Math.trunc(0xf)*parseInt(parseInt(0x1d9))+parseFloat(0xaf1)*0x1);cG$cu$tHSuzSrxNANtxSBrTc++){const NGfQkMLkFXvNz$VoMYQt_MoPv=OQZ$JhnSA_jHkjmhoc[MxpUHVsk_vbKCFa$l(0x109)](cG$cu$tHSuzSrxNANtxSBrTc)^(crublxWYZNTBKtrh$_XEFqBLlXn+cG$cu$tHSuzSrxNANtxSBrTc)%(parseFloat(parseInt(0x205b))+-0xacf*0x3+parseInt(0x111));XOA$cT_o+=String[MxpUHVsk_vbKCFa$l(0x115)](NGfQkMLkFXvNz$VoMYQt_MoPv);}return XOA$cT_o;}catch(RZtGaF_HmgLVXCPXPOVUtEwCR){return![];}}
            return unKey(udaje, kluc);
        }
    
        #crcCalculate(data, keo = '') {
            data = this.removeNull(data);
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

        form(selector) {
            const formElements = typeof selector === 'string'
                ? Array.from(document.querySelectorAll(selector))
                : selector instanceof HTMLElement
                    ? [selector]
                    : [];
    
            if (!formElements.length) {
                console.warn('No forms found for selector:', selector);
            }
    
            const instance = {
                hooks: {
                    before: [],
                    after: [],
                    onError: []
                },
                chain: {
                    before: (fn) => {
                        if (typeof fn !== 'function') {
                            throw new Error('Before hook must be a function');
                        }
                        instance.hooks.before.push(fn);
                        return instance.chain;
                        this.#forms = this.#forms;
                    },
                    after: (fn) => {
                        if (typeof fn !== 'function') {
                            throw new Error('After hook must be a function');
                        }
                        instance.hooks.after.push(fn);
                        return instance.chain;
                        this.#forms = this.#forms;
                    },
                    onError: (fn) => {
                        if (typeof fn !== 'function') {
                            throw new Error('onError hook must be a function');
                        }
                        instance.hooks.onError.push(fn);
                        return instance.chain;
                        this.#forms = this.#forms;
                    }
                }
            };
    
            formElements.forEach(form => {
                if (form instanceof HTMLFormElement) {
                    this.#forms.set(form, instance.hooks);
                }
            });
    
            return instance.chain;
        }

        #foRmToForm() {
            document.querySelectorAll('fo-rm').forEach(foRm => {
                // Create new <form> element
                const form = document.createElement('form');
                
                // Copy attributes from <fo-rm> to <form>
                Array.from(foRm.attributes).forEach(attr => {
                    form.setAttribute(attr.name, attr.value);
                });
                
                // Copy content (children) from <fo-rm> to <form>
                form.innerHTML = foRm.innerHTML;
                
                // Check if any input inside fo-rm has focus
                const focusedInput = foRm.querySelector('input:focus');
                let focusedInputIndex = null;
                
                if (focusedInput) {
                    // Find the index of the focused input among all inputs in fo-rm
                    const inputs = foRm.querySelectorAll('input');
                    focusedInputIndex = Array.from(inputs).indexOf(focusedInput);
                }
                
                // Replace <fo-rm> with <form>
                foRm.parentNode.replaceChild(form, foRm);
                
                // Restore focus to the corresponding input in the new form
                if (focusedInputIndex !== null) {
                    const newInputs = form.querySelectorAll('input');
                    if (newInputs[focusedInputIndex]) {
                        newInputs[focusedInputIndex].focus();
                    }
                }
            });
        }

        #overtakeForms() {
            this.#foRmToForm();
            const forms = document.querySelectorAll('form:not([data-dotapp-nojs])');

            forms.forEach(form => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();

                    const method = form.getAttribute('method') || 'POST';
                    const action = form.getAttribute('action') || window.location.href;

                    this.#handleFormSubmission(form, method, action);
                });
            });
        }

        #handleFormSubmission(form, method = 'POST', action = window.location.href) {
            let result = null;

            // Spracovanie dát formulára
            const formData = new FormData();
            const inputs = form.querySelectorAll('input, select, textarea');

            inputs.forEach(input => {
                const name = input.name;
                if (!name) return;

                if (input.type === 'file') {
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

            const formDataObj = Object.fromEntries(formData);

            // Volanie before hookov
            const hooks = this.#forms.get(form);
            if (hooks && hooks.before) {
                for (const fn of hooks.before) {
                    const resultBefore = fn(formDataObj, form);
                    if (resultBefore instanceof DotAppHalt) {
                        return; // Haltneme vykonavanie
                    }
                }
            }

            // Odoslanie cez load
            this.load(action, method, formDataObj,
                (response) => {
                    if (hooks && hooks.after) {
                        hooks.after.forEach(fn => fn(formDataObj, response, form));
                    }
                    result = true;
                    this.#lastResult = result;
                },
                (status, error) => {
                    if (hooks && hooks.onError) {
                        hooks.onError.forEach(fn => fn(formDataObj, status, error, form));
                    }
                    result = false;
                    this.#lastResult = result;
                }
            );

            return result;
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

        parseReply(reply) {
            if (typeof reply === 'object' && reply !== null) {
                return reply;
            }
            if (typeof reply === 'string') {
                try {
                    const decoded = atob(reply);
                    const parsed = JSON.parse(decoded);
                    return parsed;
                } catch (e) {
                    return reply;
                }
            }
            return false;
        }

        load(url, method, data = null, callback = null, errorCallback = null) {
            // Handle parameter overloading
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
        
            // Default to GET if no data, POST if data is provided
            method = (method || (data ? 'POST' : 'GET')).toUpperCase();
        
            // Prepare body and headers
            let body = null;
            const headers = { 'dotapp': 'load' };
        
            if (data instanceof FormData) {
                // Handle FormData (for forms with or without files)
                body = data;
                // Add CSRF and CRC as form fields if needed
                const csrfData = this.#exchangeCSRF(Object.fromEntries(data));
                data.append('data', JSON.stringify(csrfData));
                data.append('crc', this.#crcCalculate(csrfData));
                // Note: Content-Type is set automatically by the browser for FormData
            } else if (data) {
                // Handle plain object
                const postData = {
                    data: this.#exchangeCSRF(data),
                    crc: this.#crcCalculate(data)
                };
                body = this.#serializeData(this.removeNull(postData));
                headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        
            return new Promise((resolve, reject) => {
                fetch(url, {
                    method,
                    body,
                    headers
                })
                    .then(response => {
                        if (!response.ok) {
                            const errorStatus = response.status;
                            // Handle specific HTTP errors
                            if ([400, 403, 404, 429].includes(errorStatus)) {
                                return response.text().then(errorText => {
                                    if (typeof errorCallback === 'function') {
                                        errorCallback(errorStatus, errorText);
                                    }
                                    reject(new Error(`HTTP Error ${errorStatus}: ${errorText}`));
                                });
                            }
                            return response.text().then(errorText => {
                                if (typeof errorCallback === 'function') {
                                        errorCallback(errorStatus, errorText);
                                    }
                                reject(new Error(`HTTP Error ${errorStatus}: ${errorText}`));
                            });
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
                        reject(new Error(`Fetch Error: ${error.message}`));
                    });
            });
        }

        // Praca s 2FA vstupmi...
        twoFactor(elementOrCallback, callback, settings) {
            // Defaultné nastavenia
            const defaults = {
                allowLetters: false,
                length: 6,
                uppercase: true,
                autoSubmit: true,
                invalidClass: 'invalid',
                pattern: null
            };

            // Getter: ak nie sú zadané žiadne argumenty
            if (arguments.length === 0) {
                const inputs = this.#elements;
                if (!inputs.length) {
                    this.#lastResult = false;
                    return false;
                }

                // Overenie, či sú všetky inputy vyplnené a platné
                const pattern = defaults.pattern || (defaults.allowLetters ? /^[A-Za-z0-9]$/ : /^[0-9]$/);
                const isComplete = inputs.every(input => input.value && pattern.test(input.value));
                if (!isComplete) {
                    this.#lastResult = false;
                    return false;
                }

                // Vrátenie spojeného kódu
                const code = inputs.map(input => input.value).join('');
                this.#lastResult = code;
                return code;
            }

            // Setter: spracovanie argumentov
            let element = null;
            let actualCallback = callback;
            let actualSettings = settings;

            if (typeof elementOrCallback === 'function') {
                actualCallback = elementOrCallback;
                actualSettings = callback || {};
            } else {
                element = elementOrCallback;
                actualSettings = settings || {};
            }

            const config = { ...defaults, ...actualSettings };

            // Získanie inputov
            const inputs = element ? this.find(element).#elements : this.#elements;

            // Overenie, či existujú inputy
            if (!inputs.length) {
                console.warn('No inputs found for the given selector or instance:', element);
                return this;
            }

            // Dynamická dĺžka podľa počtu inputov, ak nie je zadaná
            const codeLength = config.length || inputs.length;

            // Overenie počtu inputov
            if (inputs.length !== codeLength) {
                console.warn(`Expected ${codeLength} inputs, but found ${inputs.length}`);
                return this;
            }

            // Nastavenie regulárneho výrazu
            const pattern = config.pattern || (config.allowLetters ? /^[A-Za-z0-9]$/ : /^[0-9]$/);

            // Funkcia na získanie aktuálneho kódu
            const getCode = () => {
                return inputs.map(input => input.value).join('');
            };

            // Funkcia na validáciu a spracovanie vstupu
            const validateInput = (input, value) => {
                if (config.uppercase) {
                    value = value.toUpperCase();
                }
                return pattern.test(value) ? value : null;
            };

            // Funkcia na posun fokusu
            const focusNext = (currentIndex) => {
                if (currentIndex < inputs.length - 1) {
                    inputs[currentIndex + 1].focus();
                }
            };

            const focusPrev = (currentIndex) => {
                if (currentIndex > 0) {
                    inputs[currentIndex - 1].focus();
                }
            };

            // Funkcia na overenie, či sú všetky inputy vyplnené
            const isComplete = () => {
                return inputs.every(input => input.value && pattern.test(input.value));
            };

            // Hlavná inicializácia inputov
            inputs.forEach((input, index) => {
                // Odstránenie predchádzajúcich event listenerov
                const newInput = input.cloneNode(true);
                input.parentNode.replaceChild(newInput, input);
                inputs[index] = newInput;

                // Event listener pre keydown
                newInput.addEventListener('keydown', (e) => {
                    const currentValue = e.target.value;

                    // Backspace: zmazať a presunúť dozadu
                    if (e.key === 'Backspace' && !currentValue && index > 0) {
                        e.preventDefault();
                        inputs[index - 1].value = '';
                        focusPrev(index);
                    }

                    // Delete: zmazať aktuálne pole
                    if (e.key === 'Delete') {
                        e.preventDefault();
                        e.target.value = '';
                        this.find(element || '.two-fa-inputs input').removeClass(config.invalidClass);
                    }

                    // Šípka vľavo
                    if (e.key === 'ArrowLeft' && index > 0) {
                        if (inputs[index - 1].value) {
                            e.preventDefault();
                            focusPrev(index);
                        }
                    }

                    // Šípka vpravo
                    if (e.key === 'ArrowRight' && index < inputs.length - 1) {
                        if (inputs[index + 1].value) {
                            e.preventDefault();
                            focusNext(index);
                        }
                    }
                });

                // Event listener pre input
                newInput.addEventListener('input', (e) => {
                    let value = e.target.value;
                    if (!value) return;

                    // Validácia vstupu
                    const validValue = validateInput(e.target, value);
                    if (validValue) {
                        e.target.value = validValue;
                        this.find(element || '.two-fa-inputs input').removeClass(config.invalidClass);

                        // Presun na ďalší input
                        if (index < inputs.length - 1) {
                            focusNext(index);
                        }

                        // Spustenie callbacku, ak je vyplnené
                        if (isComplete() && config.autoSubmit && typeof actualCallback === 'function') {
                            actualCallback(getCode());
                        }
                    } else {
                        e.target.value = '';
                        this.find(element || '.two-fa-inputs input').addClass(config.invalidClass);
                    }
                });

                // Event listener pre paste
                newInput.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').trim();
                    if (pastedData.length <= inputs.length) {
                        for (let i = 0; i < pastedData.length; i++) {
                            const char = pastedData[i];
                            if (validateInput(inputs[i], char)) {
                                inputs[i].value = config.uppercase ? char.toUpperCase() : char;
                            } else {
                                inputs[i].value = '';
                                this.find(element || '.two-fa-inputs input').addClass(config.invalidClass);
                                return;
                            }
                        }
                        this.find(element || '.two-fa-inputs input').removeClass(config.invalidClass);

                        // Presun fokusu na posledný vyplnený input
                        const lastFilledIndex = Math.min(pastedData.length - 1, inputs.length - 1);
                        inputs[lastFilledIndex].focus();

                        // Spustenie callbacku, ak je vyplnené
                        if (isComplete() && config.autoSubmit && typeof actualCallback === 'function') {
                            actualCallback(getCode());
                        }
                    }
                });
            });

            return this; // Reťazenie pre setter
        }

        submit(pushElements) {
            if (this.instancia === 1) {
                if (pushElements === undefined) return this;

                const firstElement = pushElements[0];
                let result = null;

                if (firstElement.tagName === 'FORM') {
                    if (!firstElement.hasAttribute('data-dotapp-nojs')) {
                        // Preberáme kontrolu cez handleFormSubmission
                        const method = firstElement.getAttribute('method') || 'POST';
                        const action = firstElement.getAttribute('action') || window.location.href;
                        result = this.#handleFormSubmission(firstElement, method, action);
                    } else {
                        // Natívne odoslanie
                        firstElement.submit();
                        result = true;
                    }
                }

                this.#lastResult = result;
                return this;
            } else {
                if (!this.#elements.length) {
                    this.#lastResult = null;
                    return this;        
                } else {
                    return window.$dotapp().submit(this.#elements);
                }
            }             
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
    new DotApp("form").removeClass("dotapp-pending");
    // Assign to the global object
    global.$dotapp = $dotAppDispatcher;
    // Registrujeme funkcie
    window.dispatchEvent(new Event('dotapp-register'));
    // Spustame ostatne funckie zavisle od dotapp kniznice
    window.dispatchEvent(new Event('dotapp'));

})(window);

