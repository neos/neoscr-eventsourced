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

use Neos\ContentRepository\Domain as ContentRepository;
use Neos\Flow\Annotations as Flow;

/**
 * General purpose Node read model
 *
 * @Flow\Entity
 */
class Node
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
     * @var array
     */
    public $properties = [];

    /**
     * @var string
     */
    public $nodeTypeName = 'unstructured';

    public function getNodeType(): ContentRepository\Model\NodeType
    {
        return $this->nodeTypeManager->getNodeType($this->nodeTypeName);
    }
}
