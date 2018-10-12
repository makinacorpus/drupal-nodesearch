<?php

namespace MakinaCorpus\Drupal\NodeSearch;

use Drupal\Core\Entity\EntityInterface;
use MakinaCorpus\Calista\Query\Query;

interface EntityHandlerInterface
{
    /**
     * Get supported entity types
     *
     * @return string[]
     */
    public function getSupportedTypes(): array;

    /**
     * Get entity thumbnail image
     *
     * @return null|string
     *   The image Drupal URI
     */
    public function findImage(string $type, EntityInterface $entity);

    /**
     * Process query if there is any entity specifics to process
     */
    public function processQuery(string $type, Query $query, \SelectQuery $select);
}
