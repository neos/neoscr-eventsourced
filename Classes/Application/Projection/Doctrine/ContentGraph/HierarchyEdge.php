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

use Doctrine\ORM\EntityManager;
use Neos\ContentRepository\EventSourced\Application\Persistence\DoctrinePersistableInterface;
use Neos\ContentRepository\EventSourced\Domain\Model\InterDimension\ContentSubgraph;
use Neos\Flow\Annotations as Flow;

/**
 * General purpose Hierarchy Edge read model
 */
class HierarchyEdge implements DoctrinePersistableInterface
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

    public function keys()
    {
        return [
            'parentNodesIdentifierInGraph' => $this->parentNodesIdentifierInGraph,
            'subgraphIdentifier' => $this->subgraphIdentifier,
            'childNodesIdentifierInGraph' => $this->childNodesIdentifierInGraph
        ];
    }

    /**
     * @param EntityManager $entityManager
     *
     * @return array
     */
    public function serialize(EntityManager $entityManager)
    {
        return [
            'parentNodesIdentifierInGraph' => $this->parentNodesIdentifierInGraph,
            'subgraphIdentifier' => $this->subgraphIdentifier,
            'childNodesIdentifierInGraph' => $this->childNodesIdentifierInGraph,
            'position' => $this->position,
            'name' => $this->name
        ];
    }

    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'neos_contentrepository_projection_hierarchyedge';
    }
}
