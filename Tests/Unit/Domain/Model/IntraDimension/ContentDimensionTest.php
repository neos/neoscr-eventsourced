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
use Neos\Utility\ObjectAccess;

/**
 * Test cases for content dimensions
 */
class ContentDimensionTest extends UnitTestCase
{
    /**
     * @test
     */
    public function createValueRegistersCreatedValue()
    {
        $dimension = new IntraDimension\ContentDimension('test', IntraDimension\ContentDimension::SOURCE_PRESET_SOURCE);
        $testValue = $dimension->createValue('test');

        $this->assertSame($testValue, $dimension->getValue('test'));
    }

    /**
     * @test
     */
    public function createValueWithoutFallbackDoesNotIncreaseDepth()
    {
        $dimension = new IntraDimension\ContentDimension('test', IntraDimension\ContentDimension::SOURCE_PRESET_SOURCE);
        $dimension->createValue('test');

        $this->assertSame(0, $dimension->getDepth());
    }

    /**
     * @test
     */
    public function createValueWithFallbackDoesNotDecreaseDepth()
    {
        $testDepth = random_int(1, 100);
        $dimension = new IntraDimension\ContentDimension('test', IntraDimension\ContentDimension::SOURCE_PRESET_SOURCE);
        ObjectAccess::setProperty($dimension, 'depth', $testDepth, true);
        $fallbackValue = $dimension->createValue('fallback');
        $dimension->createValue('test', $fallbackValue);

        $this->assertSame($testDepth, $dimension->getDepth());
    }

    /**
     * @test
     */
    public function createValueWithFallbackIncreasesDepthIfFallbackHasCurrentMaximumDepth()
    {
        $testDepth = random_int(0, 100);
        $dimension = new IntraDimension\ContentDimension('test', IntraDimension\ContentDimension::SOURCE_PRESET_SOURCE);
        ObjectAccess::setProperty($dimension, 'depth', $testDepth, true);
        $fallbackValue = $dimension->createValue('fallback');
        ObjectAccess::setProperty($fallbackValue, 'depth', $testDepth, true);
        $dimension->createValue('test', $fallbackValue);

        $this->assertSame($testDepth + 1, $dimension->getDepth());
    }

    /**
     * @test
     */
    public function getRootValuesOnlyReturnsValuesOfDepthZero()
    {
        $testDepth = random_int(1, 100);
        $dimension = new IntraDimension\ContentDimension('test', IntraDimension\ContentDimension::SOURCE_PRESET_SOURCE);
        $depthZeroValue = $dimension->createValue('depthZero');
        $depthGreaterZeroValue = $dimension->createValue('depthGreaterZero');
        ObjectAccess::setProperty($depthGreaterZeroValue, 'depth', $testDepth, true);

        $this->assertContains($depthZeroValue, $dimension->getRootValues());
        $this->assertNotContains($depthGreaterZeroValue, $dimension->getRootValues());
    }
}
