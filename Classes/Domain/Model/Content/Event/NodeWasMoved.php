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

use Neos\EventSourcing\Event\EventInterface;
use Neos\Flow\Annotations as Flow;

/**
 * A node was inserted
 */
class NodeWasMoved implements EventInterface
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
     * @var array
     */
    protected $contentDimensionValues = [];

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
    protected $path;

    /**
     * @var int
     */
    protected $position;

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
     * @param string $path
     * @param int $position
     * @param array $properties
     */
    public function __construct(
        string $variantIdentifier,
        string $identifier,
        array $contentDimensionValues,
        string $nodeType,
        string $parentIdentifier,
        string $path,
        int $position,
        array $properties
    ) {
        $this->variantIdentifier = $variantIdentifier;
        $this->identifier = $identifier;
        $this->contentDimensionValues = $contentDimensionValues;
        $this->nodeType = $nodeType;
        $this->parentIdentifier = $parentIdentifier;
        $this->path = $path;
        $this->position = $position;
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
     * @return array
     */
    public function getContentDimensionValues(): array
    {
        return $this->contentDimensionValues;
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
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
}
