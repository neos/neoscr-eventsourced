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

use Doctrine\DBAL\Connection;
use Neos\Flow\Annotations as Flow;

/**
 * The Doctrine DBAL hierarchy edge finder
 *
 * @Flow\Scope("singleton")
 */
class HierarchyEdgeFinder extends AbstractDbalFinder
{
    /**
     * @param string $childNodesIdentifierInGraph
     * @param array $subgraphIdentifiers
     * @return array|HierarchyEdge[]
     */
    public function findInboundByNodeAndSubgraphs(string $childNodesIdentifierInGraph, array $subgraphIdentifiers): array
    {
        $edges = [];
        foreach ($this->getDatabaseConnection()->executeQuery(
            'SELECT h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE childnodesidentifieringraph = :childNodesIdentifierInGraph
 AND subgraphidentifier IN (:subgraphIdentifiers)',
            [
                'childNodesIdentifierInGraph' => $childNodesIdentifierInGraph,
                'subgraphIdentifiers' => $subgraphIdentifiers
            ],
            [
                'subgraphIdentifiers' => Connection::PARAM_STR_ARRAY
            ]
        )->fetchAll() as $edgeData) {
            $edges[] = $this->mapRawDataToEdge($edgeData);
        }

        return $edges;
    }

    /**
     * @param string $parentNodesIdentifierInGraph
     * @param array $subgraphIdentifiers
     * @return array|HierarchyEdge[]
     */
    public function findOutboundByNodeAndSubgraphs(string $parentNodesIdentifierInGraph, array $subgraphIdentifiers): array
    {
        $edges = [];
        foreach ($this->getDatabaseConnection()->executeQuery(
            'SELECT h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND subgraphidentifier IN (:subgraphIdentifiers)',
            [
                'parentNodesIdentifierInGraph' => $parentNodesIdentifierInGraph,
                'subgraphIdentifiers' => $subgraphIdentifiers
            ],
            [
                'subgraphIdentifiers' => Connection::PARAM_STR_ARRAY
            ]
        )->fetchAll() as $edgeData) {
            $edges[] = $this->mapRawDataToEdge($edgeData);
        }

        return $edges;
    }

    /**
     * @param string $childNodesIdentifierInGraph
     * @param string $subgraphIdentifier
     * @return HierarchyEdge|null
     */
    public function findInboundByNodeAndSubgraph(string $childNodesIdentifierInGraph, string $subgraphIdentifier)
    {
        $edgeData = $this->getDatabaseConnection()->executeQuery(
            'SELECT h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE childnodesidentifieringraph = :childNodesIdentifierInGraph
 AND subgraphidentifier = :subgraphIdentifier',
            [
                'childNodesIdentifierInGraph' => $childNodesIdentifierInGraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetch();

        return $edgeData ? $this->mapRawDataToEdge($edgeData) : null;
    }

    /**
     * @param string $parentNodesIdentifierInGraph
     * @param string $subgraphIdentifier
     * @return array|HierarchyEdge[]
     */
    public function findOutboundByNodeAndSubgraph(string $parentNodesIdentifierInGraph, string $subgraphIdentifier): array
    {
        $edges = [];
        foreach ($this->getDatabaseConnection()->executeQuery(
            'SELECT h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND subgraphidentifier = :subgraphIdentifier',
            [
                'parentNodesIdentifierInGraph' => $parentNodesIdentifierInGraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetchAll() as $edgeData) {
            $edges[] = $this->mapRawDataToEdge($edgeData);
        }

        return $edges;
    }

    /**
     * @param HierarchyEdge $edge
     * @return HierarchyEdge|null
     */
    public function findYoungerSibling(HierarchyEdge $edge)
    {
        $edgeData = $this->getDatabaseConnection()->executeQuery(
            'SELECT h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND subgraphidentifier = :subgraphIdentifier
 AND `position` > :position
 ORDER BY `position` ASC',
            [
                'parentNodesIdentifierInGraph' => $edge->parentNodesIdentifierInGraph,
                'subgraphIdentifier' => $edge->subgraphIdentifier,
                'position' => $edge->position
            ]
        )->fetch();

        return $edgeData ? $this->mapRawDataToEdge($edgeData) : null;
    }

    public function findEldestSibling(string $parentNodeIdentifierInGraph, string $subgraphIdentifier)
    {
        $edgeData = $this->getDatabaseConnection()->executeQuery(
            'SELECT h.* FROM neos_contentrepository_projection_hierarchyedge h
 WHERE parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND subgraphidentifier = :subgraphIdentifier
 ORDER BY `position` ASC',
            [
                'parentNodesIdentifierInGraph' => $parentNodeIdentifierInGraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetch();

        return $edgeData ? $this->mapRawDataToEdge($edgeData) : null;
    }

    protected function mapRawDataToEdge(array $rawData): HierarchyEdge
    {
        return new HierarchyEdge(
            $rawData['parentnodesidentifieringraph'],
            $rawData['subgraphidentifier'],
            $rawData['childnodesidentifieringraph'],
            $rawData['position']
        );
    }
}
