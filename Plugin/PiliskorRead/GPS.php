<?php

namespace Drupal\piliskor_qr\Plugin\PiliskorRead;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the QR reader plugin.
 *
 * @PiliskorRead(
 *   id = "gps",
 *   label = @Translation("GPS"),
  *  forms = {
 *     "read" = "Drupal\piliskor_qr\PluginForm\PiliskorRead\GPSForm",
 *   },
 * )
 */
class GPS extends ReadBase {

  const TURMIX_TOLERANCE=60;

  /**
   * {@inheritdoc}
   */
  public function route() {
    $route = parent::route();
    $route->setPath('/'. $this->getPluginId() . '/{run}/{account}/{lat}/{lng}');
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function comparePointAndRequest(NodeInterface $point, Request $request, RouteMatchInterface $route_match) {
    $read_lat = $route_match->getParameter('lat');
    $read_lng = $route_match->getParameter('lng');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $run */
    $run = $route_match->getParameter('run');
    if ($run->get('field_points')->isEmpty()) {
      $track = $run->getPurchasedEntity()->get('field_track')->entity;
      $gps_tolerance = $track->get('field_gps_tolerance')->number;
    }
    else {
      $gps_tolerance = self::TURMIX_TOLERANCE;
    }
    $point_coordinates = $point->get('field_coordinates');
    $return = [
      'field_coordinates' => [
        'lat' => $read_lat,
        'lng' => $read_lng,
      ],
      'label' => $point->label(),
      'success' => FALSE,
    ];
    foreach ($point_coordinates as $coordinates) {
      $point_lat = $coordinates->lat;
      $point_lng = $coordinates->lng;
      $distance = $this->distance($point_lat, $point_lng, $read_lat, $read_lng, 'K');
      if (empty($return['distance']) || $distance < $return['distance']) {
        $return['distance'] = $distance;
      }
      if ($return['distance'] < $gps_tolerance / 1000) {
        $return['success'] = TRUE;
        break;
      }
    }
    return $return;
  }

  /**
   * Calculates the distance between two points (given the latitude/longitude
   * of those points). It is being used to calculate the distance between two
   * locations.
   *
   * South latitudes are negative, east longitudes are positive.
   *
   *  @param lat1, lon1
   *   The latitude and longitude of point 1 in decimal degrees.
   *  @param lat2, lon2
   *   The latitude and longitude of point 2 in decimal degrees.
   * @param unit
   *   The unit you desire for results.
   *     'M' is statute miles (default)
   *     'K' is kilometers
   *     'N' is nautical miles
   *
   * @see https://www.geodatasource.com/developers/php
   */
  protected function distance($lat1, $lon1, $lat2, $lon2, $unit) {
    if (($lat1 == $lat2) && ($lon1 == $lon2)) {
      return 0;
    }
    else {
      $theta = $lon1 - $lon2;
      $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
      $dist = acos($dist);
      $dist = rad2deg($dist);
      $miles = $dist * 60 * 1.1515;
      $unit = strtoupper($unit);

      if ($unit == "K") {
        return ($miles * 1.609344);
      } else if ($unit == "N") {
        return ($miles * 0.8684);
      } else {
        return $miles;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function failedRead(OrderItemInterface $run, AjaxResponse $response, $match) {
    $distance = $match['distance'] > 1 ? $match['distance'] : $match['distance'] * 1000;
    $vars = [
      '%distance' => round($distance),
      '%unit' => $match['distance'] > 1 ? 'km' : 'm',
      '%point' => $match['label'],
    ];
    $response->addCommand(new MessageCommand($this->t('Failed attempt to check in via GPS. You are still %distance %unit away from %point.', $vars), NULL, ['type' => 'warning']));
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['gps']['accuracy'] = [
      '#type' => 'number',
      '#title' => $this->t('GPS accuracy in meters'),
      '#min' => 0,
      '#step' => 5,
      '#default_value' => $this->configFactory->getEditable('piliskor_qr.settings')->get('gps.accuracy'),
    ];
    $form['gps']['#tree'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('piliskor_qr.settings');
    $config->set('gps.accuracy', $form_state->getValue(['gps', 'accuracy']));
    $config->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, $account = NULL, $return_as_object = FALSE) {
    if (!$account) {
      $account = $this->currentUser;
    }
    $run = $this->routeMatch->getParameter('run');
    return AccessResult::allowedIf($account->hasPermission('use gps button')
      && $this->runManager->isAccountInRun($run, $this->currentUser)
      && $this->runManager->isAccountInRun($run, $account));
  }

}
