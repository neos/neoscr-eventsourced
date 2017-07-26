<?php

namespace Neos\ContentRepository\EventSourced\TypeConverter;

/*
 * This file is part of the Neos.EventSourcing package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\EventSourced\Domain\Model\Content\Event\AbstractDimensionAwareEvent;
use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;

/**
 * Simple TypeConverter that can turn instances of EventInterface to an array that contains all properties recursively
 */
class ArrayToEventConverter extends AbstractTypeConverter
{
    /**
     * @var array<string>
     */
    protected $sourceTypes = ['array'];

    /**
     * @var string
     */
    protected $targetType = AbstractDimensionAwareEvent::class;

    /**
     * @var integer
     */
    protected $priority = 1;


    public function convertFrom($source, $targetType, array $subProperties = array(), PropertyMappingConfigurationInterface $configuration = null)
    {
        return $targetType::fromPayload($source);
    }
}
