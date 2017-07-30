<?php

namespace Neos\ContentRepository\EventSourced\Command;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Arboretum\Neo4jAdapter\Infrastructure\Service\Neo4jClient;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\EventSourced\Domain\Model\Content\Event;
use Neos\EventSourcing\Event\EventPublisher;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\ContentContextFactory;

/**
 * The test command controller
 */
class TestCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contentContextFactory;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var EventPublisher
     */
    protected $eventPublisher;

    /**
     * @Flow\Inject
     * @var \Neos\Arboretum\Neo4jAdapter\Domain\Repository\ContentGraph
     */
    protected $neo4jContentGraph;

    /**
     * @Flow\Inject
     * @var \Neos\Arboretum\DoctrineDbalAdapter\Domain\Repository\ContentGraph
     */
    protected $dbalContentGraph;


    public function publishSitesNodeCommand()
    {
        $this->eventPublisher->publish('neoscr-content', new Event\SystemNodeWasInserted(
            'fc9e984d-225a-4446-907b-59c25bf5fd5f',
            '80378b69-de58-4427-8b6d-3751036f7c7d',
            'Neos.Neos:Sites'
        ));
    }

    public function traverseCommand()
    {
        $site = $this->siteRepository->findFirstOnline();
        /** @var ContentContext $contentContext */
        $contentContext = $this->contentContextFactory->create([
            'dimensions' => ['language' => ['en_US']],
            'targetDimensions' => ['language' => 'en_US'],
            'currentSite' => $site,
            'currentDomain' => $site->getFirstActiveDomain()
        ]);

        $siteNode = $contentContext->getCurrentSiteNode();
        $time = microtime(true);
        $count = 0;
        $this->traverseNode($siteNode, function (NodeInterface $node) use(&$count) {
            $count++;
        });
        \Neos\Flow\var_dump(round((microtime(true) - $time) * 1000), 'Legacy: ' . $count);

        $dbalSubgraph = $this->dbalContentGraph->getSubgraph('live', ['language' => 'en_US']);
        $rootNode = $dbalSubgraph->findNodesByType('Neos.Demo:Homepage')[0];

        $time = microtime(true);
        $count = 0;
        $dbalSubgraph->traverse($rootNode, function (NodeInterface $node) use(&$count) {
            $count++;
        });
        \Neos\Flow\var_dump(round((microtime(true) - $time) * 1000), 'DBAL: ' . $count);

        $neo4jSubgraph = $this->neo4jContentGraph->getSubgraph('live', ['language' => 'en_US']);
        $rootNode = $neo4jSubgraph->findNodesByType('Neos.Demo:Homepage')[0];

        $time = microtime(true);
        $count = 0;
        $neo4jSubgraph->traverse($rootNode, function (NodeInterface $node) use(&$count) {
            $count++;
        });
        \Neos\Flow\var_dump(round((microtime(true) - $time) * 1000), 'neo4j: ' . $count);
    }

    protected function traverseNode(NodeInterface $node, callable $callback)
    {
        $callback($node);
        foreach ($node->getChildNodes() as $childNode) {
            $this->traverseNode($childNode, $callback);
        }
    }
}
