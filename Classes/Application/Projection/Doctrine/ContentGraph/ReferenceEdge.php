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
 * General purpose Reference Edge read model
 *
 * @Flow\Entity
 * @ORM\Table(name="neos_contentrepository_projection_referenceedge")
 */
class ReferenceEdge
{
    /**
     * @ORM\Id
     * @var string
     */
    public $referencingNodesIdentifierInGraph;

    /**
     * @ORM\Id
     * @var string
     */
    public $referencedNodesIdentifierInGraph;

    /**
     * @ORM\Id
     * @var string
     */
    public $subgraphIdentifier;

    /**
     * @ORM\Id
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $position;


    public function connect(Node $referencingNode, Node $referencedNode, string $subgraphIdentifier, string $name)
    {
        $this->referencingNodesIdentifierInGraph = $referencingNode->identifierInGraph;
        $this->referencedNodesIdentifierInGraph = $referencedNode->identifierInGraph;
        $this->subgraphIdentifier = $subgraphIdentifier;
        $this->name = $name;
    }
}
