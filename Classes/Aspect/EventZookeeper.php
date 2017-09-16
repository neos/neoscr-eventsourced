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
use Neos\Flow\Property\PropertyMapper;

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
     * @Flow\Inject
     * @var PropertyMapper
     */
    protected $propertyMapper;


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

            if ($nodeData->getPath() === '/sites') {
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
            $this->publishNodeDataRemoval($nodeData);
        }
    }

    public function postRemove(LifecycleEventArgs $event)
    {
        if ($event->getObject() instanceof ContentRepository\Model\NodeData) {
            /** @var ContentRepository\Model\NodeData $nodeData */
            $nodeData = $event->getObject();
            $this->publishNodeDataRemoval($nodeData);
        }
    }

    protected function publishNodeDataCreation(ContentRepository\Model\NodeData $nodeData)
    {
        $properties = $nodeData->getProperties();
        $this->preprocessProperties($properties);

        $dimensionValues = [];
        $subgraph = $this->fetchSubgraphForNodeData($nodeData);
        foreach ($subgraph->getDimensionValues() as $dimensionName => $dimensionValue) {
            $dimensionValues[$dimensionName] = $dimensionValue->getValue();
        }

        foreach ($subgraph->getFallback() as $fallbackSubgraph) {
            $strangeDimensionValues = $fallbackSubgraph->getDimensionValues();
            unset($strangeDimensionValues['editingSession']);
            array_walk($strangeDimensionValues, function (&$value) {
                $value = [$value];
            });
            $fallbackNodeData = $this->nodeDataRepository->findOneByIdentifier(
                $nodeData->getIdentifier(),
                $nodeData->getWorkspace(),
                $strangeDimensionValues
            );
            if ($fallbackNodeData instanceof ContentRepository\Model\NodeData) {
                $this->eventPublisher->publish('neoscr-content', new Event\NodeVariantWasCreated(
                    $this->persistenceManager->getIdentifierByObject($nodeData),
                    $this->persistenceManager->getIdentifierByObject($fallbackNodeData),
                    $dimensionValues,
                    $properties,
                    Event\NodeVariantWasCreated::STRATEGY_EMPTY
                ));

                return;
            }
        }
        $elderSibling = $this->fetchElderSibling($nodeData);
        $this->eventPublisher->publish('neoscr-content', new Event\NodeWasInserted(
            $this->persistenceManager->getIdentifierByObject($nodeData),
            $nodeData->getIdentifier(),
            $dimensionValues,
            $nodeData->getNodeType()->getName(),
            $this->persistenceManager->getIdentifierByObject($this->fetchParent($nodeData)),
            $elderSibling instanceof ContentRepository\Model\NodeData ? $this->persistenceManager->getIdentifierByObject($elderSibling) : '',
            $properties
        ));
    }

    protected function preprocessProperties(array & $properties)
    {
        array_walk($properties, function (&$value) {
            if (is_object($value)) {
                $value = $this->mapObjectToArray($value);
            } elseif (is_array($value)) {
                foreach ($value as &$valueElement) {
                    if (is_object($valueElement)) {
                        $valueElement = $this->mapObjectToArray($valueElement);
                    }
                }
            }
        });
    }

    protected function mapObjectToArray($object): array
    {
        $identifier = $this->persistenceManager->getIdentifierByObject($object);
        if ($identifier) {
            return [
                '__flow_object_type' => get_class($object),
                '__identifier' => $identifier
            ];
        } else {
            return $this->propertyMapper->convert($object, 'array');
        }
    }

    protected function fetchSubgraphForNodeData(ContentRepository\Model\NodeData $nodeData): ContentSubgraph
    {
        $subgraphIdentity = [
            'editingSession' => $nodeData->getWorkspace()->getName()
        ];
        $dimensionValues = [];
        foreach ($nodeData->getDimensionValues() as $dimensionName => $dimensionValue) {
            $subgraphIdentity[$dimensionName] = reset($dimensionValue);
            $dimensionValues[$dimensionName] = reset($dimensionValue);
        }
        $subgraphIdentifier = SubgraphUtility::hashIdentityComponents($subgraphIdentity);

        return $this->fallbackGraphService->getInterDimensionalFallbackGraph()->getSubgraph($subgraphIdentifier);
    }

    protected function publishNodeDataRemoval(ContentRepository\Model\NodeData $nodeData)
    {
    }

    /**
     * @param ContentRepository\Model\NodeData|null $nodeData
     * @return ContentRepository\Model\NodeData|null
     */
    protected function fetchParent(ContentRepository\Model\NodeData $nodeData)
    {
        return $this->nodeDataRepository->findOneByPath(
            $nodeData->getParentPath(),
            $nodeData->getWorkspace(),
            $nodeData->getDimensionValues()
        );
    }

    /**
     * @param ContentRepository\Model\NodeData $nodeData
     * @return ContentRepository\Model\NodeData|null
     */
    protected function fetchElderSibling(ContentRepository\Model\NodeData $nodeData)
    {
        /** @var ContentRepository\Model\NodeData $elderSibling */
        $elderSibling = null;
        foreach ($this->nodeDataRepository->findByParentAndNodeType(
            $nodeData->getParentPath(),
            null,
            $nodeData->getWorkspace(),
            $nodeData->getDimensionValues()) as $sibling) {
            /** @var ContentRepository\Model\NodeData $sibling */
            if ($sibling->getIndex() < $nodeData->getIndex()
                && (!$elderSibling || $sibling->getIndex() > $elderSibling->getIndex())) {
                $elderSibling = $sibling;
            }
        }

        return $elderSibling;
    }
}
