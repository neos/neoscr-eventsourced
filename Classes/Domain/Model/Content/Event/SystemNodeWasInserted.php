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
 * A system node was inserted
 */
class SystemNodeWasInserted implements EventInterface
{
    /**
     * @var string
     */
    protected $variantIdentifier;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $nodeType;


    /**
     * @param string $variantIdentifier
     * @param string $identifier
     * @param string $nodeType
     */
    public function __construct(
        string $variantIdentifier,
        string $identifier,
        string $nodeType
    ) {
        $this->variantIdentifier = $variantIdentifier;
        $this->identifier = $identifier;
        $this->nodeType = $nodeType;
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
    public function getNodeType(): string
    {
        return $this->nodeType;
    }
}
