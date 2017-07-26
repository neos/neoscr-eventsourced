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
 * A node reference was added
 */
class NodeReferenceWasAdded extends AbstractDimensionAwareEvent
{
    /**
     * @var string
     */
    protected $referencedNodeIdentifier;

    /**
     * @var string
     */
    protected $referencingNodeIdentifier;

    /**
     * @var string
     */
    protected $referenceName;

    /**
     * @var string
     */
    protected $olderSiblingNodeIdentifier;


    /**
     * @param string $referencingNodeIdentifier
     * @param string $referencedNodeIdentifier
     * @param array $contentDimensionValues
     * @param string $referenceName
     * @param string $olderSiblingNodeIdentifier
     */
    public function __construct(
        string $referencingNodeIdentifier,
        string $referencedNodeIdentifier,
        array $contentDimensionValues,
        string $referenceName,
        string $olderSiblingNodeIdentifier = null
    ) {
        parent::__construct($contentDimensionValues);
        $this->referencingNodeIdentifier = $referencingNodeIdentifier;
        $this->referencedNodeIdentifier = $referencedNodeIdentifier;
        $this->referenceName = $referenceName;
        $this->olderSiblingNodeIdentifier = $olderSiblingNodeIdentifier;
    }


    /**
     * @return string
     */
    public function getReferencedNodeIdentifier(): string
    {
        return $this->referencedNodeIdentifier;
    }

    /**
     * @return string
     */
    public function getReferencingNodeIdentifier(): string
    {
        return $this->referencingNodeIdentifier;
    }

    /**
     * @return string
     */
    public function getReferenceName(): string
    {
        return $this->referenceName;
    }

    /**
     * @return string|null
     */
    public function getOlderSiblingNodeIdentifier()
    {
        return $this->olderSiblingNodeIdentifier;
    }

    public static function fromPayload(array $payload)
    {
    }
}
