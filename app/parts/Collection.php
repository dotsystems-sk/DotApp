<?php
	/*
		Najprv boli triedy ako entity a collection anonymne. Vytahujem ich z kodu pred vypustenim na github
		Tu budu. new class($attributes = [], $db = null) sa meni na Entity($attributes = [], $db = null)
		Preco boli doteraz ako anonymne? Pretoze driver je osobitna funkcia, takze boli definovane vnutri drivera aby definicia drivera obsahovala priamo aj triedy.
		Teda aby to bola jedna velka nezavisla funkcia. Kedze su ale tieto 2 drivery vstavane, tak som zmenil pristup k tomu aby to bolo vsetko uzavrete
		do jednej funkcie a vytiahol som triedy Entity a Collection von.
	*/

namespace Dotsystems\App\Parts;

class Collection implements \IteratorAggregate, \Countable, \ArrayAccess {
	private $items = [];
	private $db;
	private $query;
	private $loaded = false;
	private $with = []; // Pre eager loading

	public function __construct($queryOrItems = null, $db = null) {
	    $this->db = $db;
	    if (is_array($queryOrItems)) {
            $this->items = $queryOrItems;
            $this->loaded = true;
	    } elseif ($queryOrItems instanceof \Dotsystems\App\Parts\Databaser) {
            $this->query = $queryOrItems;
            $this->loaded = false;
	    } elseif ($queryOrItems instanceof \Dotsystems\App\Parts\DI && $queryOrItems->getTarget() instanceof \Dotsystems\App\Parts\Databaser) {
            $this->query = $queryOrItems;
            $this->loaded = false;
	    }
	}

    public function changeItems($items) {
        $this->items = [];
        foreach ($items as $key => $item) {
            if ($item instanceof \Dotsystems\App\Parts\Entity) {
                $this->items[$key] = $item;
            } else {
                throw new \Exception("Item must be instance of \Dotsystems\App\Parts\Entity");
            }            
        }        
	}
    
    public function setItem(int $key,$item) {
        if ($item instanceof \Dotsystems\App\Parts\Entity) {
            $this->items[$key] = $item;
        } else {
            throw new \Exception("Item must be instance of \Dotsystems\App\Parts\Entity");
        }
	}

	public function getItem($key) {
	    return $this->items[$key] ?? null;
	}
	
	private function load($error_callback = null) {
	    if (!$this->loaded && $this->query) {
		$queryClone = clone $this->query;
		$this->query
		->return('RAW')
		->execute(
		    function ($result, $execution_data) use ($queryClone) {
			$this->query->return('ORM');
			$this->items = [];
			foreach ($result as $row) {
			    $entity = new Entity($row, $this->db);
			    $entity->with($this->with); // Eager loading
			    $this->items[] = $entity;
			}
			$this->loaded = true;
			$this->query = $queryClone;
		    },
		    $error_callback
		);

	    }
	}

	public function with($relations) {
	    $this->with = is_array($relations) ? $relations : [$relations];
	    return $this;
	}

	public function loadRelations($error_callback = null) {
	    $this->load($error_callback);
	    foreach ($this->with as $relation) {
		if (strpos($relation, 'hasMany:') === 0) {
		    $relatedTable = str_replace('hasMany:', '', $relation);
		    $ids = array_map(function ($item) { return $item->id; }, $this->items);
		    $related = $this->db->q(function ($qb) use ($relatedTable, $ids) {
			$qb->select('*', $relatedTable)->whereIn('user_id', $ids);
		    })->all();
		    foreach ($this->items as $item) {
			$item->relations[$relation] = array_filter($related, function ($rel) use ($item) {
			    return $rel['user_id'] == $item->id;
			});
		    }
		} else {
		    foreach ($this->items as $item) {
			$item->loadRelations();
		    }
		}
	    }
	    return $this;
	}

	public function all($error_callback = null): array {
	    $this->load($error_callback);
	    return $this->items;
	}

	public function first($error_callback = null) {
	    $this->load($error_callback);
	    return $this->items[0] ?? null;
	}

	public function count($error_callback = null): int {
	    $this->load($error_callback);
	    return count($this->items);
	}

	#[\ReturnTypeWillChange]
	public function offsetExists($offset): bool {
	    $this->load();
	    return isset($this->items[$offset]);
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($offset) {
	    $this->load();
	    return $this->items[$offset] ?? null;
	}

	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value): void {
	    $this->load();
	    if (is_null($offset)) {
		$this->items[] = $value;
	    } else {
		$this->items[$offset] = $value;
	    }
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset($offset): void {
	    $this->load();
	    unset($this->items[$offset]);
	}

	public function getIterator(): \ArrayIterator {
	    $this->load();
	    return new \ArrayIterator($this->items);
	}

	public function filter(callable $callback, $error_callback = null): self {
	    $this->load($error_callback);
	    return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH), $this->db);
	}

	public function map(callable $callback, $error_callback = null): self {
	    $this->load($error_callback);
        $vysledok = array_map($callback, $this->items);
	    return new self($vysledok, $this->db);
	}

	public function pluck(string $field, $error_callback = null): self {
	    $this->load($error_callback);
        $mapFn = function ($item) use ($field) {
            return $item->$field ?? null;
	    };
        return $this->map($mapFn);
	}

	public function paginate(int $perPage, int $currentPage = 1, $error_callback = null): self {
	    $this->load($error_callback);
	    $offset = ($currentPage - 1) * $perPage;
	    return new self(array_slice($this->items, $offset, $perPage), $this->db);
	}

	public function saveAll($error_callback = null): self {
	    $this->load($error_callback);
	    foreach ($this->items as $item) {
            if (method_exists($item, 'save')) {
                $item->save(null, $error_callback);
            }
	    }
	    return $this;
	}

	public function toArray($error_callback = null): array {
	    $this->load($error_callback);
	    return array_map(function ($item) {
		    return method_exists($item, 'toArray') ? $item->toArray() : $item;
	    }, $this->items);
	}

	public function push($entity, $error_callback = null): self {
	    $this->load($error_callback);
	    $this->items[] = $entity;
	    return $this;
	}
}
	
?>