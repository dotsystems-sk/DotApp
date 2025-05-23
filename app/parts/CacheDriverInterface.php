<?php

namespace Dotsystems\App\Parts;

interface CacheDriverInterface {
    public function get($key);
    public function set($key, $value, $ttl = null);
    public function delete($key);
    public function deleteKeys($pattern);
}
?>