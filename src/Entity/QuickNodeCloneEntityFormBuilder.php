<?php

/**
 * @file
 * Contains \Drupal\quick_node_clone\Entity\QuickNodeCloneEntityFormBuilder.
 */

namespace Drupal\quick_node_clone\Entity;

use Drupal\quick_node_clone\Form\QuickNodeCloneFormBuilder;
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
   * @param \Drupal\quick_node_clone\Form\QuickNodeCloneFormBuilder $form_builder
   *   The form builder.
   */
  public function __construct(EntityManagerInterface $entity_manager, QuickNodeCloneFormBuilder $form_builder) {
    $this->entityManager = $entity_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(EntityInterface $entity, $operation = 'default', array $form_state_additions = array()) {
    $form_object = $this->entityManager->getFormObject($entity->getEntityTypeId(), $operation);
    $form_object->setEntity($entity);

    $form_state = (new FormState())->setFormState($form_state_additions);
    return $this->formBuilder->buildForm($form_object, $form_state);
  }

}
