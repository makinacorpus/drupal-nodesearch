<?php

namespace MakinaCorpus\Drupal\NodeSearch;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Entity\EntityPublishedInterface;
use MakinaCorpus\Calista\Query\Filter;
use MakinaCorpus\Calista\Query\InputDefinition;
use MakinaCorpus\Calista\Query\Query;

class NodeSearcher
{
    /**
     * Get allowed sort fields names
     *
     * @return string[]
     *   Keys are field names, values are human readable names.
     */
    static public function getAllowedSorts()
    {
        return [
            'created'       => t("Creation date"),
            'status'        => t("Published"),
            'title'         => t("Title"),
            'updated'       => t("Updated"),
            'user_touched'  => t("Latest edition"),
            'user_viewed'   => t("Latest view"),
        ];
    }

    private $debug = false;
    private $entityTypeManager;
    private $handler;
    private $limitDefault = 12;
    private $limitMax = 100;
    private $publishedOnly = true;
    private $wildcardAllowPrefix = true;

    /**
     * Default constructor
     */
    public function __construct(
        EntityManager $entityTypeManager,
        EntityHandlerInterface $handler,
        int $limitDefault = 12,
        int $limitMax = 100,
        bool $publishedOnly = true,
        bool $wildcardPrefix = true,
        bool $debug = true
    ) {
        $this->debug = $debug;
        $this->entityTypeManager = $entityTypeManager;
        $this->handler = $handler;
        $this->limitDefault = $limitDefault;
        $this->limitMax = $limitMax;
        $this->publishedOnly = $publishedOnly;
        $this->wildcardPrefix = $wildcardPrefix;
    }

    /**
     * Get allowed node type names
     *
     * @return string[]
     *   Keys are node bundles, values are human readable names.
     *   Site-wide blacklisted node types will be excluded from this result.
     */
    private function getAllowedBundles(string $entityType): array
    {
        $info = \entity_get_info($entityType);
        $bundles = \array_map(function ($bundleInfo) { return $bundleInfo['label']; }, $info['bundles'] ?? []);

        $blacklisted = [];
        if ($blacklisted) {
            $bundles = \array_diff_key($bundles, \array_flip($blacklisted));
        }

        return $bundles;
    }

    /**
     * Create input definition for building the query
     */
    public function createInputDefinition() : InputDefinition
    {
        // @todo fix this
        //$allowedNodeTypes = $this->getAllowedNodeTypes();
        return new InputDefinition([
            'base_query'          => [],// ['type' => \array_keys($allowedNodeTypes)],
            'filter_list'         => [
                (new Filter('status'))->setChoicesMap([1 => 'Published', 0 => 'Unpublished']),
                (new Filter('entity'))->setChoicesMap($this->handler->getSupportedTypes()),
                (new Filter('type')), //->setChoicesMap($allowedNodeTypes),
                (new Filter('user_created'))->setChoicesMap([1 => 'Yes', 0 => 'All']),
                (new Filter('user_touched'))->setChoicesMap([1 => 'Yes', 0 => 'All']),
            ],
            'limit_allowed'       => true,
            'limit_default'       => $this->limitDefault,
            'limit_max'           => $this->limitMax,
            'limit_param'         => 'limit',
            'pager_enable'        => true,
            'pager_param'         => 'page',
            'search_enable'       => true,
            'search_param'        => 'search',
            'search_parse'        => false,
            'sort_allowed_list'   => array_keys(self::getAllowedSorts()),
            'sort_default_field'  => 'updated',
            'sort_default_order'  => Query::SORT_DESC,
            'sort_field_param'    => 'sort_field',
            'sort_order_param'    => 'sort_order',
        ], $this->debug);
    }

    /**
     * Create result for all given result row
     */
    public function createResultAll(string $entityType, array $results, bool $alreadyLoaded = false): array
    {
        if (!$results) {
            return [];
        }

        if (!$alreadyLoaded) {
            if (!$idList = \array_map(function ($row) { return $row->nid; }, $results)) {
                return [];
            }
            if (!$results = $this->entityTypeManager->getStorage($entityType)->loadMultiple($idList)) {
                return [];
            }
        }

        return \array_values(\array_map(function ($entity) { return $this->createResult($entity); }, $results));
    }

    /**
     * Build result array from node result row
     */
    public function createResult(EntityInterface $result): array
    {
        $entityType = $result->getEntityTypeId();

        // @todo Drupal 7 tainted - find bundle label.
        $bundleLabel = \nodesearch_bundle_label($entityType, $result);

        $ret = [
            'id'          => $result->id(),
            'title'       => (string)$result->label(),
            'status'      => 1,
            'created'     => null,
            'updated'     => null,
            'type'        => $entityType,
            'human_type'  => $bundleLabel,
            'image'       => $this->handler->findImage($entityType, $result),
        ];

        if ($result instanceof EntityPublishedInterface) {
            $ret['status'] = (int)$result->isPublished();
        }
        if ($result instanceof EntityChangedInterface) {
           $ret['updated'] = (new \DateTimeImmutable('@'.$result->getChangedTime()))->format(\DateTime::ISO8601);
        }
        // @todo awaiting for a core generic interface for this
        if (\method_exists($result, 'getCreatedTime')) {
            $ret['created'] = (new \DateTimeImmutable('@'.$result->getCreatedTime()))->format(\DateTime::ISO8601);
        }

        return $ret;
    }

