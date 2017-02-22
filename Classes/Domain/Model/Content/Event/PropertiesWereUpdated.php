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
 * Properties were inserted
 */
class PropertiesWereUpdated implements EventInterface
{
    /**
     * @var string
     */
    protected $variantIdentifier;

    /**
     * @var string
     */
    protected $nodeIdentifier;

    /**
     * @var array
     */
    protected $properties;


    public function __construct(
        string $variantIdentifier,
        string $nodeIdentifier,
        array $properties
    ) {
        $this->variantIdentifier = $variantIdentifier;
        $this->nodeIdentifier = $nodeIdentifier;
        $this->properties = $properties;
    }


    public function getVariantIdentifier(): string
    {
        return $this->variantIdentifier;
    }

    public function getNodeIdentifier(): string
    {
        return $this->nodeIdentifier;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }
}
