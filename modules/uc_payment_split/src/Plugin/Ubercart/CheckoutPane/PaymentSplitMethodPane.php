<?php

namespace Drupal\uc_payment_split\Plugin\Ubercart\CheckoutPane;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\uc_cart\CheckoutPanePluginBase;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\Entity\PaymentMethod;
use Drupal\uc_payment_split\Entity\PaymentSplitMethod;
use Drupal\uc_payment_split\Plugin\PaymentSplitMethodManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows the user to select a payment method and preview the line items.
 *
 * @CheckoutPane(
 *   id = "payment_split",
 *   title = @Translation("Payment split method"),
 *   weight = 7
 * )
 */
class PaymentSplitMethodPane extends CheckoutPanePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The payment method manager.
   *
   * @var \Drupal\uc_payment\Plugin\PaymentMethodManager
   */
  protected $paymentMethodManager;

  /**
   * Constructs a PaymentSplitMethodPane object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\uc_payment_split\Plugin\PaymentSplitMethodManager $payment_method_manager
   *   The payment method manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PaymentSplitMethodManager $payment_method_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->paymentMethodManager = $payment_method_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('plugin.manager.uc_payment_split.method')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(OrderInterface $order, array $form, FormStateInterface $form_state) {
    $contents['#attached']['library'][] = 'uc_payment_split/uc_payment_split.styles';

    if ($this->configuration['show_preview']) {
      $contents['line_items'] = [
        '#theme' => 'uc_payment_split_totals',
        '#order' => $order,
        '#weight' => -20,
      ];
    }

    // Ensure that the form builder uses #default_value to determine which
    // button should be selected after an ajax submission. This is
    // necessary because the previously selected value may have become
    // unavailable, which would result in an invalid selection.
    $input = $form_state->getUserInput();
    unset($input['panes']['payment_split']['payment_split_method']);
    $form_state->setUserInput($input);

    $options = [];
    $methods = PaymentSplitMethod::loadMultiple();
    uasort($methods, 'Drupal\uc_payment\Entity\PaymentMethod::sort');
    foreach ($methods as $method) {
      // $set = rules_config_load('uc_payment_split_method_' . $method['id']);
      // if ($set && !$set->execute($order)) {
      //   continue;
      // }

      if ($method->status()) {
        $options[$method->id()] = $method->getDisplayLabel();
      }
    }

    \Drupal::moduleHandler()->alter('uc_payment_split_method_checkout', $options, $order);

    if (!$options) {
      $contents['#description'] = $this->t('Checkout cannot be completed without any payment methods enabled. Please contact an administrator to resolve the issue.');
      $options[''] = $this->t('No payment methods available');
    }
    elseif (count($options) > 1) {
      $contents['#description'] = $this->t('Select a payment method from the following options.');
    }

    if (!$order->getPaymentMethodId() || !isset($options[$order->getPaymentMethodId()])) {
      $order->setPaymentMethodId(key($options));
    }

    $contents['payment_split_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Split Payment method'),
      '#title_display' => 'invisible',
      '#options' => $options,
      '#default_value' => $order->getPaymentMethodId(),
      '#disabled' => count($options) == 1,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'ajaxRender'],
        'wrapper' => 'payment-split-details',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
    ];

    // If there are no payment methods available, this will be ''.
    if ($order->getPaymentMethodId()) {
      $plugin = $this->paymentMethodManager->createFromOrder($order, 'PaymentSplitMethod');
      $definition = $plugin->getPluginDefinition();
      $contents['details'] = [
        '#prefix' => '<div id="payment-split-details" class="clearfix ' . Html::cleanCssIdentifier('payment-split-details-' . $definition['id']) . '">',
        '#markup' => $this->t('Continue with checkout to complete payment.'),
        '#suffix' => '</div>',
      ];

      try {
        $details = $plugin->cartDetails($order, $form, $form_state);
        if ($details) {
          unset($contents['details']['#markup']);
          $contents['details'] += $details;
        }
      }
      catch (PluginException $e) {
      }
    }

    return $contents;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order, array $form, FormStateInterface $form_state) {
    if (!$form_state->getValue(['panes', 'payment_split', 'payment_split_method'])) {
      $form_state->setErrorByName('panes][payment_split][payment_split_method', $this->t('You cannot check out without selecting a payment method.'));
      return FALSE;
    }
    $order->setPaymentMethodId($form_state->getValue(['panes', 'payment_split', 'payment_split_method']));
    $result = $this->paymentMethodManager->createFromOrder($order)->cartProcess($order, $form, $form_state);
    return $result !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function review(OrderInterface $order) {
    $line_items = $order->getDisplayLineItems();
    foreach ($line_items as $line_item) {
      $review[] = [
        'title' => $line_item['title'],
        'data' => uc_currency_format($line_item['amount']),
      ];
    }
    $method = $this->paymentMethodManager->createFromOrder($order);
    $review[] = [
      'border' => 'top',
      'title' => $this->t('Paying by'),
      'data' => $method->cartReviewTitle(),
    ];
    $result = $method->cartReview($order);
    if (is_array($result)) {
      $review = array_merge($review, $result);
    }
    return $review;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $form['show_preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the order total preview on the payment pane.'),
      '#default_value' => $this->configuration['show_preview'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['show_preview' => TRUE];
  }

  /**
   * Ajax callback to re-render the payment method pane.
   */
  public function ajaxRender($form, &$form_state) {
    return $form['panes']['payment_split']['details'];
  }

}
