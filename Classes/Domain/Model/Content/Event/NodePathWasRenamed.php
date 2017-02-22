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
 * A node path was renamed
 */
class NodePathWasRenamed implements EventInterface
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
     * @var string
     */
    protected $newPath;


    /**
     * @param string $variantIdentifier
     * @param string $nodeIdentifier
     * @param string $newPath
     */
    public function __construct(
        string $variantIdentifier,
        string $nodeIdentifier,
        string $newPath
    ) {
        $this->variantIdentifier = $variantIdentifier;
        $this->nodeIdentifier = $nodeIdentifier;
        $this->newPath = $newPath;
    }


    public function getVariantIdentifier(): string
    {
        return $this->variantIdentifier;
    }

    public function getNodeIdentifier(): string
    {
        return $this->nodeIdentifier;
    }

    public function getNewPath(): string
    {
        return $this->newPath;
    }
}
