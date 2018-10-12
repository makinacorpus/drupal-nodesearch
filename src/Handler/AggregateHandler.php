<?php

namespace MakinaCorpus\Drupal\NodeSearch\Handler;

use Drupal\Core\Entity\EntityInterface;
use MakinaCorpus\Calista\Query\Query;
use MakinaCorpus\Drupal\NodeSearch\EntityHandlerInterface;

class AggregateHandler implements EntityHandlerInterface
{
    /**
     * @var EntityHandlerInterface[]
     */
    private $handlers = [];
    private $types = [];

    /**
     * Default constructor.
     */
    public function __construct(array $handlers)
    {
        // @todo this should be in a compiler phase and not in runtime
        /** @var \MakinaCorpus\Drupal\NodeSearch\EntityHandlerInterface $handler */
        foreach ($handlers as $handler) {
            foreach ($handler->getSupportedTypes() as $type) {
                if (!isset($this->types[$type])) {
                    $this->handlers[$type] = $handler;
                    $this->types[] = $type;
                }
            }
        }
    }

    /**
     * Get supported entity types
     *
     * @return string[]
     */
    public function getSupportedTypes(): array
    {
        return $this->types;
    }

    /**
     * Get entity thumbnail image
     *
     * @return null|string
     *   The image Drupal URI
     */
    public function findImage(string $type, EntityInterface $entity)
    {
        if ($handler = ($this->handlers[$type] ?? null)) {
            return $handler->findImage($type, $entity);
        }
    }

    /**
     * Process query if there is any entity specifics to process
     */
    public function processQuery(string $type, Query $query, \SelectQuery $select)
    {
        if ($handler = ($this->handlers[$type] ?? null)) {
            return $handler->processQuery($type, $query, $select);
        }
    }
}
