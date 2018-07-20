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
        /** @var \MakinaCorpus\Drupal\NodeSearch\NodeResultFormatter $formatter */
        $formatter = \Drupal::service('nodesearch_result_formatter');

        $inputDef = $searcher->createInputDefinition();
        $ret = $searcher->find($inputDef->createQueryFromRequest($request));

        if ($ret['result']) {
            $ret['result'] = $formatter->createResultAll($ret['result']);
        }

        return new JsonResponse($ret);
    }
}
