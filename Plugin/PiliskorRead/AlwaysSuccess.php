<?php

namespace Drupal\piliskor_qr\Plugin\PiliskorRead;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a button that always creates a successful read.
 *
 * @PiliskorRead(
 *   id = "success",
 *   label = @Translation("Always success"),
  *  forms = {
 *     "read" = "Drupal\piliskor_qr\PluginForm\PiliskorRead\AlwaysSuccessForm",
 *   },
 * )
 */
class AlwaysSuccess extends ReadBase {

  /**
   * {@inheritdoc}
   */
  protected function comparePointAndRequest(NodeInterface $point, Request $request, RouteMatchInterface $route_match) {
    return ['success' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function failedRead(OrderItemInterface $run, AjaxResponse $response, $match) {
    $response->addCommand(new MessageCommand($this->t('Failed attempt.'), NULL, ['type' => 'warning']));
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, $account = NULL, $return_as_object = FALSE) {
    if (!$account) {
      $account = $this->currentUser;
    }
    $run = $this->routeMatch->getParameter('run');
    return AccessResult::allowedIf($this->currentUser->hasPermission('piliskor_run use success button')
      && $this->runManager->isAccountInRun($run, $this->currentUser)
      && $this->runManager->isAccountInRun($run, $account));
  }

}
