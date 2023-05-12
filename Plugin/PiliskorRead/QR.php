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
 * Provides the QR reader plugin.
 *
 * @PiliskorRead(
 *   id = "qr",
 *   label = @Translation("QR code"),
 *   forms = {
 *     "read" = "Drupal\piliskor_qr\PluginForm\PiliskorRead\QRForm",
 *   },
 * )
 */
class QR extends ReadBase {

  /**
   * {@inheritdoc}
   */
  public function route() {
    $route = parent::route();
    $route->setPath('/'. $this->getPluginId() . '/{run}/{account}/{code}');
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function comparePointAndRequest(NodeInterface $point, Request $request, RouteMatchInterface $route_match) {
    $code = $route_match->getParameter('code');
    $point_codes = $point->get('field_qr_codes');
    $return = [
      'success' => FALSE,
      'field_code' => $code,
      'label' => $point->label(),
    ];
    foreach ($point_codes as $point_code) {
      if ($point_code->entity->field_qr_code->value === $code) {
        $return['success'] = TRUE;
      }
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function failedRead(OrderItemInterface $run, AjaxResponse $response, $match) {
    $response->addCommand(new MessageCommand($this->t('You are trying to read a wrong QR code. Try the one at %label.', ['%label' => $match['label']]), NULL, ['type' => 'warning']));
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, $account = NULL, $return_as_object = FALSE) {
    if (!$account) {
      $account = $this->currentUser;
    }
    $run = $this->routeMatch->getParameter('run');
    return AccessResult::allowedIf($account->hasPermission('access qr reader')
      && $this->runManager->isAccountInRun($run, $this->currentUser)
      && $this->runManager->isAccountInRun($run, $account));
  }

}
