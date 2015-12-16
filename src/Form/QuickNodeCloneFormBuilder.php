<?php

/**
 * @file
 * Contains \Drupal\quick_node_clone\Form\QuickNodeCloneFormBuilder.
 */

namespace Drupal\quick_node_clone\Form;

use Drupal\quick_node_clone\Render\QuickNodeCloneElement;
use Drupal\quick_node_clone\Form\QuickNodeCloneFormValidator;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormSubmitterInterface;
use Drupal\Core\Form\FormCacheInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\Exception\BrokenPostRequestException;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides form building and processing.
 *
 * @ingroup form_api
 */
class QuickNodeCloneFormBuilder extends FormBuilder {

  /**
   * Constructs a new FormBuilder.
   *
   * @param \Drupal\content_management_relation\Form\FormValidatorInterface $form_validator
   *   The form validator.
   * @param \Drupal\Core\Form\FormSubmitterInterface $form_submitter
   *   The form submission processor.
   * @param \Drupal\Core\Form\FormCacheInterface $form_cache
   *   The form cache.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   *   The element info manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   */
  public function __construct(QuickNodeCloneFormValidator $form_validator, FormSubmitterInterface $form_submitter, FormCacheInterface $form_cache, ModuleHandlerInterface $module_handler, EventDispatcherInterface $event_dispatcher, RequestStack $request_stack, ClassResolverInterface $class_resolver, ElementInfoManagerInterface $element_info, ThemeManagerInterface $theme_manager, CsrfTokenGenerator $csrf_token = NULL) {
    parent::__construct($form_validator, $form_submitter, $form_cache, $module_handler, $event_dispatcher, $request_stack, $class_resolver, $element_info, $theme_manager, $csrf_token);
    $this->formValidator = $form_validator;
    $this->formSubmitter = $form_submitter;
    $this->formCache = $form_cache;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
    $this->requestStack = $request_stack;
    $this->classResolver = $class_resolver;
    $this->elementInfo = $element_info;
    $this->csrfToken = $csrf_token;
    $this->themeManager = $theme_manager;
  }


