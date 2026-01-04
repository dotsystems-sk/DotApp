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
		    // Extract table name from relation key
		    $parts = explode(':', $relation);
		    $relatedTable = $parts[1];
		    $foreignKey = $parts[2] ?? 'user_id'; // Default fallback

		    $ids = array_map(function ($item) {
		        return $item->id;
		    }, $this->items);

		    $related = $this->db->q(function ($qb) use ($relatedTable, $ids, $foreignKey) {
			$qb->select('*', $relatedTable)->whereIn($foreignKey, $ids);
		    })->all();

		    foreach ($this->items as $item) {
			$item->relations[$relation] = array_filter($related, function ($rel) use ($item, $foreignKey) {
			    return $rel[$foreignKey] == $item->id;
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

	public function paginate(int $perPage, int $currentPage = 1, $error_callback = null): array {
	    // Efektívna SQL pagination cez QueryObject
	    if ($this->query && !$this->loaded) {
	        return $this->query->paginate($perPage, $currentPage, $error_callback);
	    }

	    // Fallback for already loaded data (inefficient)
	    $this->load($error_callback);
	    $offset = ($currentPage - 1) * $perPage;
	    $items = array_slice($this->items, $offset, $perPage);
	    $total = count($this->items);

	    return $this->buildPaginationResult($items, $total, $perPage, $currentPage);
	}


	private function buildPaginationResult(array $items, int $total, int $perPage, int $currentPage): array {
	    $lastPage = max(1, ceil($total / $perPage));
	    $from = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : null;
	    $to = $total > 0 ? min($total, $currentPage * $perPage) : null;

	    return [
	        'data' => $items,
	        'current_page' => $currentPage,
	        'per_page' => $perPage,
	        'total' => $total,
	        'last_page' => $lastPage,
	        'from' => $from,
	        'to' => $to,
	        'has_more_pages' => $currentPage < $lastPage,
	        'prev_page' => $currentPage > 1 ? $currentPage - 1 : null,
	        'next_page' => $currentPage < $lastPage ? $currentPage + 1 : null,
	    ];
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

    // FIND - find first item by criteria
    public function find(callable $callback, $error_callback = null) {
        $this->load($error_callback);
        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }
        return null;
    }

    // SORT BY - sort by field
    public function sortBy(string $field, $direction = 'asc', $error_callback = null): self {
        $this->load($error_callback);
        $direction = strtolower($direction);

        usort($this->items, function ($a, $b) use ($field, $direction) {
            $valueA = $a->$field ?? null;
            $valueB = $b->$field ?? null;

            if ($valueA === $valueB) {
                return 0;
            }

            $result = $valueA <=> $valueB;
            return $direction === 'desc' ? -$result : $result;
        });

        return $this;
    }

    // SORT BY DESC - triedenie zostupne
    public function sortByDesc(string $field, $error_callback = null): self {
        return $this->sortBy($field, 'desc', $error_callback);
    }

    // GROUP BY - group by field
    public function groupBy(string $field, $error_callback = null): array {
        $this->load($error_callback);
        $grouped = [];

        foreach ($this->items as $item) {
            $key = $item->$field ?? '';
            if (!isset($grouped[$key])) {
                $grouped[$key] = new static([], $this->db);
            }
            $grouped[$key]->push($item);
        }

        return $grouped;
    }

    // UNIQUE - odstránenie duplikátov
    public function unique(callable $callback = null, $error_callback = null): self {
        $this->load($error_callback);

        if ($callback === null) {
            $this->items = array_unique($this->items, SORT_REGULAR);
        } else {
            $seen = [];
            $this->items = array_filter($this->items, function ($item) use ($callback, &$seen) {
                $key = $callback($item);
                if (in_array($key, $seen)) {
                    return false;
                }
                $seen[] = $key;
                return true;
            });
        }

        return $this;
    }

    // TAKE - limit number of items
    public function take(int $limit, $error_callback = null): self {
        $this->load($error_callback);
        return new self(array_slice($this->items, 0, $limit), $this->db);
    }

    // SKIP - skip items
    public function skip(int $offset, $error_callback = null): self {
        $this->load($error_callback);
        return new self(array_slice($this->items, $offset), $this->db);
    }

    // REDUCE - redukcia na jednu hodnotu
    public function reduce(callable $callback, $initial = null, $error_callback = null) {
        $this->load($error_callback);
        return array_reduce($this->items, $callback, $initial);
    }

    // SEARCH - advanced search
    public function search(string $field, string $query, $error_callback = null): self {
        $this->load($error_callback);
        $query = strtolower($query);

        return $this->filter(function ($item) use ($field, $query) {
            $value = strtolower($item->$field ?? '');
            return strpos($value, $query) !== false;
        }, $error_callback);
    }

    // CHUNK - process in chunks
    public function chunk(int $size, callable $callback, $error_callback = null): self {
        $this->load($error_callback);

        $chunks = array_chunk($this->items, $size);
        foreach ($chunks as $chunk) {
            $chunkCollection = new static($chunk, $this->db);
            $callback($chunkCollection);
        }

        return $this;
    }

    // AVG - average of field values
    public function avg(string $field, $error_callback = null) {
        $this->load($error_callback);
        if (empty($this->items)) return 0;

        $sum = 0;
        $count = 0;
        foreach ($this->items as $item) {
            $value = $item->$field ?? 0;
            if (is_numeric($value)) {
                $sum += $value;
                $count++;
            }
        }
        return $count > 0 ? $sum / $count : 0;
    }

    // SUM - sum of field values
    public function sum(string $field, $error_callback = null) {
        $this->load($error_callback);
        $sum = 0;
        foreach ($this->items as $item) {
            $value = $item->$field ?? 0;
            if (is_numeric($value)) {
                $sum += $value;
            }
        }
        return $sum;
    }

    // MIN - minimum field value
    public function min(string $field, $error_callback = null) {
        $this->load($error_callback);
        if (empty($this->items)) return null;

        $min = null;
        foreach ($this->items as $item) {
            $value = $item->$field ?? null;
            if ($value !== null && ($min === null || $value < $min)) {
                $min = $value;
            }
        }
        return $min;
    }

    // MAX - maximum field value
    public function max(string $field, $error_callback = null) {
        $this->load($error_callback);
        if (empty($this->items)) return null;

        $max = null;
        foreach ($this->items as $item) {
            $value = $item->$field ?? null;
            if ($value !== null && ($max === null || $value > $max)) {
                $max = $value;
            }
        }
        return $max;
    }

    // CONTAINS - whether collection contains item
    public function contains($value, $error_callback = null): bool {
        $this->load($error_callback);
        if (is_callable($value)) {
            foreach ($this->items as $item) {
                if ($value($item)) {
                    return true;
                }
            }
            return false;
        }

        return in_array($value, $this->items);
    }

    // CONTAINS STRICT - striktné porovnanie
    public function containsStrict($value, $error_callback = null): bool {
        $this->load($error_callback);
        return in_array($value, $this->items, true);
    }

    // DIFF - rozdiel s inou kolekciou
    public function diff(Collection $collection, $error_callback = null): self {
        $this->load($error_callback);
        $collection->load($error_callback);

        $diff = array_diff($this->items, $collection->all());
        return new self($diff, $this->db);
    }

    // INTERSECT - prienik s inou kolekciou
    public function intersect(Collection $collection, $error_callback = null): self {
        $this->load($error_callback);
        $collection->load($error_callback);

        $intersect = array_intersect($this->items, $collection->all());
        return new self($intersect, $this->db);
    }

    // MERGE - merge with another collection
    public function merge(Collection $collection, $error_callback = null): self {
        $this->load($error_callback);
        $collection->load($error_callback);

        $merged = array_merge($this->items, $collection->all());
        return new self($merged, $this->db);
    }

    // CONCAT - spájanie s inou kolekciou
    public function concat(Collection $collection, $error_callback = null): self {
        return $this->merge($collection, $error_callback);
    }

    // ZIP - spárovanie s inou kolekciou
    public function zip(Collection $collection, $error_callback = null): self {
        $this->load($error_callback);
        $collection->load($error_callback);

        $zipped = [];
        $otherItems = $collection->all();
        $count = min(count($this->items), count($otherItems));

        for ($i = 0; $i < $count; $i++) {
            $zipped[] = [$this->items[$i], $otherItems[$i]];
        }

        return new self($zipped, $this->db);
    }

    // PARTITION - split into two collections by callback
    public function partition(callable $callback, $error_callback = null): array {
        $this->load($error_callback);

        $passed = [];
        $failed = [];

        foreach ($this->items as $item) {
            if ($callback($item)) {
                $passed[] = $item;
            } else {
                $failed[] = $item;
            }
        }

        return [
            new self($passed, $this->db),
            new self($failed, $this->db)
        ];
    }

    // REJECT - reject items by callback
    public function reject(callable $callback, $error_callback = null): self {
        $this->load($error_callback);

        $filtered = array_filter($this->items, function ($item) use ($callback) {
            return !$callback($item);
        });

        return new self($filtered, $this->db);
    }

    // SORT - sort by callback
    public function sort(callable $callback = null, $error_callback = null): self {
        $this->load($error_callback);

        if ($callback === null) {
            sort($this->items);
        } else {
            usort($this->items, $callback);
        }

        return $this;
    }

    // SORT DESC - triedenie zostupne
    public function sortDesc(callable $callback = null, $error_callback = null): self {
        $this->load($error_callback);

        if ($callback === null) {
            rsort($this->items);
        } else {
            usort($this->items, function ($a, $b) use ($callback) {
                return -$callback($a, $b);
            });
        }

        return $this;
    }

    // SOME - whether at least one item matches callback
    public function some(callable $callback, $error_callback = null): bool {
        $this->load($error_callback);

        foreach ($this->items as $item) {
            if ($callback($item)) {
                return true;
            }
        }
        return false;
    }

    // EVERY - whether all items match callback
    public function every(callable $callback, $error_callback = null): bool {
        $this->load($error_callback);

        foreach ($this->items as $item) {
            if (!$callback($item)) {
                return false;
            }
        }
        return true;
    }

    // WHEN - podmienená operácia
    public function when($condition, callable $callback, callable $default = null): self {
        if ($condition) {
            return $callback($this);
        } elseif ($default) {
            return $default($this);
        }
        return $this;
    }

    // UNLESS - podmienená operácia (opak when)
    public function unless($condition, callable $callback): self {
        return $this->when(!$condition, $callback);
    }

    // TAP - debugging callback bez zmeny kolekcie
    public function tap(callable $callback): self {
        $callback($this);
        return $this;
    }

    // PIPE - chain operations via callback
    public function pipe(callable $callback) {
        return $callback($this);
    }

    // NTH - every nth item
    public function nth(int $step, int $offset = 0, $error_callback = null): self {
        $this->load($error_callback);

        $filtered = [];
        $count = count($this->items);

        for ($i = $offset; $i < $count; $i += $step) {
            $filtered[] = $this->items[$i];
        }

        return new self($filtered, $this->db);
    }
}
	
?>
