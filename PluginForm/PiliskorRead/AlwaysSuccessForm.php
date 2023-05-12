<?php

namespace Drupal\piliskor_qr\PluginForm\PiliskorRead;

use Drupal\Core\Form\FormStateInterface;

class AlwaysSuccessForm extends ReadFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['always_success'] = [
      '#type' => 'button',
      '#value' => t('Success'),
      '#attached' => [
        'library' => [
          'piliskor_qr/success',
        ],
      ],
      '#weight' => 10,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
