<?php

/**
 * @file
 * Contains \Drupal\quick_node_clone\Form\FormValidator.
 */

namespace Drupal\quick_node_clone\Form;

use Drupal\quick_node_clone\Render\QuickNodeCloneElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormValidator;
use Drupal\Core\Form\FormValidatorInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\StringTranslation\TranslationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides validation of form submissions.
 */
class QuickNodeCloneFormValidator extends FormValidator implements FormValidatorInterface {

  /**
   * Constructs a new FormValidator.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\quick_node_clone\Form\QuickNodeCloneFormErrorHandler $form_error_handler
   *   The form error handler.
   */
  public function __construct(RequestStack $request_stack, TranslationInterface $string_translation, CsrfTokenGenerator $csrf_token, LoggerInterface $logger, QuickNodeCloneFormErrorHandler $form_error_handler) {
    $this->requestStack = $request_stack;
    $this->stringTranslation = $string_translation;
    $this->csrfToken = $csrf_token;
    $this->logger = $logger;
    $this->formErrorHandler = $form_error_handler;
  }

  /**
   * Performs validation on form elements.
   *
   * First ensures required fields are completed, #maxlength is not exceeded,
   * and selected options were in the list of options given to the user. Then
   * calls user-defined validators.
   *
   * @param $elements
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. The current user-submitted data is stored
   *   in $form_state->getValues(), though form validation functions are passed
   *   an explicit copy of the values for the sake of simplicity. Validation
   *   handlers can also $form_state to pass information on to submit handlers.
   *   For example:
   *     $form_state->set('data_for_submission', $data);
   *   This technique is useful when validation requires file parsing,
   *   web service requests, or other expensive requests that should
   *   not be repeated in the submission step.
   * @param string $form_id
   *   A unique string identifying the form for validation, submission,
   *   theming, and hook_form_alter functions.
   */
  protected function doValidateForm(&$elements, FormStateInterface &$form_state, $form_id = NULL) {
    // Recurse through all children.
    foreach (QuickNodeCloneElement::children($elements) as $key) {
      if (isset($elements[$key]) && $elements[$key]) {
        $this->doValidateForm($elements[$key], $form_state);
      }
    }

    // Validate the current input.
    if (!isset($elements['#validated']) || !$elements['#validated']) {
      // The following errors are always shown.
      if (isset($elements['#needs_validation'])) {
        $this->performRequiredValidation($elements, $form_state);
      }

      // Set up the limited validation for errors.
      $form_state->setLimitValidationErrors($this->determineLimitValidationErrors($form_state));

      // Make sure a value is passed when the field is required.
      if (isset($elements['#needs_validation']) && $elements['#required']) {
        // A simple call to empty() will not cut it here as some fields, like
        // checkboxes, can return a valid value of '0'. Instead, check the
        // length if it's a string, and the item count if it's an array.
        // An unchecked checkbox has a #value of integer 0, different than
        // string '0', which could be a valid value.
        $is_empty_multiple = (!count($elements['#value']));
        $is_empty_string = (is_string($elements['#value']) && Unicode::strlen(trim($elements['#value'])) == 0);
        $is_empty_value = ($elements['#value'] === 0);
        if ($is_empty_multiple || $is_empty_string || $is_empty_value) {
          // Flag this element as #required_but_empty to allow #element_validate
          // handlers to set a custom required error message, but without having
          // to re-implement the complex logic to figure out whether the field
          // value is empty.
          $elements['#required_but_empty'] = TRUE;
        }
      }

      // Call user-defined form level validators.
      if (isset($form_id)) {
        $this->executeValidateHandlers($elements, $form_state);
      }
      // Call any element-specific validators. These must act on the element
      // #value data.
      elseif (isset($elements['#element_validate'])) {
        foreach ($elements['#element_validate'] as $callback) {
          $complete_form = &$form_state->getCompleteForm();
          call_user_func_array($form_state->prepareCallback($callback),
            array(
              &$elements,
              &$form_state,
              &$complete_form,
            )
          );
        }
      }

      // Ensure that a #required form error is thrown, regardless of whether
      // #element_validate handlers changed any properties. If $is_empty_value
      // is defined, then above #required validation code ran, so the other
      // variables are also known to be defined and we can test them again.
      if (isset($is_empty_value) && ($is_empty_multiple || $is_empty_string || $is_empty_value)) {
        if (isset($elements['#required_error'])) {
          $form_state->setError($elements, $elements['#required_error']);
        }
        // A #title is not mandatory for form elements, but without it we cannot
        // set a form error message. So when a visible title is undesirable,
        // form constructors are encouraged to set #title anyway, and then set
        // #title_display to 'invisible'. This improves accessibility.
        elseif (isset($elements['#title'])) {
          $form_state->setError($elements, $this->t('@name field is required.', array('@name' => $elements['#title'])));
        }
        else {
          $form_state->setError($elements);
        }
      }

      $elements['#validated'] = TRUE;
    }

    // Done validating this element, so turn off error suppression.
    // self::doValidateForm() turns it on again when starting on the next
    // element, if it's still appropriate to do so.
    $form_state->setLimitValidationErrors(NULL);
  }

}
