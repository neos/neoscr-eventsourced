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

use Neos\ContentRepository\EventSourced\Application\Projection\Doctrine\ContentGraph\NodeFinder;
use Neos\ContentRepository\EventSourced\Application\Projection\Doctrine\ContentGraph\NodeFinderQueryConstraints;
use Neos\ContentRepository\EventSourced\Utility\SubgraphUtility;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * The test command controller
 */
class TestCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var NodeFinder
     */
    protected $nodeFinder;

    /**
     * @return void
     */
    public function testCommand()
    {
        $queryConstraints = new NodeFinderQueryConstraints(false);
        $this->nodeFinder->findOneByIdentifierInGraph('wat', $queryConstraints);
        $subgraphIdentifier = SubgraphUtility::hashIdentityComponents(['editingSession' => 'live', 'language' => 'en_US']);
        $time = microtime(true);
        $node = $this->nodeFinder->findOneByIdentifierInGraph('789a46cd-9b5f-4aad-8d1e-c76d104fd750', $queryConstraints);

        #\Neos\Flow\var_dump(count($this->nodeFinder->findInSubgraphByParentIdentifierInGraph('80f39ffb3e5b956bbec4d9c8ca3c3c71', 'f37dac0e-c746-4b64-b396-7c4fc87d8f62')));
        \Neos\Flow\var_dump(round((microtime(true) - $time) * 10000));
    }
}
