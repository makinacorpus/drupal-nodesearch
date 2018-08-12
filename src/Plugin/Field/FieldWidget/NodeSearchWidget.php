<?php

namespace Drupal\nodesearch\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * NodeSearch field widget.
 *
 * @FieldWidget(
 *   id = "nodesearch",
 *   label = @Translation("NodeSearch"),
 *   description = @Translation("Node search dialog."),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = true
 * )
 */
final class NodeSearchWidget extends WidgetBase
{
    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        $element = [];

        $definition = $items->getFieldDefinition();
        $settings = $this->getFieldSetting('handler_settings');
        $cardinality = $definition->getFieldStorageDefinition()->getCardinality();
        $max = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED == $cardinality ? null : $cardinality;
        $multiple = !$max || 1 < $max;

        $element['nodes'] = [
            '#type' => 'nodesearch',
            '#bundles' => $settings['target_bundles'] ?? [],
            '#entity_type' => $definition->getSetting('target_type'),
            '#title' => $definition->getLabel(),
            // '#placeholder' => null,
            '#max' => $max,
            '#min' => $definition->isRequired() ? 1 : 0,
            '#multiple' => $multiple,
            '#default_value' => \array_column($items->getValue(), 'target_id'),
        ];

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function massageFormValues(array $values, array $form, FormStateInterface $form_state)
    {
        $values = (array)$values['nodes'];
        // Field widget base does stupid things...
        unset($values['values']);

        // Converts: [target_id: [7, 11]] to: [[target_id: 7], [target_id: 11]]
        // in order to properly fit the field schema, with delta as keys.
        return \array_values(\array_map(function ($value) { return ['target_id' => (int)$value]; }, $values));
    }

    /**
     * {@inheritdoc}
     */
    public static function isApplicable(FieldDefinitionInterface $field_definition)
    {
        $entityType = $field_definition->getSetting('target_type');

        return parent::isApplicable($field_definition) && (
            'node' === $entityType ||
            'media' === $entityType
        );
    }
}
