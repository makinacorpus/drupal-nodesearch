<?php

namespace MakinaCorpus\Drupal\NodeSearch\Handler;

use MakinaCorpus\Calista\Query\Query;
use MakinaCorpus\Drupal\NodeSearch\EntityHandlerInterface;

class DefaultHandler implements EntityHandlerInterface
{
    use ImageFieldTrait;

    private $additionalTypes = [];

    /**
     * Default constructor
     */
    public function __construct(array $additionalTypes = [])
    {
        $this->additionalTypes = $additionalTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes(): array
    {
        return ['file', 'media', 'node'] + $this->additionalTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function findImage(string $type, $entity)
    {
        if (\property_exists($entity, 'uri') && !empty($entity->uri)) {
            return \file_create_url($entity->uri);
        } else {
            return $this->findImageFor($entity);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function processQuery(string $type, Query $query, \SelectQuery $select)
    {
        // Order by view history
        if ('node' === $type) {
            if ($userId = $query->get('user_id')) {
                $select->leftJoin('history', 'h', "h.nid = n.nid AND h.uid = :history_uid", [':history_uid' => $userId]);
            } else {
                $select->leftJoin('history', 'h', "h.nid = n.nid AND 1 = 0");
            }
            $select->addTag('node_access');
        }
    }
}
