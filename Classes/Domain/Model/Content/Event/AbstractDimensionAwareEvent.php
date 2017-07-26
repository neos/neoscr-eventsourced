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
 * Something related to content dimensions has happened
 */
abstract class AbstractDimensionAwareEvent implements EventInterface
{
    /**
     * @var array<string>
     */
    protected $contentDimensionValues = [];


    /**
     * @param array $contentDimensionValues
     */
    public function __construct(array $contentDimensionValues)
    {
        $this->contentDimensionValues = $contentDimensionValues;
    }

    abstract public static function fromPayload(array $payload);


    public function getContentDimensionValues(): array
    {
        return $this->contentDimensionValues;
    }
}
