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
 * A node was moved
 */
class NodeWasMoved extends AbstractDimensionAwareEvent
{
    const STRATEGY_CASCADE_TO_CHILD_VARIANTS = 'cascadeToChildVariants';
    const STRATEGY_CASCADE_TO_ALL_VARIANTS = 'cascadeToAllVariants';

    /**
     * @var string
     */
    protected $variantIdentifier;

    /**
     * @todo Do we even need this?
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $newParentVariantIdentifier;

    /**
     * @var string
     */
    protected $newOlderSiblingVariantIdentifier;

    /**
     * @var string
     */
    protected $strategy;


    /**
     * @param string $variantIdentifier
     * @param string $identifier
     * @param array $contentDimensionValues
     * @param string $newParentVariantIdentifier
     * @param string $newOlderSiblingVariantIdentifier
     * @param string $strategy
     */
    public function __construct(
        string $variantIdentifier,
        string $identifier,
        array $contentDimensionValues,
        string $newParentVariantIdentifier,
        string $newOlderSiblingVariantIdentifier,
        string $strategy
    ) {
        parent::__construct($contentDimensionValues);
        $this->variantIdentifier = $variantIdentifier;
        $this->identifier = $identifier;
        $this->newParentVariantIdentifier = $newParentVariantIdentifier;
        $this->newOlderSiblingVariantIdentifier = $newOlderSiblingVariantIdentifier;
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
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return string
     */
    public function getNewParentVariantIdentifier(): string
    {
        return $this->newParentVariantIdentifier;
    }

    /**
     * @return string
     */
    public function getNewOlderSiblingVariantIdentifier(): string
    {
        return $this->newOlderSiblingVariantIdentifier;
    }

    /**
     * @return string
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }
}
