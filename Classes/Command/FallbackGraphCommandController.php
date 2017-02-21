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

use Neos\ContentRepository\EventSourced\Application\Service\FallbackGraphService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * The fallback graph command controller
 */
class FallbackGraphCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var FallbackGraphService
     */
    protected $fallbackGraphService;


    /**
     * @todo Rather use draw.io for dumping
     */
    public function dumpIntraGraphCommand()
    {
        \Neos\Flow\var_dump($this->fallbackGraphService->getIntraDimensionalFallbackGraph());
    }
    /**
     * @todo Rather use draw.io for dumping
     */
    public function dumpInterGraphCommand()
    {
        \Neos\Flow\var_dump($this->fallbackGraphService->getInterDimensionalFallbackGraph());
    }
}
