<?php

/*
    Najprv boli triedy ako entity a collection anonymne. Vytahujem ich z kodu pred vypustenim na github
    Tu budu. new class($attributes = [], $db = null) sa meni na Entity($attributes = [], $db = null)
    Preco boli doteraz ako anonymne? Pretoze driver je osobitna funkcia, takze boli definovane vnutri drivera aby definicia drivera obsahovala priamo aj triedy.
    Teda aby to bola jedna velka nezavisla funkcia. Kedze su ale tieto 2 drivery vstavane, tak som zmenil pristup k tomu aby to bolo vsetko uzavrete
    do jednej funkcie a vytiahol som triedy Entity a Collection von.
*/
namespace Dotsystems\App\Parts;

class Entity {
    private $attributes;
    private $originalAttributes;
    private $db;
    private $table = 'unknown_table';
    private $primaryKey = 'id';
    private $rules = [];
    private $relations = [];
    private $with = []; // Pre eager loading
    private $morphRelations = []; // Pre polymorfné vzťahy

    public function __construct(array $attributes, $db) {
        $this->attributes = $attributes;
        $this->originalAttributes = $attributes;
        $this->db = $db;
        $this->table = $db->statement['table'] ?? $this->guessTableName();
    }

    private function guessTableName() {
        if ($this->db instanceof \Dotsystems\App\Parts\Databaser) {
            return $this->db->statement['table'] ?? 'unknown_table';
        }
        if ($this->db instanceof \Dotsystems\App\Parts\DI && $this->db->getTarget() instanceof \Dotsystems\App\Parts\Databaser ) {
            return $this->db->getTarget()->statement['table'] ?? 'unknown_table';
        }
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower($className) . 's'; // Napr. User -> users
    }

    public function with($relations) {
        $this->with = is_array($relations) ? $relations : [$relations];
        return $this;
    }

    public function loadRelations() {
        foreach ($this->with as $relation) {
            if (method_exists($this, $relation)) {
                $this->$relation();
            }
        }
    }

