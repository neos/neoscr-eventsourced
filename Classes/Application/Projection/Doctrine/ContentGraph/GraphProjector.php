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
use Neos\ContentRepository\EventSourced\Application\Persistence\Traits\GenericEntityQueryTrait;
use Neos\ContentRepository\EventSourced\Application\Service\FallbackGraphService;
use Neos\ContentRepository\EventSourced\Domain\Model\Content\Event;
use Neos\ContentRepository\EventSourced\Domain\Model\IntraDimension\ContentDimensionValue;
use Neos\ContentRepository\EventSourced\Utility\SubgraphUtility;
use Neos\EventSourcing\Projection\AbstractBaseProjector;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

/**
 * The alternate reality-aware graph projector for the Doctrine backend
 *
 * @Flow\Scope("singleton")
 */
class GraphProjector extends AbstractBaseProjector
{
    use GenericEntityQueryTrait;

    const HIERARCHY_EDGE_POSITION_DEFAULT_OFFSET = 128; // must be 2^n

    /**
     * @Flow\Inject
     * @var ObjectManager
     */
    protected $entityManager;

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
        $this->getEntityManager()->transactional(function() use ($event) {
            $systemNode = new Node();
            $systemNode->identifierInGraph = $event->getVariantIdentifier();
            $systemNode->identifierInSubgraph = $event->getIdentifier();
            $systemNode->subgraphIdentifier = '_system';
            $systemNode->nodeTypeName = $event->getNodeType();
            $this->add($systemNode);
        });
    }

    public function whenNodeWasInserted(Event\NodeWasInserted $event)
    {
        $subgraphsForRecalculation = [];

        $this->getEntityManager()->transactional(function() use ($event, $subgraphsForRecalculation) {
            $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);

            $node = new Node();
            $node->identifierInGraph = $event->getVariantIdentifier();
            $node->identifierInSubgraph = $event->getIdentifier();
            $node->subgraphIdentifier = $subgraphIdentifier;
            $node->nodeTypeName = $event->getNodeType();
            $node->properties = new PropertyCollection($event->getProperties());
            $this->add($node);
            $parentNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getParentIdentifier(), new NodeFinderQueryConstraints());
            $subgraph = $this->fallbackGraphService->getInterDimensionalFallbackGraph()->getSubgraph($subgraphIdentifier);

            $hierarchyEdge = new HierarchyEdge();
            $hierarchyEdge->connect($parentNode, $node, $subgraph);
            $subgraphsForRecalculation[$subgraphIdentifier] = $this->assignPositionToHierarchyEdge($hierarchyEdge, $event->getElderSiblingIdentifier());
            $this->add($hierarchyEdge);

            foreach ($subgraph->getVariants() as $variantSubgraph) {
                $variantHierarchyEdge = new HierarchyEdge();
                $variantHierarchyEdge->name = $event->getPath();
                $variantHierarchyEdge->connect($parentNode, $node, $variantSubgraph);
                $subgraphsForRecalculation[$subgraphIdentifier] = $this->assignPositionToHierarchyEdge($variantHierarchyEdge, $event->getElderSiblingIdentifier());
                $this->add($variantHierarchyEdge);
            }
        });

        foreach ($subgraphsForRecalculation as $subgraphIdentifier => $recalculationNecessary) {
            if ($recalculationNecessary) {
                $this->recalculateEdgePositions($event->getParentIdentifier(), $subgraphIdentifier);
            }
        }
    }

    public function whenNodeWasCreatedAsVariant(Event\NodeWasCreatedAsVariant $event)
    {
        $this->getEntityManager()->transactional(function() use ($event) {
            $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);

            $fallbackNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getFallbackIdentifier(), new NodeFinderQueryConstraints());

            $nodeVariant = new Node();
            $nodeVariant->identifierInGraph = $event->getVariantIdentifier();
            $nodeVariant->identifierInSubgraph = $fallbackNode->identifierInSubgraph;
            $nodeVariant->nodeTypeName = $fallbackNode->nodeTypeName;
            $nodeVariant->subgraphIdentifier = $subgraphIdentifier;
            if ($event->getStrategy() === Event\NodeWasCreatedAsVariant::STRATEGY_COPY) {
                $nodeVariant->properties = $fallbackNode->properties;
            }
            $nodeVariant->properties = new PropertyCollection(Arrays::arrayMergeRecursiveOverrule($nodeVariant->properties->asRawArray(), $event->getProperties()));
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
        });
    }

    public function whenPropertiesWereUpdated(Event\PropertiesWereUpdated $event)
    {
        $this->getEntityManager()->transactional(function() use ($event) {
            $node = $this->nodeFinder->findOneByIdentifierInGraph($event->getVariantIdentifier(), new NodeFinderQueryConstraints());
            $node->properties = new PropertyCollection(Arrays::arrayMergeRecursiveOverrule($node->properties->asRawArray(), $event->getProperties()));
            $this->update($node);
        });
    }

    public function whenNodeWasMoved(Event\NodeWasMoved $event)
    {
        $this->getEntityManager()->transactional(function() use ($event) {
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
        });
    }

    public function whenNodeWasRemoved(Event\NodeWasRemoved $event)
    {
        $this->getEntityManager()->transactional(function() use ($event) {
            $node = $this->nodeFinder->findOneByIdentifierInGraph($event->getVariantIdentifier(), new NodeFinderQueryConstraints());
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
        });
    }

    public function whenNodeReferenceWasAdded(Event\NodeReferenceWasAdded $event)
    {
        $this->getEntityManager()->transactional(function() use ($event) {
            $referencingNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getReferencingNodeIdentifier(), new NodeFinderQueryConstraints());
            $referencedNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getReferencedNodeIdentifier(), new NodeFinderQueryConstraints());

            $affectedSubgraphIdentifiers = [];
            // Which variant reference edges are created alongside is determined by what subgraph this node belongs to
            // @todo define a more fitting method in a service for this
            $inboundHierarchyEdges = $this->hierarchyEdgeFinder->findByChildNodeIdentifierInGraph($event->getReferencingNodeIdentifier());

            foreach ($inboundHierarchyEdges as $hierarchyEdge) {
                $referencedNodeVariant = $this->nodeFinder->findInSubgraphByIdentifierInSubgraph($referencingNode->identifierInSubgraph, $hierarchyEdge->subgraphIdentifier, new NodeFinderQueryConstraints());
                if ($referencedNodeVariant) {
                    $referenceEdge = new ReferenceEdge();
                    $referenceEdge->connect($referencingNode, $referencedNode, $hierarchyEdge->subgraphIdentifier, $event->getReferenceName());
                    // @todo fetch position among siblings
                    // @todo implement auto-triggering of position recalculation
                }
            }
        });
    }

    /**
     * @param HierarchyEdge $hierarchyEdge
     * @param string|null $elderSiblingIdentifier
     * @return bool whether edge positions have to be recalculated or not
     */
    protected function assignPositionToHierarchyEdge(HierarchyEdge $hierarchyEdge, string $elderSiblingIdentifier = null)
    {
        if ($elderSiblingIdentifier) {
            $elderEdgeSibling = $this->hierarchyEdgeFinder->findConnectingInSubgraph($hierarchyEdge->parentNodesIdentifierInGraph, $elderSiblingIdentifier, $hierarchyEdge->subgraphIdentifier);
            if ($elderEdgeSibling) {
                $youngerEdgeSibling = $this->hierarchyEdgeFinder->findEldestYoungerSibling(
                    $hierarchyEdge->parentNodesIdentifierInGraph,
                    $elderEdgeSibling->position,
                    $hierarchyEdge->subgraphIdentifier
                );
                if ($youngerEdgeSibling) {
                    $hierarchyEdge->position = ($elderEdgeSibling->position + $youngerEdgeSibling->position) / 2;
                    if ($elderEdgeSibling->position - $hierarchyEdge->position === 1) {
                        return true;
                    }
                } else {
                    $hierarchyEdge->position = $elderEdgeSibling->position + self::HIERARCHY_EDGE_POSITION_DEFAULT_OFFSET;
                }
            } else {
                $this->setHierarchyEdgeAsEldestSibling($hierarchyEdge);
            }
        } else {
            $this->setHierarchyEdgeAsEldestSibling($hierarchyEdge);
        }

        return false;
    }

    protected function setHierarchyEdgeAsEldestSibling(HierarchyEdge $hierarchyEdge)
    {
        $eldestEdgeSibling = $this->hierarchyEdgeFinder->findEldestSibling($hierarchyEdge->parentNodesIdentifierInGraph, $hierarchyEdge->subgraphIdentifier);
        if ($eldestEdgeSibling) {
            $hierarchyEdge->position = $eldestEdgeSibling->position - self::HIERARCHY_EDGE_POSITION_DEFAULT_OFFSET;
        } else {
            $hierarchyEdge->position = 0;
        }
    }

    protected function recalculateEdgePositions(string $parentNodesIdentifierInGraph, string $subgraphIdentifier)
    {
        $position = 0;
        foreach ($this->hierarchyEdgeFinder->findOrderedOutboundByParentNodeAndSubgraph($parentNodesIdentifierInGraph, $subgraphIdentifier) as $edge) {
            $edge->position = $position;
            $this->update($edge);
            $position += self::HIERARCHY_EDGE_POSITION_DEFAULT_OFFSET;
        }
    }

    protected function extractSubgraphIdentifierFromEvent(Event\AbstractDimensionAwareEvent $event): string
    {
        $subgraphIdentity = $event->getContentDimensionValues();
        array_walk($subgraphIdentity, function (ContentDimensionValue &$dimensionValue) {
            $dimensionValue = $dimensionValue->getValue();
        });

        /* @todo fetch from event stream vector instead */
        $subgraphIdentity['editingSession'] = 'live';

        return SubgraphUtility::hashIdentityComponents($subgraphIdentity);
    }

    /**
     * Removes all objects of this repository as if remove() was called for all of them.
     * For usage in the concrete projector.
     *
     * @return void
     * @api
     */
    public function reset()
    {
        // TODO: Implement reset() method.
    }

    /**
     * Returns true if the projection maintained by the concreted projector does not contain any data (yet).
     *
     * @return boolean
     */
    public function isEmpty()
    {
        // TODO: Implement isEmpty() method.
    }

    protected function getEntityManager() : EntityManager
    {
        return $this->entityManager;
    }
}