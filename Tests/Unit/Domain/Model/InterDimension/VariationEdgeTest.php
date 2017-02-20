<?php
namespace Neos\ContentRepository\EventSourced\Tests\Unit\Domain\Model\InterDimension;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\EventSourced\Domain\Model\InterDimension;
use Neos\ContentRepository\EventSourced\Domain\Model\IntraDimension;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Tests\UnitTestCase;

/**
 * Test cases for variation edges
 */
class VariationEdgeTest extends UnitTestCase
{
    /**
     * @test
     */
    public function variationEdgesAreRegisteredInFallbackAndVariantUponCreation()
    {
        $variant = new InterDimension\ContentSubgraph(['test' => new IntraDimension\ContentDimensionValue('a')]);
        $fallback = new InterDimension\ContentSubgraph(['test' => new IntraDimension\ContentDimensionValue('b')]);

        $variationEdge = new InterDimension\VariationEdge($variant, $fallback, [1]);

        $this->assertContains($variationEdge, $variant->getFallbackEdges());
        $this->assertContains($variationEdge, $fallback->getVariantEdges());
    }
}
