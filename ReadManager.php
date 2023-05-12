<?php

namespace Drupal\piliskor_qr;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Manages discovery and instantiation of read plugins.
 *
 * @see plugin_api
 */
class ReadManager extends DefaultPluginManager {

  /**
   * Constructs a new ReadManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/PiliskorRead', $namespaces, $module_handler, 'Drupal\piliskor_qr\Plugin\PiliskorRead\ReadInterface', 'Drupal\piliskor_qr\Annotation\PiliskorRead');

    $this->alterInfo('piliskor_read_info');
    $this->setCacheBackend($cache_backend, 'piliskor_read_plugins');
  }

  /**
   * Collect plugin defined routes.
   */
  public function routes() {
    $routes = [];
    $definitions = $this->getDiscovery()->getDefinitions();
    foreach ($definitions as $plugin_id => $definition) {
      $plugin = $this->createInstance($plugin_id);
      $routes['piliskor_qr.' . $plugin_id] = $plugin->route();
    }
    return $routes;
  }

}
