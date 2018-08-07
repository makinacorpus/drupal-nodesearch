<?php

namespace MakinaCorpus\Drupal\NodeSearch;

use Drupal\Core\Entity\EntityManager;
use Drupal\node\NodeInterface;

/**
 * @tainted Drupal 7
 */
class NodeResultFormatter
{
    private $entityManager;
    private $withImage = true;

    /**
     * Default constructor
     */
    public function __construct(EntityManager $entityManager, bool $withImage = true)
    {
        $this->entityManager = $entityManager;
        $this->withImage = $withImage;
    }

    /**
     * Create result for all given result row
     */
    public function createResultAll(array $results, bool $alreadyLoaded = false) : array
    {
        if (!$results) {
            return [];
        }

        if (!$alreadyLoaded) {
            if (!$idList = \array_map(function ($row) { return $row->nid; }, $results)) {
                return [];
            }
            if (!$results = $this->entityManager->getStorage('node')->loadMultiple($idList)) {
                return [];
            }
        }

        return \array_values(\array_map(function ($node) { return $this->createResult($node); }, $results));
    }

    /**
     * Build result array from node result row
     */
    public function createResult(NodeInterface $result) : array
    {
        return [
            'id'          => $result->id(),
            'title'       => (string)$result->getTitle(),
            'status'      => (int)$result->isPublished(),
            'created'     => (new \DateTimeImmutable('@'.$result->getCreatedTime()))->format(\DateTime::ISO8601),
            'updated'     => (new \DateTimeImmutable('@'.$result->getChangedTime()))->format(\DateTime::ISO8601),
            'type'        => $result->getEntityTypeId(),
            'human_type'  => $result->getEntityType()->getBundleLabel(),
            'image'       => $this->withImage ? $this->findImage($result) : '',
        ];
    }

    /**
     * Attempt to find a suitable image style automatically
     */
    private function findImageStyle() : string
    {
        $styles = \image_styles();

        // Attempt with the standard profile defaults, most people keep a thumbnail
        // image style somehow. A square one would be better.
        if (isset($styles['thumbnail'])) {
            return 'thumbnail';
        }
        if (isset($styles['medium'])) {
            return 'medim';
        }

        return 'full';
    }

    /**
     * Find image field for given node type
     */
    private function findImageField(string $type) : array
    {
        return [];

        $candidates = [];

        if ($config = \variable_get('nodesearch_preview_image_field')) {
            if (isset($config[$type])) {
                $candidates = \is_array($config[$type]) ? $config[$type] : [$config[$type]];
            }
        }

        // @todo cache result
        if (!$candidates) {
            foreach (\field_info_instances('node', $type) as $fieldname => $instance) {
                $field = \field_info_field($fieldname);
                if ('image' === $field['type'] || 'unoderef' === $field['type']) {
                    $candidates[] = $fieldname;
                }
            }
        }

        return $candidates;
    }

    /**
     * From the given node, attempt to find an image within and return an absolute
     * usable image
     *
     * @param \stdClass|\Drupal\node\NodeInterface $node
     *
     * @return null|string
     *   Absolute usable image for display
     */
    private function findImage($result) : string
    {
        return '';
        $storage = $this->entityManager->getStorage('node');
        $style = \variable_get('nodesearch_preview_image_style', null) ?? $this->findImageStyle();

        if (!$candidates = $this->findImageField($result->type)) {
            return '';
        }

        // Ensure node is properly loaded
        if ($result instanceof NodeInterface) { // @todo suppress this branch?
            $node = $result;
        } else  {
            if (!$node = $storage->load($result->nid)) {
                return '';
            }
        }

        foreach ($candidates as $candidate) {
            if ($items = \field_get_items('node', $node, $candidate)) {

                // 'image' field type.
                if (isset($items[0]['uri'])) {
                    return \image_style_url($style, $items[0]['uri']);
                }

                // 'unoderef' field type: this requires recursivity.
                // @todo
                //   - write a recursivity breaker
                //   - make it faster
                if (isset($items[0]['nid'])) {
                    foreach ($items as $item) {
                        if ($child = $storage->load($item['nid'])) {
                            if ($url = $this->findImage($child)) {
                                return $url;
                            }
                        }
                    }
                }
            }
        }

        return '';
    }
}
