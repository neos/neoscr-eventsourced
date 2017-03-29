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

/**
 * The hierarchy edge finder
 *
 * @Flow\Scope("singleton")
 */
class HierarchyEdgeFinder extends AbstractReadModelFinder
{
    /**
     * @param string $childNodesIdentifierInGraph
     * @param array $subgraphIdentifiers
     * @return array|HierarchyEdge[]
     */
    public function findInboundByNodeAndSubgraphs(string $childNodesIdentifierInGraph, array $subgraphIdentifiers): array
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
     * @return array|HierarchyEdge[]
     */
    public function findOutboundByNodeAndSubgraphs(string $parentNodesIdentifierInGraph, array $subgraphIdentifiers): array
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

    /**
     * @param string $childNodesIdentifierInGraph
     * @return array|HierarchyEdge[]
     */
    public function findByChildNodeIdentifierInGraph(string $childNodesIdentifierInGraph): array
    {
        $result = [];

        $edgeRecords = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE childnodesidentifieringraph = :childNodesIdentifierInGraph',
            [
                'childNodesIdentifierInGraph' => $childNodesIdentifierInGraph
            ]
        )->fetchAll();

        foreach ($edgeRecords as $edgeRecord) {
            $result[] = $this->mapRawDataToEdge($edgeRecord);
        }

        return $result;
    }

    public function findConnectingInSubgraph(string $parentNodesIdentifierInGraph, string $childNodesIdentifierInGraph, string $subgraphIdentifier)
    {
        $edgeRecord = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND childnodesidentifieringraph = :childNodesIdentifierInGraph
 AND subgraphidentifier = :subgraphIdentifier',
            [
                'parentNodesIdentifierInGraph' => $parentNodesIdentifierInGraph,
                'childNodesIdentifierInGraph' => $childNodesIdentifierInGraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetch();

        return $edgeRecord ? $this->mapRawDataToEdge($edgeRecord) : null;
    }

    /**
     * @param string $parentNodesIdentifierInGraph
     * @param string $subgraphIdentifier
     * @return HierarchyEdge|null
     */
    public function findEldestSibling(string $parentNodesIdentifierInGraph, string $subgraphIdentifier)
    {
        $edgeRecord = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT MIN(h.position) AS minimalPosition, h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND subgraphIdentifier = :subgraphIdentifier',
            [
                'parentNodesIdentifierInGraph' => $parentNodesIdentifierInGraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetch();

        return $edgeRecord ? $this->mapRawDataToEdge($edgeRecord) : null;
    }

    /**
     * @param string $parentNodesIdentifierInGraph
     * @param int $position
     * @param string $subgraphIdentifier
     * @return HierarchyEdge|null
     */
    public function findEldestYoungerSibling(string $parentNodesIdentifierInGraph, int $position, string $subgraphIdentifier)
    {
        $edgeRecord = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT MIN(h.position) AS minimalPosition, h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND subgraphIdentifier = :subgraphIdentifier
 AND position > :position',
            [
                'parentNodesIdentifierInGraph' => $parentNodesIdentifierInGraph,
                'position' => $position,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetch();

        return $edgeRecord ? $this->mapRawDataToEdge($edgeRecord) : null;
    }

    /**
     * @param string $parentNodesIdentifierInGraph
     * @param string $subgraphIdentifier
     * @return array|HierarchyEdge[]
     */
    public function findOrderedOutboundByParentNodeAndSubgraph(string $parentNodesIdentifierInGraph, string $subgraphIdentifier): array
    {
        $result = [];

        $edgeRecords = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND subgraphIdentifier = :subgraphIdentifier
 ORDER BY position ASC',
            [
                'parentNodesIdentifierInGraph' => $parentNodesIdentifierInGraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetchAll();

        foreach ($edgeRecords as $edgeRecord) {
            $result[] = $this->mapRawDataToEdge($edgeRecord);
        }

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
