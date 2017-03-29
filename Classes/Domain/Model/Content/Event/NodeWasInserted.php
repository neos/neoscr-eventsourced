<?php
namespace Neos\ContentRepository\EventSourced\Domain\Model\Content\Event;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;

/**
 * A node was inserted
 */
class NodeWasInserted extends AbstractDimensionAwareEvent
{
    /**
     * @var string
     */
    protected $variantIdentifier;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $nodeType;

    /**
     * @var string
     */
    protected $parentIdentifier;

    /**
     * @var string
     */
    protected $elderSiblingIdentifier;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $properties;


    /**
     * @param string $variantIdentifier
     * @param string $identifier
     * @param array $contentDimensionValues
     * @param string $nodeType
     * @param string $parentIdentifier
     * @param string $elderSiblingIdentifier
     * @param string $path
     * @param array $properties
     */
    public function __construct(
        string $variantIdentifier,
        string $identifier,
        array $contentDimensionValues,
        string $nodeType,
        string $parentIdentifier,
        string $elderSiblingIdentifier = null,
        string $path = '',
        array $properties = []
    ) {
        parent::__construct($contentDimensionValues);
        $this->variantIdentifier = $variantIdentifier;
        $this->identifier = $identifier;
        $this->nodeType = $nodeType;
        $this->parentIdentifier = $parentIdentifier;
        $this->elderSiblingIdentifier = $elderSiblingIdentifier;
        $this->path = $path;
        $this->properties = $properties;
    }


    /**
     * @return string
     */
    public function getVariantIdentifier(): string
    {
        return $this->variantIdentifier;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return string
     */
    public function getNodeType(): string
    {
        return $this->nodeType;
    }

    /**
     * @return string
     */
    public function getParentIdentifier(): string
    {
        return $this->parentIdentifier;
    }

    /**
     * @return string|null
     */
    public function getElderSiblingIdentifier()
    {
        return $this->elderSiblingIdentifier;
    }

    /**
     * @return string|null
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
}
