<?php

/**
 * @file
 * Contains \Drupal\quick_node_clone\Entity\QuickNodeCloneEntityFormBuilder.
 */

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
   * Constructs a new EntityFormBuilder.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityManagerInterface $entity_manager, FormBuilderInterface $form_builder) {
    $this->entityManager = $entity_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(EntityInterface $original_entity, $operation = 'default', array $form_state_additions = array()) {

    // Clone the entity using the awesome createDuplicate() core function
    $new_entity = $original_entity->createDuplicate();

    $new_entity->setTitle(t('Clone of ') . $new_entity->getTitle());

    // Get the form object for the entity defined in entity definition
    $form_object = $this->entityManager->getFormObject($new_entity->getEntityTypeId(), $operation);

    // Assign the form's entity to our duplicate!
    $form_object->setEntity($new_entity);

    $form_state = (new FormState())->setFormState($form_state_additions);
    return $this->formBuilder->buildForm($form_object, $form_state);
  }

}
