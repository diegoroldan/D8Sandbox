<?php

/**
 * @file
 * Provides Drupal\syncal\CalendarBase.
 */

namespace Drupal\syncal;

use Drupal\Component\Plugin\PluginBase;

class CalendarBase extends PluginBase implements CalendarInterface {

  public function getName() {
    return $this->pluginDefinition['name'];
  }
}
