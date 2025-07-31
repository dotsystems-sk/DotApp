<?php
namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Databaser;
use Dotsystems\App\Parts\QueryBuilder;

class DatabaserQueryObject {
    private $querybuilder;
    private $databaser;

    function __construct(QueryBuilder $qb, Databaser $databaser) {
        $this->querybuilder = $qb;
        $this->databaser = $databaser;
    }

    public function execute($success = null, $onError = null) {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->execute($success, $onError);
    }

    public function getQuery() {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->getQuery();
    }

    public function first() {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->first();
    }

    public function all() {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->all();
    }

    public function raw() {
        $this->databaser->setQB($this->querybuilder);
        return $this->databaser->raw();
    }
}