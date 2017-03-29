<?php
namespace Neos\ContentRepository\EventSourced\Application\Persistence;

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
use Doctrine\ORM\EntityManager;
use Neos\Flow\Annotations as Flow;

/**
 * Generic finder for ReadModels
 *
 * @Flow\Scope("singleton")
 */
abstract class AbstractReadModelFinder
{

    /**
     * @Flow\Inject(lazy=FALSE)
     * @var ObjectManager
     */
    protected $entityManager;

    /**
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    abstract protected function getReadModelTableName();

    abstract protected function mapRawDataToEntity(array $entityData);
}
