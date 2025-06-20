<?php

class ConnectorHalt {
    public $reason;

    public function __construct($reason = null) {
        $this->reason = $reason;
    }
}

class Node {
    public $id;
    public $title;
    public $content;
    public $inputs = [];
    public $outputs = [];
    public $type;
    public $callback;
    public $customParameters = [];
    public $originalConfig;
    public $location;

    public function __construct($data, $location = 'canvas') {
        $this->id = $data['id'] ?? 'node-' . uniqid();
        $this->title = $data['title'] ?? 'Untitled Node';
        $this->content = $data['content'] ?? '';
        $this->inputs = $data['inputs'] ?? [];
        $this->outputs = $data['outputs'] ?? [];
        $this->type = $data['type'] ?? $this->determineType();
        $this->callback = $data['callback'] ?? null;
        $this->customParameters = $data['customParameters'] ?? [];
        $this->originalConfig = $data['originalConfig'] ?? $data;
        $this->location = $location;
    }

    private function determineType() {
        if (empty($this->inputs)) return 'source';
        if (empty($this->outputs)) return 'sink';
        return 'processor';
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'inputs' => $this->inputs,
            'outputs' => $this->outputs,
            'type' => $this->type,
            'customParameters' => $this->customParameters,
            'originalConfig' => $this->originalConfig,
            'location' => $this->location,
        ];
    }
}

class Connector {
    const CANVAS = 'canvas';
    const SIDEBAR = 'sidebar';

    private $nodes = [];
    private $connections = [];
    private $connectionRules = [];
    private $nodeRules = [];
    private $premadeNodes = [];
    private $gridSettings = [];
    private $canvasSettings = [];

    public function __construct($data = null) {
        if ($data !== null) {
            $this->load($data);
        }
    }

    public function load($data) {
        $this->nodes = [];
        $this->connections = [];
        $this->connectionRules = $data['rules'] ?? [];
        $this->nodeRules = $data['nodeRules'] ?? [];
        $this->gridSettings = $data['gridSettings'] ?? [];
        $this->canvasSettings = $data['canvasSettings'] ?? [];

        foreach ($data['nodes'] ?? [] as $nodeData) {
            $location = $nodeData['location'] ?? self::CANVAS;
            $this->nodes[$nodeData['id']] = new Node($nodeData, $location);
        }

        foreach ($data['connections'] ?? [] as $connData) {
            $this->connections[] = [
                'id' => $connData['id'] ?? 'conn-' . uniqid(),
                'start' => $connData['start'],
                'end' => $connData['end'],
            ];
        }

        $this->premadeNodes = $data['premadeNodes'] ?? [];
        return $this;
    }

    public function save() {
        $nodes = [];
        foreach ($this->nodes as $node) {
            $nodes[] = $node->toArray();
        }

        return [
            'nodes' => $nodes,
            'connections' => $this->connections,
            'rules' => $this->connectionRules,
            'nodeRules' => $this->nodeRules,
            'premadeNodes' => $this->premadeNodes,
            'gridSettings' => $this->gridSettings,
            'canvasSettings' => $this->canvasSettings,
        ];
    }

    public function exportSidebar() {
        return array_filter($this->nodes, function ($node) {
            return $node->location === self::SIDEBAR;
        });
    }

    public function addNode($location, $id, $data) {
        if (isset($this->nodes[$id])) {
            throw new Exception("Node with ID '$id' already exists.");
        }

        $data['id'] = $id;
        $node = new Node($data, $location);
        $this->nodes[$id] = $node;
        return $this;
    }

    public function removeNode($id) {
        if (!isset($this->nodes[$id])) {
            return $this;
        }

        $node = $this->nodes[$id];
        $nodePorts = array_merge(
            array_column($node->inputs, 'id'),
            $node->outputs
        );

        $this->connections = array_filter($this->connections, function ($conn) use ($nodePorts) {
            return !in_array($conn['start'], $nodePorts) && !in_array($conn['end'], $nodePorts);
        });

        unset($this->nodes[$id]);
        return $this;
    }

