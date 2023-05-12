<?php

namespace Drupal\piliskor_qr\PluginForm\PiliskorRead;

use Drupal\Core\Form\FormStateInterface;
use Drupal\piliskor_qr\Plugin\PiliskorRead\GPS;

class GPSForm extends ReadFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['gps'] = [
      '#type' => 'button',
      '#value' => t('GPS'),
      '#attached' => [
        'library' => [
          'piliskor_qr/gps',
        ],
      ],
      '#weight' => 20,
    ];
    $run = $form_state->getBuildInfo()['args'][0];
    if ($run->get('field_points')->isEmpty()) {
      $track = $run->getPurchasedEntity()->get('field_track')->entity;
      $form['#attached']['drupalSettings']['piliskorQR']['gpsTolerance'] = $track->get('field_gps_tolerance')->number;
    }
    else {
      $form['gps']['#attached']['drupalSettings']['piliskorQR']['gpsTolerance'] = GPS::TURMIX_TOLERANCE;
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
