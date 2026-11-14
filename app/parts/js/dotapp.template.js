/*
    DotApp Template Module
    Author: Štefan Miščík
    Website: https://dotapp.dev/
    Email: dotapp@dotapp.dev
    Version: 1.2 (Stable)
    License: MIT License
    Description:
    A lightweight client-side template engine for the DotApp framework.
    Provides lazy loading of templates from /app/views/, reactive rendering
    via DotAppVariable, automatic XSS protection and extensible renderers/blocks.
    Fully symmetric with the server-side PHP Renderer.
    Supports:
      - {{ var: $variable }}
      - {{ foreach $items as $item }} ... {{ /foreach }}
      - {{ if $condition }} ... {{ elseif $condition }} ... {{ else }} ... {{ /if }}
      - {{ block:name }} ... {{ /block:name }}
      - {{ include partials/header }} or {{ include 'partials/header' }}
    Note:
    You are free to use, modify, and distribute this code. Please include
    this header with the author's information in all copies or substantial
    portions of the software.
*/

(function() {
    // Flag to prevent double registration
    let isRegistered = false;
    
    const runMe = function($dotapp) {
        // Prevent double registration
        if (isRegistered) {
            console.warn('DotApp Template Engine already registered, skipping...');
            return;
        }
        isRegistered = true;
        /**
         * Main template engine class
         */
        class DotAppTemplateEngine {
            /**
             * Cache for loaded templates: path → raw template string
             * @private
             */
            #cache = new Map();

            /**
             * Pending promises to deduplicate parallel fetches
             * @private
             */
            #pending = new Map();

            /**
             * Registered renderers: name → function(code, data)
             * @private
             */
            #renderers = new Map();

            /**
             * Registered blocks: name → function(innerContent, blockVars, data)
             * @private
             */
            #blocks = new Map();

            /**
             * Base path for templates
             * @private
             */
            #basePath = '/app/views/';

            constructor() {
                this.#registerCoreRenderers();
            }

            /**
             * Sets the base path for loading templates
             * @param {string} path Base path (should end with /)
             */
            setBasePath(path) {
                this.#basePath = path.endsWith('/') ? path : path + '/';
                return this;
            }

            /**
             * Gets the base path for templates
             * @returns {string} Base path
             */
            getBasePath() {
                return this.#basePath;
            }

            /**
             * Registers all core renderers (var, foreach, if, block, include)
             * @private
             */
            #registerCoreRenderers() {
                // {{ foreach $items as $item }} ... {{ /foreach }}
                // Oprava: povodny vzor uzavrel vonkajsi cyklus pri PRVOM {{ /foreach }}, teda pri konci vnutorneho cyklu.
                // Vnorene cykly preto nechavali v stranke zvysky znaciek. Teraz hladame k otvaracej znacke jej vlastnu
                // zatvaraciu (pocitame hlbku) a vnutro posleme do rendererov - vnoreny cyklus sa vyrendruje s polozkou vonkajsieho.
                // Vysledok skladame priamo, nie cez String.replace(), ktore v texte interpretuje znak $ (z "$$5" spravilo "$5").
                this.addRenderer('dotapp.foreach', async (code, data) => {
                    const openPattern = /\{\{\s*foreach\s+\$([a-zA-Z_$][a-zA-Z0-9_$-]*)\s+as\s+\$([a-zA-Z_$][a-zA-Z0-9_$-]*)\s*\}\}/g;
                    const tagPattern = /\{\{\s*(\/foreach|foreach\s[^}]*)\s*\}\}/g;
                    let result = '';
                    let position = 0;

                    while (true) {
                        openPattern.lastIndex = position;
                        const open = openPattern.exec(code);
                        if (!open) break;

                        // Najdeme zatvaraciu znacku ktora patri k tejto otvaracej
                        tagPattern.lastIndex = open.index + open[0].length;
                        let depth = 1;
                        let closeStart = -1;
                        let closeEnd = -1;
                        let tag;
                        while ((tag = tagPattern.exec(code)) !== null) {
                            if (tag[1].charAt(0) === '/') {
                                depth--;
                                if (depth === 0) {
                                    closeStart = tag.index;
                                    closeEnd = tag.index + tag[0].length;
                                    break;
                                }
                            } else {
                                depth++;
                            }
                        }
                        // Cyklus bez zatvaracej znacky nechame tak ako je
                        if (closeStart < 0) break;

                        const listKey = open[1];
                        const itemKey = open[2];
                        const inner = code.slice(open.index + open[0].length, closeStart);
                        result += code.slice(position, open.index);

                        const list = data[listKey] ?? [];
                        if (Array.isArray(list)) {
                            // Process all items asynchronously
                            const renderedItems = await Promise.all(
                                list.map(async item => {
                                    const nested = { ...data, [itemKey]: item };
                                    return await this.#runAllRenderers(inner, nested);
                                })
                            );
                            result += renderedItems.join('');
                        }
                        position = closeEnd;
                    }

                    return result + code.slice(position);
                });

                // {{ if $condition }} ... {{ elseif $condition }} ... {{ else }} ... {{ /if }}
                // Oprava: povodna verzia mala tri chyby.
                // 1) Text za poslednou znackou sa pripojil dvakrat (raz v cykle, raz za cyklom) - kazda sablona mala zdvojeny koniec.
                // 2) {{ else }} a {{ elseif }} sa vypisali aj ked uz predtym presla ina vetva (if A else B davalo "AB").
                // 3) Vnorene {{ if }} prepisalo stav vonkajsieho, lebo stav bol iba jeden.
                // Teraz ma kazde {{ if }} vlastny zaznam v zasobniku: ci je aktivny rodic, ci uz nejaka vetva presla a ci sa prave vypisuje.
                this.addRenderer('dotapp.if', (code, data) => {
                    const pattern = /\{\{\s*(if|elseif|else|\/if)\s*(.*?)\s*\}\}/g;
                    const stack = [];
                    let result = '';
                    let position = 0;
                    let match;

                    // Text sa vypisuje iba ak sme mimo podmienok alebo v prave platnej vetve
                    const isActive = () => stack.length === 0 || stack[stack.length - 1].active;

                    while ((match = pattern.exec(code)) !== null) {
                        const type = match[1].trim();
                        const cond = match[2]?.trim();

                        if (isActive()) {
                            result += code.slice(position, match.index);
                        }
                        position = match.index + match[0].length;

                        if (type === 'if') {
                            const parentActive = isActive();
                            // Podmienku vyhodnocujeme iba ak sa rodic vypisuje
                            const ok = parentActive ? !!this.#evalCondition(cond, data) : false;
                            stack.push({ parentActive: parentActive, done: ok, active: ok });
                        } else if (type === 'elseif') {
                            // Znacka mimo if - iba ju vynechame, rovnako ako predtym
                            if (stack.length === 0) continue;
                            const frame = stack[stack.length - 1];
                            if (!frame.parentActive || frame.done) {
                                frame.active = false;
                            } else {
                                const ok = !!this.#evalCondition(cond, data);
                                frame.active = ok;
                                frame.done = ok;
                            }
                        } else if (type === 'else') {
                            if (stack.length === 0) continue;
                            const frame = stack[stack.length - 1];
                            frame.active = frame.parentActive && !frame.done;
                            frame.done = true;
                        } else if (type === '/if') {
                            if (stack.length > 0) {
                                stack.pop();
                            }
                        }
                    }

                    // Zvysok za poslednou znackou - pripojime ho presne raz
                    if (isActive()) {
                        result += code.slice(position);
                    }
                    return result;
                });

                // {{ block:name }} ... {{ /block:name }}
                this.addRenderer('dotapp.block', (code, data) => {
                    const pattern = /\{\{\s*block:([\w.-]+)\s*\}\}(.*?)\{\{\s*\/block:\1\s*\}\}/gs;
                    return code.replace(pattern, (match, name, content) => {
                        const fn = this.#blocks.get(name);
                        return typeof fn === 'function' ? fn(content, [], data) || '' : content;
                    });
                });

                // {{ include partials/header }} or {{ include 'partials/header' }}
                this.addRenderer('dotapp.include', async (code, data) => {
                    const pattern = /\{\{\s*include\s+(?:'([^']+)'|"([^"]+)"|([^\s\}]+))\s*\}\}/g;
                    const includes = [];
                    let match;

                    while ((match = pattern.exec(code)) !== null) {
                        const path = match[1] || match[2] || match[3];
                        if (path) {
                            includes.push({
                                placeholder: match[0],
                                path: path.trim()
                            });
                        }
                    }

                    let result = code;
                    for (const inc of includes) {
                        try {
                            const partialCode = await this.#load(inc.path);
                            const rendered = await this.#runAllRenderers(partialCode, data);
                            // Oprava: replace() s textom interpretuje $ vo vkladanom obsahu ($&, $$...). Funkcia vlozi obsah presne.
                            result = result.replace(inc.placeholder, () => rendered);
                        } catch (e) {
                            console.warn(`Failed to include template '${inc.path}':`, e);
                            result = result.replace(inc.placeholder, () => `<!-- Include failed: ${inc.path} -->`);
                        }
                    }
                    return result;
                });

                // {{ var: $variable }} – with automatic XSS protection
                // Supports nested properties like $task.status, $task.title, etc.
                // IMPORTANT: This renderer must be registered LAST, after foreach and if,
                // so that variables inside blocks are rendered after the blocks are processed
                this.addRenderer('dotapp.var', (code, data) => {
                    return code.replace(/\{\{\s*var:\s*\$([a-zA-Z_$][a-zA-Z0-9_$.-]*)\s*\}\}/g, (match, keyPath) => {
                        // Split key path by dots to access nested properties
                        const keys = keyPath.split('.');
                        let value = data;
                        
                        // Navigate through nested properties
                        for (const key of keys) {
                            if (value === null || value === undefined) {
                                return '';
                            }
                            value = value[key];
                        }
                        
                        if (value === undefined || value === null) {
                            return '';
                        }
                        if (typeof value !== 'string') value = String(value);
                        return value
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;')
                            .replace(/\$/g, '&#36;');
                    });
                });
            }

            /**
             * Simple condition evaluator for {{ if }}
             * Supports nested properties like $task.status, $task.title, etc.
             * @private
             */
            #evalCondition(cond, data) {
                if (!cond) return true;
                try {
                    // Replace $variable.property with data.variable.property
                    // This handles nested properties correctly
                    const expr = cond.replace(/\$([a-zA-Z_$][a-zA-Z0-9_$.-]*)/g, (match, keyPath) => {
                        // Build the path: $task.status -> data.task.status
                        const keys = keyPath.split('.');
                        let path = 'data';
                        for (const key of keys) {
                            path += `['${key}']`;
                        }
                        return path;
                    });
                    return new Function('data', `return !!(${expr});`)(data);
                } catch (e) {
                    console.warn('Invalid if condition:', cond, e);
                    return false;
                }
            }

            /**
             * Adds a custom renderer
             * @param {string} name
             * @param {function(string, object): string|Promise<string>} fn
             */
            addRenderer(name, fn) {
                this.#renderers.set(name, fn);
                return this;
            }

            /**
             * Registers a block
             * @param {string} name
             * @param {function(string, array, object): string} fn
             */
            addBlock(name, fn) {
                this.#blocks.set(name, fn);
                return this;
            }

            /**
             * Runs all registered renderers (supports async)
             * @private
             */
            async #runAllRenderers(code, data) {
                let result = code;
                for (const fn of this.#renderers.values()) {
                    result = await fn(result, data);
                }
                return result;
            }

            /**
             * Lazy loads a template with caching
             * @private
             */
            async #load(path) {
                if (this.#cache.has(path)) return this.#cache.get(path);
                if (this.#pending.has(path)) return this.#pending.get(path);

                const fullPath = path.startsWith('/') ? path : `${this.#basePath}${path}`;
                const url = fullPath.endsWith('.view.js') ? fullPath : `${fullPath}.view.js`;

                const promise = fetch(url)
                    .then(r => {
                        if (!r.ok) throw new Error(`Template ${path} not found at ${url}`);
                        return r.text();
                    })
                    .then(text => {
                        this.#cache.set(path, text);
                        return text;
                    })
                    .finally(() => this.#pending.delete(path));

                this.#pending.set(path, promise);
                return promise;
            }

            /**
             * Renders a template into an element
             */
            async render(element, path, dataOrVar) {
                const template = await this.#load(path);

                let data = {};
                let reactiveVar = null;

                if ($dotapp().isInstanceOfDotAppVariable(dataOrVar)) {
                    reactiveVar = dataOrVar;
                    data = reactiveVar.value || {};
                } else if (typeof dataOrVar === 'string') {
                    reactiveVar = $dotapp().getVariable(dataOrVar);
                    data = reactiveVar ? reactiveVar.value || {} : {};
                } else {
                    data = dataOrVar || {};
                }

                const doRender = async () => {
                    const current = reactiveVar ? reactiveVar.value || {} : data;
                    element.innerHTML = await this.#runAllRenderers(template, current);
                };

                if (reactiveVar) {
                    reactiveVar.onChange(doRender);
                }

                await doRender();
            }
        }

        const engine = new DotAppTemplateEngine();

        /**
         * Main template method – renders into selected elements
         * Always returns the original DotApp instance for chaining
         */
        $dotapp().fn('template', function(path, data) {
            if (typeof path === 'string') {
                // Render all elements asynchronously
                // Don't return promises - just start the rendering process
                this.getElements().forEach(el => {
                    engine.render(el, path, data).catch(err => {
                        console.error('Template rendering error:', err);
                    });
                });
            }

            const self = this;
            return {
                addRenderer: function(name, fn) {
                    engine.addRenderer(name, fn);
                    return self;
                },
                addBlock: function(name, fn) {
                    engine.addBlock(name, fn);
                    return self;
                },
                setBasePath: function(path) {
                    engine.setBasePath(path);
                    return self;
                },
                getBasePath: function() {
                    return engine.getBasePath();
                }
            };
        });

        // Dispatch event when module is ready
        window.dispatchEvent(new Event('dotapp-template-ready'));
    };

    if (window.$dotapp) {
        runMe(window.$dotapp);
    } else {
        window.addEventListener('dotapp-register', () => runMe(window.$dotapp), { once: true });
    }
})();