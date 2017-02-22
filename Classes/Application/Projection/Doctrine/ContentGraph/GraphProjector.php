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

use Neos\ContentRepository\EventSourced\Application\Service\FallbackGraphService;
use Neos\ContentRepository\EventSourced\Domain\Model\Content\Event;
use Neos\ContentRepository\EventSourced\Utility\SubgraphUtility;
use Neos\EventSourcing\Projection\Doctrine\AbstractDoctrineProjector;
use Neos\Flow\Annotations as Flow;

/**
 * The alternate reality-aware graph projector for the Doctrine backend
 *
 * @Flow\Scope("singleton")
 */
class GraphProjector extends AbstractDoctrineProjector
{
    /**
     * @Flow\Inject
     * @var NodeFinder
     */
    protected $nodeFinder;

    /**
     * @Flow\Inject
     * @var FallbackGraphService
     */
    protected $fallbackGraphService;


    public function whenSystemNodeWasInserted(Event\SystemNodeWasInserted $event)
    {
        $systemNode = new Node();
        $systemNode->identifierInGraph = $event->getVariantIdentifier();
        $systemNode->identifierInSubgraph = $event->getIdentifier();
        $systemNode->subgraphIdentifier = '_system';
        $systemNode->nodeTypeName = $event->getNodeType();
        $this->add($systemNode);

        $this->projectionPersistenceManager->persistAll();
    }

    public function whenNodeWasInserted(Event\NodeWasInserted $event)
    {
        $subgraphIdentity = $event->getContentDimensionValues();
        /* @todo fetch from event stream vector instead */
        $subgraphIdentity['editingSession'] = 'live';
        $subgraphIdentifier = SubgraphUtility::hashIdentityComponents($subgraphIdentity);

        $node = new Node();
        $node->identifierInGraph = $event->getVariantIdentifier();
        $node->identifierInSubgraph = $event->getIdentifier();
        $node->subgraphIdentifier = $subgraphIdentifier;
        $node->nodeTypeName = $event->getNodeType();
        $this->add($node);

        $parentNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getParentIdentifier());
        $subgraph = $this->fallbackGraphService->getInterDimensionalFallbackGraph()->getSubgraph($subgraphIdentifier);

        $hierarchyEdge = new HierarchyEdge();
        $hierarchyEdge->connect($parentNode, $node, $subgraph);
        $hierarchyEdge->name = $event->getPath();
        $hierarchyEdge->position = $event->getPosition();
        $this->add($hierarchyEdge);

        foreach ($subgraph->getVariants() as $variantSubgraph) {
            $variantHierarchyEdge = new HierarchyEdge();
            $variantHierarchyEdge->connect($parentNode, $node, $variantSubgraph);
            $variantHierarchyEdge->name = $event->getPath();
            $variantHierarchyEdge->position = $event->getPosition();
            $this->add($variantHierarchyEdge);
        }

        $this->projectionPersistenceManager->persistAll();
    }
}
