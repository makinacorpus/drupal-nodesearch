<?php
/**
 * Some bits of field API integration (for usage with unoderef module).
 */

/**
 * Implements hook_field_widget_info().
 */
function nodesearch_field_widget_info() {
  return [
    'nodesearch_unoderef' => [
      'label'       => t('Node Reference drop area'),
      'field types' => ['unoderef'],
      // Oh I hate you seb, this should be at the instance level, not in the widget.
      'settings'    => ['bundles' => [], 'view_mode' => ''],
      'behaviors'   => [
        'multiple values' => FIELD_BEHAVIOR_CUSTOM,
        'default value'   => FIELD_BEHAVIOR_DEFAULT,
      ],
    ],
  ];
}

/**
 * Implements hook_field_widget_settings_form().
 */
function nodesearch_field_widget_settings_form($field, $instance) {
  if (function_exists('unoderef_field_widget_settings_form')) {
    return unoderef_field_widget_settings_form($field, $instance);
  }
  return [];
}

/**
 * Implements hook_field_widget_form().
 */
function nodesearch_field_widget_form(&$form, &$form_state, $field, $instance, $langcode, $items, $delta, $element) {
  $multiple = $field['cardinality'] > 1 || $field['cardinality'] == FIELD_CARDINALITY_UNLIMITED;

  // Compute min and max from field configuration
  $min = (int)$instance['required'] ?? false;
  $max = (int)$field['cardinality'] ?? 1;

  return $element += [
    '#title'          => $instance['label'],
    '#type'           => 'nodesearch',
    '#multiple'       => $multiple,
    '#default_value'  => $items,
    '#bundles'        => $instance['widget']['settings']['bundles'],
    '#max'            => $max,
    '#min'            => $min,
    '#field_api'      => true,
  ];
}
