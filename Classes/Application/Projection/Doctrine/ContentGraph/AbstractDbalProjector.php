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

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Neos\Flow\Annotations as Flow;

/**
 * The abstract Doctrine DBAL entity finder
 */
abstract class AbstractDbalProjector
{
    /**
     * @Flow\Inject(lazy=FALSE)
     * @var ObjectManager
     */
    protected $entityManager;


    final protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    final protected function getDatabaseConnection(): Connection
    {
        return $this->entityManager->getConnection();
    }
}
