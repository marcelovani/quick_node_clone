<?php

namespace Drupal\quick_node_clone\Entity;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;

/**
 * Builds entity forms.
 */
class QuickNodeCloneEntityFormBuilder extends EntityFormBuilder {

  /**
   * {@inheritdoc}
   */
  public function getForm(EntityInterface $original_entity, $operation = 'default', array $form_state_additions = array()) {

    // Clone the node using the awesome createDuplicate() core function.
    /** @var \Drupal\node\Entity\Node $new_node */
    $new_node = $original_entity->createDuplicate();

    // Check for paragraph fields which need to be duplicated as well.
    foreach ($new_node->getTranslationLanguages() as $langcode => $language) {
      $translated_node = $new_node->getTranslation($langcode);

      foreach ($translated_node->getFieldDefinitions() as $field_definition) {
        $field_storage_definition = $field_definition->getFieldStorageDefinition();
        $field_settings = $field_storage_definition->getSettings();
        $field_name = $field_storage_definition->getName();
        if (isset($field_settings['target_type']) && $field_settings['target_type'] == "paragraph") {

          // Each paragraph entity will be duplicated, so we won't be editing the same as the parent in every clone.
          if (!$translated_node->get($field_name)->isEmpty()) {
            foreach ($translated_node->get($field_name) as $value) {
              if ($value->entity) {
                $value->entity = $value->entity->createDuplicate();
              }
            }
          }
        }
        \Drupal::moduleHandler()->alter('cloned_node', $translated_node, $field_name, $field_settings);
      }
      $prepend_text = "";
      $qnc_config = \Drupal::config('quick_node_clone.settings');
      if(!empty($qnc_config->get('text_to_prepend_to_title'))) {
        $prepend_text = $qnc_config->get('text_to_prepend_to_title') . " ";
      }
      $translated_node->setTitle(t($prepend_text . '@title', ['@title' => $original_entity->getTitle()], ['langcode' => $langcode]));
    }

    // Get the form object for the entity defined in entity definition
    $form_object = $this->entityManager->getFormObject($new_node->getEntityTypeId(), $operation);

    // Assign the form's entity to our duplicate!
    $form_object->setEntity($new_node);

    $form_state = (new FormState())->setFormState($form_state_additions);
    return $this->formBuilder->buildForm($form_object, $form_state);
  }

}
