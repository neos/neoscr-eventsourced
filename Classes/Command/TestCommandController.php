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

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\EventSourced\Application\Projection\Doctrine\ContentGraph\Node;
use Neos\ContentRepository\EventSourced\Application\Repository\ContentGraph;
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
     * @var ContentGraph
     */
    protected $contentGraph;

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


    public function traverseCommand()
    {
        $subgraph = $this->contentGraph->getSubgraph('live', ['language' => 'en_US']);
        $rootNode = $subgraph->findNodesByType('Neos.Demo:Homepage')[0];

        $time = microtime(true);
        $subgraph->traverse($rootNode, function (Node $node) {
            #\Neos\Flow\var_dump($node->nodeTypeName, $node->properties['title'] ?? '');
        });
        \Neos\Flow\var_dump(round((microtime(true) - $time) * 1000));

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
        $this->traverseNode($siteNode, function (NodeInterface $node) {
            #\Neos\Flow\var_dump($node->getNodeType()->getName(), $node->getProperty('title'));
        });
        \Neos\Flow\var_dump(round((microtime(true) - $time) * 1000));
    }

    protected function traverseNode(NodeInterface $node, callable $callback)
    {
        $callback($node);
        foreach ($node->getChildNodes() as $childNode) {
            $this->traverseNode($childNode, $callback);
        }
    }
}
