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
 * A node variant was created
 */
class NodeVariantWasCreated implements EventInterface
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
    protected $contentDimensionValues = [];

    /**
     * @var string
     */
    protected $strategy;


    /**
     * @param string $variantIdentifier
     * @param string $fallbackIdentifier
     * @param array $contentDimensionValues
     * @param string $strategy
     */
    public function __construct(
        string $variantIdentifier,
        string $fallbackIdentifier,
        array $contentDimensionValues,
        string $strategy
    ) {
        $this->variantIdentifier = $variantIdentifier;
        $this->fallbackIdentifier = $fallbackIdentifier;
        $this->contentDimensionValues = $contentDimensionValues;
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
    public function getContentDimensionValues(): array
    {
        return $this->contentDimensionValues;
    }

    /**
     * @return string
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }
}
