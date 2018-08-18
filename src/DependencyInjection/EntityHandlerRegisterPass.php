<?php

namespace MakinaCorpus\Drupal\NodeSearch\DependencyInjection;

use MakinaCorpus\Drupal\NodeSearch\EntityHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;

/**
 * @codeCoverageIgnore
 */
class EntityHandlerRegisterPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('nodesearch_entity_handler')) {
            return;
        }

        $definition = $container->getDefinition('nodesearch_entity_handler');
        $instances = [];

        // Register custom action providers
        $taggedServices = $this->findAndSortTaggedServices('nodesearch_entity_handler', $container);
        foreach ($taggedServices as $reference) {
            $id = (string)$reference;
            $def = $container->getDefinition($id);

            $class = $container->getParameterBag()->resolveValue($def->getClass());
            $refClass = new \ReflectionClass($class);

            if (!$refClass->implementsInterface(EntityHandlerInterface::class)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, EntityHandlerInterface::class));
            }

            $instances[] = $reference;
        }

        $definition->setArguments([$instances]);
    }
}
