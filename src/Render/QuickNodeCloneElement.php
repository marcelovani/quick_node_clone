<?php

/**
 * @file
 * Contains \Drupal\quick_node_clone\Render\QuickNodeCloneElement.
 * 
 * The reason we're extending this method for quick_node_clone is to allow 
 * non-standard elements in a render array to not throw "User error" when Devel is 
 * disabled.
 */

namespace Drupal\quick_node_clone\Render;

use Drupal\Core\Render\Element;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Access\AccessResultInterface;

/**
 * Provides helper methods for Drupal render elements.
 *
 * @see \Drupal\Core\Render\Element\ElementInterface
 *
 * @ingroup theme_render
 */
class QuickNodeCloneElement extends Element {


  /**
   * Identifies the children of an element array, optionally sorted by weight.
   *
   * The children of a element array are those key/value pairs whose key does
   * not start with a '#'. See drupal_render() for details.
   *
   * @param array $elements
   *   The element array whose children are to be identified. Passed by
   *   reference.
   * @param bool $sort
   *   Boolean to indicate whether the children should be sorted by weight.
   *
   * @return array
   *   The array keys of the element's children.
   */
  public static function children(array &$elements, $sort = FALSE) {
    // Do not attempt to sort elements which have already been sorted.
    $sort = isset($elements['#sorted']) ? !$elements['#sorted'] : $sort;

    // Filter out properties from the element, leaving only children.
    $count = count($elements);
    $child_weights = array();
    $i = 0;
    $sortable = FALSE;
    foreach ($elements as $key => $value) {
      if ($key === '' || $key[0] !== '#') {
        if (is_array($value)) {
          if (isset($value['#weight'])) {
            $weight = $value['#weight'];
            $sortable = TRUE;
          }
          else {
            $weight = 0;
          }
          // Supports weight with up to three digit precision and conserve
          // the insertion order.
          $child_weights[$key] = floor($weight * 1000) + $i / $count;
        }
        // Only trigger an error if the value is not null.
        // @see https://www.drupal.org/node/1283892
        elseif (isset($value)) {
          //@GFS
          //We're commenting out this error in order to have non-standard
          //elements in a render array for cloning purposes (IEF/ICPF
          //insert non-standard elements.)
          //trigger_error(SafeMarkup::format('"@key" is an invalid render array key', array('@key' => $key)), E_USER_ERROR);
        }
      }
      $i++;
    }

    // Sort the children if necessary.
    if ($sort && $sortable) {
      asort($child_weights);
      // Put the sorted children back into $elements in the correct order, to
      // preserve sorting if the same element is passed through
      // \Drupal\Core\Render\Element::children() twice.
      foreach ($child_weights as $key => $weight) {
        $value = $elements[$key];
        unset($elements[$key]);
        $elements[$key] = $value;
      }
      $elements['#sorted'] = TRUE;
    }

    return array_keys($child_weights);
  }

}
