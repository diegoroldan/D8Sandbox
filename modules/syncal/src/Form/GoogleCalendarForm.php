<?php

/**
 * @file
 * Contains Drupal\syncal\Form\GoogleCalendarForm
 */

namespace Drupal\syncal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class GoogleCalendarForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'syncal.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'syncal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = [];

    $form['google_calendar'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Google Calendar Settings'),
    ];

    $form['google_calendar']['auth_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Authentication file'),
      '#upload_location' => 'private://syncal',
      '#upload_validators' => [
        'file_validate_extensions' => ['json'],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    parent::submitForm($form, $form_state);

    $form_file = $form_state->getValue('auth_file', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      $file = File::load($form_file[0]);
      $file->setPermanent();
      $file->save();
    }

    $this->config('syncal.settings')
      ->set('syncal_google_calendar_auth_file_id', $file->id())
      ->save();
  }
}
