<?php

namespace duncan3dc\SqlClass;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\ArrayCache;

/**
 * Represent a single row from a result set.
 */
class Row implements \ArrayAccess
{
    /**
     * @var array $data The row data as an associative array.
     */
    protected $data = [];

    /**
     * @var string[] $mapping Map the numerically indexed array elements to the associative ones.
     */
    protected $mapping = [];

    /**
     * @var Cache $cache Internal cache of data.
     */
    protected $cache;

    /**
     * Create a Row instance to provide extra functionality.
     *
     * @param array $row The row returned from a result set
     */
    public function __construct(array $row)
    {
        foreach ($row as $key => $val) {
            $key = strtolower($key);

            $this->data[$key] = $val;

            $this->mapping[] = $key;
        }

        $this->cache = new ArrayCache;
    }


    /**
     * Convert the user specified key to a valid internal one
     *
     * String keys are lower cased.
     *
     * @param string|int $key The key to convert
     *
     * @return string
     */
    protected function getKey($key)
    {
        if (!is_string($key)) {
            if (!isset($this->mapping[$key])) {
                throw new \InvalidArgumentException("Invalid index: {$key}");
            }
            $key = $this->mapping[$key];
        }

        $key = strtolower($key);

        if (!isset($this->data[$key])) {
            throw new \InvalidArgumentException("Invalid field: {$key}");
        }

        return $key;
    }


    /**
     * Get a specified field from the row (without triming)
     *
     * @return mixed
     */
    public function raw($key)
    {
        $key = $this->getKey($key);

        return $this->data[$key];
    }


    /**
     * Check whether a field exists.
     *
     * @param string|int $key The field name to check for
     *
     * @return bool
     */
    public function has($key)
    {
        try {
            $this->getKey($key);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        return true;
    }


    /**
     * Get a specified field from the row and right-trim it if it's a string.
     *
     * @return mixed
     */
    public function get($key)
    {
        $key = $this->getKey($key);
        $cacheKey = "value_{$key}";

        if ($this->cache->contains($cacheKey)) {
            return $this->cache->fetch($cacheKey);
        }

        $value = $this->raw($key);

        if (is_string($value)) {
            $value = rtrim($value);
        }

        $this->cache->save($cacheKey, $value);

        return $value;
    }


    /**
     * Set a field to a new value.
     *
     * @param string|int $key The field name or row index to set the value of
     * @param mixed $value The value to set
     *
     * @return static
     */
    public function set($key, $value)
    {
        $key = $this->getKey($key);

        $this->data[$key] = $value;
        $this->cache->delete("value_{$key}");

        return $this;
    }


    /**
     * Delete a field from the row.
     *
     * @param string|int $key The field name or row index to delete
     *
     * @return static
     */
    public function delete($key)
    {
        $key = $this->getKey($key);

        unset($this->data[$key]);

        $index = array_search($key, $this->mapping);
        unset($this->mapping[$index]);

        $this->cache->delete("value_{$key}");

        return $this;
    }


    /**
     * PropertyAccess - Get a specified field from the row.
     *
     * @param mixed $key The field name to get the value of
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }


    /**
     * PropertyAccess - Set a field to a new value.
     *
     * @param mixed $key The field name to set the value of
     * @param mixed $value The value to set
     *
     * @return void
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }


    /**
     * PropertyAccess - Check whether a field exists.
     *
     * @param mixed $key The field name to check for
     *
     * @return bool
     */
    public function __isset($key)
    {
        return $this->has($key);
    }


    /**
     * PropertyAccess - Unset a field.
     *
     * @param mixed $key The field name to unset
     *
     * @return void
     */
    public function __unset($key)
    {
        $this->delete($key);
    }


    /**
     * ArrayAccess - Whether an offset exists.
     *
     * @param mixed $offset An offset to check for
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }


    /**
     * ArrayAccess - Retrieve an offset.
     *
     * @param mixed $offset The offset to retrieve
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }


    /**
     * ArrayAccess - Set an offset.
     *
     * @param mixed $offset The offset to assign the value to
     * @param mixed $value The value to set
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }


    /**
     * ArrayAccess - Unset an offset.
     *
     * @param mixed $offset The offset to unset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }
}
