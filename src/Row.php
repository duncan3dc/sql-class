<?php

namespace duncan3dc\SqlClass;

/**
 * Represent a single row from a result set.
 */
class Row implements \ArrayAccess
{

    /**
     * Create a Row instance to provide extra functionality.
     *
     * @param array $row The row returned from a result set
     */
    public function __construct(array $row)
    {
        $this->index = [];
        $this->assoc = [];
        foreach ($row as $key => $val) {
            $this->index[] = $val;

            $key = strtolower($key);
            $this->assoc[$key] = $val;
        }
    }


    protected function getKey($key)
    {
        if (is_string($key)) {
            $key = strtolower($key);

            if (!isset($this->assoc[$key])) {
                throw new \InvalidArgumentException("Invalid field: {$key}");
            }
        } else {
            if (!isset($this->index[$key])) {
                throw new \InvalidArgumentException("Invalid index: {$key}");
            }
        }

        return $key;
    }


    /**
     * Get a specified field from the row.
     *
     * @return mixed
     */
    public function raw($key)
    {
        $key = $this->getKey($key);

        if (is_string($key)) {
            return $this->assoc[$key];
        }

        return $this->index[$key];
    }


    /**
     * Get a specified field from the row.
     *
     * @return mixed
     */
    public function get($key)
    {
        $value = $this->raw($key);

        if (is_string($value)) {
            $value = rtrim($value);
        }

        return $value;
    }


    /**
     * Get a specified field from the row.
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
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
        try {
            $this->getKey($offset);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        return true;
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
        $key = $this->getKey($offset);
        if (is_string($key)) {
            $this->assoc[$key] = $value;
        } else {
            $this->index[$key] = $value;
        }
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
        $key = $this->getKey($offset);
        if (is_string($key)) {
            unset($this->assoc[$key]);
            # ?
            if (isset($this->{$key})) {
                unset($this->{$key});
            }
        } else {
            unset($this->index[$key]);
        }
    }
}