    /**
     * Query database for content, returns the JSON response as array.
     */
    public function find(Query $query): array
    {
        $userId = $query->get('user_id');
        $entityType = $query->get('entity', 'node');

        // Without any types configured, this serves no purpose
        if (!$allowedTypes = $this->getAllowedBundles($entityType)) {
            return [];
        }

        $entityTypeDef = $this->entityTypeManager->getDefinition($entityType);
        $entityKeys = $entityTypeDef->getKeys();
        $bundleColumn = $entityKeys['bundle'];
        $idCol = $entityKeys['id'];
        $nameCol = $entityKeys['label'];
        // Some entities won't have a data table.
        if (!$baseTable = $entityTypeDef->getDataTable()) {
            $baseTable = $entityTypeDef->getBaseTable();
        }

        // Create the query, the rest will flow along.
        $select = \db_select($baseTable, 'n');
        $select->fields('n', ['status', 'created', 'changed']);
        $select->addField('n', $idCol, 'nid');
        $select->addField('n', $nameCol, 'title');

        // Some entity tables don't have a bundle column.
        $types = [];
        if ($bundleColumn) {
            $select->addField('n', $bundleColumn, 'type');
            if ($query->has('type') && ($types = $query->get('type'))) {
                $select->condition('n.'.$bundleColumn, (array)$types, 'IN');
            } else {
                $select->condition('n.'.$bundleColumn, \array_keys($allowedTypes), 'IN');
            }
        }

        // Allow other modules to compete with us (contextual filtering, etc...).
        $select->addTag('nodesearch');

        if ($query->has('status')) {
            $select->condition('n.status', (int)(bool)$query->get('status'));
        } else if ($this->publishedOnly) {
            $select->condition('n.status', 1);
        }

        if ($rawSearchString = $query->getRawSearchString()) {
            // As of now, only title search is allowed
            if ($this->wildcardAllowPrefix) {
                $select->condition('n.'.$nameCol, '%'.\db_like($rawSearchString).'%', 'LIKE');
            } else {
                $select->condition('n.'.$nameCol, \db_like($rawSearchString).'%', 'LIKE');
            }
        }

        // Allow a boolean field "my content only".
        if ($userId) {
            if ($query->get('user_touched')) {
                $revisionExists = \db_select('node_revision', 'r')
                    ->condition('r.revision_uid', (int)(bool)$query->has('revision_user_id'))
                    ->where('r.'.$idCol.' = n.'.$idCol)
                    ->range(0, 1)
                ;
                $revisionExists->addExpression('1');
                $select->exists($revisionExists);
            }
            if ($query->get('user_created')) {
                $select->condition('n.uid', $userId);
            }
        }

        // Count query before sort, seems legit, give the front a range to play with.
        $countSelect = $select->countQuery();
        $total = (int)$countSelect->execute()->fetchField();
        $result = [];

        if ($total) {
            $sortFieldReal = null;
            $drupalSortOrder = Query::SORT_DESC !== $query->getSortOrder() ? 'asc' : 'desc';

            // @todo be aware that some entity types don't have those fields
            //    this code should probably moved to the entity handler instead
            switch ($query->getSortField()) {
                case 'title':
                    $sortFieldReal = 'n.'.$nameCol;
                    break;
                case 'updated':
                    $sortFieldReal = 'n.changed';
                    break;
                case 'created':
                    $sortFieldReal = 'n.created';
                    break;
                case 'status':
                    $sortFieldReal = 'n.status';
                    break;
                case 'user_viewed':
                    $sortFieldReal = 'h.timestamp';
                    break;
                case 'user_touched':
                    $sortFieldReal = 'r.timestamp';
                    break;
            }
            // @todo Oh yes please, add a NULLS FIRST | LAST at the right place.
            if ($sortFieldReal) {
                $select->orderBy($sortFieldReal, $drupalSortOrder);
            }

            // Always end ordering by a default to make result predictible
            $select->orderBy('n.'.$idCol, $drupalSortOrder);

            // If page is higher than max page, then reset the current page.
            $select->range($query->getOffset(), $query->getLimit());

            $result = $select->execute()->fetchAll();
        }

        return [
            'limit'       => $query->getLimit(),
            'page'        => $total ? $query->getPageNumber() : 1,
            'result'      => $result,
            'sort_field'  => $query->getSortField(),
            'sort_order'  => $query->getSortOrder(),
            'total'       => $total,
            'types'       => $types ? (\is_array($types) ? $types : [$types]) : \array_keys($allowedTypes),
            'types_all'   => $allowedTypes,
        ];
    }
}
