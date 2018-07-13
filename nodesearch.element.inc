<?php

const NODESEARCH_ELEMENT_DEFAULT_MAX = 1000;

/**
 * Implements hook_element_info().
 */
function nodesearch_element_info() {
  return [
    // Input (#value or #default_value) must be a single node identifier if not
    // multiple or an array of node identifiers if multiple. Restitution will be
    // the same (an array if multiple, a single value if not).
    'nodesearch' => [
      '#process'            => ['nodesearch_element_process'],
      // Will be the selection dialog title.
      '#title'              => null,
      // Will be the search input placeholder.
      '#placeholder'        => null,
      // If element is multiple, set this to any number higher than 0. If you
      // set 1 here, element will fallback on NOT being multiple anyway.
      '#max'                => NODESEARCH_ELEMENT_DEFAULT_MAX,
      // Will fallback to 1 if element is required.
      '#min'                => 0,
      // Element will work with multiple input.
      '#multiple'           => false,
      '#input'              => true,
      '#value_callback'     => 'nodesearch_element_value',
      //'#element_validate'   => ['nodesearch_element_validate'],
      '#attached'           => ['library' => [['nodesearch', 'nodesearch']]],
    ],
  ];
}

/**
 * Element process callback.
 */
function nodesearch_element_process($element) {

  $values   = [];
  $required = (bool)$element['#required'];
  $multiple = (bool)$element['#multiple'];
  $min      = (int)$element['#min'];
  $max      = (int)$element['#max'];
  $output   = [];

  // Default minimum value normalisation.
  if ($min < 0) {
    if ($required) {
      trigger_error(sprintf("nodesearch widget min value %d is lower than 0, fallback to 1 (value is required)", $min), E_USER_WARNING);
      $min = 1;
    } else {
      trigger_error(sprintf("nodesearch widget min value %d is lower than 0, fallback to 0 (value is optional)", $min), E_USER_WARNING);
      $min = 0;
    }
  } else if (0 < $min && !$required) {
    trigger_error(sprintf("nodesearch widget min value %d is higher than 0, but value is optional, falling back to being required", $min), E_USER_WARNING);
    $required = true;
  }

  // Default max value normalisation.
  if ($max < $min) {
    trigger_error(sprintf("nodesearch widget min value %d is higher than max value %d, falling back to %d", $min, $max, NODESEARCH_ELEMENT_DEFAULT_MAX), E_USER_WARNING);
    // Ensure that we are always higher or equal than min.
    $max = max([$min, NODESEARCH_ELEMENT_DEFAULT_MAX]);
  }
  if ($max === 1 && $multiple) {
    trigger_error(sprintf("nodesearch widget max value %d is higher than 1, but value is single, falling back to being multiple", $min), E_USER_WARNING);
    $multiple = false;
  }

  // Required for value processing
  $element['#tree'] = true;
  // Reset item internals
  $element['#multiple'] = $multiple;
  $element['#required'] = $required;
  $element['#min'] = $min;
  $element['#max'] = $max;

  // Normalize input depending on the widget being multiple or not.
  if (!empty($element['#value'])) {
    if ($element['#multiple']) {
      if (!is_array($element['#value'])) {
        trigger_error("nodesearch widget value must be an integer array if element is multiple", E_USER_WARNING);
        $values = [$element['#value']];
      } else {
        $values = $element['#value'];
      }
    } else {
      if (is_array($element['#value'])) {
        trigger_error("nodesearch widget value must be a single integer if element is not multiple", E_USER_WARNING);
        // We have a problem here, the element do not allow more than one value
        // take it all anyway, we will reduce later after having load the nodes.
        $values = $element['#value'];
      } else {
        $values = [$element['#value']];
      }
    }
  }

  if ($values) {
    // Validate input by loading all the nodes. If there are duplicates,
    // ordering will probably be broken using this array_unique() call.
    $values = array_unique($values);
    $nodes  = node_load_multiple($values);
    if (count($nodes) !== count($values)) {
      trigger_error("nodesearch widget contains one or more node that don't exist", E_USER_NOTICE);
    }
    foreach ($nodes as $node) {
      $output[] = nodesearch_result($node);
    }
    // In all cases, normalize values input (ordering is kept here).
    $values = array_keys($nodes);
  }

  // Ensure that number of nodes is in adequation with min and max values.
  if ($max < count($values)) {
    trigger_error(sprintf("nodesearch widget contains %d nodes whereas max count is %d, validation will fail", count($values), $max), E_USER_NOTICE);
  }

  // Hidden element containing the JavaScript result, if any.
  $element['values'] = [
    '#type'          => 'hidden',
    '#default_value' => implode(',', $values),
    '#attributes'    => [
      'data-nodesearch'   => "true",
      'data-title'        => $element['#title'] ?? '',
      'data-placeholder'  => $element['#placeholder'] ?? '',
      'data-default'      => json_encode($output),
      'data-min'          => $min,
      'data-max'          => $max,
    ],
  ];

  return $element;
}

/**
 * Element value callback.
 */
function nodesearch_element_value(&$element, $input = false) {
  if ($input) {
    return array_filter(array_unique(explode(',', $input['values'])));
  }
  return $element['#default_value'];
}

/**
 * Implements hook_field_validate().
 */
function nodesearch_field_validate($entity_type, $entity, $field, $instance, $langcode, $items, &$errors) {
  if (!count($items)) {
    return;
  }

  // Collect nodes.
  $nids = [];
  foreach ($items as $item) {
    if (!empty($item['nid'])) {
      $nids[] = $item['nid'];
    }
  }
  $nodes = node_load_multiple($nids);

  // Verify node exist.
  if (count($nodes) != count($nids)) {
    $errors[$field['field_name']][$langcode][0][] = [
      'error'   => 'nodesearch_non_existent_nodes',
      'message' => t('There is content no longer available.'),
    ];
  } else {
    // Verify bundles are correct.
    foreach ($items as $delta => $item) {
      if (empty($item['nid']) || !isset($nodes[$item['nid']])) {
        continue;
      }
      $node = $nodes[$item['nid']];

      if (!in_array($node->type, $instance['widget']['settings']['bundles'])) {
        $errors[$field['field_name']][$langcode][$delta][] = [
          'error'   => 'nodesearch_wrong_bundle',
          'message' => t("%name: this node's type is not allowed here.", [
            '%name' => entity_label('node', $node),
          ]),
        ];
      }
    }
  }
}

/**
 * Implements hook_field_is_empty().
 */
function nodesearch_field_is_empty($item) {
  return empty($item['nid']);
}
