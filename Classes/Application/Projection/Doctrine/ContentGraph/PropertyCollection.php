<?php
namespace Neos\ContentRepository\EventSourced\Application\Projection\Doctrine\ContentGraph;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;

/**
 * The property collection implementation
 *
 * Takes care of lazily resolving entity properties
 */
class PropertyCollection implements \ArrayAccess, \Iterator
{
    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @var array
     */
    protected $properties;

    /**
     * @var array
     * @Flow\Transient
     */
    protected $resolvedProperties;


    public function __construct(array $properties)
    {
        $this->properties = $properties;
    }

    /**
     * @param EntityManager $entityManager
     *
     * @return mixed
     */
    public function serialize($entityManager)
    {
        return Type::getType('flow_json_array')->convertToDatabaseValue($this->properties, $entityManager->getConnection()->getDatabasePlatform());
    }

    public function asRawArray()
    {
        return $this->properties;
    }


    public function offsetExists($offset)
    {
        return isset($this->properties[$offset]);
    }

    public function offsetGet($offset)
    {
        if (! isset($this->resolvedProperties[$offset])) {
            $this->resolvedProperties[$offset] = $this->resolveObjectIfNecessary($this->properties[$offset]);
        }

        return $this->resolvedProperties[$offset];
    }

    /**
     * @param $value
     *
     * @return object
     */
    protected function resolveObjectIfNecessary($value)
    {
        if (isset($value['__identifier']) && isset($value['__flow_object_type'])) {
            return $this->persistenceManager->getObjectByIdentifier(
                $value['__identifier'],
                $value['__flow_object_type']
            );
        } elseif (is_array($value)) {
            return array_map(function ($element) {
                return $this->resolveObjectIfNecessary($element);
            }, $value);
        } else {
            return $value;
        }
    }

    public function offsetSet($offset, $value)
    {
        $this->properties[$offset] = $value;
        unset($this->resolvedProperties[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->properties);
    }

    public function current()
    {
        return $this->offsetGet($this->key());
    }

    public function next()
    {
        next($this->properties);
    }

    public function key()
    {
        return key($this->properties);
    }

    public function valid()
    {
        return current($this->properties) !== false;
    }

    public function rewind()
    {
        reset($this->properties);
    }
}
