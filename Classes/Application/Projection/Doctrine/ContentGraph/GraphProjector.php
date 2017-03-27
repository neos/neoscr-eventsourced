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
use Neos\Utility\Arrays;

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
     * @var HierarchyEdgeFinder
     */
    protected $hierarchyEdgeFinder;

    /**
     * @Flow\Inject
     * @var ReferenceEdgeFinder
     */
    protected $referenceEdgeFinder;

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
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);

        $node = new Node();
        $node->identifierInGraph = $event->getVariantIdentifier();
        $node->identifierInSubgraph = $event->getIdentifier();
        $node->subgraphIdentifier = $subgraphIdentifier;
        $node->nodeTypeName = $event->getNodeType();
        $node->properties = $event->getProperties();
        $this->add($node);

        $parentNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getParentIdentifier(), new NodeFinderQueryConstraints());
        $subgraph = $this->fallbackGraphService->getInterDimensionalFallbackGraph()->getSubgraph($subgraphIdentifier);

        $hierarchyEdge = new HierarchyEdge();
        $hierarchyEdge->connect($parentNode, $node, $subgraph);
        #$hierarchyEdge->position = $event->getPosition();
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

    public function whenNodeVariantWasCreated(Event\NodeVariantWasCreated $event)
    {
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);

        $fallbackNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getFallbackIdentifier(), new NodeFinderQueryConstraints());

        $nodeVariant = new Node();
        $nodeVariant->identifierInGraph = $event->getVariantIdentifier();
        $nodeVariant->identifierInSubgraph = $fallbackNode->identifierInSubgraph;
        $nodeVariant->nodeTypeName = $fallbackNode->nodeTypeName;
        $nodeVariant->subgraphIdentifier = $subgraphIdentifier;
        if ($event->getStrategy() === Event\NodeVariantWasCreated::STRATEGY_COPY) {
            $nodeVariant->properties = $fallbackNode->properties;
        }
        $nodeVariant->properties = Arrays::arrayMergeRecursiveOverrule($nodeVariant->properties, $event->getProperties());
        $this->add($nodeVariant);

        $variantSubgraphIdentifiers = $this->fallbackGraphService->determineAffectedVariantSubgraphIdentifiers($subgraphIdentifier);

        foreach ($this->hierarchyEdgeFinder->findInboundByNodeAndSubgraphs($fallbackNode->identifierInGraph, $variantSubgraphIdentifiers) as $variantEdge) {
            $variantEdge->childNodesIdentifierInGraph = $nodeVariant->identifierInGraph;
            $this->update($variantEdge);
        }
        foreach ($this->hierarchyEdgeFinder->findOutboundByNodeAndSubgraphs($fallbackNode->identifierInGraph, $variantSubgraphIdentifiers) as $variantEdge) {
            $variantEdge->parentNodesIdentifierInGraph = $nodeVariant->identifierInGraph;
            $this->update($variantEdge);
        }

        // @todo handle reference edges, also respect strategy

        $this->projectionPersistenceManager->persistAll();
    }

    public function whenPropertiesWereUpdated(Event\PropertiesWereUpdated $event)
    {
        $node = $this->nodeFinder->findOneByIdentifierInGraph($event->getVariantIdentifier(), new NodeFinderQueryConstraints());
        $node->properties = Arrays::arrayMergeRecursiveOverrule($node->properties, $event->getProperties());
        $this->update($node);

        $this->projectionPersistenceManager->persistAll();
    }

    public function whenNodeWasMoved(Event\NodeWasMoved $event)
    {
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);
        $affectedSubgraphIdentifiers = $event->getStrategy() === Event\NodeWasMoved::STRATEGY_CASCADE_TO_ALL_VARIANTS
            ? $this->fallbackGraphService->determineAffectedVariantSubgraphIdentifiers($subgraphIdentifier)
            : $this->fallbackGraphService->determineConnectedSubgraphIdentifiers($subgraphIdentifier);

        foreach ($this->hierarchyEdgeFinder->findInboundByNodeAndSubgraphs($event->getVariantIdentifier(), $affectedSubgraphIdentifiers) as $variantEdge) {
            if ($event->getNewParentVariantIdentifier()) {
                $variantEdge->parentNodesIdentifierInGraph = $event->getNewParentVariantIdentifier();
            }
            // @todo: handle new older sibling

            $this->update($variantEdge);
        }

        $this->projectionPersistenceManager->persistAll();
    }

    public function whenNodeWasRemoved(Event\NodeWasRemoved $event)
    {
        $node = $this->nodeFinder->findOneByIdentifierInGraph($event->getVariantIdentifier());
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);

        if ($node->subgraphIdentifier === $subgraphIdentifier) {
            foreach ($this->hierarchyEdgeFinder->findByChildNodeIdentifierInGraph($event->getVariantIdentifier()) as $inboundEdge) {
                $this->remove($inboundEdge);
            }
            $this->remove($node);
        } else {
            $affectedSubgraphIdentifiers = $this->fallbackGraphService->determineAffectedVariantSubgraphIdentifiers($subgraphIdentifier);
            foreach ($this->hierarchyEdgeFinder->findInboundByNodeAndSubgraphs($event->getVariantIdentifier(), $affectedSubgraphIdentifiers) as $affectedInboundEdge) {
                $this->remove($affectedInboundEdge);
            }
        }

        // @todo handle reference edges

        $this->projectionPersistenceManager->persistAll();
    }

    public function whenNodeReferenceWasAdded(Event\NodeReferenceWasAdded $event)
    {
        $referencingNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getReferencingNodeIdentifier());
        $referencedNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getReferencedNodeIdentifier());

        $affectedSubgraphIdentifiers = [];
        // Which variant reference edges are created alongside is determined by what subgraph this node belongs to
        // @todo define a more fitting method in a service for this
        $inboundHierarchyEdges = $this->hierarchyEdgeFinder->findByChildNodeIdentifierInGraph($event->getReferencingNodeIdentifier());

        foreach ($inboundHierarchyEdges as $hierarchyEdge) {
            $referencedNodeVariant = $this->nodeFinder->findInSubgraphByIdentifierInSubgraph($referencingNode->identifierInSubgraph, $hierarchyEdge->subgraphIdentifier);
            if ($referencedNodeVariant) {
                $referenceEdge = new ReferenceEdge();
                $referenceEdge->connect($referencingNode, $referencedNode, $hierarchyEdge->name, $event->getReferenceName());
                // @todo fetch position among siblings
                // @todo implement auto-triggering of position recalculation
            }
        }
    }


    protected function extractSubgraphIdentifierFromEvent(Event\AbstractDimensionAwareEvent $event): string
    {
        $subgraphIdentity = $event->getContentDimensionValues();
        /* @todo fetch from event stream vector instead */
        $subgraphIdentity['editingSession'] = 'live';

        return SubgraphUtility::hashIdentityComponents($subgraphIdentity);
    }
}
