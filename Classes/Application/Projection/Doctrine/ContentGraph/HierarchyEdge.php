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
     * @var int
     */
    public $position;


    public function __construct(string $parentNodesIdentifierInGraph, string $childNodesIdentifierInGraph, string $subgraphIdentifier, int $position)
    {
        $this->parentNodesIdentifierInGraph = $parentNodesIdentifierInGraph;
        $this->subgraphIdentifier = $subgraphIdentifier;
        $this->childNodesIdentifierInGraph = $childNodesIdentifierInGraph;
        $this->position = $position;
    }


    public static function asConnection(Node $parentNode, Node $childNode, ContentSubgraph $contentSubgraph, int $position)
    {
        return new HierarchyEdge(
            $parentNode->identifierInGraph,
            $childNode->identifierInGraph,
            $contentSubgraph->getIdentityHash(),
            $position
        );
    }
}
