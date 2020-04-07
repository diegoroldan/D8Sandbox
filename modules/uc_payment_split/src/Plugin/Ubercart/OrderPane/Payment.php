<?php

namespace Drupal\uc_payment_split\Plugin\Ubercart\OrderPane;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uc_order\EditableOrderPanePluginBase;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment_split\Entity\PaymentSplitMethod;

/**
 * Specify and collect payment for an order.
 *
 * @UbercartOrderPane(
 *   id = "payment",
 *   title = @Translation("Payment"),
 *   weight = 4,
 * )
 */
class Payment extends EditableOrderPanePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    return ['pos-left'];
  }

  /**
   * {@inheritdoc}
   */
  public function view(OrderInterface $order, $view_mode) {
    if ($view_mode != 'customer') {
      $build['balance'] = [
        '#markup' => $this->t('Balance: @balance', ['@balance' => uc_currency_format(uc_payment_split_balance($order))]),
      ];

      $account = \Drupal::currentUser();
      if ($account->hasPermission('view payments')) {
        $build['view_payments'] = [
          '#type' => 'link',
          '#prefix' => ' (',
          '#title' => $this->t('View'),
          '#url' => Url::fromRoute('uc_payment_splits.order_payments', ['uc_order' => $order->id()]),
          '#suffix' => ')',
        ];
      }

      $method = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);
      $build['method'] = [
        '#markup' => $this->t('Method: @payment_method', ['@payment_method' => $method->cartReviewTitle()]),
        '#prefix' => '<br />',
      ];

      $method_output = $method->orderView($order);
      if (!empty($method_output)) {
        $build['output'] = $method_output + [
          '#prefix' => '<br />',
        ];
      }
    }
    else {
      $method = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);
      $build['method'] = [
        '#markup' => $this->t('Method: @payment_method', ['@payment_method' => $method->cartReviewTitle()]),
      ];

      $method_output = $method->customerView($order);
      if (!empty($method_output)) {
        $build['output'] = $method_output + [
          '#prefix' => '<br />',
        ];
      }

    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(OrderInterface $order, array $form, FormStateInterface $form_state) {
    $options = [];
    $methods = PaymentSplitMethod::loadMultiple();
    uasort($methods, 'Drupal\uc_payment_split\Entity\PaymentSplitMethod::sort');
    foreach ($methods as $method) {
      $options[$method->id()] = $method->label();
    }

    $form['payment_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Payment method'),
      '#default_value' => $order->getPaymentMethodId(),
      '#options' => $options,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'progress' => ['type' => 'throbber'],
        'wrapper' => 'payment-details',
      ],
    ];

    // An empty <div> for Ajax.
    $form['payment_details'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'payment-details'],
      '#tree' => TRUE,
    ];

    $method = $form_state->getValue('payment_method') ?: $order->getPaymentMethodId();
    if ($method && $details = PaymentSplitMethod::load($method)->getPlugin()->orderEditDetails($order)) {
      if (is_array($details)) {
        $form['payment_details'] += $details;
      }
      else {
        $form['payment_details']['#markup'] = $details;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(OrderInterface $order, array &$form, FormStateInterface $form_state) {
    $changes['payment_method'] = $form_state->getValue('payment_method');
    $changes['payment_details'] = $form_state->getValue('payment_details') ?: [];

    $order->setPaymentMethodId($changes['payment_method']);
    $method = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);
    $return = $method->orderEditProcess($order, $form, $form_state);
    if (is_array($return)) {
      $changes['payment_details'] = array_merge($changes['payment_details'], $return);
    }
    $order->payment_details = $changes['payment_details'];
  }

  /**
   * AJAX callback to render the payment method pane.
   */
  public function ajaxCallback($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#payment-details', trim(drupal_render($form['payment']['payment_details']))));
    $status_messages = ['#type' => 'status_messages'];
    $response->addCommand(new PrependCommand('#payment-details', drupal_render($status_messages)));

    return $response;
  }

}
