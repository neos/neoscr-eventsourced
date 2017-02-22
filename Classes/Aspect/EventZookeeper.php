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
use Neos\ContentRepository\EventSourced\Domain\Model\Content\Event;
use Neos\EventSourcing\Event\EventPublisher;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;

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
        /* @todo this also may be a node variant created event */
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
        $dimensionValues = [];
        foreach ($nodeData->getDimensionValues() as $dimensionName => $dimensionValue) {
            $dimensionValues[$dimensionName] = reset($dimensionValue);
        }

        $this->eventPublisher->publish('neoscr-content', new Event\NodeWasInserted(
            $this->persistenceManager->getIdentifierByObject($nodeData),
            $nodeData->getIdentifier(),
            $dimensionValues,
            $nodeData->getNodeType()->getName(),
            $this->persistenceManager->getIdentifierByObject($nodeData->getParent()),
            $nodeData->getName(),
            $nodeData->getIndex(),
            $nodeData->getProperties()
        ));
    }
}
