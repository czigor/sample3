<?php

namespace Drupal\piliskor_qr\PluginForm\PiliskorRead;

use Drupal\Core\Form\FormStateInterface;

class QRForm extends ReadFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['qr']['reader'] = [
      '#theme' => 'piliskor_qr_reader',
      '#attached' => [
        'library' => [
          'piliskor_qr/qr',
        ],
      ],
      '#weight' => 20,
    ];
    $form['qr']['#weight'] = 10;
    return $form;
  }

}
