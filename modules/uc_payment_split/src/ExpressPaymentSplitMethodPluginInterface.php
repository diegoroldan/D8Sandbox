<?php

namespace Drupal\uc_payment_split;

/**
 * Defines an interface for payment methods that bypass standard checkout.
 */
interface ExpressPaymentSplitMethodPluginInterface extends PaymentSplitMethodPluginInterface {

  /**
   * Form constructor.
   *
   * @return array
   *   A Form API button element that will bypass standard checkout.
   */
  public function getExpressButton($method_id);

}
