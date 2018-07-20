<?php
/**
 * Examples, only for you!
 */

/**
 * Testing handler.
 */
function nodesearch_page_test_form($form, &$form_state) {

  // Randomly select default nodes
  $idList = db_select('node', 'n')
    ->fields('n', ['nid'])
    ->addTag('node_access')
    ->orderRandom()
    ->range(0, rand(2, 5))
    ->execute()
    ->fetchCol()
  ;

  $form['my_nodes'] = [
    '#title'          => t("Choose a node"),
    '#type'           => 'nodesearch',
    '#default_value'  => $idList,
    '#required'       => true,
    '#multiple'       => true,
    '#min'            => 2,
    '#max'            => 6,
    //'#bundles'        => 'page',
  ];

  $form['submit'] = [
    '#type'   => 'submit',
    '#submit' => ['nodesearch_page_test_form_submit'],
    '#value'  => t("Select this node and be happy"),
  ];

  return $form;
}

/**
 * Testing form submit handler.
 */
function nodesearch_page_test_form_submit($form, &$form_state) {
  $idList = $form_state['values']['my_nodes'];
  if ($idList) {
    $text = [];
    foreach (node_load_multiple($idList) as $node) {
      $text[] = $node->nid . ' - ' . $node->title;
    }
    drupal_set_message(t("Selected values are:<ul><li>!items</li></ul>", ['!items' => implode('</li><li>', $text)]));
  } else {
    drupal_set_message(t("You have no selected content."));
  }
}
