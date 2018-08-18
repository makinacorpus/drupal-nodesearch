<?php

namespace Drupal\nodesearch\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Node selector widget.
 *
 * @FormElement("nodesearch")
 */
class NodeSearch extends FormElement
{
    const DEFAULT_MAX = 1000;

    /**
     * {@inheritdoc}
     */
    public function getInfo()
    {
        return [
            '#process' => [
                [static::class, 'processNodeSearch'],
                [static::class, 'processAjaxForm'],
                [static::class, 'processGroup'],
            ],
            '#element_validate' => [
                [static::class, 'validateNodeSearch'],
            ],
            // Entity type (defaults to node)
            '#entity_type' => 'node',
            // Allowed bundles (if empty all content type are allowed)
            '#bundles' => [],
            // Will be the selection dialog title.
            '#title' => null,
            // Will be the search input placeholder.
            '#placeholder' => null,
            // If element is multiple, set this to any number higher than 0. If you
            // set 1 here, element will fallback on NOT being multiple anyway.
            '#max' => self::DEFAULT_MAX,
            // Will fallback to 1 if element is required.
            '#min' => 0,
            // Element will work with multiple input.
            '#multiple' => false,
            '#input' => true,
            '#attached' => [
                'library' => [
                    'nodesearch/react-dev',
                    'nodesearch/nodesearch',
                ]
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function valueCallback(&$element, $input, FormStateInterface $form_state)
    {
        $values = [];

        if ($input) {
            $values = \array_filter(\explode(',', $input['values']));
        } else if (!empty($element['#default_value'])) {
            if (\is_array($element['#default_value'])) {
                $values = $element['#default_value'];
            } else {
                $values = [$element['#default_value']];
            }
        }

        return $values;
    }

    /**
     * Validate callback.
     */
    public static function validateNodeSearch(array &$element, FormStateInterface $form_state)
    {
        $value = self::normalizeValues($element);

        // Ensure bounds.
        $count = \count($value);
        if ($element['#min'] && $count < $element['#min']) {
            $form_state->setError($element, new PluralTranslatableMarkup(
                $element['#min'],
                "%name must contain at least @count node.",
                "%name must contain at least @count nodes.",
                ['%name' => $element['#title']]
            ));
        }
        if ($element['#max'] && $count > $element['#max']) {
            $form_state->setError($element, new PluralTranslatableMarkup(
                $element['#max'],
                "%name can contain a maximum of @count node.",
                "%name can contain a maximum of @count nodes.",
                ['%name' => $element['#title']]
            ));
        }

        // Ensure all nodes exist and have the right bundle.
        if ($value) {
            // Handle duplicates (this is valid to have duplicates).
            $idList = \array_unique($value);
            $entities  = \Drupal::entityTypeManager()->getStorage($element['#entity_type'])->loadMultiple($idList);

            if (\count($entities) !== count($idList)) {
                $form_state->setError($element, new TranslatableMarkup("%name contains one or more non existing nodes.", ['%name' => $element['#title']]));
            }

            if ($element['#bundles']) {
                if (is_array($element['#bundles'])) {
                    $bundles = $element['#bundles'];
                } else {
                    $bundles = [$element['#bundles']];
                }

                foreach ($entities as $entity) {
                    if (!in_array($entity->bundle(), $bundles)) {
                        $form_state->setError($element, new TranslatableMarkup("%name's %title does not have an allowed node type.", [
                            '%name' => $element['#title'],
                            '%title' => $entity->label(),
                        ]));
                    }
                }
            }
        }
    }

    /**
     * Normalize element values and return them.
     */
    protected static function normalizeValues(array $element): array
    {
        $values = [];

        if (!empty($element['#value'])) {
            // Normalize input depending on the widget being multiple or not.
            if (\is_array($element['#value'])) {
                // We have a problem here, the element do not allow more than one value
               // take it all anyway, we will reduce later after having load the nodes.
                $values = $element['#value'];
            } else {
                $values = [$element['#value']];
            }
        }

        return $values;
    }

    /**
     * Expand element.
     */
    public static function processNodeSearch(&$element, FormStateInterface $form_state, &$complete_form)
    {
        $values   = [];
        $required = (bool)$element['#required'];
        $multiple = (bool)$element['#multiple'];
        $min      = (int)$element['#min'];
        $max      = (int)$element['#max'];
        $output   = [];

        // Normalize bundles
        if (\is_array($element['#bundles'])) {
            $bundles = $element['#bundles'];
        } else {
            $bundles = $element['#bundles'] = [$element['#bundles']];
        }

        if (!$max && !$multiple) { // Not multiple item means max is 1.
          $max = 1;
        }
        if (!$min && $required) { // Required item means min is at least 1.
          $min = 1;
        }

        // Default minimum value normalisation.
        if ($min < 0) {
            if ($required) {
                \trigger_error(sprintf("nodesearch widget min value %d is lower than 0, fallback to 1 (value is required)", $min), E_USER_WARNING);
                $min = 1;
            } else {
                \trigger_error(sprintf("nodesearch widget min value %d is lower than 0, fallback to 0 (value is optional)", $min), E_USER_WARNING);
                $min = 0;
            }
        } else if (0 < $min && !$required) {
            \trigger_error(\sprintf("nodesearch widget min value %d is higher than 0, but value is optional, falling back to being required", $min), E_USER_WARNING);
            $required = true;
        }

        // Default max value normalisation.
        if ($max && $max < $min) {
            \trigger_error(\sprintf("nodesearch widget min value %d is higher than max value %d, falling back to %d", $min, $max, NODESEARCH_ELEMENT_DEFAULT_MAX), E_USER_WARNING);
            // Ensure that we are always higher or equal than min.
            $max = \max([$min, NODESEARCH_ELEMENT_DEFAULT_MAX]);
        }
        if ($max === 1 && $multiple) {
            \trigger_error(\sprintf("nodesearch widget max value %d is higher than 1, but value is single, falling back to being multiple", $min), E_USER_WARNING);
            $multiple = false;
        }

        // Required for value processing
        $element['#tree'] = true;
        $element['#theme_wrappers'] = ['form_element'];
        // Reset item internals
        $element['#multiple'] = $multiple;
        $element['#required'] = $required;
        $element['#min'] = $min;
        $element['#max'] = $max;

        if ($values = self::normalizeValues($element)) {
            $entityType = $element['#entity_type'];

            // Validate input by loading all the entities. Work on a copy of
            // the values so that we can keep duplicates entries in the final
            // widget.
            $idList = \array_unique($values);

            $entities  = \Drupal::entityTypeManager()->getStorage($entityType)->loadMultiple($idList);
            if (\count($entities) !== \count($idList)) {
                \trigger_error("nodesearch widget contains one or more node that don't exist", E_USER_NOTICE);
            }

            /** @var \MakinaCorpus\Drupal\NodeSearch\NodeSearcher $searcher */
            $searcher = \Drupal::service('nodesearch_node_searcher');
            $output = $searcher->createResultAll($entityType, $entities, true);

            // In all cases, normalize values input (ordering is kept here).
            $idList = \array_keys($entities);
        }

        // Ensure that number of nodes is in adequation with min and max values.
        if ($max && $max < \count($values)) {
            \trigger_error(\sprintf("nodesearch widget contains %d nodes whereas max count is %d, validation will fail", count($values), $max), E_USER_NOTICE);
        }

        // Hidden element containing the JavaScript result, if any.
        $element['values'] = [
            '#type'          => 'hidden',
            '#default_value' => \implode(',', $values),
            '#attributes'    => [
                'data-nodesearch'   => "true",
                'data-entity'       => $element['#entity_type'],
                'data-title'        => $element['#title'] ?? '',
                'data-placeholder'  => $element['#placeholder'] ?? '',
                'data-default'      => \json_encode($output),
                'data-min'          => $min,
                'data-max'          => $max,
                'data-bundle'       => \implode(',', $bundles),
            ],
        ];

        return $element;
    }
}
