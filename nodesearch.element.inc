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
      // Allowed node types (if empty all content type are allowed)
      '#bundles'            => [],
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
      '#element_validate'   => ['nodesearch_element_validate'],
      '#attached'           => ['library' => [['nodesearch', 'nodesearch']]],
      // For internal use only, transparently handle ['nid' => int] values.
      '#field_api'          => false,
    ],
  ];
}

function _nodesearch_element_normalize_values($element) {
  $values = [];

  // Transparently accept ['nid' => int] arrays for field API to be happy.
  if ($element['#field_api']) {
    $values = array_map(function ($item) { return $item['nid']; }, $element['#value']);
  } else if (!empty($element['#value'])) {
    // Normalize input depending on the widget being multiple or not.
    if (is_array($element['#value'])) {
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
 * Element process callback.
 */
function nodesearch_element_process($element) {

  $values   = [];
  $required = (bool)$element['#required'];
  $multiple = (bool)$element['#multiple'];
  $min      = (int)$element['#min'];
  $max      = (int)$element['#max'];
  $output   = [];

  // Normalize bundles
  if (is_array($element['#bundles'])) {
    $bundles = $element['#bundles'];
  } else {
    $bundles = $element['#bundles'] = [$element['#bundles']];
  }

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
  $element['#theme_wrappers'] = ['form_element'];
  // Reset item internals
  $element['#multiple'] = $multiple;
  $element['#required'] = $required;
  $element['#min'] = $min;
  $element['#max'] = $max;

  if ($values = _nodesearch_element_normalize_values($element)) {
    // Validate input by loading all the nodes. If there are duplicates,
    // ordering will probably be broken using this array_unique() call.
    $values = array_unique($values);
    $nodes  = node_load_multiple($values);
    if (count($nodes) !== count($values)) {
      trigger_error("nodesearch widget contains one or more node that don't exist", E_USER_NOTICE);
    }
    /** @var \MakinaCorpus\Drupal\NodeSearch\NodeSearcher $formatter */
    $formatter = \Drupal::service('nodesearch_node_searcher');
    $output = $formatter->createResultAll('node', $nodes, true);
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
      'data-bundle'       => implode(',', $bundles),
    ],
  ];

  return $element;
}

/**
 * Element value callback.
 */
function nodesearch_element_value(&$element, $input = false) {
  $values = [];

  if ($input) {
    $values = array_filter(array_unique(explode(',', $input['values'])));
  } else if ($element['#default_value']) {
    if (is_array($element['#default_value'])) {
      $values = $element['#default_value'];
    } else {
      $values = [$element['#default_value']];
    }
  }

  // Handle field API transparently.
  if ($element['#field_api']) {
    // This also handles the fact that we may, for some unknown reason, pass
    // in here more than once, values could end up be ['nid' =>  ['nid => int]]
    // and we sure don't want that.
    return array_map(function ($value) { return is_array($value) ? $value : ['nid' => $value]; }, $values);
  }

  return $values;
}

/**
 * Element validate callback.
 */
function nodesearch_element_validate(&$element, $form_state) {
  $value = _nodesearch_element_normalize_values($element);

  // Ensure bounds.
  $count = count($value);
  if ($element['#min'] && $count < $element['#min']) {
    form_error($element, format_plural(
      $element['#min'],
      "%name must contain at least @count node.",
      "%name must contain at least @count nodes.",
      ['%name' => $element['#title']]
    ));
  }
  if ($element['#max'] && $count > $element['#max']) {
    form_error($element, format_plural(
      $element['#max'],
      "%name can contain a maximum of @count node.",
      "%name can contain a maximum of @count nodes.",
      ['%name' => $element['#title']]
    ));
  }

  // Ensure all nodes exist and have the right bundle.
  if ($value) {
    $nodes = node_load_multiple($value);
    if (count($nodes) !== $count) {
      form_error($element, t("%name contains one or more non existing nodes.", ['%name' => $element['#title']]));
    }

    if ($element['#bundles']) {
      if (is_array($element['#bundles'])) {
        $bundles = $element['#bundles'];
      } else {
        $bundles = [$element['#bundles']];
      }

      foreach ($nodes as $node) {
        if (!in_array($node->type, $bundles)) {
          form_error($element, t("%name's %title does not have an allowed node type.", [
            '%name' => $element['#title'],
            '%title' => $node->title,
          ]));
        }
      }
    }
  }
}
