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

use Neos\ContentRepository\EventSourced\Domain\Model\InterDimension\ContentSubgraph;
use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;

/**
 * General purpose Hierarchy Edge read model
 *
 * @Flow\Entity
 * @ORM\Table(name="neos_contentrepository_projection_hierarchyedge")
 */
class HierarchyEdge
{
    /**
     * @ORM\Id
     * @var string
     */
    public $parentNodesIdentifierInGraph;

    /**
     * @var string
     */
    public $parentNodesIdentifierInSubgraph;

    /**
     * @ORM\Id
     * @var string
     */
    public $subgraphIdentifier;

    /**
     * @var string
     */
    public $childNodesIdentifierInGraph;

    /**
     * @ORM\Id
     * @var string
     */
    public $childNodesIdentifierInSubgraph;

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
        $this->parentNodesIdentifierInSubgraph = $parentNode->identifierInSubgraph;
        $this->subgraphIdentifier = $contentSubgraph->getIdentityHash();
        $this->childNodesIdentifierInGraph = $childNode->identifierInGraph;
        $this->childNodesIdentifierInSubgraph = $childNode->identifierInSubgraph;
    }
}
