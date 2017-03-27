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

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Neos\Flow\Annotations as Flow;

/**
 * The Doctrine DBAL node finder
 *
 * @Flow\Scope("singleton")
 */
class NodeFinder
{
    /**
     * @Flow\Inject(lazy=FALSE)
     * @var ObjectManager
     */
    protected $entityManager;


    /**
     * @param string $identifierInGraph
     * @param NodeFinderQueryConstraints $constraints
     * @return Node|null
     */
    public function findOneByIdentifierInGraph(string $identifierInGraph, NodeFinderQueryConstraints $constraints)
    {
        $nodeData = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n
 WHERE identifieringraph = :identifierInGraph' . $constraints,
            array_merge([
                'identifierInGraph' => $identifierInGraph,
            ], $constraints->__toArray())
        )->fetch();

        return $nodeData ? $this->mapRawDataToNode($nodeData) : null;
    }

    /**
     * @param string $subgraphIdentifier
     * @param string $identifierInSubgraph
     * @param NodeFinderQueryConstraints $constraints
     * @return Node|null
     */
    public function findInSubgraphByIdentifierInSubgraph(string $subgraphIdentifier, string $identifierInSubgraph, NodeFinderQueryConstraints $constraints)
    {
        $nodeData = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n
 INNER JOIN neos_contentrepository_projection_hierarchyedge h ON h.childnodesidentifieringraph = n.identifieringraph
 WHERE n.identifierinsubgraph = :identifierInSubgraph
 AND h.subgraphidentifier = :subgraphIdentifier' . $constraints,
            array_merge([
                'identifierInSubgraph' => $identifierInSubgraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ], $constraints->__toArray())
        )->fetch();

        return $nodeData ? $this->mapRawDataToNode($nodeData) : null;
    }

    /**
     * @param string $subgraphIdentifier
     * @param string $parentIdentifierInGraph
     * @return array|Node[]
     */
    public function findInSubgraphByParentIdentifierInGraph(string $subgraphIdentifier, string $parentIdentifierInGraph, NodeFinderQueryConstraints $constraints): array
    {
        $result = [];
        foreach ($this->getEntityManager()->getConnection()->executeQuery(
            'SELECT n.* FROM neos_contentrepository_projection_node n
 INNER JOIN neos_contentrepository_projection_hierarchyedge h ON h.childnodesidentifieringraph = n.identifieringraph
 WHERE h.parentnodesidentifieringraph = :parentNodesIdentifierInGraph
 AND h.subgraphidentifier = :subgraphIdentifier' . $constraints . '
 ORDER BY h.index',
            array_merge([
                'parentNodesIdentifierInGraph' => $parentIdentifierInGraph,
                'subgraphIdentifier' => $subgraphIdentifier
            ], $constraints->__toArray())
        )->fetchAll() as $nodeData) {
            $result[] = $this->mapRawDataToNode($nodeData);
        }

        return $result;
    }

    public function findInSubgraphByNodeTypeNames(string $subgraphIdentifier, array $nodeTypeNames)
    {

    }


    /**
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    protected function mapRawDataToNode(array $nodeData): Node
    {
        $node = new Node();

        $node->identifierInGraph = $nodeData['identifieringraph'];
        $node->identifierInSubgraph = $nodeData['identifierinsubgraph'];
        $node->subgraphIdentifier = $nodeData['subgraphidentifier'];
        $node->properties = new PropertyCollection(json_decode($nodeData['properties'], true));
        $node->nodeTypeName = $nodeData['nodetypename'];
        $node->name = $nodeData['name'];
        $node->removed = (bool)$nodeData['removed'];

        return $node;
    }
}
