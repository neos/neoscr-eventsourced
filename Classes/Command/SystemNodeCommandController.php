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

use Neos\ContentRepository\Domain as ContentRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Utility\Algorithms;

/**
 * The fallback graph command controller
 */
class SystemNodeCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var ContentRepository\Repository\NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var ContentRepository\Repository\WorkspaceRepository
     */
    protected $workspaceRepository;


    /**
     * @return void
     */
    public function restoreSystemNodesCommand()
    {
        /** @var ContentRepository\Model\Workspace $liveWorkspace */
        $liveWorkspace = $this->workspaceRepository->findByIdentifier('live');
        if (is_null($this->nodeDataRepository->findOneByPath('/sites', $liveWorkspace))) {
            $nodeData = new ContentRepository\Model\NodeData('/sites', $liveWorkspace, Algorithms::generateUUID());
            $this->nodeDataRepository->add($nodeData);
        }
    }
}
