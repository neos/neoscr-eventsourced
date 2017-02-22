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

    public function whenNodeVariantWasCreated(Event\NodeVariantWasCreated $event)
    {
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);

        $fallbackNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getFallbackIdentifier());

        $nodeVariant = new Node();
        $nodeVariant->identifierInGraph = $event->getVariantIdentifier();
        $nodeVariant->identifierInSubgraph = $fallbackNode->identifierInSubgraph;
        $nodeVariant->nodeTypeName = $fallbackNode->nodeTypeName;
        $nodeVariant->subgraphIdentifier = $subgraphIdentifier;
        if ($event->getStrategy() === Event\NodeVariantWasCreated::STRATEGY_COPY) {
            $nodeVariant->properties = $fallbackNode->properties;
        }
        $this->add($nodeVariant);

        $variantSubgraphIdentifiers = $this->fallbackGraphService->determineAffectedVariantSubgraphIdentifiers($subgraphIdentifier);

        foreach ($this->hierarchyEdgeFinder->findInboundByNodeAndSubgraphs($fallbackNode, $variantSubgraphIdentifiers) as $variantEdge) {
            $variantEdge->childNodesIdentifierInGraph = $nodeVariant->identifierInGraph;
            $this->update($variantEdge);
        }
        foreach ($this->hierarchyEdgeFinder->findOutboundByNodeAndSubgraphs($fallbackNode, $variantSubgraphIdentifiers) as $variantEdge) {
            $variantEdge->parentNodesIdentifierInGraph = $nodeVariant->identifierInGraph;
            $this->update($variantEdge);
        }

        $this->projectionPersistenceManager->persistAll();
    }

    public function whenPropertiesWereUpdated(Event\PropertiesWereUpdated $event)
    {
        $node = $this->nodeFinder->findOneByIdentifierInGraph($event->getVariantIdentifier());
        $node->properties = Arrays::arrayMergeRecursiveOverrule($node->properties, $event->getProperties());
        $this->update($node);

        $this->projectionPersistenceManager->persistAll();
    }

    public function whenNodeWasMoved(Event\NodeWasMoved $event)
    {
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);
        $variantSubgraphIdentifiers = $this->fallbackGraphService->determineAffectedVariantSubgraphIdentifiers($subgraphIdentifier);

        foreach ($this->hierarchyEdgeFinder->findInboundByNodeAndSubgraphs($event->getVariantIdentifier(), $variantSubgraphIdentifiers) as $variantEdge) {
            $variantEdge->parentNodesIdentifierInGraph = $event->getParentIdentifier();
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

        $this->projectionPersistenceManager->persistAll();
    }

    protected function extractSubgraphIdentifierFromEvent(Event\AbstractDimensionAwareEvent $event): string
    {
        $subgraphIdentity = $event->getContentDimensionValues();
        /* @todo fetch from event stream vector instead */
        $subgraphIdentity['editingSession'] = 'live';

        return SubgraphUtility::hashIdentityComponents($subgraphIdentity);
    }
}
