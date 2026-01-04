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
    private $events = []; // Event callbacks
    private $softDeletes = false; // Soft delete flag
    private $deletedAtColumn = 'deleted_at';

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
        $this->table = $tablename; // Bez backticks - QueryBuilder si ich pridá sám podľa DB typu
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

    public function belongsTo($relatedTable, $foreignKey, $ownerKey = null, $callback = null) {
        $ownerKey = $ownerKey ?? 'id';
        $key = "belongsTo:{$relatedTable}";
        if (!isset($this->relations[$key]) || in_array($key, $this->with)) {
            $related = $this->db->q(function ($qb) use ($relatedTable, $foreignKey, $ownerKey, $callback) {
                $qb->select('*', $relatedTable)->where($ownerKey, '=', $this->attributes[$foreignKey]);
                if ($callback) {
                    $callback($qb);
                }
            })->first();
            $this->relations[$key] = $related;
        }
        return $this->relations[$key];
    }

    public function belongsToMany($relatedTable, $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey = null, $relatedKey = null, $callback = null) {
        $parentKey = $parentKey ?? $this->primaryKey;
        $relatedKey = $relatedKey ?? 'id';
        $key = "belongsToMany:{$relatedTable}";
        if (!isset($this->relations[$key]) || in_array($key, $this->with)) {
            $related = $this->db->q(function ($qb) use ($relatedTable, $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $callback) {
                $qb->select("{$relatedTable}.*", "{$pivotTable}.*")
                   ->from($pivotTable)
                   ->join($relatedTable, "{$pivotTable}.{$relatedPivotKey}", '=', "{$relatedTable}.{$relatedKey}")
                   ->where("{$pivotTable}.{$foreignPivotKey}", '=', $this->attributes[$parentKey]);
                if ($callback) {
                    $callback($qb);
                }
            })->all();
            $this->relations[$key] = $related;
        }
        return $this->relations[$key];
    }

    public function hasManyThrough($throughTable, $relatedTable, $throughForeignKey, $throughLocalKey, $localKey = null, $secondLocalKey = null, $callback = null) {
        $localKey = $localKey ?? $this->primaryKey;
        $secondLocalKey = $secondLocalKey ?? 'id';
        $key = "hasManyThrough:{$relatedTable}";
        if (!isset($this->relations[$key]) || in_array($key, $this->with)) {
            $related = $this->db->q(function ($qb) use ($throughTable, $relatedTable, $throughForeignKey, $throughLocalKey, $localKey, $secondLocalKey, $callback) {
                $qb->select("{$relatedTable}.*")
                   ->from($throughTable)
                   ->join($relatedTable, "{$throughTable}.{$throughLocalKey}", '=', "{$relatedTable}.{$secondLocalKey}")
                   ->where("{$throughTable}.{$throughForeignKey}", '=', $this->attributes[$localKey]);
                if ($callback) {
                    $callback($qb);
                }
            })->all();
            $this->relations[$key] = $related;
        }
        return $this->relations[$key];
    }

    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null) {
        $type = $type ?? $name . '_type';
        $id = $id ?? $name . '_id';
        $ownerKey = $ownerKey ?? 'id';

        if (!isset($this->attributes[$type]) || !isset($this->attributes[$id])) {
            return null;
        }

        $relatedType = $this->attributes[$type];
        $relatedId = $this->attributes[$id];

        // Map class names to table names (simplified)
        $tableName = strtolower(str_replace('\\', '_', $relatedType)) . 's';

        $key = "morphTo:{$name}";
        if (!isset($this->relations[$key]) || in_array($key, $this->with)) {
            $related = $this->db->q(function ($qb) use ($tableName, $ownerKey, $relatedId) {
                $qb->select('*', $tableName)->where($ownerKey, '=', $relatedId);
            })->first();
            $this->relations[$key] = $related;
        }
        return $this->relations[$key];
    }

    public function toArray() {
        return $this->attributes;
    }


    // FIND - statická metóda pre nájdenie podľa ID
    public static function find($db, $id, $callback_ok = null, $callback_error = null) {
        $instance = new static([], $db);
        $instance->db->q(function ($qb) use ($instance, $id) {
            $qb->select('*', $instance->table)->where($instance->primaryKey, '=', $id);
        })->execute(
            function ($result, $db, $execution_data) use ($callback_ok) {
                $entity = null;
                if (!empty($result) && isset($result[0])) {
                    $entity = new static($result[0], $db);
                }
                if (is_callable($callback_ok)) $callback_ok($entity, $db, $execution_data);
            },
            $callback_error
        );

        $instance->db->q(function ($qb) {});
    }

    // CREATE - statická metóda pre vytvorenie novej entity
    public static function create($db, array $attributes, $callback_ok = null, $callback_error = null) {
        $instance = new static($attributes, $db);
        $instance->save($callback_ok, $callback_error);
        return $instance;
    }

    // UPDATE - hromadné update všetkých zmien
    public function update(array $attributes, $callback_ok = null, $callback_error = null) {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        $this->save($callback_ok, $callback_error);
    }

    // IS DIRTY - či má entity neuložené zmeny
    public function isDirty($attribute = null) {
        if ($attribute === null) {
            return !empty(array_diff_assoc($this->attributes, $this->originalAttributes));
        }
        return isset($this->attributes[$attribute]) &&
               isset($this->originalAttributes[$attribute]) &&
               $this->attributes[$attribute] !== $this->originalAttributes[$attribute];
    }

    // GET DIRTY - získa všetky zmenené atribúty
    public function getDirty() {
        return array_diff_assoc($this->attributes, $this->originalAttributes);
    }

    // FRESH - reload z databázy
    public function fresh() {
        if (!isset($this->attributes[$this->primaryKey])) {
            return null;
        }

        $fresh = null;
        $this->db->q(function ($qb) {
            $qb->select('*', $this->table)->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
        })->execute(function ($result) use (&$fresh) {
            if (!empty($result) && isset($result[0])) {
                $fresh = new static($result[0], $this->db);
            }
        });

        $this->db->q(function ($qb) {});
        return $fresh;
    }

    // TOUCH - update updated_at timestamp
    public function touch($attribute = 'updated_at') {
        $this->attributes[$attribute] = date('Y-m-d H:i:s');
        $this->save();
        return $this;
    }

    // REPLICATE - vytvorenie kópie entity bez ID
    public function replicate(array $except = null) {
        $attributes = $this->attributes;
        unset($attributes[$this->primaryKey]);

        if ($except) {
            $attributes = array_diff_key($attributes, array_flip($except));
        }

        if (isset($attributes['created_at'])) {
            unset($attributes['created_at']);
        }
        if (isset($attributes['updated_at'])) {
            unset($attributes['updated_at']);
        }

        return new static($attributes, $this->db);
    }

    // FILL - hromadné nastavenie atribútov
    public function fill(array $attributes) {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    // GET KEY - získa hodnotu primárneho kľúča
    public function getKey() {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    // GET KEY NAME - získa názov primárneho kľúča
    public function getKeyName() {
        return $this->primaryKey;
    }

    // SET UPDATED AT - automatické nastavenie updated_at
    public function setUpdatedAt() {
        if (array_key_exists('updated_at', $this->attributes)) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }
    }

    // SET CREATED AT - automatické nastavenie created_at
    public function setCreatedAt() {
        if (array_key_exists('created_at', $this->attributes)) {
            $this->attributes['created_at'] = date('Y-m-d H:i:s');
        }
    }

    // USES TIMESTAMPS - či používa timestamps
    public function usesTimestamps() {
        return array_key_exists('created_at', $this->attributes) &&
               array_key_exists('updated_at', $this->attributes);
    }

    // GET CREATED AT COLUMN - názov created_at stĺpca
    public function getCreatedAtColumn() {
        return 'created_at';
    }

    // GET UPDATED AT COLUMN - názov updated_at stĺpca
    public function getUpdatedAtColumn() {
        return 'updated_at';
    }

    // SOFT DELETES METHODS
    public function usesSoftDeletes() {
        return $this->softDeletes;
    }

    public function setSoftDeletes($enabled = true, $column = 'deleted_at') {
        $this->softDeletes = $enabled;
        $this->deletedAtColumn = $column;
        return $this;
    }

    public function getDeletedAtColumn() {
        return $this->deletedAtColumn;
    }

    public function trashed() {
        return !is_null($this->attributes[$this->deletedAtColumn] ?? null);
    }

    public function restore() {
        if ($this->trashed()) {
            $this->attributes[$this->deletedAtColumn] = null;
            $this->save();
        }
        return $this;
    }

    public function forceDelete() {
        $this->softDeletes = false;
        $this->delete();
        return $this;
    }

    // EVENT METHODS
    public function observe($event, callable $callback) {
        $this->events[$event][] = $callback;
        return $this;
    }

    private function fireEvent($event, ...$args) {
        if (isset($this->events[$event])) {
            foreach ($this->events[$event] as $callback) {
                $callback($this, ...$args);
            }
        }
    }

    // OVERRIDE SAVE METHOD WITH EVENTS
    public function save($callback_ok = null, $callback_error = null) {
        // Fire creating/created events for new records
        if (!isset($this->attributes[$this->primaryKey])) {
            $this->fireEvent('creating');
        }

        $this->fireEvent('saving');

        $changes = array_diff_assoc($this->attributes, $this->originalAttributes);
        if (empty($changes)) {
            if (is_callable($callback_ok)) $callback_ok([], $this->db, []);
            $this->fireEvent('saved');
            if (!isset($this->attributes[$this->primaryKey])) {
                $this->fireEvent('created');
            }
            return;
        }

        // Set timestamps
        if ($this->usesTimestamps()) {
            if (!isset($this->attributes[$this->primaryKey])) {
                $this->setCreatedAt();
            }
            $this->setUpdatedAt();
        }

        $this->validate();

        $this->fireEvent('updating');

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
                if (isset($this->db->cacheDriver) && method_exists($this->db->cacheDriver, 'deleteKeys')) {
                    $this->db->cacheDriver->deleteKeys("{$this->table}:{$this->db->getReturnType()}:*");
                } elseif (isset($this->db->cacheDriver)) {
                    throw new \Exception("Method deleteKeys do not exist !");
                }
                if (is_callable($callback_ok)) $callback_ok($result, $db, $execution_data);
                $this->fireEvent('saved');
                if (!isset($this->attributes[$this->primaryKey])) {
                    $this->fireEvent('created');
                } else {
                    $this->fireEvent('updated');
                }
            },
            function ($error, $db, $execution_data) use ($callback_error) {
                if (is_callable($callback_error)) $callback_error($error, $db, $execution_data);
            }
        );

        $this->db->q(function ($qb) {});
    }

    // OVERRIDE DELETE METHOD WITH EVENTS
    public function delete($callback_ok = null, $callback_error = null) {
        if (!isset($this->attributes[$this->primaryKey])) {
            throw new \Exception("Cannot delete entity without primary key");
        }

        $this->fireEvent('deleting');

        if ($this->usesSoftDeletes()) {
            // Soft delete
            $this->attributes[$this->deletedAtColumn] = date('Y-m-d H:i:s');
            $this->save($callback_ok, $callback_error);
            $this->fireEvent('deleted');
        } else {
            // Hard delete
            $this->db->q(function ($qb) {
                $qb->delete($this->table)->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
            })->execute(
                function ($result, $db, $execution_data) use ($callback_ok) {
                    if (isset($this->db->cacheDriver) && method_exists($this->db->cacheDriver, 'deleteKeys')) {
                        $this->db->cacheDriver->deleteKeys("{$this->table}:{$this->db->getReturnType()}:*");
                    }
                    if (is_callable($callback_ok)) $callback_ok($result, $db, $execution_data);
                    $this->fireEvent('deleted');
                },
                $callback_error
            );

            $this->db->q(function ($qb) {});
        }
    }
}

?>