    public function connect($startPort, $endPort) {
        $startNode = $this->findNodeByPort($startPort, 'output');
        $endNode = $this->findNodeByPort($endPort, 'input');

        if (!$startNode || !$endNode) {
            throw new Exception("Invalid ports: '$startPort' or '$endPort' not found or incorrect type.");
        }

        if ($this->isInput($startPort) || $this->isOutput($endPort)) {
            [$startPort, $endPort] = [$endPort, $startPort];
        }

        if (!$this->isOutput($startPort) || !$this->isInput($endPort)) {
            throw new Exception("Connections must be from output to input.");
        }

        if ($this->connectionExists($startPort, $endPort)) {
            return $this;
        }

        if (!$this->isConnectionAllowed($startNode->id, $endNode->id, $startPort, $endPort)) {
            throw new Exception("Connection between '$startPort' and '$endPort' is not allowed by rules.");
        }

        $this->connections[] = [
            'id' => 'conn-' . uniqid(),
            'start' => $startPort,
            'end' => $endPort,
        ];
        return $this;
    }

    public function disconnect($portId) {
        $this->connections = array_filter($this->connections, function ($conn) use ($portId) {
            return $conn['start'] !== $portId && $conn['end'] !== $portId;
        });
        return $this;
    }

    public function setRules($rules) {
        if (isset($rules['rules'])) {
            $this->connectionRules = $rules['rules'];
        }
        if (isset($rules['nodeRules'])) {
            $this->nodeRules = $rules['nodeRules'];
        }
        return $this;
    }

    public function route($endNodeId) {
        $endNode = $this->nodes[$endNodeId] ?? null;
        if (!$endNode || $endNode->type !== 'sink') {
            return null;
        }

        $visited = [];
        $path = $this->buildPath($endNodeId, null, $visited);

        if (!$path) {
            return null;
        }

        return new class($path, $this) {
            private $path;
            private $connector;

            public function __construct($path, $connector) {
                $this->path = $path;
                $this->connector = $connector;
            }

            public function getPath() {
                return $this->path;
            }

            public function run() {
                return $this->connector->evaluateNode($this->path['nodeId'], null);
            }
        };
    }

    private function buildPath($nodeId, $inputId, &$visited) {
        $node = $this->nodes[$nodeId] ?? null;
        if (!$node) {
            return null;
        }

        $nodePath = ['nodeId' => $nodeId, 'inputId' => $inputId, 'connections' => []];

        if ($inputId) {
            $connection = $this->findConnectionByEnd($inputId);
            if (!$connection) {
                return $nodePath;
            }

            $sourceNode = $this->findNodeByPort($connection['start'], 'output');
            if (!$sourceNode || in_array($sourceNode->id, $visited)) {
                return null;
            }

            $visited[] = $sourceNode->id;
            $sourceInputs = [];
            foreach ($sourceNode->inputs as $input) {
                $subPath = $this->buildPath($sourceNode->id, $input['id'], $visited);
                if ($subPath) {
                    $sourceInputs[] = $subPath;
                }
            }
            $nodePath['connections'][] = [
                'nodeId' => $sourceNode->id,
                'outputId' => $connection['start'],
                'inputs' => $sourceInputs,
            ];
            array_pop($visited);
        } else {
            foreach ($node->inputs as $input) {
                $connection = $this->findConnectionByEnd($input['id']);
                if ($connection) {
                    $sourceNode = $this->findNodeByPort($connection['start'], 'output');
                    if ($sourceNode && !in_array($sourceNode->id, $visited)) {
                        $visited[] = $sourceNode->id;
                        $sourceInputs = [];
                        foreach ($sourceNode->inputs as $subInput) {
                            $subPath = $this->buildPath($sourceNode->id, $subInput['id'], $visited);
                            if ($subPath) {
                                $sourceInputs[] = $subPath;
                            }
                        }
                        $nodePath['connections'][] = [
                            'nodeId' => $sourceNode->id,
                            'outputId' => $connection['start'],
                            'inputs' => $sourceInputs,
                        ];
                        array_pop($visited);
                    }
                }
            }
        }
        return $nodePath;
    }

