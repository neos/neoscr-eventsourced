<?php
namespace Neos\ContentRepository\EventSourced\Application\Projection\Doctrine\ContentGraph;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;

/**
 * Constraints for queries against the node finder
 */
class NodeFinderQueryConstraints
{
    /**
     * @var bool
     */
    protected $deletionConstraint;

    /**
     * @var array
     */
    protected $nodeTypesConstraint;


    public function __construct(bool $deletionConstraint = false, array $nodeTypesConstraint = [])
    {
        $this->deletionsConstraint = $deletionConstraint;
        $this->nodeTypesConstraint = $nodeTypesConstraint;
    }

    public function withDeletionConstraint()
    {
        $this->deletionConstraint = true;
    }

    public function withoutDeletionConstraint()
    {
        $this->deletionConstraint = false;
    }

    public function __toString(): string
    {
        $queryString = '';
        if ($this->deletionConstraint) {
            $queryString .= ' AND removed = false';
        }
        if ($this->nodeTypesConstraint) {
            $queryString .= ' AND nodetypename :nodeTypeNamesIN (' . implode(',', $this->nodeTypesConstraint) . ')';
        }

        return $queryString;
    }

    public function __toArray(): array
    {
        $parameters = [];

        return $parameters;
    }
}
