<?php
namespace Neos\ContentRepository\EventSourced\Application\Persistence;

use Doctrine\ORM\EntityManager;

interface DoctrinePersistableInterface
{
    /**
     * @return string
     */
    public static function getTableName();

    /**
     * @return array
     */
    public function keys();

    /**
     * @param EntityManager $entityManager
     *
     * @return array
     */
    public function serialize(EntityManager $entityManager);
}