  /**
   * {@inheritdoc}
   */
  public function doBuildForm($form_id, &$element, FormStateInterface &$form_state) {
    // Initialize as unprocessed.
    $element['#processed'] = FALSE;

    // Use element defaults.
    if (isset($element['#type']) && empty($element['#defaults_loaded']) && ($info = $this->elementInfo->getInfo($element['#type']))) {
      // Overlay $info onto $element, retaining preexisting keys in $element.
      $element += $info;
      $element['#defaults_loaded'] = TRUE;
    }
    // Assign basic defaults common for all form elements.
    $element += array(
      '#required' => FALSE,
      '#attributes' => array(),
      '#title_display' => 'before',
      '#description_display' => 'after',
      '#errors' => NULL,
    );

    // Special handling if we're on the top level form element.
    if (isset($element['#type']) && $element['#type'] == 'form') {
      if (!empty($element['#https']) && !UrlHelper::isExternal($element['#action'])) {
        global $base_root;

        // Not an external URL so ensure that it is secure.
        $element['#action'] = str_replace('http://', 'https://', $base_root) . $element['#action'];
      }

      // Store a reference to the complete form in $form_state prior to building
      // the form. This allows advanced #process and #after_build callbacks to
      // perform changes elsewhere in the form.
      $form_state->setCompleteForm($element);

      // Set a flag if we have a correct form submission. This is always TRUE
      // for programmed forms coming from self::submitForm(), or if the form_id
      // coming from the POST data is set and matches the current form_id.
      $input = $form_state->getUserInput();
      if ($form_state->isProgrammed() || (!empty($input) && (isset($input['form_id']) && ($input['form_id'] == $form_id)))) {
        $form_state->setProcessInput();
        if (isset($element['#token'])) {
          $input = $form_state->getUserInput();
          if (empty($input['form_token']) || !$this->csrfToken->validate($input['form_token'], $element['#token'])) {
            // Set an early form error to block certain input processing since
            // that opens the door for CSRF vulnerabilities.
            $this->setInvalidTokenError($form_state);

            // This value is checked in self::handleInputElement().
            $form_state->setInvalidToken(TRUE);

            // Make sure file uploads do not get processed.
            $this->requestStack->getCurrentRequest()->files = new FileBag();
          }
        }
      }
      else {
        $form_state->setProcessInput(FALSE);
      }

      // All form elements should have an #array_parents property.
      $element['#array_parents'] = array();
    }

    if (!isset($element['#id'])) {
      $unprocessed_id = 'edit-' . implode('-', $element['#parents']);
      $element['#id'] = Html::getUniqueId($unprocessed_id);
      // Provide a selector usable by JavaScript. As the ID is unique, its not
      // possible to rely on it in JavaScript.
      $element['#attributes']['data-drupal-selector'] = Html::getId($unprocessed_id);
    }
    else {
      // Provide a selector usable by JavaScript. As the ID is unique, its not
      // possible to rely on it in JavaScript.
      $element['#attributes']['data-drupal-selector'] = Html::getId($element['#id']);
    }

    // Add the aria-describedby attribute to associate the form control with its
    // description.
    if (!empty($element['#description'])) {
      $element['#attributes']['aria-describedby'] = $element['#id'] . '--description';
    }
    // Handle input elements.
    if (!empty($element['#input'])) {
      $this->handleInputElement($form_id, $element, $form_state);
    }
    // Allow for elements to expand to multiple elements, e.g., radios,
    // checkboxes and files.
    if (isset($element['#process']) && !$element['#processed']) {
      foreach ($element['#process'] as $callback) {
        $complete_form = &$form_state->getCompleteForm();
        $element = call_user_func_array($form_state->prepareCallback($callback), array(&$element, &$form_state, &$complete_form));
      }
      $element['#processed'] = TRUE;
    }

    // We start off assuming all form elements are in the correct order.
    $element['#sorted'] = TRUE;

    // Recurse through all child elements.
    $count = 0;
    if (isset($element['#access'])) {
      $access = $element['#access'];
      $inherited_access = NULL;
      if (($access instanceof AccessResultInterface && !$access->isAllowed()) || $access === FALSE) {
        $inherited_access = $access;
      }
    }
    foreach (QuickNodeCloneElement::children($element) as $key) {
      // Prior to checking properties of child elements, their default
      // properties need to be loaded.
      if (isset($element[$key]['#type']) && empty($element[$key]['#defaults_loaded']) && ($info = $this->elementInfo->getInfo($element[$key]['#type']))) {
        $element[$key] += $info;
        $element[$key]['#defaults_loaded'] = TRUE;
      }

      // Don't squash an existing tree value.
      if (!isset($element[$key]['#tree'])) {
        $element[$key]['#tree'] = $element['#tree'];
      }

      // Children inherit #access from parent.
      if (isset($inherited_access)) {
        $element[$key]['#access'] = $inherited_access;
      }

      // Make child elements inherit their parent's #disabled and #allow_focus
      // values unless they specify their own.
      foreach (array('#disabled', '#allow_focus') as $property) {
        if (isset($element[$property]) && !isset($element[$key][$property])) {
          $element[$key][$property] = $element[$property];
        }
      }

      // Don't squash existing parents value.
      if (!isset($element[$key]['#parents'])) {
        // Check to see if a tree of child elements is present. If so,
        // continue down the tree if required.
        $element[$key]['#parents'] = $element[$key]['#tree'] && $element['#tree'] ? array_merge($element['#parents'], array($key)) : array($key);
      }
      // Ensure #array_parents follows the actual form structure.
      $array_parents = $element['#array_parents'];
      $array_parents[] = $key;
      $element[$key]['#array_parents'] = $array_parents;

      // Assign a decimal placeholder weight to preserve original array order.
      if (!isset($element[$key]['#weight'])) {
        $element[$key]['#weight'] = $count/1000;
      }
      else {
        // If one of the child elements has a weight then we will need to sort
        // later.
        unset($element['#sorted']);
      }
      $element[$key] = $this->doBuildForm($form_id, $element[$key], $form_state);
      $count++;
    }

    // The #after_build flag allows any piece of a form to be altered
    // after normal input parsing has been completed.
    if (isset($element['#after_build']) && !isset($element['#after_build_done'])) {
      foreach ($element['#after_build'] as $callback) {
        $element = call_user_func_array($form_state->prepareCallback($callback), array($element, &$form_state));
      }
      $element['#after_build_done'] = TRUE;
    }

    // If there is a file element, we need to flip a flag so later the
    // form encoding can be set.
    if (isset($element['#type']) && $element['#type'] == 'file') {
      $form_state->setHasFileElement();
    }

    // Final tasks for the form element after self::doBuildForm() has run for
    // all other elements.
    if (isset($element['#type']) && $element['#type'] == 'form') {
      // If there is a file element, we set the form encoding.
      if ($form_state->hasFileElement()) {
        $element['#attributes']['enctype'] = 'multipart/form-data';
      }

      // Allow Ajax submissions to the form action to bypass verification. This
      // is especially useful for multipart forms, which cannot be verified via
      // a response header.
      $element['#attached']['drupalSettings']['ajaxTrustedUrl'][$element['#action']] = TRUE;

      // If a form contains a single textfield, and the ENTER key is pressed
      // within it, Internet Explorer submits the form with no POST data
      // identifying any submit button. Other browsers submit POST data as
      // though the user clicked the first button. Therefore, to be as
      // consistent as we can be across browsers, if no 'triggering_element' has
      // been identified yet, default it to the first button.
      $buttons = $form_state->getButtons();
      if (!$form_state->isProgrammed() && !$form_state->getTriggeringElement() && !empty($buttons)) {
        $form_state->setTriggeringElement($buttons[0]);
      }

      $triggering_element = $form_state->getTriggeringElement();
      // If the triggering element specifies "button-level" validation and
      // submit handlers to run instead of the default form-level ones, then add
      // those to the form state.
      if (isset($triggering_element['#validate'])) {
        $form_state->setValidateHandlers($triggering_element['#validate']);
      }
      if (isset($triggering_element['#submit'])) {
        $form_state->setSubmitHandlers($triggering_element['#submit']);
      }

      // If the triggering element executes submit handlers, then set the form
      // state key that's needed for those handlers to run.
      if (!empty($triggering_element['#executes_submit_callback'])) {
        $form_state->setSubmitted();
      }

      // Special processing if the triggering element is a button.
      if (!empty($triggering_element['#is_button'])) {
        // Because there are several ways in which the triggering element could
        // have been determined (including from input variables set by
        // JavaScript or fallback behavior implemented for IE), and because
        // buttons often have their #name property not derived from their
        // #parents property, we can't assume that input processing that's
        // happened up until here has resulted in
        // $form_state->getValue(BUTTON_NAME) being set. But it's common for
        // forms to have several buttons named 'op' and switch on
        // $form_state->getValue('op') during submit handler execution.
        $form_state->setValue($triggering_element['#name'], $triggering_element['#value']);
      }
    }
    return $element;
  }



}
