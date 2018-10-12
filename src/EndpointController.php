<?php

namespace MakinaCorpus\Drupal\NodeSearch;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class EndpointController
{
    public function search(Request $request)
    {
        // @todo Ideally services would be injected as parameters
        /** @var \MakinaCorpus\Drupal\NodeSearch\NodeSearcher $searcher */
        $searcher = \Drupal::service('nodesearch_node_searcher');

        $inputDef = $searcher->createInputDefinition();
        $query = $inputDef->createQueryFromRequest($request);
        $ret = $searcher->find($query);

        if ($ret['result']) {
            $ret['result'] = $searcher->createResultAll($query->get('entity', 'node'), $ret['result']);
        }

        return new JsonResponse($ret);
    }
}
