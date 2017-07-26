<?php

namespace Neos\ContentRepository\EventSourced\Application\Repository;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\EventSourced\Utility\SubgraphUtility;
use Neos\Flow\Annotations as Flow;

/**
 * The content graph application service
 *
 * To be used as a read-only source of nodes and edges
 *
 * @Flow\Scope("singleton")
 * @api
 */
class ContentGraph
{
    /**
     * @var array|ContentSubgraph[]
     */
    protected $subgraphs;


    public function getSubgraph(string $workspaceName, array $dimensionValues): ContentSubgraph
    {
        $subgraphIdentity = array_merge(['editingSession' => $workspaceName], $dimensionValues);

        return new ContentSubgraph(SubgraphUtility::hashIdentityComponents($subgraphIdentity));
    }
}
