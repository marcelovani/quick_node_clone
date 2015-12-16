<?php

/**
 * @file
 * Contains \Drupal\quick_node_clone\Form\QuickNodeCloneNodeForm.
 */

namespace Drupal\quick_node_clone\Form;

use Drupal\node\NodeForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\PrivateTempStoreFactory;

/**
 * Form controller for Quick Node Clone edit forms.
 *
 * We can override most of the node form from here! Hooray!
 */
class QuickNodeCloneNodeForm extends NodeForm {

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   */
  public function __construct(EntityManagerInterface $entity_manager, PrivateTempStoreFactory $temp_store_factory) {
    parent::__construct($entity_manager, $temp_store_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $clone_form_state = $form_state;
    $form = parent::form($form, $form_state);

    // Set default values for our form from the parent.
    $form = $this->populateCloneAddForm($form, $clone_form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);

    $node = $this->entity;
    $preview_mode = $node->type->entity->getPreviewMode();

    $element['submit']['#access'] = $preview_mode != DRUPAL_REQUIRED || $this->hasBeenPreviewed;

    // If saving is an option, privileged users get dedicated form submit
    // buttons to adjust the publishing status while saving in one go.
    // @todo This adjustment makes it close to impossible for contributed
    //   modules to integrate with "the Save operation" of this form. Modules
    //   need a way to plug themselves into 1) the ::submit() step, and
    //   2) the ::save() step, both decoupled from the pressed form button.
    if ($element['submit']['#access'] && \Drupal::currentUser()->hasPermission('access content overview')) {

      // Add a "Publish" button.
      $element['publish'] = $element['submit'];
      // If the "Publish" button is clicked, we want to update the status
      // to "published".
      $element['publish']['#published_status'] = TRUE;

      if ($node->isNew()) {
        $element['publish']['#value'] = t('Save New Clone');
      }

      $element['publish']['#weight'] = 0;

      // Remove the "Save" button.
      $element['submit']['#access'] = FALSE;
      $element['unpublish']['#access'] = FALSE;
    }

    $element['preview'] = array(
      '#type' => 'submit',
      '#access' => $preview_mode != DRUPAL_DISABLED && ($node->access('create') || $node->access('update')),
      '#value' => t('Preview'),
      '#weight' => 20,
      '#submit' => array('::submitForm', '::preview'),
    );

    $element['delete']['#access'] = $node->access('delete');
    $element['delete']['#weight'] = 100;
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Updates the node object by processing the submitted values.
   *
   * This function can be called by a "Next" button of a wizard to update the
   * form state's entity with the current step's values before proceeding to the
   * next step.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Build the node object from the submitted values.
    parent::submitForm($form, $form_state);
    $node = $this->entity;

    // Save as a new revision if requested to do so.
    if (!$form_state->isValueEmpty('revision') && $form_state->getValue('revision') != FALSE) {
      $node->setNewRevision();
      // If a new revision is created, save the current user as revision author.
      $node->setRevisionCreationTime(REQUEST_TIME);
      $node->setRevisionAuthorId(\Drupal::currentUser()->id());
    }
    else {
      $node->setNewRevision(FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $node = $this->entity;
    $insert = $node->isNew();
    $node->save();
    $node_link = $node->link($this->t('View'));
    $context = array(
      '@type'  => $node->getType(),
      '%title' => $node->label(),
      'link'   => $node_link,
    );
    $t_args = array('@type' => node_get_type_label($node), '%title' => $node->label());

    if ($insert) {
      $this->logger('content')->notice('@type: added %title.', $context);
      drupal_set_message(t('@type %title has been created.', $t_args));
    }
    else {
      $this->logger('content')->notice('@type: updated %title.', $context);
      drupal_set_message(t('@type %title has been updated.', $t_args));
    }

    if ($node->id()) {
      $form_state->setValue('nid', $node->id());
      $form_state->set('nid', $node->id());
      if ($node->access('view')) {
        $form_state->setRedirect(
          'entity.node.canonical',
          array('node' => $node->id())
        );
      }
      else {
        $form_state->setRedirect('<front>');
      }

      // Remove the preview entry from the temp store, if any.
      $store = $this->tempStoreFactory->get('node_preview');
      $store->delete($node->uuid());
    }
    else {
      // In the unlikely case something went wrong on save, the node will be
      // rebuilt and node form redisplayed the same way as in preview.
      drupal_set_message(t('The post could not be saved.'), 'error');
      $form_state->setRebuild();
    }
  }


  /**
   * Construct a form with default values from the parent content.
   *
   * Uses the data within $form_state that QuickNodeCloneNodeController
   * sends over.
   *
   * @param array $form
   *   The new node form.
   * @param FormStateInterface $form_state
   *   The new form state.
   *
   * @return array
   *   The manipulated form with default values.
   */
  public function populateCloneAddForm(array $form, FormStateInterface $form_state) {
    // Retreive values from form_state
    // The inline_entity_form module uses its injected states from the
    // controller to pre-populate.
    $values = array();
    foreach ($form as $key => $field) {
      $values[$key] = $form_state->get($key);
    }

    // Sets the $form element for IEF in order for the parent
    // to communicate with new clone in QuickNodeCloneNodeController.
    $form['inline_entity_form'] = $form_state->get('inline_entity_form');

    // Loop through all of the valid passed form_state content
    // Populate textfields, selects, and taxonomy.
    foreach ($values as $name => $value) {
      if (strpos($name, 'field_', 0) === 0 || $name == 'title' || $name == 'body') {
        $form = $this->populateDefaultTextfield($form, $name, $value);
        $form = $this->populateDefaultTextarea($form, $name, $value);
        $form = $this->populateDefaultSelect($form, $name, $value);
        $form = $this->populateDefaultTaxonomy($form, $name, $value);
      }
    }

    return $form;
  }


  /**
   * Populate textfield default values in a form field.
   *
   * @param array $form
   *   The form to populate.
   * @param string $name
   *   The field name to target.
   * @param array $value
   *   The value to set as default.
   *
   * @return array
   *   The manipulated form with default value added to one textfield.
   */
  public function populateDefaultTextfield(array $form, $name, array $value) {
    // Default textfield content.
    if ($name == 'title') {
      $content = 'Clone of ' . $value[0]['value'];
    }
    else {
      if (isset($value[0]['value'])) {
        $content = $value[0]['value'];
      }
      else {
        $content = '';
      }
    }
    if (isset($form[$name]['widget'][0]['value']) && is_array($form[$name]['widget'][0]['value'])) {
      $form[$name]['widget'][0]['value']['#default_value'] = $content;
    }
    return $form;
  }

  /**
   * Populate textarea default values in a form field.
   *
   * @param array $form
   *   The form to populate.
   * @param string $name
   *   The field name to target.
   * @param array $value
   *   The value to set as default.
   *
   * @return array
   *   The manipulated form with default value added to one textarea.
   */
  public function populateDefaultTextarea(array $form, $name, array $value) {
    // Default textarea content.
    if (isset($value[0]['value'])) {
      $content = $value[0]['value'];
    }
    else {
      $content = '';
    }
    if (isset($form[$name]['widget'][0])) {
      $form[$name]['widget'][0]['#default_value'] = $content;
    }

    return $form;
  }

  /**
   * Populate select list default values in a form field.
   *
   * @param array $form
   *   The form to populate.
   * @param string $name
   *   The field name to target.
   * @param array $value
   *   The value to set as default.
   *
   * @return array
   *   The manipulated form with default value added to one select list.
   */
  public function populateDefaultSelect(array $form, $name, array $value) {
    if (!isset($form[$name]['widget']['#default_value']) &&
      !isset($form[$name]['widget']['#ief_id'])) {

      // The widget value is just a single select value.
      if (isset($value[0]['value']) && !isset($value[1]['value'])) {
        $form[$name]['widget']['#default_value'] = $value[0]['value'];
      }
    }
    return $form;
  }

  /**
   * Populate taxonomy default values in a form field.
   *
   * @param array $form
   *   The form to populate.
   * @param string $name
   *   The field name to target.
   * @param array $value
   *   The value to set as default.
   *
   * @return array
   *   The manipulated form with default value added to one taxonomy
   *   term reference.
   */
  public function populateDefaultTaxonomy(array $form, $name, array $value) {
    if (!isset($form[$name]['widget']['#ief_id'])) {

      // The widget value might be multiple references (Ex: Taxonomy).
      if (isset($value[0]['target_id'])) {
        $referenced_ids = [];
        foreach ($value as $ids) {
          $referenced_ids[] = $ids['target_id'];
        }
        $form[$name]['widget']['#default_value'] = $referenced_ids;
      }
    }
    return $form;
  }

}
