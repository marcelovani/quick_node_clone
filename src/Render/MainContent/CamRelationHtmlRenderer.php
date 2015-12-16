<?php

/**
 * @file
 * Contains \Drupal\quick_node_clone\Render\MainContent\QuickNodeCloneHtmlRenderer.
 */

namespace Drupal\quick_node_clone\Render\MainContent;

use Drupal\quick_node_clone\Render\QuickNodeCloneRenderer;
use Drupal\Core\Render\MainContent\HtmlRenderer;
use Drupal\Core\Render\MainContent\MainContentRendererInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Display\ContextAwareVariantInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderCacheInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Default main content renderer for Quick Node Clone HTML requests.
 *
 * For attachment handling of HTML responses:
 * @see template_preprocess_html()
 * @see \Drupal\Core\Render\AttachmentsResponseProcessorInterface
 * @see \Drupal\Core\Render\BareHtmlPageRenderer
 * @see \Drupal\Core\Render\HtmlResponse
 * @see \Drupal\Core\Render\HtmlResponseAttachmentsProcessor
 */
class QuickNodeCloneHtmlRenderer extends HtmlRenderer implements MainContentRendererInterface {

  /**
   * Constructs a new QuickNodeCloneHtmlRenderer.
   *
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $display_variant_manager
   *   The display variant manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Render\RenderCacheInterface $render_cache
   *   The render cache service.
   * @param array $renderer_config
   *   The renderer configuration array.
   */
  public function __construct(TitleResolverInterface $title_resolver, PluginManagerInterface $display_variant_manager, EventDispatcherInterface $event_dispatcher, ModuleHandlerInterface $module_handler, QuickNodeCloneRenderer $renderer, RenderCacheInterface $render_cache, array $renderer_config) {
    $this->titleResolver = $title_resolver;
    $this->displayVariantManager = $display_variant_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->renderCache = $render_cache;
    $this->rendererConfig = $renderer_config;
  }

}
