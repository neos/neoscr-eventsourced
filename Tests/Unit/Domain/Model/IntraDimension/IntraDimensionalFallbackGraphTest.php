<?php
namespace Neos\ContentRepository\EventSourced\Tests\Unit\Domain\Model\IntraDimension;

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
use Neos\Flow\Tests\UnitTestCase;

/**
 * Test cases for the intra dimensional fallback graph
 */
class IntraDimensionalFallbackGraphTest extends UnitTestCase
{
    /**
     * @test
     */
    public function createDimensionRegistersDimension()
    {
        $graph = new IntraDimension\IntraDimensionalFallbackGraph();
        $dimension = $graph->createDimension('test');

        $this->assertSame($dimension, $graph->getDimension('test'));
    }
}
