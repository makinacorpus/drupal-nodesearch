<?php

namespace MakinaCorpus\Drupal\NodeSearch;

use MakinaCorpus\Calista\Query\InputDefinition;
use MakinaCorpus\Calista\Query\Query;
use MakinaCorpus\Calista\Query\Filter;

/**
 * @tainted Drupal 7
 */
class NodeSearcher
{
    const LIMIT_DEFAULT = 12;
    const LIMIT_MAX = 100;

    /**
     * Get allowed node type names
     *
     * @return string[]
     *   Keys are node bundles, values are human readable names.
     *   Site-wide blacklisted node types will be excluded from this result.
     */
    static public function getAllowedNodeTypes(): array
    {
        $types = node_type_get_names();
        // Filter out site-wide black listed content types.
        if ($blacklisted = variable_get('nodesearch_endpoint_node_type_blacklist', [])) {
            $types = \array_diff_key($types, array_flip($blacklisted));
        }

        return $types;
    }

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
    private $limitDefault = self::LIMIT_DEFAULT;
    private $limitMax = self::LIMIT_MAX;
    private $publishedOnly = true;
    private $wildcardAllowPrefix = true;

    /**
     * Default constructor
     */
    public function __construct(bool $debug = true)
    {
        $this->debug = $debug;

        // @todo Drupal 7 tainted
        $this->limitDefault = (int)\variable_get('nodesearch_endpoint_limit_default', self::LIMIT_DEFAULT);
        $this->limitMax = (int)\variable_get('nodesearch_endpoint_limit_max', self::LIMIT_MAX);
        $this->publishedOnly = (bool)\variable_get('nodesearch_endpoint_published_only', $this->publishedOnly);
        $this->wildcardPrefix = (bool)\variable_get('nodesearch_endpoint_prefix_wildcard_enable', $this->wildcardAllowPrefix);
    }

    /**
     * Create input definition for building the query
     */
    public function createInputDefinition() : InputDefinition
    {
        $allowedNodeTypes = self::getAllowedNodeTypes();

        return new InputDefinition([
            'base_query'          => ['type' => \array_keys($allowedNodeTypes)],
            'filter_list'         => [
                (new Filter('status'))->setChoicesMap([1 => 'Published', 0 => 'Unpublished']),
                (new Filter('type'))->setChoicesMap($allowedNodeTypes),
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
     * Query database for content, returns the JSON response as array.
     */
    public function find(Query $query): array
    {
        $userId = $query->get('user_id');

        // Without any types configured, this serves no purpose
        if (!$allowedTypes = self::getAllowedNodeTypes()) {
            return MENU_NOT_FOUND;
        }

        // Create the query, the rest will flow along.
        $select = \db_select('node', 'n');
        $select->fields('n', ['nid', 'title', 'status', 'created', 'changed', 'type']);
        $select->addTag('node_access');
        // Allow other modules to compete with us (contextual filtering, etc...).
        $select->addTag('nodesearch');

        $types = [];
        if ($query->has('type')) {
            $select->condition('n.type', $types = $query->get('type'));
        } else {
            $select->condition('n.type', \array_keys($allowedTypes));
        }

        if ($query->has('status')) {
            $select->condition((int)(bool)$query->get('status'));
        } else if ($this->publishedOnly) {
            $select->condition('n.status', 1);
        }

        if ($rawSearchString = $query->getRawSearchString()) {
            // As of now, only title search is allowed
            if ($this->wildcardAllowPrefix) {
                $select->condition('n.title', '%'.\db_like($rawSearchString).'%', 'like');
            } else {
                $select->condition('n.title', \db_like($rawSearchString).'%', 'like');
            }
        }

        // Order by view history
        if ($userId) {
            $select->leftJoin('history', 'h', "h.nid = n.nid AND h.uid = :history_uid", [':history_uid' => $userId]);
        } else {
            $select->leftJoin('history', 'h', "h.nid = n.nid AND 1 = 0");
        }

        // Allow a boolean field "my content only".
        if ($userId) {
            if ($query->get('user_touched')) {
                $revisionExists = \db_select('node_revision', 'r')
                    ->condition('r.uid', $query->has('revision_user_id'))
                    ->where("r.nid = n.nid")
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
            $drupalSortOrder = $query->isSortAsc() ? 'asc' : 'desc';

            switch ($query->getSortField()) {
                case 'title':
                    $sortFieldReal = 'n.title';
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
            $select->orderBy('n.nid', $drupalSortOrder);

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
            'types'       => $types ? $types : \array_keys($allowedTypes),
            'types_all'   => $allowedTypes,
        ];
    }
}
