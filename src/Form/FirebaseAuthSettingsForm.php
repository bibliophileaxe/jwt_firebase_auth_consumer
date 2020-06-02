<?php

namespace Drupal\jwt_firebase_auth_consumer\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FirebaseAuthSettingsForm.
 *
 * @package Drupal\jwt\Form
 */
class FirebaseAuthSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'jwt.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jwt_config_form';
  }

  /**
   * AJAX Function callback.
   *
   * @param array $form
   *   Drupal form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Drupal formstate object.
   *
   * @return mixed
   *   Updated AJAXed form.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['key-container'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['key-container'] = [
      '#type' => 'container',
      '#prefix' => '<div id="jwt-key-container">',
      '#suffix' => '</div>',
      '#weight' => 10,
    ];

    $form['jwt_algorithm'] = [
      '#type' => 'select',
      '#title' => $this->t('Algorithm'),
      '#options' => $this->transcoder->getAlgorithmOptions(),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'event' => 'change',
        'wrapper' => 'jwt-key-container',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
      '#default_value' => $this->config('jwt.config')->get('algorithm'),
    ];

    if ($form_state->isValueEmpty('jwt_algorithm')) {
      if (!empty($this->config('jwt.config')->get('algorithm'))) {
        $type = $this->transcoder->getAlgorithmType($this->config('jwt.config')->get('algorithm'));
      }
      else {
        $type = 'jwt_hs';
      }
    }
    else {
      $type = $this->transcoder->getAlgorithmType($form_state->getValue('jwt_algorithm'));
    }
    $text = ($type == 'jwt_hs') ? $this->t('Secret') : $this->t('Private Key');

    $form['key-container']['jwt_key'] = [
      '#type' => 'key_select',
      '#title' => $text,
      '#default_value' => $this->config('jwt.config')->get('key_id'),
      '#key_filters' => [
        'type' => $type,
      ],
      '#validated' => TRUE,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $algorithm = $form_state->getValue('jwt_algorithm');
    $key_id = $form_state->getValue('jwt_key');
    $key = $this->keyRepo->getKey($key_id);

    if ($key != NULL && $key->getKeyType()->getPluginId() != $this->transcoder->getAlgorithmType($algorithm)) {
      $form_state->setErrorByName('jwt_key', $this->t('Incorrect key type selected.'));
    }

    if ($key != NULL && $key->getKeyType()->getConfiguration()['algorithm'] != $algorithm) {
      $form_state->setErrorByName('jwt_key', $this->t('Key does not match algorithm selected.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $values = $form_state->getValues();

    if (isset($values['jwt_algorithm'])) {
      $this->config('jwt.config')->set('algorithm', $values['jwt_algorithm'])->save();
    }

    if (isset($values['jwt_key'])) {
      $this->config('jwt.config')->set('key_id', $values['jwt_key'])->save();
    }
  }

}