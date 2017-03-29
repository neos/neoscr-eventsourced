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

use Neos\ContentRepository\EventSourced\Application\Persistence\AbstractReadModelFinder;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryResultInterface;

/**
 * The hierarchy edge finder
 *
 * @method HierarchyEdge[] findByChildNodeIdentifierInGraph(string $childNodeIdentifierInGraph)
 *
 * @Flow\Scope("singleton")
 */
class HierarchyEdgeFinder extends AbstractReadModelFinder
{
    /**
     * @param string $childNodesIdentifierInGraph
     * @param array $subgraphIdentifiers
     * @return QueryResultInterface|HierarchyEdge[]
     */
    public function findInboundByNodeAndSubgraphs(string $childNodesIdentifierInGraph, array $subgraphIdentifiers)
    {
        $queryResult = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT n.* FROM ' . $this->getReadModelTableName() . ' n
 WHERE childNodesIdentifierInGraph = :childNodesIdentifierInGraph
 AND subgraphIdentifier IN ("' . implode('","', $subgraphIdentifiers) . '")',
            ['childNodesIdentifierInGraph' => $childNodesIdentifierInGraph]
        )->fetchAll();

        $result = array_map(function ($edgeData) {
            return $this->mapRawDataToEntity($edgeData);
        }, $queryResult);

        return $result;
    }

    /**
     * @param string $parentNodesIdentifierInGraph
     * @param array $subgraphIdentifiers
     * @return HierarchyEdge[]
     */
    public function findOutboundByNodeAndSubgraphs(string $parentNodesIdentifierInGraph, array $subgraphIdentifiers)
    {
        $queryResult = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT n.* FROM ' . $this->getReadModelTableName() . ' n
 WHERE parentNodesIdentifierInGraph = :parentNodesIdentifierInGraph
 AND subgraphIdentifier IN ("' . implode('","', $subgraphIdentifiers) . '")',
            ['parentNodesIdentifierInGraph' => $parentNodesIdentifierInGraph]
        )->fetchAll();

        $result = array_map(function ($edgeData) {
            return $this->mapRawDataToEntity($edgeData);
        }, $queryResult);

        return $result;
            }

    protected function getReadModelTableName()
    {
        return HierarchyEdge::getTableName();
    }

    protected function mapRawDataToEntity(array $entityData)
    {
        $edge = new HierarchyEdge();

        $edge->parentNodesIdentifierInGraph = $entityData['parentnodesidentifieringraph'];
        $edge->subgraphIdentifier = $entityData['subgraphidentifier'];
        $edge->childNodesIdentifierInGraph = $entityData['childnodesidentifieringraph'];

        return $edge;
    }
}
