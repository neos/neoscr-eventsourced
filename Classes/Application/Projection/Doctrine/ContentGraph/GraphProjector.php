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
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

/**
 * The alternate reality-aware graph projector for the Doctrine backend
 *
 * @Flow\Scope("singleton")
 */
class GraphProjector extends AbstractDbalProjector
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
        $systemNode = new Node(
            $event->getVariantIdentifier(),
            $event->getIdentifier(),
            '_system',
            new PropertyCollection([]),
            $event->getNodeType()
        );
        $this->getDatabaseConnection()->transactional(function () use ($systemNode) {
            $this->addNode($systemNode);
        });
    }

    public function whenNodeWasInserted(Event\NodeWasInserted $event)
    {
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);

        $edges = [];
        $node = new Node(
            $event->getVariantIdentifier(),
            $event->getIdentifier(),
            $subgraphIdentifier,
            new PropertyCollection($event->getProperties()),
            $event->getNodeType()
        );

        $parentNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getParentIdentifier());
        $subgraph = $this->fallbackGraphService->getInterDimensionalFallbackGraph()->getSubgraph($subgraphIdentifier);
        $position = $this->getEdgePosition($event->getParentIdentifier(), $event->getElderSiblingIdentifier(), $subgraphIdentifier);

        $hierarchyEdge = HierarchyEdge::asConnection($parentNode, $node, $subgraph, $position);
        $edges[] = $hierarchyEdge;

        foreach ($subgraph->getVariants() as $variantSubgraph) {
            $variantHierarchyEdge = HierarchyEdge::asConnection($parentNode, $node, $variantSubgraph, $position);
            $edges[] = $variantHierarchyEdge;
        }
        // @todo handle reference edges

        $this->getDatabaseConnection()->transactional(function () use ($node, $edges) {
            $this->addNode($node);
            foreach ($edges as $edge) {
                $this->addHierarchyEdge($edge);
            }
        });

    }

    protected function getEdgePosition(string $parentIdentifier, string $elderSiblingIdentifier = null, string $subgraphIdentifier): int
    {
        if ($elderSiblingIdentifier) {
            $elderSiblingEdge = $this->hierarchyEdgeFinder->findInboundByNodeAndSubgraph($elderSiblingIdentifier, $subgraphIdentifier);
            if (!$elderSiblingEdge) {
                exit();
            }
            $youngerSiblingEdge = $this->hierarchyEdgeFinder->findYoungerSibling($elderSiblingEdge);
            if ($youngerSiblingEdge) {
                $position = ($elderSiblingEdge->position + $youngerSiblingEdge->position) / 2;
            } else {
                $position = $elderSiblingEdge->position + 128;
            }
        } else {
            $eldestSiblingEdge = $this->hierarchyEdgeFinder->findEldestSibling($parentIdentifier, $subgraphIdentifier);
            if ($eldestSiblingEdge) {
                $position = $eldestSiblingEdge->position - 128;
            } else {
                $position = 0;
            }
        }

        if ($position % 2 !== 0) {
            $position = $this->recalculateEdgePositions($parentIdentifier, $elderSiblingIdentifier, $subgraphIdentifier);
        }

        return $position;
    }

    /**
     * @param string $parentIdentifier
     * @param string $elderSiblingIdentifier
     * @param string $subgraphIdentifier
     * @return int The gap left after the elder sibling identifier
     */
    protected function recalculateEdgePositions(string $parentIdentifier, string $elderSiblingIdentifier, string $subgraphIdentifier): int
    {
        $offset = 0;
        $position = 0;
        foreach ($this->hierarchyEdgeFinder->findOutboundByNodeAndSubgraph($parentIdentifier, $subgraphIdentifier) as $edge) {
            $edge->position = $offset;
            $offset += 128;
            if ($edge->childNodesIdentifierInGraph === $elderSiblingIdentifier) {
                $position = $offset;
                $offset += 128;
            }
        }

        return $position;
    }

    public function whenNodeVariantWasCreated(Event\NodeVariantWasCreated $event)
    {
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);

        $fallbackNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getFallbackIdentifier());

        $properties = $event->getStrategy() === Event\NodeVariantWasCreated::STRATEGY_COPY ? $fallbackNode->properties->toArray() : [];
        $properties = Arrays::arrayMergeRecursiveOverrule($properties, $event->getProperties());

        $nodeVariant = new Node($event->getVariantIdentifier(), $fallbackNode->identifierInSubgraph, $subgraphIdentifier, new PropertyCollection($properties), $fallbackNode->nodeTypeName);

        $variantSubgraphIdentifiers = $this->fallbackGraphService->determineAffectedVariantSubgraphIdentifiers($subgraphIdentifier);

        $inboundEdges = $this->hierarchyEdgeFinder->findInboundByNodeAndSubgraphs($fallbackNode->identifierInGraph, $variantSubgraphIdentifiers);
        $outboundEdges = $this->hierarchyEdgeFinder->findOutboundByNodeAndSubgraphs($fallbackNode->identifierInGraph, $variantSubgraphIdentifiers);

        // @todo handle reference edges

        $this->getDatabaseConnection()->transactional(function () use ($nodeVariant, $inboundEdges, $outboundEdges) {
            $this->addNode($nodeVariant);
            foreach ($inboundEdges as $edge) {
                $this->assignNewChildToHierarchyEdge($edge, $nodeVariant->identifierInGraph);
            }
            foreach ($outboundEdges as $edge) {
                $this->assignNewParentToHierarchyEdge($edge, $nodeVariant->identifierInGraph);
            }
        });
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

    protected function addNode(Node $node)
    {
        $this->getDatabaseConnection()->insert('neos_contentrepository_projection_node', [
            'identifieringraph' => $node->identifierInGraph,
            'identifierinsubgraph' => $node->identifierInSubgraph,
            'subgraphidentifier' => $node->subgraphIdentifier,
            'properties' => json_encode($node->properties->toArray()),
            'nodetypename' => $node->getNodeType()
        ]);
    }

    protected function addHierarchyEdge(HierarchyEdge $edge)
    {
        $this->getDatabaseConnection()->insert('neos_contentrepository_projection_hierarchyedge', [
            'parentnodesidentifieringraph' => $edge->parentNodesIdentifierInGraph,
            'childnodesidentifieringraph' => $edge->childNodesIdentifierInGraph,
            'subgraphidentifier' => $edge->subgraphIdentifier,
            'position' => $edge->position
        ]);
    }

    protected function assignNewChildToHierarchyEdge(HierarchyEdge $edge, string $childNodeIdentifierInGraph)
    {
        $this->getDatabaseConnection()->update(
            'neos_contentrepository_projection_hierarchyedge',
            [
                'childnodesidentifieringraph' => $childNodeIdentifierInGraph,
            ],
            [
                'parentnodesidentifieringraph' => $edge->parentNodesIdentifierInGraph,
                'childnodesidentifieringraph' => $edge->childNodesIdentifierInGraph,
                'subgraphidentifier' => $edge->subgraphIdentifier
            ]
        );
    }

    protected function assignNewParentToHierarchyEdge(HierarchyEdge $edge, string $parentNodeIdentifierInGraph)
    {
        $this->getDatabaseConnection()->update(
            'neos_contentrepository_projection_hierarchyedge',
            [
                'parentnodesidentifieringraph' => $parentNodeIdentifierInGraph,
            ],
            [
                'parentnodesidentifieringraph' => $edge->parentNodesIdentifierInGraph,
                'childnodesidentifieringraph' => $edge->childNodesIdentifierInGraph,
                'subgraphidentifier' => $edge->subgraphIdentifier
            ]
        );
    }
}
