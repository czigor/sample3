<?php

namespace Drupal\piliskor_qr\Plugin\PiliskorRead;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;

interface ReadInterface extends AccessibleInterface, PluginFormInterface, PluginWithFormsInterface {

  /**
   * Sets the route for the read plugin.
   *
   * The first part of the path must be equal to the plugin id. A "run"
   * parameter must be present in the route.
   */
  public function route();

  /**
   * Create a response for this route.
   *
   * @param OrderItemInterface $run
   *   The run order item.
   * @param UserInterface $account
   *   The account who owns the read.
   * @param Request $request
   *   The request.
   * @param RouteMatchInterface $route_match
   *   The route match.
   */
  public function createResponse(OrderItemInterface $run, UserInterface $account, Request $request, RouteMatchInterface $route_match);

  /**
   * Perform actions when starting a run.
   *
   * @param OrderItemInterface $run
   *   The run.
   * @param AjaxResponse $response
   *   The ajax response that can be used to communicate with the runner.
   */
  public function startRun(OrderItemInterface $run, AjaxResponse $response);

  /**
   * Perform actions when continuing a run.
   *
   * @param OrderItemInterface $run
   *   The run.
   * @param AjaxResponse $response
   *   The ajax response that can be used to communicate with the runner.
   */
  public function continueRun(OrderItemInterface $run, AjaxResponse $response);

  /**
   * Perform actions when taking over a run.
   *
   * @param OrderItemInterface $run
   *   The run.
   * @param AjaxResponse $response
   *   The ajax response that can be used to communicate with the runner.
   */
  public function takeOverRun(OrderItemInterface $run, AjaxResponse $response);

  /**
   * Perform actions when finishing a run.
   *
   * @param OrderItemInterface $run
   *   The run.
   * @param AjaxResponse $response
   *   The ajax response that can be used to communicate with the runner.
   */
  public function finishRun(OrderItemInterface $run, AjaxResponse $response);

  /**
   * Perform actions when a read fails.
   *
   * @param OrderItemInterface $run
   *   The run.
   * @param AjaxResponse $response
   *   The ajax response that can be used to communicate with the runner.
   * @param array|bool|int $match
   *   The result of the faied match.
   *   @see \Drupal\piliskor_qr\Plugin\PiliskorRead\ReadBase::matchCodeToPoint().
   */
  public function failedRead(OrderItemInterface $run, AjaxResponse $response, $match);

}
