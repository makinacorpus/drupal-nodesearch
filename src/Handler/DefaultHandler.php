<?php

namespace MakinaCorpus\Drupal\NodeSearch\Handler;

use Drupal\Core\Database\Query\Select;
use Drupal\Core\Entity\EntityInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use MakinaCorpus\Calista\Query\Query;
use MakinaCorpus\Drupal\NodeSearch\EntityHandlerInterface;
use Drupal\Core\Image\ImageFactory;

class DefaultHandler implements EntityHandlerInterface
{
    use ImageFieldTrait;

    private $additionalTypes = [];
    private $imageFactory;

    /**
     * Default constructor
     */
    public function __construct(ImageFactory $imageFactory, array $additionalTypes = [])
    {
        $this->additionalTypes = $additionalTypes;
        $this->imageFactory = $imageFactory;
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
    public function findImage(string $type, EntityInterface $entity)
    {
        if ($entity instanceof MediaInterface) {
            if ($uri = $entity->getSource()->getMetadata($entity, 'thumbnail_uri')) {
                return \file_create_url($uri);
            }
        } else if ($entity instanceof FileInterface) {
            $uri = $entity->getFileUri();
            if ($this->imageFactory->get($uri)->isValid()) {
                return \file_create_url($uri);
            }
        } else {
            return $this->findImageFor($entity);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function processQuery(string $type, Query $query, Select $select)
    {
        // Order by view history
        if ('node' === $type) {
            if ($userId = $query->get('user_id')) {
                $select->leftJoin('history', 'h', "h.nid = n.nid AND h.uid = :history_uid", [':history_uid' => $userId]);
            } else {
                $select->leftJoin('history', 'h', "h.nid = n.nid AND 1 = 0");
            }
        }
    }
}
