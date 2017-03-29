<?php
namespace Neos\ContentRepository\EventSourced\Application\Persistence\Traits;

use Neos\ContentRepository\EventSourced\Application\Persistence\DoctrinePersistableInterface;

trait GenericEntityQueryTrait
{
    protected function add(DoctrinePersistableInterface $entity)
    {
        $tableName = $entity->getTableName();
        $values = $entity->serialize($this->getEntityManager());

        $valuePlaceholders = array_map(function ($value) { return '?'; }, $values);

        $this->getEntityManager()->getConnection()->executeQuery(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $tableName,
                implode(', ', array_keys($values)),
                implode(', ', $valuePlaceholders)
            ),
            array_values($values)
        );
    }

    protected function update(DoctrinePersistableInterface $entity)
    {
        $tableName = $entity->getTableName();
        $values = $entity->serialize($this->getEntityManager());
        $conditions = $entity->keys();

        $valuePlaceholders = $this->asPlaceholders($values);
        $conditionPlaceholders = $this->asPlaceholders($conditions);

        $this->getEntityManager()->getConnection()->executeQuery(
            sprintf(
                'UPDATE %s SET %s WHERE %s',
                $tableName,
                implode(', ', $valuePlaceholders),
                implode(' AND ', $conditionPlaceholders)
            ),
            array_merge($values, $conditions)
        );
    }

    protected function remove(DoctrinePersistableInterface $entity)
    {
        $tableName = $entity->getTableName();
        $conditions = $entity->keys();

        $conditionPlaceholders = $this->asPlaceholders($conditions);

        $this->getEntityManager()->getConnection()->executeQuery(
            sprintf('DELETE FROM %s WHERE %s',
            $tableName,
            implode(' AND ', $conditionPlaceholders)
            ),
            $conditions
        );
    }

    private function asPlaceholders($conditions)
    {
        $result = [];
        foreach ($conditions as $key => $value) {
            $result[] = $key . ' = :' . $key;
        }

        return $result;
    }
}
