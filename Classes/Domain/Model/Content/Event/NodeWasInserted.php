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
     * @param array $properties
     */
    public function __construct(
        string $variantIdentifier,
        string $identifier,
        array $contentDimensionValues,
        string $nodeType,
        string $parentIdentifier,
        string $elderSiblingIdentifier = null,
        array $properties
    ) {
        parent::__construct($contentDimensionValues);
        $this->variantIdentifier = $variantIdentifier;
        $this->identifier = $identifier;
        $this->nodeType = $nodeType;
        $this->parentIdentifier = $parentIdentifier;
        $this->elderSiblingIdentifier = $elderSiblingIdentifier;
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
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function toArray(): array
    {
        return [
            'contentDimensionValues' => $this->contentDimensionValues,
            'variantIdentifier' => $this->variantIdentifier,
            'identifier' => $this->identifier,
            'nodeType' => $this->nodeType,
            'parentIdentifier' => $this->parentIdentifier,
            'elderSiblingIdentifier' => $this->elderSiblingIdentifier,
            'properties' => $this->properties,
        ];
    }

    function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromPayload(array $payload): NodeWasInserted
    {
        return new NodeWasInserted(
            $payload['variantIdentifier'],
            $payload['identifier'],
            $payload['contentDimensionValues'],
            $payload['nodeType'],
            $payload['parentIdentifier'],
            $payload['elderSiblingIdentifier'],
            $payload['properties']
        );
    }
}