    public function evaluateInput($nodeId, $inputId, $visited = []) {
        $node = $this->nodes[$nodeId] ?? null;
        if (!$node) {
            return ['value' => null, 'connectedOutputId' => null, 'isConnected' => false];
        }

        $input = array_filter($node->inputs, fn($i) => $i['id'] === $inputId);
        $input = reset($input);
        if (!$input) {
            return ['value' => null, 'connectedOutputId' => null, 'isConnected' => false];
        }

        $connection = $this->findConnectionByEnd($inputId);
        if (!$connection) {
            return ['value' => null, 'connectedOutputId' => null, 'isConnected' => false];
        }

        $sourceNode = $this->findNodeByPort($connection['start'], 'output');
        if (!$sourceNode) {
            return ['value' => null, 'connectedOutputId' => null, 'isConnected' => false];
        }

        if (in_array($sourceNode->id, $visited)) {
            return ['value' => new ConnectorHalt('cycle_detected'), 'connectedOutputId' => $connection['start'], 'isConnected' => true];
        }

        $visited[] = $sourceNode->id;
        $inputs = array_map(fn($input) => $this->evaluateInput($sourceNode->id, $input['id'], $visited), $sourceNode->inputs);
        $result = $sourceNode->callback ? call_user_func($sourceNode->callback, $inputs, $connection['start']) : null;
        array_pop($visited);

        return ['value' => $result, 'connectedOutputId' => $connection['start'], 'isConnected' => true];
    }

    public function evaluateNode($nodeId, $outputId) {
        $node = $this->nodes[$nodeId] ?? null;
        if (!$node) {
            return new ConnectorHalt('node_not_found');
        }

        $inputs = array_map(fn($input) => $this->evaluateInput($nodeId, $input['id']), $node->inputs);
        return $node->callback ? call_user_func($node->callback, $inputs, $outputId) : null;
    }

    private function findNodeByPort($portId, $type) {
        foreach ($this->nodes as $node) {
            if ($type === 'output' && in_array($portId, $node->outputs)) {
                return $node;
            }
            if ($type === 'input' && array_filter($node->inputs, fn($i) => $i['id'] === $portId)) {
                return $node;
            }
        }
        return null;
    }

    private function isOutput($portId) {
        foreach ($this->nodes as $node) {
            if (in_array($portId, $node->outputs)) {
                return true;
            }
        }
        return false;
    }

    private function isInput($portId) {
        foreach ($this->nodes as $node) {
            if (array_filter($node->inputs, fn($i) => $i['id'] === $portId)) {
                return true;
            }
        }
        return false;
    }

    private function connectionExists($startPort, $endPort) {
        return !empty(array_filter($this->connections, fn($conn) => $conn['start'] === $startPort && $conn['end'] === $endPort));
    }

    private function isConnectionAllowed($startNodeId, $endNodeId, $startPort, $endPort) {
        $portRules = $this->connectionRules[$startPort] ?? null;
        if ($portRules && !in_array($endPort, $portRules)) {
            return false;
        }

        $nodeRules = $this->nodeRules[$startNodeId] ?? null;
        if ($nodeRules && !in_array($endNodeId, $nodeRules)) {
            return false;
        }

        return true;
    }

    private function findConnectionByEnd($endPort) {
        foreach ($this->connections as $conn) {
            if ($conn['end'] === $endPort) {
                return $conn;
            }
        }
        return null;
    }

    public function getNodes() {
        return $this->nodes;
    }

    public function getConnections() {
        return $this->connections;
    }
}

?>