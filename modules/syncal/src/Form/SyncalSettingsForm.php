<?php

/**
 * @file
 * Contains Drupal\syncal\Form\SyncalSettingsForm
 */

namespace Drupal\syncal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

class SyncalSettingsForm extends ConfigFormBase {

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
    $config = $this->config('syncal.settings');

    $form['syncal_content_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Content types'),
      '#description' => $this->t('Select the content type(s) that will sync up to supported third-party calendars.'),
      '#multiple' => TRUE,
      '#default_value' => $config->get('syncal_content_types'),
      '#options' => $this->getContentTypeOptions(),
    ];

    $bundles = $config->get('syncal_content_types');
    $plugins = $this->getCalendarPlugins();

    foreach($bundles as $bundle) {

      $form['syncal_bundle_' . $bundle] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Bundle @bundle settings', array('@bundle' => $bundle)),
      ];

      $daterange_options = $this->getAvailableDateFields('node', $bundle);

      if(!$daterange_options) {
        $form['syncal_bundle_' . $bundle][$bundle . '_skip'] = [
          '#type' => 'markup',
          '#markup' => t('Bundle @bundle has no date range fields.', array('@bundle' => $bundle)),
        ];
        continue;
      }

      $form['syncal_bundle_' . $bundle][$bundle . '_daterange'] = [
        '#type' => 'select',
        '#title' => $this->t('Date range field', array('@bundle' => $bundle)),
        '#description' => $this->t('Select the date range field to be used to sync up to supported third-party calendars for the @bundle bundle.', array('@bundle' => $bundle)),
        '#multiple' => FALSE,
        '#default_value' => $config->get($bundle . '_daterange'),
        '#options' => $daterange_options,
      ];

      $form['syncal_bundle_' . $bundle][$bundle . '_plugins'] = [
        '#type' => 'select',
        '#title' => $this->t('Plugins', array('@bundle' => $bundle)),
        '#description' => $this->t('Select the third-party calendars that will be supported for the @bundle bundle.', array('@bundle' => $bundle)),
        '#multiple' => TRUE,
        '#default_value' => $config->get($bundle . '_plugins'),
        '#options' => $plugins,
      ];
    }

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

    $this->config('syncal.settings')
      ->set('syncal_content_types', $form_state->getValue('syncal_content_types'))
      ->save();

    $config = $this->config('syncal.settings');
    $bundles = $config->get('syncal_content_types');

    foreach($bundles as $bundle) {
      $this->config('syncal.settings')
        ->set($bundle . '_daterange', $form_state->getValue($bundle . '_daterange'))
        ->set($bundle . '_plugins', $form_state->getValue($bundle . '_plugins'))
        ->save();

      $plugins = $this->getCalendarPlugins();
      $supported_apps = \Drupal::config('syncal.settings')->get($bundle . '_plugins');

      foreach($plugins as $plugin => $plugin_config) {
        $field_name = "field_{$plugin}_event_id";
        if(in_array($plugin, $supported_apps)) {
          $this->createBundlePluginField($bundle, $field_name);
        } else {
          $this->deleteBundlePluginField($bundle, $field_name);
        }
      }
    }
  }

  /**
   * Creates a field and associates it to a node type.
   *
   * @param $bundle
   *   A node type object.
   * @param $field_name
   *   (optional) The label for the field instance.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  function createBundlePluginField($bundle, $field_name) {

    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    if(isset($fields[$field_name])) {
      return;
    }

    if(!FieldStorageConfig::loadByName('node', $field_name)) {
      \Drupal\field\Entity\FieldStorageConfig::create(array(
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'string_long',
        'cardinality' => -1,
      ))->save();
    }

    if(!FieldConfig::loadByName('node', $bundle, $field_name)) {
      \Drupal\field\Entity\FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => $field_name,
      ])->save();
    }
  }

  /**
   * Delete the field instance from the bundle.
   *
   * @param $bundle
   *   A node type object.
   * @param $field_name
   *   The machine name for the field instance.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  function deleteBundlePluginField($bundle, $field_name) {
    if($field_config = FieldConfig::loadByName('node', $bundle, $field_name)) {
      $field_config->delete();
    }
  }

  /**
   * Get a list of content types available.
   *
   * @return array
   *   A list of available content types.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getContentTypeOptions() {

    $types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();

    $options = [];

    foreach($types as $type) {
      $options[$type->id()] = $this->t($type->label());
    }

    return $options;
  }

  /**
   * Get available date fields for an specific entity type's bundle. Method also
   * verifies that the following date requirements are met.
   *  - Field type: the field must be a date range.
   *
   * @param string $entity_type
   *   An entity type name, e.g. "node"
   * @param $bundle
   *   The bundle name, e.g. "article", "page"
   * @return array
   *   A list of available fields.
   */
  public function getAvailableDateFields($entity_type = 'node', $bundle) {

    $active_fields = [];

    $fields = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity_type, $bundle);

    foreach ($fields as $field) {
      if ($field->getType() == 'daterange') {
        $active_fields[$field->getName()] = $field->getLabel();
      }
    }

    return $active_fields;
  }

  /**
   * Return a list of available calendar plugins.
   *
   * @return array
   *   A list of plugins.
   */
  public function getCalendarPlugins() {
    $manager = \Drupal::service('plugin.manager.syncal');
    $plugins = $manager->getDefinitions();
    $options = [];

    foreach ($plugins as $calendar) {
      $instance = $manager->createInstance($calendar['id']);
      $options[$calendar['id']] = $instance->getName();
    }

    return $options;
  }

  /**
   * Return calendar plugin configuration.
   *
   * @return array
   *   A list of plugin configurations.
   */
  public function getCalendarPluginConfigs() {
    $configs = [];

    $manager = \Drupal::service('plugin.manager.syncal');
    $plugins = $manager->getDefinitions();

    foreach ($plugins as $calendar) {
      $instance = $manager->createInstance($calendar['id']);
      $configs[$calendar['id']] = $instance;
    }

    return $configs;
  }
}