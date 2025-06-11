(function() {
    var runMe = function($dotapp) {

        class ConnectorHalt {
            reason = "";

            constructor(reason) {
                this.reason = reason;
            }
        }

        class DotAppConnector {
            #dotAppInstance;
            #parent;
            #nodes = [];
            #connections = [];
            #connectionRules = {};
            #nodeRules = {};
            #svg;
            #tempLine = null;
            #isConnecting = false;
            #startPort = null;
            #removedConnection = null;
            #sidebar = null;
            #premadeNodes = [];
            #draggedNodeConfig = null;
            #eventHandlers = {};
            #gridEnabled = false;
            #gridX = 10;
            #gridY = 10;
            #isDragging = false;
            #initialWidth = 800;
            #initialHeight = 600;
            #lastUserWidth = 800;
            #lastUserHeight = 600;
            #autoExpand = true;
            #expandBy = 100;

            halt(someInput = null) {
                if (someInput !== null) {
                    return new ConnectorHalt(someInput);
                }
                return new ConnectorHalt();
            }

            isConnectorHalt(someInput) {
                return someInput instanceof ConnectorHalt;
            }

            isHalted(someInput) {
                return this.isConnectorHalt(someInput);
            }

            constructor(dotAppInstance, settings = {}) {
                this.#dotAppInstance = dotAppInstance;
                const elements = dotAppInstance.getElements();
                if (elements.length !== 1) {
                    throw new Error('Connector requires exactly one HTMLElement as the parent element');
                }
                this.#parent = elements[0];
                this.#connectionRules = settings.rules || {};
                this.#nodeRules = settings.nodeRules || {};
                this.#gridEnabled = settings.grid || false;
                this.#gridX = settings.gridX || 10;
                this.#gridY = settings.gridY || 10;
                this.#autoExpand = settings.autoExpand !== undefined ? settings.autoExpand : true;
                this.#expandBy = settings.expandBy || 100;
                this.#initializeCanvas();
                this.#setupEventListeners();
            }

            #initializeCanvas() {
                this.#parent.style.position = 'relative';
                this.#parent.style.overflow = 'auto';
                this.#parent.style.resize = 'both';
                this.#svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                this.#svg.setAttribute('id', 'connections');
                this.#svg.style.position = 'absolute';
                this.#svg.style.top = '0';
                this.#svg.style.left = '0';
                this.#svg.style.width = '100%';
                this.#svg.style.height = '100%';
                this.#svg.style.pointerEvents = 'none';
                this.#parent.appendChild(this.#svg);
                this.#initialWidth = this.#parent.offsetWidth || 800;
                this.#initialHeight = this.#parent.offsetHeight || 600;
                this.#lastUserWidth = this.#initialWidth;
                this.#lastUserHeight = this.#initialHeight;
                this.#parent.style.width = `${this.#initialWidth}px`;
                this.#parent.style.height = `${this.#initialHeight}px`;
                if (this.#gridEnabled) {
                    this.#drawGrid();
                    this.#svg.querySelector('#grid').classList.add('hidden');
                }
            }

            #drawGrid() {
                let group = this.#svg.querySelector('#grid');
                if (!group) {
                    group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                    group.setAttribute('id', 'grid');
                    this.#svg.appendChild(group);
                }
                group.innerHTML = '';
                const canvasWidth = this.#parent.offsetWidth;
                const canvasHeight = this.#parent.offsetHeight;

                for (let x = 0; x <= canvasWidth; x += this.#gridX) {
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', x);
                    line.setAttribute('y1', 0);
                    line.setAttribute('x2', x);
                    line.setAttribute('y2', canvasHeight);
                    line.setAttribute('stroke', '#e0e0e0');
                    line.setAttribute('stroke-width', '1');
                    line.setAttribute('stroke-opacity', '0.5');
                    line.style.pointerEvents = 'none';
                    group.appendChild(line);
                }

                for (let y = 0; y <= canvasHeight; y += this.#gridY) {
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', 0);
                    line.setAttribute('y1', y);
                    line.setAttribute('x2', canvasWidth);
                    line.setAttribute('y2', y);
                    line.setAttribute('stroke', '#e0e0e0');
                    line.setAttribute('stroke-width', '1');
                    line.setAttribute('stroke-opacity', '0.5');
                    line.style.pointerEvents = 'none';
                    group.appendChild(line);
                }

                if (!this.#isDragging) {
                    group.classList.add('hidden');
                } else {
                    group.classList.remove('hidden');
                }
            }

            #initializeNodes() {
                this.#nodes.forEach(node => {
                    node.element.addEventListener('mousedown', this.#startDragging.bind(this));
                    node.element.querySelectorAll('.port').forEach(port => {
                        port.addEventListener('mousedown', this.#startConnection.bind(this));
                        port.addEventListener('contextmenu', this.#handlePortContextMenu.bind(this));
                    });
                });
                this.#updateCanvasSize();
                this.#updateConnections();
            }

            #snapToGrid(value, gridSize) {
                return Math.round(value / gridSize) * gridSize;
            }

            #startDragging(e) {
                if (e.target.classList.contains('port') || e.target.classList.contains('close-button') || !e.target.classList.contains('node-header')) return;
                this.#isDragging = true;
                const node = e.target.closest('.node');
                const canvasRect = this.#parent.getBoundingClientRect();
                const rect = node.getBoundingClientRect();
                const offsetX = e.clientX - rect.left + canvasRect.left;
                const offsetY = e.clientY - rect.top + canvasRect.top;

                if (this.#gridEnabled) {
                    this.#drawGrid();
                }

                const onMouseMove = (e) => {
                    let newX = e.clientX - offsetX;
                    let newY = e.clientY - offsetY;
                    newX = Math.max(0, Math.min(newX, canvasRect.width - node.offsetWidth));
                    newY = Math.max(0, Math.min(newY, canvasRect.height - node.offsetHeight));
                    if (this.#gridEnabled) {
                        newX = this.#snapToGrid(newX, this.#gridX);
                        newY = this.#snapToGrid(newY, this.#gridY);
                    }
                    node.style.left = newX + 'px';
                    node.style.top = newY + 'px';
                    this.#updateConnections();
                };

                const onMouseUp = () => {
                    this.#isDragging = false;
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                    this.#updateCanvasSize();
                    if (this.#gridEnabled) {
                        this.#drawGrid();
                    }
                };

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            }

            #startConnection(e) {
                e.preventDefault();
                if (!this.#isConnecting) {
                    this.#isConnecting = true;
                    this.#startPort = e.target;
                    const existingConnection = this.#connections.find(conn =>
                        conn.start === this.#startPort.id || conn.end === this.#startPort.id
                    );
                    if (existingConnection) {
                        this.#removedConnection = existingConnection;
                        this.#connections = this.#connections.filter(conn => conn.id !== existingConnection.id);
                        this.#updateConnections();
                    } else {
                        this.#removedConnection = null;
                    }
                    this.#tempLine = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    this.#tempLine.setAttribute('class', 'temp-connection');
                    this.#svg.appendChild(this.#tempLine);
                    document.addEventListener('mousemove', this.#drawTempConnection.bind(this));
                    document.addEventListener('mouseup', this.#endConnection.bind(this));
                }
            }

            #drawTempConnection(e) {
                if (this.#isConnecting && this.#startPort) {
                    const startRect = this.#startPort.getBoundingClientRect();
                    const canvasRect = this.#parent.getBoundingClientRect();
                    const x1 = startRect.left + startRect.width / 2 - canvasRect.left;
                    const y1 = startRect.top + startRect.height / 2 - canvasRect.top;
                    const x2 = e.clientX - canvasRect.left;
                    const y2 = e.clientY - canvasRect.top;
                    const pathData = `M${x1},${y1} C${x1 + 50},${y1} ${x2 - 50},${y2} ${x2},${y2}`;
                    this.#tempLine.setAttribute('d', pathData);
                }
            }

            #endConnection(e) {
                if (this.#isConnecting) {
                    if (e.target.classList.contains('port') && e.target !== this.#startPort) {
                        const endPort = e.target;
                        let startId = this.#startPort.id;
                        let endId = endPort.id;

                        let isForward = (this.#startPort.classList.contains('output') && endPort.classList.contains('input'));
                        let isReverse = (this.#startPort.classList.contains('input') && endPort.classList.contains('output'));

                        if (!isForward && !isReverse) {
                            console.warn('Connections must be between an output and an input port.');
                            this.#cleanupConnection();
                            return;
                        }

                        if (isReverse) {
                            [startId, endId] = [endId, startId];
                        }

                        if (endPort.classList.contains('input') || (isReverse && this.#startPort.classList.contains('input'))) {
                            const targetInputId = isReverse ? startId : endId;
                            const existingConnection = this.#connections.find(conn => conn.end === targetInputId);
                            if (existingConnection) {
                                console.warn(`Input port ${targetInputId} already has a connection. Only one connection per input is allowed.`);
                                this.#cleanupConnection();
                                return;
                            }
                        }

                        const isStartAllowed = !this.#connectionRules[startId] || this.#connectionRules[startId].includes(endId);
                        const isEndAllowed = !this.#connectionRules[endId] || this.#connectionRules[endId].includes(startId);
                        const isPortAllowed = isStartAllowed && isEndAllowed;

                        const startNode = this.#nodes.find(n => n.outputs.includes(startId) || n.inputs.some(i => i.id === startId));
                        const endNode = this.#nodes.find(n => n.outputs.includes(endId) || n.inputs.some(i => i.id === endId));
                        const isNodeAllowed = !this.#nodeRules[startNode?.element.id] || this.#nodeRules[startNode?.element.id].includes(endNode?.element.id);

                        const isDuplicate = this.#connections.some(conn =>
                            (conn.start === startId && conn.end === endId)
                        );

                        if (isPortAllowed && isNodeAllowed && !isDuplicate) {
                            const newConnection = {
                                start: startId,
                                end: endId,
                                id: `conn-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`
                            };
                            this.#connections.push(newConnection);
                            this.#updateConnections();

                            const eventDetail = {
                                action: 'added',
                                connection: newConnection,
                                startNodeId: startNode?.element.id,
                                endNodeId: endNode?.element.id,
                                startPortId: startId,
                                endPortId: endId
                            };
                            const event = new CustomEvent('connector:connectionchange', { detail: eventDetail });
                            this.#parent.dispatchEvent(event);

                            this.#triggerEvent('connectionchange', eventDetail);
                            if (startNode) this.#triggerEvent(`connectionchange:${startNode.element.id}`, eventDetail);
                            if (endNode) this.#triggerEvent(`connectionchange:${endNode.element.id}`, eventDetail);
                        } else {
                            if (!isPortAllowed) {
                                console.warn(`Connection ${startId} -> ${endId} is not allowed according to port rules.`);
                            } else if (!isNodeAllowed) {
                                console.warn(`Connection between nodes ${startNode?.element.id} -> ${endNode?.element.id} is not allowed according to node rules.`);
                            } else if (isDuplicate) {
                                console.warn(`Connection ${startId} -> ${endId} already exists.`);
                            }
                        }
                    } else if (this.#removedConnection) {
                        const startNode = this.#nodes.find(n => n.outputs.includes(this.#removedConnection.start) || n.inputs.some(i => i.id === this.#removedConnection.start));
                        const endNode = this.#nodes.find(n => n.outputs.includes(this.#removedConnection.end) || n.inputs.some(i => i.id === this.#removedConnection.end));
                        const eventDetail = {
                            action: 'removed',
                            connection: this.#removedConnection,
                            startNodeId: startNode?.element.id,
                            endNodeId: endNode?.element.id,
                            startPortId: this.#removedConnection.start,
                            endPortId: this.#removedConnection.end
                        };
                        const event = new CustomEvent('connector:connectionchange', { detail: eventDetail });
                        this.#parent.dispatchEvent(event);

                        this.#triggerEvent('connectionchange', eventDetail);
                        if (startNode) this.#triggerEvent(`connectionchange:${startNode.element.id}`, eventDetail);
                        if (endNode) this.#triggerEvent(`connectionchange:${endNode.element.id}`, eventDetail);
                    }
                    this.#cleanupConnection();
                }
            }

            #cleanupConnection() {
                this.#isConnecting = false;
                this.#startPort = null;
                this.#removedConnection = null;
                if (this.#tempLine) {
                    this.#tempLine.remove();
                    this.#tempLine = null;
                }
                document.removeEventListener('mousemove', this.#drawTempConnection);
                document.removeEventListener('mouseup', this.#endConnection);
                this.#updateConnections();
            }

            #handleConnectionContextMenu(e) {
                e.preventDefault();
                const path = e.target;
                const connId = path.getAttribute('data-id');
                const connection = this.#connections.find(conn => conn.id === connId);
                if (connection && confirm(`Do you want to remove the connection ${connection.start} -> ${connection.end}?`)) {
                    this.#connections = this.#connections.filter(conn => conn.id !== connId);
                    this.#updateConnections();

                    const startNode = this.#nodes.find(n => n.outputs.includes(connection.start) || n.inputs.some(i => i.id === connection.start));
                    const endNode = this.#nodes.find(n => n.outputs.includes(connection.end) || n.inputs.some(i => i.id === connection.end));
                    const eventDetail = {
                        action: 'removed',
                        connection,
                        startNodeId: startNode?.element.id,
                        endNodeId: endNode?.element.id,
                        startPortId: connection.start,
                        endPortId: connection.end
                    };
                    const event = new CustomEvent('connector:connectionchange', { detail: eventDetail });
                    this.#parent.dispatchEvent(event);

                    this.#triggerEvent('connectionchange', eventDetail);
                    if (startNode) this.#triggerEvent(`connectionchange:${startNode.element.id}`, eventDetail);
                    if (endNode) this.#triggerEvent(`connectionchange:${endNode.element.id}`, eventDetail);
                }
            }

            #handlePortContextMenu(e) {
                e.preventDefault();
                const port = e.target;
                const portId = port.id;
                const connectionsToRemove = this.#connections.filter(conn =>
                    conn.start === portId || conn.end === portId
                );
                if (connectionsToRemove.length > 0) {
                    if (confirm(`Do you want to remove ${connectionsToRemove.length} connections for port ${portId}?`)) {
                        this.#connections = this.#connections.filter(conn =>
                            conn.start !== portId && conn.end !== portId
                        );
                        this.#updateConnections();

                        connectionsToRemove.forEach(connection => {
                            const startNode = this.#nodes.find(n => n.outputs.includes(connection.start) || n.inputs.some(i => i.id === connection.start));
                            const endNode = this.#nodes.find(n => n.outputs.includes(connection.end) || n.inputs.some(i => i.id === connection.end));
                            const eventDetail = {
                                action: 'removed',
                                connection,
                                startNodeId: startNode?.element.id,
                                endNodeId: endNode?.element.id,
                                startPortId: connection.start,
                                endPortId: connection.end
                            };
                            const event = new CustomEvent('connector:connectionchange', { detail: eventDetail });
                            this.#parent.dispatchEvent(event);

                            this.#triggerEvent('connectionchange', eventDetail);
                            if (startNode) this.#triggerEvent(`connectionchange:${startNode.element.id}`, eventDetail);
                            if (endNode) this.#triggerEvent(`connectionchange:${endNode.element.id}`, eventDetail);
                        });
                    }
                }
            }

            on(...args) {
                let nodeId, triggername, handler, eventKey;
                if (args.length === 3) {
                    [nodeId, triggername, handler] = args;
                    eventKey = `${triggername}:${nodeId}`;
                } else if (args.length === 2) {
                    [triggername, handler] = args;
                    eventKey = triggername;
                } else {
                    throw new Error('Invalid arguments for on method. Use on(triggername, handler) or on(nodeID, triggername, handler).');
                }

                if (!this.#eventHandlers[eventKey]) {
                    this.#eventHandlers[eventKey] = [];
                }
                this.#eventHandlers[eventKey].push(handler);

                return () => {
                    this.#eventHandlers[eventKey] = this.#eventHandlers[eventKey].filter(h => h !== handler);
                };
            }

            #triggerEvent(event, detail) {
                if (this.#eventHandlers[event]) {
                    this.#eventHandlers[event].forEach(handler => handler(detail));
                }
            }

            trigger(event, detail) {
                this.#triggerEvent(event, detail);
            }

            #updateConnections() {
                this.#svg.innerHTML = '';
                if (this.#gridEnabled) {
                    this.#drawGrid();
                }
                this.#connections.forEach(conn => {
                    const startPort = document.getElementById(conn.start);
                    const endPort = document.getElementById(conn.end);
                    if (startPort && endPort) {
                        const startRect = startPort.getBoundingClientRect();
                        const endRect = endPort.getBoundingClientRect();
                        const canvasRect = this.#parent.getBoundingClientRect();
                        const x1 = startRect.left + startRect.width / 2 - canvasRect.left;
                        const y1 = startRect.top + startRect.height / 2 - canvasRect.top;
                        const x2 = endRect.left + endRect.width / 2 - canvasRect.left;
                        const y2 = endRect.top + endRect.height / 2 - canvasRect.top;
                        const cx1 = x1 + 50;
                        const cx2 = x2 - 50;
                        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                        const pathData = `M${x1},${y1} C${cx1},${y1} ${cx2},${y2} ${x2},${y2}`;
                        path.setAttribute('d', pathData);
                        path.setAttribute('class', 'connection');
                        path.setAttribute('data-id', conn.id);
                        path.style.pointerEvents = 'stroke';
                        path.addEventListener('contextmenu', this.#handleConnectionContextMenu.bind(this));
                        this.#svg.appendChild(path);
                    }
                });
                if (this.#tempLine) {
                    this.#svg.appendChild(this.#tempLine);
                }
            }

            #updateCanvasSize() {
                let maxWidth = this.#initialWidth;
                let maxHeight = this.#initialHeight;
                const margin = 50;
                const edgeThreshold = 50;

                this.#nodes.forEach(node => {
                    const rect = node.element.getBoundingClientRect();
                    const right = parseFloat(node.element.style.left) + rect.width;
                    const bottom = parseFloat(node.element.style.top) + rect.height;
                    maxWidth = Math.max(maxWidth, right + margin);
                    maxHeight = Math.max(maxHeight, bottom + margin);

                    if (this.#autoExpand) {
                        if (right + edgeThreshold >= this.#parent.offsetWidth) {
                            maxWidth += this.#expandBy;
                        }
                        if (bottom + edgeThreshold >= this.#parent.offsetHeight) {
                            maxHeight += this.#expandBy;
                        }
                    }
                });

                maxWidth = Math.max(maxWidth, this.#initialWidth, this.#lastUserWidth);
                maxHeight = Math.max(maxHeight, this.#initialHeight, this.#lastUserHeight);

                this.#parent.style.width = `${maxWidth}px`;
                this.#parent.style.height = `${maxHeight}px`;

                const resizeObserver = new ResizeObserver((entries) => {
                    for (const entry of entries) {
                        if (entry.target === this.#parent) {
                            const { width, height } = entry.contentRect;
                            this.#lastUserWidth = Math.max(width, this.#initialWidth);
                            this.#lastUserHeight = Math.max(height, this.#initialHeight);
                        }
                    }
                });
                resizeObserver.observe(this.#parent);
            }

            #setupSidebarDragAndDrop() {
                if (!this.#sidebar) return;

                this.#parent.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'copy';
                });

                this.#parent.addEventListener('drop', (e) => {
                    e.preventDefault();
                    if (this.#draggedNodeConfig) {
                        const canvasRect = this.#parent.getBoundingClientRect();
                        let x = e.clientX - canvasRect.left;
                        let y = e.clientY - canvasRect.top;
                        if (this.#gridEnabled) {
                            x = this.#snapToGrid(x, this.#gridX);
                            y = this.#snapToGrid(y, this.#gridY);
                        }
                        this.#addNodeFromConfig(this.#draggedNodeConfig, x, y);

                        const nodeIndex = e.dataTransfer.getData('nodeIndex');
                        if (nodeIndex !== '') {
                            this.#premadeNodes.splice(parseInt(nodeIndex), 1);
                            this.#renderSidebar();
                        }

                        this.#draggedNodeConfig = null;
                        this.#updateCanvasSize();
                    }
                });
            }

            #addNodeFromConfig(config, x = 50, y = 50) {
                if (this.#nodes.some(node => node.element.id === config.id)) {
                    throw new Error(`Node with ID "${config.id}" already exists.`);
                }

                const newInputs = config.inputs.map((input, index) => ({
                    id: input.id || `input-${index}-${config.id}`,
                    required: input.required || false
                }));
                const newOutputs = config.outputs.map((output, index) => ({
                    id: output.id || `output-${index}-${config.id}`
                }));

                const allPortIds = this.#nodes.flatMap(node => [...node.inputs.map(i => i.id), ...node.outputs]);
                const newPortIds = [...newInputs.map(i => i.id), ...newOutputs];
                const duplicates = newPortIds.filter(id => allPortIds.includes(id));
                if (duplicates.length > 0) {
                    throw new Error(`Duplicate port IDs: ${duplicates.join(', ')}`);
                }

                if (this.#gridEnabled) {
                    x = this.#snapToGrid(x, this.#gridX);
                    y = this.#snapToGrid(y, this.#gridY);
                }

                const nodeConfig = {
                    title: config.title,
                    id: config.id,
                    content: config.content,
                    inputs: newInputs,
                    outputs: newOutputs,
                    x: x,
                    y: y,
                    type: config.inputs.length === 0 ? 'source' : (config.outputs.length === 0 ? 'sink' : 'processor'),
                    callback: config.callback || (() => null),
                    onSave: config.onSave || null,
                    onLoad: config.onLoad || null,
                    customParameters: config.customParameters || {},
                    originalConfig: { ...config }
                };

                this.addNode(nodeConfig);
                return this;
            }

            #renderSidebar() {
                if (!this.#sidebar) return;

                this.#sidebar.innerHTML = '';
                this.#sidebar.style.display = 'flex';
                this.#sidebar.style.flexDirection = 'column';
                this.#sidebar.style.gap = '10px';
                this.#sidebar.style.padding = '10px';
                this.#sidebar.style.maxHeight = '100vh';
                this.#sidebar.style.overflowY = 'auto';

                this.#premadeNodes.forEach((nodeConfig, index) => {
                    const node = document.createElement('div');
                    node.className = 'node sidebar-node';
                    node.draggable = true;
                    node.style.cursor = 'grab';
                    node.style.opacity = '0.8';
                    node.style.transform = 'scale(0.9)';
                    node.style.transition = 'all 0.3s ease';

                    node.innerHTML = `
                        <div class="node-header">${nodeConfig.title}</div>
                        <div class="content">${nodeConfig.content}</div>
                    `;

                    nodeConfig.inputs.forEach((input, i) => {
                        const port = document.createElement('span');
                        port.className = 'port input';
                        port.style.top = `${(i + 1) * (80 / (nodeConfig.inputs.length + 1)) + 20}%`;
                        port.style.left = '-6px';
                        port.style.transform = 'translateY(-50%)';
                        port.style.pointerEvents = 'none';
                        node.appendChild(port);
                    });

                    nodeConfig.outputs.forEach((output, i) => {
                        const port = document.createElement('span');
                        port.className = 'port output';
                        port.style.top = `${(i + 1) * (80 / (nodeConfig.outputs.length + 1)) + 20}%`;
                        port.style.right = '-6px';
                        port.style.transform = 'translateY(-50%)';
                        port.style.pointerEvents = 'none';
                        node.appendChild(port);
                    });

                    node.addEventListener('dragstart', (e) => {
                        this.#draggedNodeConfig = nodeConfig;
                        e.target.style.opacity = '0.5';
                        e.dataTransfer.effectAllowed = 'copy';
                        e.dataTransfer.setData('nodeIndex', index);
                    });

                    node.addEventListener('dragend', (e) => {
                        e.target.style.opacity = '0.8';
                    });

                    node.addEventListener('mouseenter', () => {
                        node.style.opacity = '1';
                        node.style.transform = 'scale(1)';
                    });

                    node.addEventListener('mouseleave', () => {
                        node.style.opacity = '0.8';
                        node.style.transform = 'scale(0.9)';
                    });

                    this.#sidebar.appendChild(node);
                });
            }

            addNode(config = {}) {
                const {
                    title = `Node ${this.#nodes.length + 1}`,
                    id = `node-${this.#nodes.length + 1}`,
                    content = 'Text',
                    inputs = [{ id: `input-${this.#nodes.length + 1}-1`, required: false }],
                    outputs = [{ id: `output-${this.#nodes.length + 1}-1` }],
                    x = 50,
                    y = 50 + this.#nodes.length * 120,
                    type = inputs.length === 0 ? 'source' : (outputs.length === 0 ? 'sink' : 'processor'),
                    callback = () => null,
                    onSave = null,
                    onLoad = null,
                    customParameters = {},
                    originalConfig = null
                } = config;

                if (this.#nodes.some(node => node.element.id === id)) {
                    throw new Error(`Node ID "${id}" already exists.`);
                }

                const allPortIds = this.#nodes.flatMap(node => [...node.inputs.map(i => i.id), ...node.outputs]);
                const newInputIds = inputs.map(input => input.id);
                const newOutputIds = outputs.map(output => output.id);
                const duplicatePorts = [...newInputIds, ...newOutputIds].filter(id => allPortIds.includes(id));
                if (duplicatePorts.length > 0) {
                    throw new Error(`Port IDs "${duplicatePorts.join(', ')}" already exist.`);
                }

                let snappedX = x;
                let snappedY = y;
                if (this.#gridEnabled) {
                    snappedX = this.#snapToGrid(x, this.#gridX);
                    snappedY = this.#snapToGrid(y, this.#gridY);
                }

                const node = document.createElement('div');
                node.className = 'node';
                node.id = id;
                node.style.left = snappedX + 'px';
                node.style.top = snappedY + 'px';
                node.style.position = 'absolute';
                node.style.width = '160px';
                node.style.height = '130px';

                let nodeContent = `
                    <div class="node-header">
                        ${title}
                        <span class="close-button" style="position: absolute; right: 8px; top: 8px; cursor: pointer; font-size: 16px; color: var(--port-hover);">×</span>
                    </div>
                    <div class="content">${content}</div>
                `;
                inputs.forEach((input, i) => {
                    nodeContent += `<span class="port input" id="${input.id}" style="top: ${(i + 1) * (80 / (inputs.length + 1)) + 20}%; left: -6px; transform: translateY(-50%)"></span>`;
                });
                outputs.forEach((output, i) => {
                    nodeContent += `<span class="port output" id="${output.id}" style="top: ${(i + 1) * (80 / (outputs.length + 1)) + 20}%; right: -6px; transform: translateY(-50%)"></span>`;
                });
                node.innerHTML = nodeContent;
                this.#parent.appendChild(node);

                const nodeData = {
                    element: node,
                    inputs,
                    outputs: outputs.map(output => output.id),
                    content,
                    type,
                    callback: (inputs, outputId) => callback(inputs, outputId, nodeData),
                    onSave,
                    onLoad,
                    customParameters: { ...customParameters },
                    originalConfig: originalConfig || {
                        title,
                        id,
                        content,
                        inputs: inputs.map(input => ({ id: input.id, required: input.required })),
                        outputs: outputs.map(output => ({ id: output.id })),
                        customParameters: { ...customParameters }
                    },
                    getCustomParameter: (name) => this.#getCustomParameter(nodeData, name),
                    setCustomParameter: (name, value) => this.#setCustomParameter(nodeData, name, value)
                };
                this.#nodes.push(nodeData);

                const closeButton = node.querySelector('.close-button');
                closeButton.addEventListener('click', () => {
                    const nodePorts = [...inputs.map(i => i.id), ...outputs.map(o => o.id)];
                    this.#connections = this.#connections.filter(conn =>
                        !nodePorts.includes(conn.start) && !nodePorts.includes(conn.end)
                    );

                    this.#nodes = this.#nodes.filter(n => n.element.id !== id);
                    node.remove();

                    const configToAdd = nodeData.originalConfig;
                    if (!this.#premadeNodes.some(n => n.id === configToAdd.id)) {
                        this.#premadeNodes.push(configToAdd);
                    }
                    this.#renderSidebar();

                    this.#updateConnections();
                    this.#updateCanvasSize();
                });

                node.addEventListener('mousedown', this.#startDragging.bind(this));
                node.querySelectorAll('.port').forEach(port => {
                    port.addEventListener('mousedown', this.#startConnection.bind(this));
                    port.addEventListener('contextmenu', this.#handlePortContextMenu.bind(this));
                });

                this.#updateCanvasSize();
                this.#updateConnections();
                return this;
            }

            #getCustomParameter(nodeData, name) {
                return nodeData.customParameters[name];
            }

            #setCustomParameter(nodeData, name, value) {
                nodeData.customParameters[name] = value;
            }

            nodes(settings = {}) {
                const {
                    sidebar = null,
                    nodes = [],
                    autoLoad = true
                } = settings;

                if (sidebar) {
                    if (typeof sidebar === 'string') {
                        this.#sidebar = document.querySelector(sidebar);
                    } else if (sidebar instanceof HTMLElement) {
                        this.#sidebar = sidebar;
                    } else if (sidebar instanceof this.#dotAppInstance.constructor) {
                        const elements = sidebar.getElements();
                        this.#sidebar = elements.length > 0 ? elements[0] : null;
                    }
                }

                this.#premadeNodes = nodes;
                this.#setupSidebarDragAndDrop();
                if (this.#sidebar) {
                    this.#renderSidebar();
                }

                if (autoLoad && nodes.length > 0) {
                    nodes.forEach((nodeConfig, index) => {
                        let x = 50 + (index * 200);
                        let y = 50;
                        if (this.#gridEnabled) {
                            x = this.#snapToGrid(x, this.#gridX);
                            y = this.#snapToGrid(y, this.#gridY);
                        }
                        this.#addNodeFromConfig(nodeConfig, x, y);
                    });
                }

                return this;
            }

            fn(nodeId, callback) {
                const node = this.#nodes.find(n => n.element.id === nodeId);
                if (node) {
                    node.callback = (inputs, outputId) => callback(inputs, outputId, node);
                }
                return this;
            }

            connect(startPort, endPort) {
                const isPortAllowed = !this.#connectionRules[startPort] || this.#connectionRules[startPort].includes(endPort);
                const startNode = this.#nodes.find(n => n.outputs.includes(startPort) || n.inputs.some(i => i.id === startPort));
                const endNode = this.#nodes.find(n => n.outputs.includes(endPort) || n.inputs.some(i => i.id === endPort));
                const isNodeAllowed = !this.#nodeRules[startNode?.element.id] || this.#nodeRules[startNode?.element.id].includes(endNode?.element.id);
                const isDuplicate = this.#connections.some(conn =>
                    (conn.start === startPort && conn.end === endPort)
                );

                if (isPortAllowed && isNodeAllowed && !isDuplicate) {
                    this.#connections.push({
                        start: startPort,
                        end: endPort,
                        id: `conn-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`
                    });
                    this.#updateConnections();
                    return this;
                }
                return false;
            }

            disconnect(portId) {
                this.#connections = this.#connections.filter(conn =>
                    conn.start !== portId && conn.end !== portId
                );
                this.#updateConnections();
                return this;
            }

            clearNodes() {
                this.#nodes.forEach(node => {
                    node.element.remove();
                });
                this.#nodes = [];
                this.#connections = [];
                this.#updateConnections();
                return this;
            }

            evaluateInput(nodeId, inputId, visited = new Set()) {
                const node = this.#nodes.find(n => n.element.id === nodeId);
                if (!node) return { value: null, connectedOutputId: null, isConnected: false };

                const input = node.inputs.find(i => i.id === inputId);
                if (!input) return { value: null, connectedOutputId: null, isConnected: false };

                const connection = this.#connections.find(c => c.end === inputId);
                if (!connection) return { value: null, connectedOutputId: null, isConnected: false };

                const sourceNode = this.#nodes.find(n => n.outputs.includes(connection.start));
                if (!sourceNode) return { value: null, connectedOutputId: null, isConnected: false };

                if (visited.has(sourceNode.element.id)) {
                    console.warn(`Cycle detected while evaluating node ${sourceNode.element.id}`);
                    return { value: new ConnectorHalt('cycle_detected'), connectedOutputId: connection.start, isConnected: true };
                }

                visited.add(sourceNode.element.id);
                const inputs = sourceNode.inputs.map(input => this.evaluateInput(sourceNode.element.id, input.id, new Set(visited)));
                const result = sourceNode.callback(inputs, connection.start);
                visited.delete(sourceNode.element.id);

                return { value: result, connectedOutputId: connection.start, isConnected: true };
            }

            route(endNodeId) {
                const endNode = this.#nodes.find(n => n.element.id === endNodeId);
                if (!endNode || endNode.type !== 'sink') return null;

                const visited = new Set();
                const path = [];

                const buildPath = (nodeId, inputId) => {
                    const node = this.#nodes.find(n => n.element.id === nodeId);
                    if (!node) return null;

                    const nodePath = { nodeId, inputId, connections: [] };
                    if (inputId) {
                        const connection = this.#connections.find(c => c.end === inputId);
                        if (!connection) return nodePath;

                        const sourceNode = this.#nodes.find(n => n.outputs.includes(connection.start));
                        if (!sourceNode) return nodePath;

                        if (visited.has(sourceNode.element.id)) return null;
                        visited.add(sourceNode.element.id);

                        const sourceInputs = sourceNode.inputs.map(input => buildPath(sourceNode.element.id, input.id));
                        nodePath.connections.push({
                            nodeId: sourceNode.element.id,
                            outputId: connection.start,
                            inputs: sourceInputs.filter(p => p !== null)
                        });

                        visited.delete(sourceNode.element.id);
                    } else {
                        node.inputs.forEach(input => {
                            const connection = this.#connections.find(c => c.end === input.id);
                            if (connection) {
                                const sourceNode = this.#nodes.find(n => n.outputs.includes(connection.start));
                                if (sourceNode && !visited.has(sourceNode.element.id)) {
                                    visited.add(sourceNode.element.id);
                                    const sourceInputs = sourceNode.inputs.map(inp => buildPath(sourceNode.element.id, inp.id));
                                    nodePath.connections.push({
                                        nodeId: sourceNode.element.id,
                                        outputId: connection.start,
                                        inputs: sourceInputs.filter(p => p !== null)
                                    });
                                    visited.delete(sourceNode.element.id);
                                }
                            }
                        });
                    }
                    return nodePath;
                };

                const result = buildPath(endNodeId, null);
                if (!result) return null;

                return {
                    path: result,
                    run: () => {
                        const evaluateNode = (nodeId, outputId) => {
                            const node = this.#nodes.find(n => n.element.id === nodeId);
                            if (!node) return new ConnectorHalt('node_not_found');

                            const inputs = node.inputs.map(input => this.evaluateInput(nodeId, input.id));
                            return node.callback(inputs, outputId);
                        };

                        return evaluateNode(endNodeId, null);
                    }
                };
            }

            setRules(rules) {
                if (rules.rules) {
                    this.#connectionRules = { ...rules.rules };
                }
                if (rules.nodeRules) {
                    this.#nodeRules = { ...rules.nodeRules };
                }
                return this;
            }

            getConnections() {
                return this.#connections;
            }

            getNodes() {
                return this.#nodes;
            }

            save() {
                this.#nodes.forEach(node => {
                    if (node.onSave) {
                        node.onSave(node);
                    }
                });

                return {
                    nodes: this.#nodes.map(node => ({
                        id: node.element.id,
                        title: node.element.querySelector('.node-header').innerText.replace('×', '').trim(),
                        content: node.element.querySelector('.content').innerHTML,
                        inputs: node.inputs,
                        outputs: node.outputs,
                        position: {
                            left: node.element.style.left,
                            top: node.element.style.top
                        },
                        type: node.type,
                        customParameters: { ...node.customParameters },
                        originalConfig: node.originalConfig
                    })),
                    connections: this.#connections.map(conn => ({
                        id: conn.id,
                        start: conn.start,
                        end: conn.end
                    })),
                    rules: this.#connectionRules,
                    nodeRules: this.#nodeRules,
                    premadeNodes: this.#premadeNodes,
                    gridSettings: {
                        grid: this.#gridEnabled,
                        gridX: this.#gridX,
                        gridY: this.#gridY
                    },
                    canvasSettings: {
                        initialWidth: this.#initialWidth,
                        initialHeight: this.#initialHeight,
                        lastUserWidth: this.#lastUserWidth,
                        lastUserHeight: this.#lastUserHeight,
                        autoExpand: this.#autoExpand,
                        expandBy: this.#expandBy
                    }
                };
            }

            load(data) {
                this.#nodes = [];
                this.#connections = [];
                this.#connectionRules = data.rules || {};
                this.#nodeRules = data.nodeRules || {};
                this.#premadeNodes = data.premadeNodes || [];
                this.#gridEnabled = data.gridSettings?.grid || false;
                this.#gridX = data.gridSettings?.gridX || 10;
                this.#gridY = data.gridSettings?.gridY || 10;
                this.#initialWidth = data.canvasSettings?.initialWidth || 800;
                this.#initialHeight = data.canvasSettings?.initialHeight || 600;
                this.#lastUserWidth = data.canvasSettings?.lastUserWidth || this.#initialWidth;
                this.#lastUserHeight = data.canvasSettings?.lastUserHeight || this.#initialHeight;
                this.#autoExpand = data.canvasSettings?.autoExpand !== undefined ? data.canvasSettings.autoExpand : true;
                this.#expandBy = data.canvasSettings?.expandBy || 100;
                this.#parent.innerHTML = '';
                this.#initializeCanvas();

                data.nodes.forEach(nodeData => {
                    const node = document.createElement('div');
                    node.className = 'node';
                    node.id = nodeData.id;
                    node.style.left = nodeData.position.left;
                    node.style.top = nodeData.position.top;
                    node.style.position = 'absolute';
                    node.style.width = '160px';
                    node.style.height = '130px';

                    let nodeContent = `
                        <div class="node-header">
                            ${nodeData.title}
                            <span class="close-button" style="position: absolute; right: 8px; top: 8px; cursor: pointer; font-size: 16px; color: var(--port-hover);">×</span>
                        </div>
                        <div class="content">${nodeData.content}</div>
                    `;
                    nodeData.inputs.forEach((input, i) => {
                        nodeContent += `<span class="port input" id="${input.id}" style="top: ${(i + 1) * (80 / (nodeData.inputs.length + 1)) + 20}%; left: -6px; transform: translateY(-50%)"></span>`;
                    });
                    nodeData.outputs.forEach((outputId, i) => {
                        nodeContent += `<span class="port output" id="${outputId}" style="top: ${(i + 1) * (80 / (nodeData.outputs.length + 1)) + 20}%; right: -6px; transform: translateY(-50%)"></span>`;
                    });
                    node.innerHTML = nodeContent;
                    this.#parent.appendChild(node);

                    const nodeDataConfig = {
                        element: node,
                        inputs: nodeData.inputs,
                        outputs: nodeData.outputs,
                        content: nodeData.content,
                        type: nodeData.type,
                        callback: (inputs, outputId) => {
                            const originalCallback = nodeData.originalConfig.callback || (() => null);
                            return originalCallback(inputs, outputId, nodeDataConfig);
                        },
                        onSave: nodeData.originalConfig.onSave || null,
                        onLoad: nodeData.originalConfig.onLoad || null,
                        customParameters: { ...nodeData.customParameters },
                        originalConfig: nodeData.originalConfig || {
                            title: nodeData.title,
                            id: nodeData.id,
                            content: nodeData.content,
                            inputs: nodeData.inputs.map(input => ({ id: input.id, required: input.required })),
                            outputs: nodeData.outputs.map(id => ({ id: id })),
                            customParameters: { ...nodeData.customParameters }
                        },
                        getCustomParameter: (name) => this.#getCustomParameter(nodeDataConfig, name),
                        setCustomParameter: (name, value) => this.#setCustomParameter(nodeDataConfig, name, value)
                    };
                    this.#nodes.push(nodeDataConfig);

                    const closeButton = node.querySelector('.close-button');
                    closeButton.addEventListener('click', () => {
                        const nodePorts = [...nodeData.inputs.map(i => i.id), ...nodeData.outputs];
                        this.#connections = this.#connections.filter(conn =>
                            !nodePorts.includes(conn.start) && !nodePorts.includes(conn.end)
                        );

                        this.#nodes = this.#nodes.filter(n => n.element.id !== nodeData.id);
                        node.remove();

                        const configToAdd = nodeDataConfig.originalConfig;
                        if (!this.#premadeNodes.some(n => n.id === configToAdd.id)) {
                            this.#premadeNodes.push(configToAdd);
                        }
                        this.#renderSidebar();

                        this.#updateConnections();
                        this.#updateCanvasSize();
                    });

                    if (nodeDataConfig.onLoad) {
                        nodeDataConfig.onLoad(nodeDataConfig);
                    }
                });

                this.#connections = data.connections.map((conn, index) => ({
                    start: conn.start,
                    end: conn.end,
                    id: conn.id || `conn-${index}-${Math.random().toString(36).slice(2, 9)}`
                }));

                this.#initializeNodes();
                if (this.#sidebar) {
                    this.#renderSidebar();
                }

                return this;
            }

            #setupEventListeners() {
                this.#parent.addEventListener('dotapp-connector-update', () => this.#updateConnections());
                window.addEventListener('resize', () => {
                    this.#updateConnections();
                });
            }
        }

        const connector = function(settings) {
            const instanceKey = '_connector';
            const elements = this.getElements();
            if (elements.length !== 1) {
                throw new Error('Connector must be applied to exactly one element');
            }
            const element = elements[0];
            if (!element[instanceKey]) {
                element[instanceKey] = new DotAppConnector(this, settings);
            }
            return element[instanceKey];
        };

        $dotapp().fn("connector", connector);
        window.dispatchEvent(new Event('dotapp-connector'));
    };

    if (window.$dotapp) {
        runMe(window.$dotapp);
    } else {
        window.addEventListener('dotapp-register', function() {
            runMe(window.$dotapp);
        }, { once: true });
    }
})();