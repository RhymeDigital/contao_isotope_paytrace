<?php

/**
 *  Copyright (c) 2025 Rhyme Digital, LLC.
 *
 *  @link			https://rhyme.digital
 *  @license		https://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

namespace Rhyme\ContaoIsotopePaytraceBundle\Connector\Model;

use JsonException;
use JsonSerializable;
use ArrayAccess;

abstract class BaseModel implements ArrayAccess, JsonSerializable
{
    /**
     * @var array
     */
    protected array $attributes = [];

    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes($attributes);
    }

    /**
     * @param array $attributes
     * @return $this
     */
    protected function setAttributes(array $attributes = []): self
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }

        return $this;
    }

    /**
     * @return string
     * @throws JsonException
     */
    public function toJson(): string
    {
        return \json_encode($this, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $object = \get_object_vars($this);

        foreach ($object as $key => $value) {
            if ($value instanceof self) {
                $object[$key] = $value->toArray();
            }
        }

        return $object;
    }

    /**
     * @param $name
     * @param $value
     * @return void
     */
    public function __set($name, $value)
    {
        $method = 'set'.\ucwords($name).'Attribute';

        if (\method_exists($this, $method)) {
            $this->$method($value);
            return;
        }

        $this->$name = $value;
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->$name);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * Whether a offset exists
     *
     * @param mixed $offset
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return \property_exists($this, $offset);
    }

    /**
     * Offset to retrieve
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Offset to set
     *
     * @param $offset
     * @param $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->$offset = $value;
    }

    /**
     * Offset to unset
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->$offset);
    }

    /**
     * Specify data from this object that should be serialized to JSON (data which can be serialized by json_encode)
     *
     * @return BaseModel
     */
    public function jsonSerialize(): self
    {
        return $this;
    }
}