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
      'label'       => t('Node Reference'),
      'field types' => ['unoderef'],
      // Oh I hate you seb, this should be at the instance level, not in the widget.
      'settings'    => ['bundles' => [], 'view_mode' => ''],
      'behaviors'   => [
        'multiple values' => FIELD_BEHAVIOR_CUSTOM,
        'default value'   => FIELD_BEHAVIOR_DEFAULT,
      ],
    ],
    'nodesearch_ulink' => [
      'label'       => t("Link"),
      'field types' => ['ulink'],
      'settings'    => [],
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
  $element = [];

  switch ($instance['widget']['type']) {

    case 'nodesearch_unoderef':
      if (function_exists('unoderef_field_widget_settings_form')) {
        $element += unoderef_field_widget_settings_form($field, $instance);
      }
      break;

    case 'nodesearch_ulink':
      break;
  }

  return $element;
}

/**
 * Implements hook_field_widget_form().
 */
function nodesearch_field_widget_form(&$form, &$form_state, $field, $instance, $langcode, $items, $delta, $element) {
  $element = [];
  $multiple = $field['cardinality'] > 1 || $field['cardinality'] == FIELD_CARDINALITY_UNLIMITED;

  // Compute min and max from field configuration
  $min = (int)$instance['required'] ?? false;
  $max = (int)$field['cardinality'] ?? 1;

  switch ($instance['widget']['type']) {

    case 'nodesearch_unoderef':
      $element += [
        '#title'          => $instance['label'],
        '#type'           => 'nodesearch',
        '#multiple'       => $multiple,
        '#default_value'  => $items,
        '#bundles'        => $instance['widget']['settings']['bundles'],
        '#max'            => $max,
        '#min'            => $min,
        '#field_api'      => true,
      ];
      break;

    case 'nodesearch_ulink':
      $element += [
        '#title'          => $instance['label'],
        '#type'           => 'nodesearch',
        '#multiple'       => $multiple,
        '#default_value'  => $items,
        '#bundles'        => $instance['widget']['settings']['bundles'],
        '#max'            => $max,
        '#min'            => $min,
        '#field_api'      => true,
      ];
      break;
  }

  return $element;
}
