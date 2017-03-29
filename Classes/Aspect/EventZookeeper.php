<?php
namespace Neos\ContentRepository\EventSourced\Aspect;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Neos\ContentRepository\Domain as ContentRepository;
use Neos\ContentRepository\EventSourced\Application\Service\FallbackGraphService;
use Neos\ContentRepository\EventSourced\Domain\Model\Content\Event;
use Neos\ContentRepository\EventSourced\Domain\Model\InterDimension\ContentSubgraph;
use Neos\ContentRepository\EventSourced\Utility\SubgraphUtility;
use Neos\EventSourcing\Event\EventPublisher;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Persistence\QueryInterface;

/**
 * The event zookeeper
 *
 * Creates and relays events from all kinds of sources
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class EventZookeeper implements EventSubscriber
{
    /**
     * @Flow\Inject
     * @var EventPublisher
     */
    protected $eventPublisher;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var ContentRepository\Repository\NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var FallbackGraphService
     */
    protected $fallbackGraphService;

    /**
     * @Flow\Inject
     * @var ContentRepository\Repository\WorkspaceRepository
     */
    protected $workspaceRepository;


    /**
     * @var ContentRepository\Model\NodeData
     */
    protected $nodeDataInUpdateProcess;

    /**
     * @var ContentRepository\Model\NodeData
     */
    protected $nodeDataInRemovalProcess;


    /**
     * @Flow\Before("method(Neos\ContentRepository\Domain\Model\Workspace->publishNode())")
     * @param JoinPointInterface $joinPoint
     */
    public function interceptNodePublishing(JoinPointInterface $joinPoint)
    {
        /** @var ContentRepository\Model\NodeInterface $node */
        $node = $joinPoint->getMethodArgument('node');
    }

    /**
     * @Flow\AfterReturning("method(Neos\ContentRepository\Domain\Service\ImportExport\NodeImportService->persistNodeData())")
     * @param JoinPointInterface $joinPoint
     */
    public function postProcessDirectNodeDataPersistence(JoinPointInterface $joinPoint)
    {
        $nodeDataArray = $joinPoint->getMethodArgument('nodeData');
        /** @var ContentRepository\Model\NodeData $nodeData */
        $nodeData = $this->nodeDataRepository->findByIdentifier($nodeDataArray['Persistence_Object_Identifier']);

        $this->publishNodeDataCreation($nodeData);
    }


    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::postPersist,
            Events::preUpdate,
            Events::postUpdate,
            Events::preRemove,
            Events::postRemove
        ];
    }

    /**
     * For debugging purposes only
     *
     * @param LifecycleEventArgs $event
     */
    public function prePersist(LifecycleEventArgs $event)
    {
        if ($event->getObject() instanceof ContentRepository\Model\NodeData) {
            /** @var ContentRepository\Model\NodeData $nodeData */
            $nodeData = $event->getObject();
        }
    }

    public function postPersist(LifecycleEventArgs $event)
    {
        if ($event->getObject() instanceof ContentRepository\Model\NodeData) {
            /** @var ContentRepository\Model\NodeData $nodeData */
            $nodeData = $event->getObject();
            if ($nodeData->getPath() === '/') {
                return;
            } elseif ($nodeData->getPath() === '/sites') {
                $this->eventPublisher->publish('neoscr-content', new Event\SystemNodeWasInserted(
                    $this->persistenceManager->getIdentifierByObject($nodeData),
                    $nodeData->getIdentifier(),
                    'Neos.Neos:Sites'
                ));
            } else {
                $this->publishNodeDataCreation($nodeData);
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $event)
    {
        if ($event->getObject() instanceof ContentRepository\Model\NodeData) {
            $this->nodeDataInUpdateProcess = clone $event->getObject();
        }
    }

    public function postUpdate(LifecycleEventArgs $event)
    {
        if ($event->getObject() instanceof ContentRepository\Model\NodeData && $event->getObject()->getWorkspace()->getName() === 'live') {
            /** @var ContentRepository\Model\NodeData $nodeData */
            $nodeData = $event->getObject();
        }
    }

    public function preRemove(LifecycleEventArgs $event)
    {
        if ($event->getObject() instanceof ContentRepository\Model\NodeData) {
            /** @var ContentRepository\Model\NodeData $nodeData */
            $nodeData = $event->getObject();
        }
    }

    public function postRemove(LifecycleEventArgs $event)
    {
        if ($event->getObject() instanceof ContentRepository\Model\NodeData) {
            /** @var ContentRepository\Model\NodeData $nodeData */
            $nodeData = $event->getObject();
        }
    }

    protected function publishNodeDataCreation(ContentRepository\Model\NodeData $nodeData)
    {
        $subgraph = $this->getSubgraph($nodeData);

        foreach ($subgraph->getFallback() as $fallbackSubgraph) {
            $legacyDimensionValues = $this->getLegacyDimensionValues($fallbackSubgraph);
            $fallbackNodeData = $this->nodeDataRepository->findOneByIdentifier(
                $nodeData->getIdentifier(),
                $nodeData->getWorkspace(),
                $legacyDimensionValues
            );
            if ($fallbackNodeData) {
                $this->eventPublisher->publish('neoscr-content', new Event\NodeWasCreatedAsVariant(
                    $this->persistenceManager->getIdentifierByObject($nodeData),
                    $this->persistenceManager->getIdentifierByObject($fallbackNodeData),
                    $subgraph->getDimensionValues(),
                    $nodeData->getProperties(),
                    Event\NodeWasCreatedAsVariant::STRATEGY_EMPTY
                ));

                return;
            }
        }

        $parent = $this->findParent($nodeData, $subgraph);
        if ($parent) {
            $this->eventPublisher->publish('neoscr-content', new Event\NodeWasInserted(
                $this->persistenceManager->getIdentifierByObject($nodeData),
                $nodeData->getIdentifier(),
                $subgraph->getDimensionValues(),
                $nodeData->getNodeType()->getName(),
                $this->persistenceManager->getIdentifierByObject($this->findParent($nodeData, $subgraph)),
                $this->findElderSibling($nodeData, $subgraph) ? $this->persistenceManager->getIdentifierByObject($this->findElderSibling($nodeData, $subgraph)) : null,
                $nodeData->getName(),
                $nodeData->getProperties()
            ));
        } else {
            return; // disconnected node, will be ignored
        }
    }

    /**
     * @param ContentRepository\Model\NodeData $nodeData
     * @param ContentSubgraph $subgraph
     * @return ContentRepository\Model\NodeData|null
     */
    protected function findElderSibling(ContentRepository\Model\NodeData $nodeData, ContentSubgraph $subgraph)
    {
        $priorities = $this->getLegacyFallbackPriorities($subgraph);

        $query = $this->nodeDataRepository->createQuery();
        /** @var ContentRepository\Model\NodeData $result */
        $result = $query->matching($query->logicalAnd([
            $query->equals('parentPathHash', md5($nodeData->getParentPath())),
            $query->lessThan('index', $nodeData->getIndex()),
            $query->equals('workspace', $nodeData->getWorkspace()->getName()),
            $query->in('dimensionsHash', array_keys($priorities))
        ]))->setOrderings([
            'index' => QueryInterface::ORDER_DESCENDING
        ])->execute()
            ->getFirst();

        return $result;
    }

    /**
     * @param ContentRepository\Model\NodeData $nodeData
     * @param ContentSubgraph $subgraph
     * @return ContentRepository\Model\NodeData|null
     */
    protected function findParent(ContentRepository\Model\NodeData $nodeData, ContentSubgraph $subgraph)
    {
        if ($nodeData->getParentPath() === '/sites') {
            $query = $this->nodeDataRepository->createQuery();
            /** @var ContentRepository\Model\NodeData $parentNode */
            $parentNode = $query->matching($query->logicalAnd([
                $query->equals('pathHash', md5($nodeData->getParentPath()))
            ]))->setLimit(1)
                ->execute()
                ->getFirst();

            return $parentNode;
        }
        $priorities = $this->getLegacyFallbackPriorities($subgraph);

        $query = $this->nodeDataRepository->createQuery();
        $parentCandidates = $query->matching($query->logicalAnd([
            $query->equals('workspace', $nodeData->getWorkspace()->getName()),
            $query->equals('pathHash', md5($nodeData->getParentPath())),
            $query->in('dimensionsHash', array_keys($priorities))
        ]))->execute()->toArray();

        usort($parentCandidates, function (ContentRepository\Model\NodeData $nodeDataA, ContentRepository\Model\NodeData $nodeDataB) use ($priorities) {
            return $priorities[$nodeDataA->getDimensionsHash()] <=> $priorities[$nodeDataB->getDimensionsHash()];
        });

        return reset($parentCandidates) ?: null;
    }

    protected function getLegacyFallbackPriorities(ContentSubgraph $subgraph): array
    {
        $priorities = [
            md5(json_encode($this->getLegacyDimensionValues($subgraph))) => 0
        ];

        $fallbackSubgraphs = $subgraph->getFallback();
        uasort($fallbackSubgraphs, function (ContentSubgraph $subgraphA, ContentSubgraph $subgraphB) {
            return $subgraphA->getWeight() <=> $subgraphB->getWeight();
        });
        $priority = 1;
        foreach ($fallbackSubgraphs as $fallbackSubgraph) {
            $priorities[md5(json_encode($this->getLegacyDimensionValues($fallbackSubgraph)))] = $priority;
            $priority++;
        }

        return $priorities;
    }

    protected function getLegacyDimensionValues(ContentSubgraph $subgraph): array
    {
        $legacyDimensionValues = $subgraph->getDimensionValues();
        unset($legacyDimensionValues['editingSession']);
        array_walk($legacyDimensionValues, function (&$value) {
            $value = [$value->getValue()];
        });

        return $legacyDimensionValues;
    }

    protected function getSubgraph(ContentRepository\Model\NodeData $nodeData): ContentSubgraph
    {
        $subgraphIdentity = [
            'editingSession' => $nodeData->getWorkspace()->getName()
        ];
        foreach ($nodeData->getDimensionValues() as $dimensionName => $dimensionValue) {
            $subgraphIdentity[$dimensionName] = reset($dimensionValue);
        }
        $subgraphIdentifier = SubgraphUtility::hashIdentityComponents($subgraphIdentity);

        return $this->fallbackGraphService->getInterDimensionalFallbackGraph()->getSubgraph($subgraphIdentifier);
    }
}
