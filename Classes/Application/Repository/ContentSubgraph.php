<?php

namespace Neos\ContentRepository\EventSourced\Application\Repository;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\EventSourced\Application\Projection\Doctrine\ContentGraph\HierarchyEdgeFinder;
use Neos\ContentRepository\EventSourced\Application\Projection\Doctrine\ContentGraph\Node;
use Neos\ContentRepository\EventSourced\Application\Projection\Doctrine\ContentGraph\NodeFinder;
use Neos\Flow\Annotations as Flow;

/**
 * The content subgraph application repository
 *
 * To be used as a read-only source of nodes
 *
 * @api
 */
class ContentSubgraph
{
    /**
     * @Flow\Inject
     * @var NodeFinder
     */
    protected $nodeFinder;

    /**
     * @Flow\Inject
     * @var HierarchyEdgeFinder
     */
    protected $hierarchyEdgeFinder;

    /**
     * @var string
     */
    protected $identifier;


    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }


    public function findNodeByIdentifier(string $nodeIdentifier): Node
    {
        return $this->nodeFinder->findInSubgraphByIdentifierInSubgraph($this->identifier, $nodeIdentifier);
    }

    /**
     * @param string $parentIdentifier
     * @return array|Node[]
     */
    public function findNodesByParent(string $parentIdentifier): array
    {
        return $this->nodeFinder->findInSubgraphByParentIdentifierInGraph($this->identifier, $parentIdentifier);
    }

    /**
     * @param string $nodeTypeName
     * @return array|Node[]
     */
    public function findNodesByType(string $nodeTypeName): array
    {
        return $this->nodeFinder->findInSubgraphByType($this->identifier, $nodeTypeName);
    }

    public function traverse(Node $parent, callable $callback)
    {
        $callback($parent);
        foreach ($this->findNodesByParent($parent->identifierInGraph) as $childNode) {
            $this->traverse($childNode, $callback);
        }
    }
}
