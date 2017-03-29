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
use Neos\ContentRepository\Domain as ContentRepository;
use Neos\ContentRepository\EventSourced\Application\Persistence\DoctrinePersistableInterface;
use Neos\Flow\Annotations as Flow;

/**
 * General purpose Node read model
 */
class Node implements DoctrinePersistableInterface
{
    /**
     * @Flow\Inject
     * @var ContentRepository\Service\NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @var string
     */
    public $identifierInGraph;

    /**
     * @var string
     */
    public $identifierInSubgraph;

    /**
     * @var string
     */
    public $subgraphIdentifier;

    /**
     * @var bool
     */
    public $removed = false;

    /**
     * @var PropertyCollection
     */
    public $properties;

    /**
     * @var string
     */
    public $nodeTypeName = 'unstructured';


    public function __construct()
    {
        $this->properties = new PropertyCollection([]);
    }


    public function getNodeType(): ContentRepository\Model\NodeType
    {
        return $this->nodeTypeManager->getNodeType($this->nodeTypeName);
    }

    public function keys()
    {
        return [
            'identifierInGraph' => $this->identifierInGraph
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
            'identifierInGraph' => $this->identifierInGraph,
            'identifierInSubgraph' => $this->identifierInSubgraph,
            'subgraphIdentifier' => $this->subgraphIdentifier,
            'name' => $this->name,
            'removed' => $this->removed ? 1 : 0,
            'properties' => $this->properties->serialize($entityManager),
            'nodeTypeName' => $this->nodeTypeName
        ];
    }

    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'neos_contentrepository_projection_node';
    }
}
