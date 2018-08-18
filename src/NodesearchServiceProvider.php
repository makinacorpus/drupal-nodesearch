<?php

namespace Drupal\nodesearch;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use MakinaCorpus\Drupal\NodeSearch\DependencyInjection\EntityHandlerRegisterPass;

class NodesearchServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerBuilder $container)
    {
        $container->addCompilerPass(new EntityHandlerRegisterPass());
    }
}
