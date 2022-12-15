<?php

namespace Drupal\drupal_document\Plugin\views\argument_default;

use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The entity browser widget context argument plugin to extract a node.
 *
 * @ViewsArgumentDefault(
 *   id = "entity_browser_widget_context_nid",
 *   title = @Translation("Entity Browser Context Node ID")
 * )
 */
class EntityBrowserWidgetContextNode extends ArgumentDefaultPluginBase {

  /**
   * The node storage controller.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a new Node instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *   The route match.
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   The node storage controller.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, NodeStorageInterface $node_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access() {
    return $this->view->getDisplay()->pluginId === 'entity_browser';
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $currentRequest = $this->view->getRequest();
    $path = ($currentRequest->query->has('original_path')) ? $currentRequest->query->get('original_path') : NULL;
    if (preg_match('/node\/(\d+)/', $path, $matches)) {
      $node = $this->nodeStorage->load($matches[1]);
      if ($node instanceof NodeInterface) {
        return $node->id();
      }
    }
  }

}
