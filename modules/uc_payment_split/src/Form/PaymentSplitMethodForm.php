<?php

namespace Drupal\uc_payment_split\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides form for block instance forms.
 */
class PaymentSplitMethodForm extends EntityForm {

  /**
   * The payment method entity.
   *
   * @var \Drupal\uc_payment_split\PaymentSplitMethodInterface
   */
  protected $entity;

  /**
   * The payment method plugin instance.
   *
   * @var \Drupal\uc_payment_split\PaymentSplitMethodPluginInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->plugin = $this->entity->getPlugin();
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $definition = $this->plugin->getPluginDefinition();
    $form['type'] = [
      '#type' => 'item',
      '#title' => $this->t('Type'),
      '#markup' => $definition['name'],
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('The name shown to customers when they choose this payment method at checkout.'),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#maxlength' => 32,
      '#machine_name' => [
        'exists' => '\Drupal\uc_payment_split\Entity\PaymentSplitMethod::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['settings'] = $this->plugin->buildConfigurationForm([], $form_state);
    $form['settings']['#tree'] = TRUE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $this->plugin->validateConfigurationForm($form['settings'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->plugin->submitConfigurationForm($form['settings'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();

    $this->messenger()->addMessage($this->t('Saved the %label payment method.', ['%label' => $this->entity->label()]));

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

}
