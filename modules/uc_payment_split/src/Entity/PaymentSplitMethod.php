<?php

namespace Drupal\uc_payment_split\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment_split\PaymentSplitMethodInterface;

/**
 * Defines a configured payment method.
 *
 * @ConfigEntityType(
 *   id = "uc_payment_split_method",
 *   label = @Translation("Payment split method"),
 *   label_singular = @Translation("payment split method"),
 *   label_plural = @Translation("payment split methods"),
 *   label_count = @PluralTranslation(
 *     singular = "@count payment split method",
 *     plural = "@count payment split methods",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\uc_payment_split\PaymentSplitMethodListBuilder",
 *     "form" = {
 *       "default" = "Drupal\uc_payment_split\Form\PaymentSplitMethodForm",
 *       "delete" = "Drupal\uc_payment_split\Form\PaymentSplitMethodDeleteConfirm"
 *     }
 *   },
 *   config_prefix = "method",
 *   admin_permission = "administer store",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "weight",
 *     "plugin",
 *     "settings",
 *     "locked"
 *   },
 *   links = {
 *     "edit-form" = "/admin/store/config/payment/method/{uc_payment_split_method}",
 *     "enable" = "/admin/store/config/payment/method/{uc_payment_split_method}/enable",
 *     "disable" = "/admin/store/config/payment/method/{uc_payment_split_method}/disable",
 *     "delete-form" = "/admin/store/config/payment/method/{uc_payment_split_method}/delete",
 *     "collection" = "/admin/store/config/payment"
 *   }
 * )
 */
class PaymentSplitMethod extends ConfigEntityBase implements PaymentSplitMethodInterface {

  /**
   * The payment method ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The payment method label.
   *
   * @var string
   */
  protected $label;

  /**
   * The payment method weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The payment method plugin ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The payment method plugin settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * The locked status of this payment method.
   *
   * @var bool
   */
  protected $locked = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return (bool) $this->locked;
  }

  /**
   * {@inheritdoc}
   */
  public function setLocked($locked) {
    $this->locked = (bool) $locked;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return \Drupal::service('plugin.manager.uc_payment.method')->createInstance($this->plugin, $this->settings);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel() {
    $build = $this->getPlugin()->getDisplayLabel($this->label());
    return \Drupal::service('renderer')->renderPlain($build);
  }

  /**
   * Returns the payment method entity for a specific order.
   *
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order from which the payment method should be loaded.
   *
   * @return static|null
   *   The entity object or NULL if there is no valid payment method.
   */
  public static function loadFromOrder(OrderInterface $order) {
    if ($method = $order->getPaymentMethodId()) {
      return static::load($method);
    }
    return NULL;
  }

}
