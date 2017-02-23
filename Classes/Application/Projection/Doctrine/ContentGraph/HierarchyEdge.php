<?php
namespace Neos\ContentRepository\EventSourced\Application\Projection\Doctrine\ContentGraph;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\EventSourced\Domain\Model\InterDimension\ContentSubgraph;
use Neos\Flow\Annotations as Flow;

/**
 * General purpose Hierarchy Edge read model
 *
 * @Flow\Entity
 */
class HierarchyEdge
{
    /**
     * @var string
     */
    public $parentNodesIdentifierInGraph;

    /**
     * @var string
     */
    public $subgraphIdentifier;

    /**
     * @var string
     */
    public $childNodesIdentifierInGraph;

    /**
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $position;


    public function connect(Node $parentNode, Node $childNode, ContentSubgraph $contentSubgraph)
    {
        $this->parentNodesIdentifierInGraph = $parentNode->identifierInGraph;
        $this->subgraphIdentifier = $contentSubgraph->getIdentityHash();
        $this->childNodesIdentifierInGraph = $childNode->identifierInGraph;
    }
}
