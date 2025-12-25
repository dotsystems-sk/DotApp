/*
    DotApp Reactive Module
    Author: Štefan Miščík
    Website: https://dotapp.dev/
    Email: dotapp@dotapp.dev
    Version: 1.0
    License: MIT License
    Description:
    A robust reactive data binding library for the DotApp framework.
    Provides automatic data fetching from API endpoints with secure communication
    (CRC, CSRF), reactive updates via DotAppVariable, and integration with template engine.
    Supports:
      - HTML attributes: reactive-api, reactive-trigger, reactive-variable, reactive-interval
      - JavaScript API: $dotapp().reactive(url, config)
      - Automatic polling, event triggers, and reactive updates
    Note:
    You are free to use, modify, and distribute this code. Please include
    this header with the author's information in all copies or substantial
    portions of the software.
*/

(function() {
    /**
     * Flag to prevent double registration of the reactive module
     * @type {boolean}
     * @private
     */
    let isRegistered = false;
    
    /**
     * Initializes the DotApp Reactive module
     * @param {Function} $dotapp - The DotApp framework instance
     * @private
     */
    const runMe = function($dotapp) {
        // Prevent double registration
        if (isRegistered) {
            console.warn('DotApp Reactive Engine already registered, skipping...');
            return;
        }
        isRegistered = true;
        
        /**
         * Represents a single reactive API endpoint configuration
         * Handles data fetching, polling, event triggers, and reactive updates
         * @class ReactiveEndpoint
         * @private
         */
        class ReactiveEndpoint {
            /** @type {HTMLElement|null} @private */
            #element = null;
            
            /** @type {string} @private */
            #apiUrl = '';
            
            /** @type {string} @private */
            #method = 'GET';
            
            /** @type {string|string[]|null} @private */
            #trigger = null;
            
            /** @type {DotAppVariable|string|null} @private */
            #variable = null;
            
            /** @type {number|null} @private */
            #interval = null;
            
            /** @type {number|null} @private */
            #intervalId = null;
            
            /** @type {string} @private */
            #key = '';
            
            /** @type {string} @private */
            #id = '';
            
            /** @type {string} @private */
            #data = '';
            
            /** @type {DotApp} @private */
            #dotAppInstance = null;
            
            /** @type {Object} @private */
            #hooks = {};
            
            /** @type {number} @private */
            #retryCount = 0;
            
            /** @type {number} @private */
            #maxRetries = 3;
            
            /** @type {number} @private */
            #retryDelay = 1000;
            
            /** @type {boolean} @private */
            #isLoading = false;
            
            /** @type {any} @private */
            #lastData = null;
            
            /** @type {string|null} @private */
            #template = null;
            
            /** @type {string|null} @private */
            #templatePath = null;

            /**
             * Creates a new ReactiveEndpoint instance
             * @constructor
             * @param {DotApp} dotAppInstance - The DotApp framework instance
             * @param {HTMLElement|null} element - DOM element to update with response data
             * @param {Object} config - Configuration object
             * @param {string} config.api - API endpoint URL
             * @param {string} [config.method='GET'] - HTTP method (GET or POST)
             * @param {string|string[]} [config.trigger] - Event trigger(s) or 'variable' for variable changes
             * @param {string|DotAppVariable} [config.variable] - DotAppVariable name or instance to bind data to
             * @param {number|string} [config.interval] - Polling interval in milliseconds
             * @param {string} [config.key=''] - Encryption key for secure data
             * @param {string} [config.id=''] - Custom endpoint ID
             * @param {string} [config.data=''] - Encrypted data payload
             * @param {string} [config.template] - Template path for rendering
             * @param {string} [config.templatePath] - Template path (alternative to template)
             */
            constructor(dotAppInstance, element, config) {
                this.#dotAppInstance = dotAppInstance;
                this.#element = element;
                this.#apiUrl = config.api || '';
                this.#method = (config.method || 'GET').toUpperCase();
                this.#trigger = config.trigger || null;
                this.#variable = config.variable || null;
                this.#interval = config.interval ? parseInt(config.interval) : null;
                this.#key = config.key || '';
                this.#id = config.id || '';
                this.#data = config.data || '';
                this.#template = config.template || null;
                this.#templatePath = config.templatePath || null;
                this.#hooks = {
                    before: [],
                    after: [],
                    onError: [],
                    onResponseCode: {}
                };

                this.#initialize();
            }

            /**
             * Initializes the endpoint: sets up variables, polling, and triggers
             * @private
             */
            #initialize() {
                // Create DotAppVariable if variable name is provided
                if (this.#variable) {
                    const variable = this.#dotAppInstance.variable(this.#variable, null);
                    if (variable) {
                        this.#variable = variable;
                    }
                }

                // Setup polling if interval is provided
                if (this.#interval && this.#interval > 0) {
                    this.#setupPolling();
                }

                // Setup event triggers
                if (this.#trigger) {
                    this.#setupEventTriggers();
                } else if (!this.#interval) {
                    // If no trigger and no interval, fetch immediately
                    this.#fetch();
                }
            }

            /**
             * Sets up automatic polling at the specified interval
             * @private
             */
            #setupPolling() {
                if (this.#intervalId) {
                    clearInterval(this.#intervalId);
                }

                this.#intervalId = setInterval(() => {
                    this.#fetch();
                }, this.#interval);
            }

            /**
             * Sets up event triggers for the endpoint
             * Supports DOM events and DotAppVariable changes
             * @private
             */
            #setupEventTriggers() {
                if (!this.#element) return;

                const triggers = Array.isArray(this.#trigger) ? this.#trigger : [this.#trigger];
                
                triggers.forEach(triggerEvent => {
                    if (triggerEvent === 'variable') {
                        // Trigger on DotAppVariable change
                        if (this.#variable && this.#variable.onChange) {
                            this.#variable.onChange(() => {
                                this.#fetch();
                            });
                        }
                    } else {
                        // DOM event trigger via DotApp API
                        if (typeof $dotapp !== 'undefined') {
                            $dotapp(this.#element).on(triggerEvent, () => {
                                this.#fetch();
                            });
                        } else {
                            // Fallback vanilla JS
                            this.#element.addEventListener(triggerEvent, () => {
                                this.#fetch();
                            });
                        }
                    }
                });
            }

            /**
             * Main fetch method - handles the complete fetch lifecycle
             * Calls before hooks, performs fetch, handles errors
             * @private
             * @async
             */
            async #fetch() {
                if (this.#isLoading) return;
                
                this.#isLoading = true;
                this.#retryCount = 0;

                // Call before hooks
                if (this.#hooks.before.length > 0) {
                    for (const fn of this.#hooks.before) {
                        try {
                            await fn(this.#lastData, this.#element);
                        } catch (e) {
                            console.warn('Error in before hook:', e);
                        }
                    }
                }

                try {
                    await this.#performFetch();
                } catch (error) {
                    this.#handleError(error);
                } finally {
                    this.#isLoading = false;
                }
            }

            /**
             * Performs the actual API fetch operation
             * Handles CSRF/CRC via DotApp load() method, falls back to direct fetch
             * Processes response data and updates variables/templates
             * @private
             * @async
             * @throws {Error} When fetch fails or response is not OK
             */
            async #performFetch() {
                // Decrypt API URL from reactive-data
                let apiUrl = this.#apiUrl;
                if (this.#data && this.#key) {
                    try {
                        // Try to decrypt using load() method's internal mechanism
                        // If decryption fails, use provided URL
                        apiUrl = this.#apiUrl; // For now, use direct URL
                    } catch (e) {
                        console.warn('Failed to decrypt reactive-data, using provided URL');
                    }
                }

                // Use load() method which handles CSRF and CRC automatically
                let responseData;
                try {
                    if (this.#method === 'GET') {
                        responseData = await this.#dotAppInstance.load(apiUrl);
                    } else {
                        // For POST, use load with data
                        responseData = await this.#dotAppInstance.load(apiUrl, 'POST', {});
                    }
                } catch (error) {
                    // If load() fails, try direct fetch
                    const response = await fetch(apiUrl, {
                        method: this.#method,
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'dotreactive': 'true'
                        }
                    });
                    
                    if (!response.ok) {
                        const statusCode = response.status;
                        const errorText = await response.text();
                        
                        // Call onResponseCode hooks
                        if (this.#hooks.onResponseCode[statusCode]) {
                            for (const fn of this.#hooks.onResponseCode[statusCode]) {
                                try {
                                    await fn(statusCode, errorText, this.#element);
                                } catch (e) {
                                    console.warn('Error in onResponseCode hook:', e);
                                }
                            }
                            return;
                        }

                        throw new Error(`HTTP ${statusCode}: ${errorText}`);
                    }
                    
                    const responseText = await response.text();
                    let parsedData = null;

                    try {
                        parsedData = JSON.parse(atob(responseText));
                    } catch (e) {
                        try {
                            parsedData = JSON.parse(responseText);
                        } catch (e2) {
                            parsedData = responseText;
                        }
                    }

                    this.#lastData = parsedData;
                    this.#retryCount = 0;

                    // Update DotAppVariable
                    if (this.#variable && this.#variable.value !== undefined) {
                        this.#variable.value = parsedData;
                    }

                    // Render template if provided
                    if (this.#templatePath && this.#element) {
                        await this.#renderTemplate(parsedData);
                    } else if (this.#element && !this.#variable) {
                        this.#element.innerHTML = typeof parsedData === 'string' 
                            ? parsedData 
                            : JSON.stringify(parsedData);
                    }

                    // Call after hooks
                    if (this.#hooks.after.length > 0) {
                        for (const fn of this.#hooks.after) {
                            try {
                                await fn(parsedData, this.#element);
                            } catch (e) {
                                console.warn('Error in after hook:', e);
                            }
                        }
                    }
                    return;
                }

                // Process responseData from load()
                let parsedData = responseData;
                if (typeof responseData === 'string') {
                    try {
                        parsedData = JSON.parse(atob(responseData));
                    } catch (e) {
                        try {
                            parsedData = JSON.parse(responseData);
                        } catch (e2) {
                            parsedData = responseData;
                        }
                    }
                }

                this.#lastData = parsedData;
                this.#retryCount = 0;

                // Update DotAppVariable
                if (this.#variable && this.#variable.value !== undefined) {
                    this.#variable.value = parsedData;
                }

                // Render template if provided
                if (this.#templatePath && this.#element) {
                    await this.#renderTemplate(parsedData);
                } else if (this.#element && !this.#variable) {
                    // Update element content directly if no variable
                    this.#element.innerHTML = typeof parsedData === 'string' 
                        ? parsedData 
                        : JSON.stringify(parsedData);
                }

                // Call after hooks
                if (this.#hooks.after.length > 0) {
                    for (const fn of this.#hooks.after) {
                        try {
                            await fn(parsedData, this.#element);
                        } catch (e) {
                            console.warn('Error in after hook:', e);
                        }
                    }
                }
            }

            /**
             * Renders fetched data using the DotApp template engine
             * @private
             * @async
             * @param {any} data - Data to render in the template
             */
            async #renderTemplate(data) {
                if (!this.#element || !this.#templatePath) return;

                try {
                    // Use template engine if available
                    if (typeof $dotapp().fn === 'function') {
                        const templateFn = $dotapp().fn('template');
                        if (templateFn) {
                            await $dotapp(this.#element).template(this.#templatePath, data);
                        }
                    }
                } catch (e) {
                    console.warn('Failed to render template:', e);
                }
            }

            /**
             * Handles fetch errors with automatic retry logic
             * Uses exponential backoff for retries
             * Calls onError hooks after all retries are exhausted
             * @private
             * @param {Error} error - The error that occurred
             */
            #handleError(error) {
                this.#retryCount++;

                if (this.#retryCount < this.#maxRetries) {
                    // Exponential backoff
                    const delay = this.#retryDelay * Math.pow(2, this.#retryCount - 1);
                    setTimeout(() => {
                        this.#isLoading = false;
                        this.#fetch();
                    }, delay);
                    return;
                }

                // Call onError hooks
                if (this.#hooks.onError.length > 0) {
                    for (const fn of this.#hooks.onError) {
                        try {
                            fn(error, this.#element);
                        } catch (e) {
                            console.warn('Error in onError hook:', e);
                        }
                    }
                } else {
                    console.error('Reactive endpoint error:', error);
                }
            }

            /**
             * Adds a hook that runs before each fetch operation
             * @param {Function} fn - Hook function: (lastData: any, element: HTMLElement) => void | Promise&lt;void&gt;
             * @returns {ReactiveEndpoint} Returns this for method chaining
             * @example
             * endpoint.before((lastData, element) => {
             *     element.classList.add('loading');
             * });
             */
            before(fn) {
                this.#hooks.before.push(fn);
                return this;
            }

            /**
             * Adds a hook that runs after a successful fetch
             * @param {Function} fn - Hook function: (data: any, element: HTMLElement) => void | Promise&lt;void&gt;
             * @returns {ReactiveEndpoint} Returns this for method chaining
             * @example
             * endpoint.after((data, element) => {
             *     element.classList.remove('loading');
             *     console.log('Data received:', data);
             * });
             */
            after(fn) {
                this.#hooks.after.push(fn);
                return this;
            }

            /**
             * Adds an error handler that runs after all retries are exhausted
             * @param {Function} fn - Error handler: (error: Error, element: HTMLElement) => void
             * @returns {ReactiveEndpoint} Returns this for method chaining
             * @example
             * endpoint.onError((error, element) => {
             *     element.innerHTML = 'Error: ' + error.message;
             * });
             */
            onError(fn) {
                this.#hooks.onError.push(fn);
                return this;
            }

            /**
             * Adds a handler for a specific HTTP response code
             * @param {number} code - HTTP status code (e.g., 404, 403, 500)
             * @param {Function} fn - Handler function: (code: number, errorText: string, element: HTMLElement) => void | Promise&lt;void&gt;
             * @returns {ReactiveEndpoint} Returns this for method chaining
             * @example
             * endpoint.onResponseCode(404, (code, errorText, element) => {
             *     element.innerHTML = 'Resource not found';
             * });
             */
            onResponseCode(code, fn) {
                if (!this.#hooks.onResponseCode[code]) {
                    this.#hooks.onResponseCode[code] = [];
                }
                this.#hooks.onResponseCode[code].push(fn);
                return this;
            }

            /**
             * Destroys the endpoint and cleans up resources
             * Stops polling intervals and removes event listeners
             * @returns {void}
             */
            destroy() {
                if (this.#intervalId) {
                    clearInterval(this.#intervalId);
                    this.#intervalId = null;
                }
                // Note: Event listeners will be cleaned up when element is removed
            }

            /**
             * Manually triggers a fetch operation
             * @returns {void}
             * @example
             * endpoint.refresh(); // Force a new fetch
             */
            refresh() {
                this.#fetch();
            }
        }

        /**
         * Manages all reactive endpoints in the application
         * Handles creation, initialization, and lifecycle of reactive endpoints
         * @class DotAppReactiveEngine
         * @private
         */
        class DotAppReactiveEngine {
            /** @type {Map<string, ReactiveEndpoint>} @private */
            #endpoints = new Map();
            
            /** @type {DotApp} @private */
            #dotAppInstance = null;

            /**
             * Creates a new DotAppReactiveEngine instance
             * @constructor
             * @param {DotApp} dotAppInstance - The DotApp framework instance
             */
            constructor(dotAppInstance) {
                this.#dotAppInstance = dotAppInstance;
            }

            /**
             * Creates a reactive endpoint from HTML element attributes
             * Automatically extracts configuration from reactive-* attributes
             * @param {HTMLElement} element - DOM element with reactive attributes
             * @returns {ReactiveEndpoint|null} The created endpoint, or null if no reactive-api attribute found
             */
            createFromElement(element) {
                const api = element.getAttribute('reactive-api');
                if (!api) return null;

                const config = {
                    api: api,
                    method: element.getAttribute('reactive-method') || 'GET',
                    trigger: element.getAttribute('reactive-trigger'),
                    variable: element.getAttribute('reactive-variable'),
                    interval: element.getAttribute('reactive-interval'),
                    key: element.getAttribute('reactive-key'),
                    id: element.getAttribute('reactive-id'),
                    data: element.getAttribute('reactive-data'),
                    templatePath: element.getAttribute('reactive-template')
                };

                const endpointId = config.id || `reactive-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
                const endpoint = new ReactiveEndpoint(this.#dotAppInstance, element, config);
                this.#endpoints.set(endpointId, endpoint);

                return endpoint;
            }

            /**
             * Creates a reactive endpoint programmatically
             * @param {string} url - API endpoint URL
             * @param {Object} [config={}] - Configuration object
             * @param {string} [config.method='GET'] - HTTP method (GET or POST)
             * @param {HTMLElement} [config.element] - DOM element to update
             * @param {string|string[]} [config.trigger] - Event trigger(s) or 'variable'
             * @param {string|DotAppVariable} [config.variable] - DotAppVariable to bind to
             * @param {number} [config.interval] - Polling interval in milliseconds
             * @param {string} [config.template] - Template path for rendering
             * @param {string} [config.key=''] - Encryption key
             * @param {string} [config.id] - Custom endpoint ID
             * @param {string} [config.data=''] - Encrypted data payload
             * @returns {Object} Endpoint instance with chainable methods
             * @example
             * const endpoint = engine.create('/api/data', {
             *     element: document.getElementById('content'),
             *     interval: 5000
             * });
             */
            create(url, config = {}) {
                const endpointId = config.id || `reactive-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
                
                const endpointConfig = {
                    api: url,
                    method: config.method || 'GET',
                    trigger: config.trigger || null,
                    variable: config.variable || null,
                    interval: config.interval || null,
                    key: config.key || '',
                    id: endpointId,
                    data: config.data || '',
                    templatePath: config.template || null
                };

                const endpoint = new ReactiveEndpoint(this.#dotAppInstance, config.element || null, endpointConfig);
                this.#endpoints.set(endpointId, endpoint);

                const instance = {
                    before: (fn) => {
                        endpoint.before(fn);
                        return instance;
                    },
                    after: (fn) => {
                        endpoint.after(fn);
                        return instance;
                    },
                    onError: (fn) => {
                        endpoint.onError(fn);
                        return instance;
                    },
                    onResponseCode: (code, fn) => {
                        endpoint.onResponseCode(code, fn);
                        return instance;
                    },
                    refresh: () => {
                        endpoint.refresh();
                        return instance;
                    },
                    destroy: () => {
                        endpoint.destroy();
                        this.#endpoints.delete(endpointId);
                    }
                };

                return instance;
            }

            /**
             * Initializes all reactive elements from HTML attributes
             * Scans the DOM for elements with reactive-api attribute
             * Removes attributes from DOM after initialization for security
             * @returns {void}
             */
            initializeElements() {
                const reactiveElements = document.querySelectorAll('[reactive-api]');
                reactiveElements.forEach(element => {
                    // Store attributes in _reactiveData
                    element._reactiveData = {
                        api: element.getAttribute('reactive-api'),
                        method: element.getAttribute('reactive-method'),
                        trigger: element.getAttribute('reactive-trigger'),
                        variable: element.getAttribute('reactive-variable'),
                        interval: element.getAttribute('reactive-interval'),
                        key: element.getAttribute('reactive-key'),
                        id: element.getAttribute('reactive-id'),
                        data: element.getAttribute('reactive-data'),
                        template: element.getAttribute('reactive-template')
                    };

                    // Create endpoint
                    this.createFromElement(element);

                    // Remove attributes from DOM for security
                    element.removeAttribute('reactive-api');
                    element.removeAttribute('reactive-method');
                    element.removeAttribute('reactive-trigger');
                    element.removeAttribute('reactive-variable');
                    element.removeAttribute('reactive-interval');
                    element.removeAttribute('reactive-key');
                    element.removeAttribute('reactive-id');
                    element.removeAttribute('reactive-data');
                    element.removeAttribute('reactive-template');
                });
            }

            /**
             * Gets all registered reactive endpoints
             * @returns {ReactiveEndpoint[]} Array of all endpoint instances
             */
            getEndpoints() {
                return Array.from(this.#endpoints.values());
            }

            /**
             * Gets a specific endpoint by its ID
             * @param {string} id - Endpoint ID
             * @returns {ReactiveEndpoint|undefined} The endpoint instance, or undefined if not found
             */
            getEndpoint(id) {
                return this.#endpoints.get(id);
            }

            /**
             * Destroys all registered endpoints and cleans up resources
             * @returns {void}
             */
            destroyAll() {
                this.#endpoints.forEach(endpoint => endpoint.destroy());
                this.#endpoints.clear();
            }
        }

        /**
         * Global reactive engine instance
         * @type {DotAppReactiveEngine}
         * @private
         */
        const engine = new DotAppReactiveEngine($dotapp());

        /**
         * Initializes reactive elements from HTML attributes
         * Called on DOM ready and on dotapp-register event
         * @private
         */
        function initializeReactiveElements() {
            engine.initializeElements();
        }

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeReactiveElements);
        } else {
            initializeReactiveElements();
        }

        // Also initialize on dotapp-register event (for dynamically loaded content)
        window.addEventListener('dotapp-register', initializeReactiveElements);

        /**
         * Main reactive method – registers reactive endpoints
         * Available as $dotapp().reactive(url, config)
         * @param {string} url - API endpoint URL
         * @param {Object} [config] - Configuration object
         * @returns {Object|DotApp} Endpoint instance or DotApp instance for chaining
         * @example
         * // Create endpoint
         * const endpoint = $dotapp().reactive('/api/data', {
         *     element: document.getElementById('content')
         * });
         * 
         * // Chain hooks
         * endpoint.after((data) => console.log(data));
         */
        $dotapp().fn('reactive', function(url, config) {
            if (typeof url === 'string') {
                return engine.create(url, config || {});
            }
            return this;
        });

        /**
         * Dispatches event when reactive module is ready
         * Listen for 'dotapp-reactive-ready' event to know when module is initialized
         * @event dotapp-reactive-ready
         */
        window.dispatchEvent(new Event('dotapp-reactive-ready'));
    };

    if (window.$dotapp) {
        runMe(window.$dotapp);
    } else {
        window.addEventListener('dotapp-register', () => runMe(window.$dotapp), { once: true });
    }
})();

