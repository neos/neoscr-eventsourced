<?php
namespace Neos\ContentRepository\EventSourced\Controller;

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
use Neos\ContentRepository\EventSourced\Presentation\Model\DrawIo\IntraDimensionalFallbackGraph;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;

/**
 * The fallback graph controller
 */
class FallbackGraphController extends ActionController
{
    /**
     * @Flow\Inject
     * @var FallbackGraphService
     */
    protected $fallbackGraphService;


    public function showIntraDimensionalFallbackGraphAction()
    {
        $this->view->assign('graph', new IntraDimensionalFallbackGraph($this->fallbackGraphService->getIntraDimensionalFallbackGraph()));
    }
}
