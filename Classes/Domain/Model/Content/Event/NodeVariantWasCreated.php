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
 * A node variant was created
 */
class NodeVariantWasCreated extends AbstractDimensionAwareEvent implements \JsonSerializable
{
    const STRATEGY_EMPTY = 'empty';
    const STRATEGY_COPY = 'copy';


    /**
     * @var string
     */
    protected $variantIdentifier;

    /**
     * @var string
     */
    protected $fallbackIdentifier;

    /**
     * @var array
     */
    protected $properties;

    /**
     * @var string
     */
    protected $strategy;


    public function __construct(
        string $variantIdentifier,
        string $fallbackIdentifier,
        array $contentDimensionValues,
        array $properties,
        string $strategy
    ) {
        parent::__construct($contentDimensionValues);
        $this->variantIdentifier = $variantIdentifier;
        $this->fallbackIdentifier = $fallbackIdentifier;
        $this->properties = $properties;
        $this->strategy = $strategy;
    }


    public function getVariantIdentifier(): string
    {
        return $this->variantIdentifier;
    }

    public function getFallbackIdentifier(): string
    {
        return $this->fallbackIdentifier;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function toArray(): array
    {
        return [
            'contentDimensionValues' => $this->contentDimensionValues,
            'variantIdentifier' => $this->variantIdentifier,
            'fallbackIdentifier' => $this->fallbackIdentifier,
            'properties' => $this->properties,
            'strategy' => $this->strategy
        ];
    }

    function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromPayload(array $payload): NodeVariantWasCreated
    {
        return new NodeVariantWasCreated(
            $payload['variantIdentifier'],
            $payload['fallbackIdentifier'],
            $payload['contentDimensionValues'],
            $payload['properties'],
            $payload['strategy']
        );
    }
}
