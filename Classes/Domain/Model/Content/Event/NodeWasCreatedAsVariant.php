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
class NodeWasCreatedAsVariant extends AbstractDimensionAwareEvent
{
    const STRATEGY_COPY = 'copy';
    const STRATEGY_EMPTY = 'empty';

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


    /**
     * @param string $variantIdentifier
     * @param string $fallbackIdentifier
     * @param array $contentDimensionValues
     * @param array $properties
     * @param string $strategy
     */
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
    public function getFallbackIdentifier(): string
    {
        return $this->fallbackIdentifier;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return string
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }
}
