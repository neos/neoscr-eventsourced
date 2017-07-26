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

use Neos\Flow\Annotations as Flow;

/**
 * The Doctrine DBAL node finder
 *
 * @Flow\Scope("singleton")
 */
class NodeFinder extends AbstractDbalFinder
{
    /**
     * @param string $identifierInGraph
     * @return Node|null
     */
    public function findOneByIdentifierInGraph(string $identifierInGraph)
    {
        $nodeData = $this->getDatabaseConnection()->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n
 WHERE identifieringraph = :identifierInGraph',
            [
                'identifierInGraph' => $identifierInGraph,
            ]
        )->fetch();

        return $nodeData ? $this->mapRawDataToNode($nodeData) : null;
    }

    /**
     * @param string $subgraphIdentifier
     * @param string $identifierInSubgraph
     * @return Node|null
     */
    public function findInSubgraphByIdentifierInSubgraph(string $subgraphIdentifier, string $identifierInSubgraph)
    {
        $nodeData = $this->getDatabaseConnection()->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n
 INNER JOIN neos_contentrepository_projection_hierarchyedge h ON h.childnodesidentifieringraph = n.identifieringraph
 WHERE n.identifierinsubgraph = :identifierInSubgraph
 AND h.subgraphidentifier = :subgraphIdentifier',
            [
                'identifierInSubgraph' => $identifierInSubgraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetch();

        return $nodeData ? $this->mapRawDataToNode($nodeData) : null;
    }

    /**
     * @param string $subgraphIdentifier
     * @param string $parentIdentifierInGraph
     * @return array|Node[]
     */
    public function findInSubgraphByParentIdentifierInGraph(string $subgraphIdentifier, string $parentIdentifierInGraph): array
    {
        $result = [];
        foreach ($this->getDatabaseConnection()->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n
 INNER JOIN neos_contentrepository_projection_hierarchyedge h ON h.childnodesidentifieringraph = n.identifieringraph
 WHERE h.parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND h.subgraphidentifier = :subgraphIdentifier
 ORDER BY h.position',
            [
                'parentNodesIdentifierInGraph' => $parentIdentifierInGraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetchAll() as $nodeData) {
            $result[] = $this->mapRawDataToNode($nodeData);
        }

        return $result;
    }

    /**
     * @param string $subgraphIdentifier
     * @param string $nodeTypeName
     * @return array|Node[]
     */
    public function findInSubgraphByType(string $subgraphIdentifier, string $nodeTypeName)
    {
        $result = [];
        foreach ($this->getDatabaseConnection()->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n
 INNER JOIN neos_contentrepository_projection_hierarchyedge h ON h.childnodesidentifieringraph = n.identifieringraph
 WHERE n.nodetypename = :nodeTypeName
 AND h.subgraphidentifier = :subgraphIdentifier',
            [
                'nodeTypeName' => $nodeTypeName,
                'subgraphIdentifier' => $subgraphIdentifier
            ]
        )->fetchAll() as $nodeData) {
            $result[] = $this->mapRawDataToNode($nodeData);
        }

        return $result;
    }


    protected function mapRawDataToNode(array $nodeData): Node
    {
        return new Node(
            $nodeData['identifieringraph'],
            $nodeData['identifierinsubgraph'],
            $nodeData['subgraphidentifier'],
            new PropertyCollection(json_decode($nodeData['properties'], true)),
            $nodeData['nodetypename']
        );
    }
}