    public function __get($key) {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }
        return $this->attributes[$key] ?? null;
    }

    public function __set($key, $value) {
        $this->attributes[$key] = $value;
    }

    // Niekedy moze byt v tabulke stlpec co zacina na cislo... Vtedy musime pouzit tento getter.
    public function get($key) {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }
        return $this->attributes[$key] ?? null;
    }

    // Niekedy moze byt v tabulke stlpec co zacina na cislo... Vtedy musime pouzit tento setter.
    public function set($key,$value) {
        $this->attributes[$key] = $value;
    }

    public function table($tablename) {
        $tablename = preg_replace('/[^a-zA-Z0-9_]/', '', $tablename);
        $this->table = "`".$tablename."`";
    }

    public function setPrimaryKey($key) {
        $this->primaryKey = $key;
        return $this;
    }

    public function setRules(array $rules) {
        $this->rules = $rules;
        return $this;
    }

    private function validate() {
        foreach ($this->rules as $field => $rule) {
            $value = $this->attributes[$field] ?? null;
            $rules = is_array($rule) ? $rule : [$rule];
            foreach ($rules as $r) {
                if ($r === 'required' && (is_null($value) || $value === '')) {
                    throw new \Exception("Pole '$field' je povinné.");
                }
                if ($r === 'integer' && !is_null($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    throw new \Exception("Pole '$field' musí byť celé číslo.");
                }
                if ($r === 'string' && !is_null($value) && !is_string($value)) { // Doplnené
                    throw new \Exception("Pole '$field' musí byť reťazec.");
                }
                if (preg_match('/^min:(\d+)$/', $r, $matches) && !is_null($value) && strlen($value) < $matches[1]) {
                    throw new \Exception("Pole '$field' musí mať minimálne {$matches[1]} znakov.");
                }
                if (preg_match('/^max:(\d+)$/', $r, $matches) && !is_null($value) && strlen($value) > $matches[1]) { // Doplnené
                    throw new \Exception("Pole '$field' môže mať maximálne {$matches[1]} znakov.");
                }
                if ($r === 'email' && !is_null($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) { // Doplnené
                    throw new \Exception("Pole '$field' musí byť platná emailová adresa.");
                }
            }
        }
        return true;
    }

    public function save($callback_ok = null, $callback_error = null) {
        $changes = array_diff_assoc($this->attributes, $this->originalAttributes);
        if (empty($changes)) {
            if (is_callable($callback_ok)) $callback_ok([], $this->db, []);
            return;
        }

        $this->validate();

        $this->db->q(function ($qb) use ($changes) {
            if (isset($this->attributes[$this->primaryKey])) {
                $qb->update($this->table)->set($changes)->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
            } else {
                $qb->insert($this->table, $changes);
            }
        })->execute(
            function ($result, $db, $execution_data) use ($callback_ok) {
                if (!isset($this->attributes[$this->primaryKey])) {
                    $this->attributes[$this->primaryKey] = $execution_data['insert_id'];
                }
                $this->originalAttributes = $this->attributes;
                $this->loadRelations();
                if (isSet($this->db->cacheDriver) && method_exists($this->db->cacheDriver, 'deleteKeys')) {
                    $this->db->cacheDriver->deleteKeys("{$this->table}:{$this->db->getReturnType()}:*");
                } else if (isSet($this->db->cacheDriver)) {
                    throw new \Exception("Method deleteKeys do not exist !");
                }
                if (is_callable($callback_ok)) $callback_ok($result, $db, $execution_data);
            },
            $callback_error
        );

        $this->db->q(function ($qb) {});
    }

    public function hasOne($relatedTable, $foreignKey, $localKey = null, $callback = null) {
        $localKey = $localKey ?? $this->primaryKey;
        $key = "hasOne:{$relatedTable}";
        if (!isset($this->relations[$key]) || in_array($key, $this->with)) {
            $related = $this->db->q(function ($qb) use ($relatedTable, $foreignKey, $localKey, $callback) {
                $qb->select('*', $relatedTable)->where($foreignKey, '=', $this->attributes[$localKey]);
                if ($callback) {
                    $callback($qb);
                }
            })->first();
            $this->relations[$key] = $related;
        }
        return $this->relations[$key];
    }

    public function hasMany($relatedTable, $foreignKey, $localKey = null, $callback = null) {
        $localKey = $localKey ?? $this->primaryKey;
        $key = "hasMany:{$relatedTable}";
        if (!isset($this->relations[$key]) || in_array($key, $this->with)) {
            $related = $this->db->q(function ($qb) use ($relatedTable, $foreignKey, $localKey, $callback) {
                $qb->select('*', $relatedTable)->where($foreignKey, '=', $this->attributes[$localKey]);
                if ($callback) {
                    $callback($qb);
                }
            })->all();
            $this->relations[$key] = $related;
        }
        return $this->relations[$key];
    }

    public function morphOne($relatedTable, $typeField, $idField, $typeValue, $localKey = null, $callback = null) {
        $localKey = $localKey ?? $this->primaryKey;
        $key = "morphOne:{$relatedTable}:{$typeValue}";
        if (!isset($this->relations[$key])) {
            $related = $this->db->q(function ($qb) use ($relatedTable, $typeField, $idField, $typeValue, $localKey, $callback) {
                $qb->select('*', $relatedTable)
                   ->where($typeField, '=', $typeValue)
                   ->where($idField, '=', $this->attributes[$localKey]);
                if ($callback) {
                    $callback($qb);
                }
            })->first();
            $this->relations[$key] = $related;
        }
        return $this->relations[$key];
    }

    public function morphMany($relatedTable, $typeField, $idField, $typeValue, $localKey = null, $callback = null) {
        $localKey = $localKey ?? $this->primaryKey;
        $key = "morphMany:{$relatedTable}:{$typeValue}";
        if (!isset($this->relations[$key])) {
            $related = $this->db->q(function ($qb) use ($relatedTable, $typeField, $idField, $typeValue, $localKey, $callback) {
                $qb->select('*', $relatedTable)
                   ->where($typeField, '=', $typeValue)
                   ->where($idField, '=', $this->attributes[$localKey]);
                    if ($callback) {
                        $callback($qb);
                    }
            })->all();
            $this->relations[$key] = $related;
        }
        return $this->relations[$key];
    }

    public function toArray() {
        return $this->attributes;
    }
}

?>