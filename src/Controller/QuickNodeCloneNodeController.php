<?php

/**
 * @file
 * Contains \Drupal\quick_node_clone\Controller\QuickNodeCloneNodeController.
 */

namespace Drupal\quick_node_clone\Controller;

use Drupal\quick_node_clone\Form\QuickNodeCloneFormBuilder;
use Drupal\quick_node_clone\Entity\QuickNodeCloneEntityFormBuilder;
use Drupal\quick_node_clone\Render\QuickNodeCloneRenderer;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\node\NodeTypeInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Controller\NodeController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Quick Node Clone Node routes.
 */
class QuickNodeCloneNodeController extends NodeController implements ContainerInjectionInterface {

  /**
   * The entity form builder.
   *
   * @var \Drupal\quick_node_clone\Form\QuickNodeCloneEntityFormBuilder
   */
  protected $qncEntityFormBuilder;

  /**
   * Constructs a NodeController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(DateFormatterInterface $date_formatter, QuickNodeCloneRenderer $renderer, QuickNodeCloneEntityFormBuilder $entity_form_builder) {
    parent::__construct($date_formatter, $renderer);
    $this->qncEntityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('quick_node_clone.renderer'),
      $container->get('quick_node_clone.entity.form_builder')
    );
  }

  /**
   * Retrieves the entity form builder.
   *
   * @return \Drupal\quick_node_clone\Form\QuickNodeCloneFormBuilder
   *   The entity form builder.
   */
  protected function entityFormBuilder() {
    return $this->qncEntityFormBuilder;
  }

  /**
   * Provides the node submission form.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type entity for the node.
   *
   * @return array
   *   A node submission form.
   */
  public function cloneNode($node) {
    $parent_node = $this->entityManager()->getStorage('node')->load($node);
    $starting_fields = $this->getParentNodeFields($parent_node);

    //Get the form of a parent node
    $parent_form = $this->entityFormBuilder()->getForm($parent_node);  

    //Set our starting IEF form states to the parent.
    //This will make IEF build the correct references in the child.
    if(isset($parent_form['inline_entity_form'])) {
      $starting_fields['inline_entity_form'] = $this->getParentIefs($parent_form['inline_entity_form']);
    }
    //Create the new child node, but don't save yet!
    $node = $this->entityManager()->getStorage('node')->create(['type' => $parent_node->getType()]);
    //Get the actual child form
    $form = $this->entityFormBuilder()->getForm($node, 'quick_node_clone', $starting_fields);

    return $form;
  }

  /**
   * The _title_callback for the node.add route.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The current node.
   *
   * @return string
   *   The page title.
   */
  public function clonePageTitle($node) {
    $parent  = Node::load($node);

    return $this->t('Clone of "@node_id"', array(
      '@node_id' => $parent->getTitle()
      )
    );
  }

  /**
   * Get the parent fields in order to inject into form_state for 
   * pre-population.
   *
   * @param \Drupal\node\Node $parent_node
   *   The parent node.
   *
   * @return array
   *   The associative array of starting fields.
   */
  public function getParentNodeFields($parent_node) {
    $parent = $parent_node->toArray();
    $starting_fields = array();
    foreach($parent as $name => $value) {
      if(strpos($name, 'field_', 0) === 0 || 
        $name == 'title' ||
        $name == 'body' ||
        $name == 'form' ||
        $name == 'instance' ||
        $name == 'bundle') {
        $starting_fields[$name] = $value;
      }
    }
    return $starting_fields;
  }

  /**
   * Only add an IEF to the child if it existed in the parent. 
   * If we don't do this check, validation is on for blank child IEF fields.
   *
   * @param $iefs
   *   An array from the parent form.
   *
   * @return array
   *   The manipulated array without blank iefs.
   */
  public function getParentIefs($iefs) {
    $hasReferences = FALSE;
    foreach($iefs as $key => $value) {
      if(isset($value['#id'])) {
        $hasReferences = FALSE;
        foreach($value['entities'] as $eVal) {
          if(isset($eVal['#id'])) {
            $hasReferences = TRUE;
          }
        }
        if(!$hasReferences) {
          unset($iefs[$key]);
        }
      }
    }
    return $iefs;
  }

}
