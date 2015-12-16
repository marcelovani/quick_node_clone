<?php

/**
 * @file
 * Contains \Drupal\quick_node_clone\Form\QuickNodeCloneFormErrorHandler.
 */

namespace Drupal\quick_node_clone\Form;

use Drupal\quick_node_clone\Render\QuickNodeCloneElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormErrorHandlerInterface;
use Drupal\Core\Form\FormErrorHandler;

/**
 * Handles form errors.
 */
class QuickNodeCloneFormErrorHandler extends FormErrorHandler implements FormErrorHandlerInterface {

  /**
   * Stores the errors of each element directly on the element.
   *
   * We must provide a way for non-form functions to check the errors for a
   * specific element. The most common usage of this is a #pre_render callback.
   *
   * @param array $elements
   *   An associative array containing the structure of a form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function setElementErrorsFromFormState(array &$elements, FormStateInterface &$form_state) {
    // Recurse through all children.
    foreach (QuickNodeCloneElement::children($elements) as $key) {
      if (isset($elements[$key]) && $elements[$key]) {
        $this->setElementErrorsFromFormState($elements[$key], $form_state);
      }
    }

    // Store the errors for this element on the element directly.
    $elements['#errors'] = $form_state->getError($elements);
    $form_state->setRedirect('<front>');
  }

}
