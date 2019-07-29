<?php

/**
 * @file
 * Contains \Drupal\syncal\Annotation\Calendar.
 */

namespace Drupal\syncal\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a calendar item annotation object.
 *
 * Plugin Namespace: Plugin\syncal\calendar
 *
 * @see \Drupal\syncal\Plugin\SynCalManager
 * @see plugin_api
 *
 * @Annotation
 */
class Calendar extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the calendar.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

}
