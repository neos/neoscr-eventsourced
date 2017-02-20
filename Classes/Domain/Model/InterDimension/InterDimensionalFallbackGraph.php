<?php
namespace Neos\ContentRepository\EventSourced\Domain\Model\InterDimension;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\EventSourced\Domain\Model\IntraDimension;
use Neos\Flow\Annotations as Flow;

/**
 * The inter dimensional fallback graph domain model
 * Represents the fallback mechanism between content subgraphs
 * @api
 */
class InterDimensionalFallbackGraph
{
    /**
     * @var array
     */
    protected $subgraphs = [];

    /**
     * @var array|IntraDimension\ContentDimension[]
     */
    protected $prioritizedContentDimensions = [];


    public function __construct(array $prioritizedContentDimensions)
    {
        $this->prioritizedContentDimensions = $prioritizedContentDimensions;
    }


    public function createContentSubgraph(array $dimensionValues): ContentSubgraph
    {
        $subgraph = new ContentSubgraph($dimensionValues);
        $this->subgraphs[$subgraph->getIdentityHash()] = $subgraph;

        return $subgraph;
    }

    public function connectSubgraphs(ContentSubgraph $variant, ContentSubgraph $fallback)
    {
        new VariationEdge($variant, $fallback, $this->calculateFallbackWeight($variant, $fallback));
    }

    public function calculateFallbackWeight(ContentSubgraph $variant, ContentSubgraph $fallback)
    {
        $weight = [];
        foreach ($this->prioritizedContentDimensions as $contentDimension) {
            $weight[$contentDimension->getName()] = $variant->getDimensionValue($contentDimension->getName())
                ->calculateFallbackDepth($fallback->getDimensionValue($contentDimension->getName())
            );
        }

        return $weight;
    }

    public function normalizeWeight(VariationEdge $variationEdge): int
    {
        $base = $this->determineWeightNormalizationBase();
        $normalizedWeight = 0;
        $exponent = 0;
        foreach (array_reverse($variationEdge->getWeight()) as $dimensionName => $dimensionFallbackWeight) {
            $normalizedWeight += pow($base, $exponent) * $dimensionFallbackWeight;
            $exponent++;
        }

        return $normalizedWeight;
    }

    public function determineWeightNormalizationBase(): int
    {
        $base = 0;
        foreach ($this->prioritizedContentDimensions as $contentDimension) {
            $base = max($base, $contentDimension->getDepth() + 1);
        }

        return $base;
    }

    /**
     * @param ContentSubgraph $contentSubgraph
     * @return ContentSubgraph|null
     */
    public function getPrimaryFallback(ContentSubgraph $contentSubgraph)
    {
        $fallbackEdges = $contentSubgraph->getFallbackEdges();
        if (empty($fallbackEdges)) {
            return null;
        }

        uasort($fallbackEdges, function ($edgeA, $edgeB) {
            return $this->normalizeWeight($edgeA) <=> $this->normalizeWeight($edgeB);
        });

        return reset($fallbackEdges)->getFallback();
    }

    /**
     * @return array|ContentSubgraph[]
     */
    public function getSubgraphs(): array
    {
        return $this->subgraphs;
    }

    /**
     * @param string $identityHash
     * @return ContentSubgraph|null
     */
    public function getSubgraph(string $identityHash)
    {
        return $this->subgraphs[$identityHash] ?: null;
    }
}
