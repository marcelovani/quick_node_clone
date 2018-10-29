<?php
namespace Drupal\quick_node_clone\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QuickNodeParagraphCloneSettingForm extends ConfigFormBase {

  /**
   * The Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Entity Bundle Type Info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info')
    );
  }

   /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['quick_node_clone.settings'];
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quick_node_clone_paragraph_setting_form';
  }

  /**
   * QuickNodeParagraphCloneSettingForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, ConfigFactoryInterface $configFactory, EntityTypeBundleInfoInterface $entityTypeBundleInfo) {
    $this->entityFieldManager = $entityFieldManager;
    $this->configFactory = $configFactory;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $paragraph_bundles = $this->entityTypeBundleInfo->getBundleInfo('paragraph');
    if (!empty($paragraph_bundles)) {
      $para_bundle_list = [];
      foreach ($paragraph_bundles as $paragraph => $label) {
        $para_bundle_list[$paragraph] = $label['label'];
      }
      $form['exclude'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Exclusion list'),
      ];
      $form['exclude']['description'] = [
        '#markup' => $this->t('You can select fields that you do not want to be included when the paragraph is cloned.'),
      ];
      $form['exclude']['paragraphs'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Paragraph Types'),
        '#options' => $para_bundle_list,
        '#default_value' => ($this->getSettings('paragraphs')) ? $this->getSettings('paragraphs') : [],
        '#description' => $this->t('Select paragraph types above and you will see a list of fields that can be excluded.'),
        '#ajax' => [
          'callback' => 'Drupal\quick_node_clone\Form\QuickNodeParagraphCloneSettingForm::paragraphFieldsCallback',
          'wrapper' => 'pfields-list',
          'method' => 'replace',
        ],
      ];

      $form['exclude']['pfields'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->t('Fields'),
        '#description' => $this->getParagraphDescription($form_state),
        '#prefix' => '<div id="pfields-list" >',
        '#suffix' => '</div>',
      ];

      if ($paragraph_fields = $this->getparagraphFields($form_state)) {
        foreach ($paragraph_fields as $k => $value) {
          if (!empty($value)) {
            $foptions = [];
            $fields = $this->entityFieldManager->getFieldDefinitions('paragraph', $k);
            foreach ($fields as $key => $f) {
              if ($f instanceof FieldConfig) {
                $foptions[$f->getName()] = $f->getLabel();
              }
              $description = "";
              if (empty($foptions)) {
                $description = "No Fields Available";
              }
              $form['exclude']['pfields']['paragraph_' . $k] = [
                '#type' => 'details',
                '#title' => $value,
                '#open' => TRUE,
              ];
              $form['exclude']['pfields']['paragraph_' . $k][$k] = [
                '#type' => 'checkboxes',
                '#title' => $this->t('Fields'),
                '#default_value' => ($this->getSettings($k)) ? $this->getSettings($k) : [],
                '#options' => $foptions,
                '#description' => $description,
              ];
            }
          }
        }
      }
    }
    else {
      $form['exclude']['no_paragraph'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No paragraph available'),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $formvalues = $form_state->getValues();
    foreach ($formvalues['paragraphs'] as $key => $values) {
      if (empty($value)) {
        $this->config('quick_node_clone.settings')->clear($key)->save();
      }
    }
    foreach ($formvalues as $key => $values) {
      $this->config('quick_node_clone.settings')->set($key, $values)->save();
    }
  }

  public static function paragraphFieldsCallback(array $form, FormStateInterface $form_state) {
    return $form['exclude']['pfields'];
  }

  /**
   * Get the correct description for the fields form.
   *
   * @param $form_state
   *
   * @return string
   */
  public function getParagraphDescription($form_state) {
    $desc = $this->t('No paragraph selected');
    if ($form_state->getValue('paragraphs') != NULL && array_filter($form_state->getValue('paragraphs'))) {
      $desc = '';
    }
    if (!empty($this->getSettings('paragraphs')) && array_filter($this->getSettings('paragraphs'))) {
      $desc = '';
    }
    return $desc;
  }

  /**
   * Get the paragraph bundles.
   *
   * @param $form_state
   *
   * @return array|mixed|null
   */
  public function getparagraphFields($form_state) {
    $para_bundles = NULL;
    if ($form_state->getValue('paragraphs') != NULL && array_filter($form_state->getValue('paragraphs'))) {
      $para_bundles = $form_state->getValue('paragraphs');
    }
    else {
      if (!empty($this->getSettings('paragraphs')) && array_filter($this->getSettings('paragraphs'))) {
        $para_bundles = $this->getSettings('paragraphs');
      }
    }
    return $para_bundles;
  }

  /**
   * Get the settings.
   *
   * @param $value
   *
   * @return array|mixed|null
   */
  public function getSettings($value) {
    $settings = $this->configFactory->get('quick_node_clone.settings')->get($value);
    return $settings;
  }
}
