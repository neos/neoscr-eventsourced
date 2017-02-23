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

/**
 * The doctrine node finder
 *
 * @Flow\Scope("singleton")
 */
class NodeFinder extends AbstractDoctrineFinder
{
    /**
     * @param string $identifierInGraph
     * @return Node|null
     */
    public function findOneByIdentifierInGraph(string $identifierInGraph)
    {
        $query = $this->createQuery();
        $connection = $query->getQueryBuilder()->getEntityManager()->getConnection();

        $nodeData = $connection->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n 
WHERE n.identifieringraph = :identifierInGraph',
            [
                'identifierInGraph' => $identifierInGraph,
            ])->fetch();

        return $nodeData ? $this->mapRawDataToNode($nodeData) : null;
    }

    /**
     * @param string $subgraphIdentifier
     * @param string $identifierInSubgraph
     * @return Node|null
     */
    public function findInSubgraphByIdentifierInSubgraph(string $subgraphIdentifier, string $identifierInSubgraph)
    {
        $query = $this->createQuery();
        $connection = $query->getQueryBuilder()->getEntityManager()->getConnection();

        $nodeData = $connection->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n 
INNER JOIN neos_contentrepository_projection_hierarchyedge h ON h.childnodesidentifieringraph = n.identifieringraph
WHERE n.identifierinsubgraph = :identifierInSubgraph AND h.subgraphidentifier = :subgraphIdentifier',
            [
                'identifierInSubgraph' => $identifierInSubgraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ])->fetch();

        return $nodeData ? $this->mapRawDataToNode($nodeData) : null;
    }

    /**
     * @param string $subgraphIdentifier
     * @param string $parentIdentifierInGraph
     * @return array|Node[]
     */
    public function findInSubgraphByParentIdentifierInGraph(string $subgraphIdentifier, string $parentIdentifierInGraph): array
    {
        $query = $this->createQuery();
        $connection = $query->getQueryBuilder()->getEntityManager()->getConnection();

        $result = [];
        foreach ($connection->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n 
INNER JOIN neos_contentrepository_projection_hierarchyedge h ON h.childnodesidentifieringraph = n.identifieringraph
WHERE h.parentnodesidentifieringraph = :parentNodesIdentifierInGraph AND h.subgraphidentifier = :subgraphIdentifier',
            [
                'parentNodesIdentifierInGraph' => $parentIdentifierInGraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ])->fetchAll() as $nodeData) {

            $result[] = $this->mapRawDataToNode($nodeData);
        }

        return $result;
    }

    protected function mapRawDataToNode(array $nodeData): Node
    {
        $node = new Node();
        $node->identifierInGraph = $nodeData['identifieringraph'];
        $node->identifierInSubgraph = $nodeData['identifierinsubgraph'];
        $node->subgraphIdentifier = $nodeData['subgraphidentifier'];
        $node->properties = json_decode($nodeData['properties'], true);
        $node->nodeTypeName = $nodeData['nodetypename'];

        return $node;
    }
}
