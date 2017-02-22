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

use Neos\EventSourcing\Projection\Doctrine\AbstractDoctrineFinder;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryResultInterface;

/**
 * The doctrine hierarchy edge finder
 *
 * @method QueryResultInterface|HierarchyEdge[] findByChildNodeIdentifierInGraph(string $childNodeIdentifierInGraph)
 *
 * @Flow\Scope("singleton")
 */
class HierarchyEdgeFinder extends AbstractDoctrineFinder
{
    /**
     * @param string $childNodeIdentifierInGraph
     * @param array $subgraphIdentifiers
     * @return QueryResultInterface|HierarchyEdge[]
     */
    public function findInboundByNodeAndSubgraphs(string $childNodeIdentifierInGraph, array $subgraphIdentifiers)
    {
        $query = $this->createQuery();

        return $query->matching($query->logicalAnd([
            $query->equals('childNodeIdentifierInGraph', $childNodeIdentifierInGraph),
            $query->in('subgraphIdentifier', $subgraphIdentifiers)
        ]))->execute();
    }

    /**
     * @param string $parentNodeIdentifierInGraph
     * @param array $subgraphIdentifiers
     * @return QueryResultInterface|HierarchyEdge[]
     */
    public function findOutboundByNodeAndSubgraphs(string $parentNodeIdentifierInGraph, array $subgraphIdentifiers)
    {
        $query = $this->createQuery();

        return $query->matching($query->logicalAnd([
            $query->equals('parentNodeIdentifierInGraph', $parentNodeIdentifierInGraph),
            $query->in('subgraphIdentifier', $subgraphIdentifiers)
        ]))->execute();
    }
}
