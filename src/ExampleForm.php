<?php

namespace MakinaCorpus\Drupal\NodeSearch;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

class ExampleForm extends FormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'nodesearch_example_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
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
            '#title' => t("Choose a node"),
            '#type' => 'nodesearch',
            '#default_value' => $idList,
            '#required' => true,
            '#multiple' => true,
            '#min' => 2,
            '#max' => 6,
            //'#bundles' => 'page',
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => new TranslatableMarkup("Select this node and be happy"),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        if ($idList = $form_state->getValue('my_nodes', [])) {
            $text = [];
            foreach (\node_load_multiple($idList) as $node) {
                $text[] = $node->id().' - '.$node->getTitle();
            }
            \drupal_set_message(new TranslatableMarkup("Selected values are: %items", ['%items' => implode(', ', $text)]));
        } else {
            \drupal_set_message(new TranslatableMarkup("You have no selected content."));
        }
    }
}
