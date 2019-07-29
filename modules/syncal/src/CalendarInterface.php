<?php

/**
 * @file
 * Provides Drupal\syncal\CalendarInterface
 */

namespace Drupal\syncal;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for syncal calendar plugins.
 */
interface CalendarInterface extends PluginInspectionInterface {

  /**
   * Return the name of the syncal calendar.
   *
   * @return string
   */
  public function getName();
  
}
