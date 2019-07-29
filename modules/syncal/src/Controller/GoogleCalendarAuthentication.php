<?php

namespace Drupal\syncal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Controller class used to retrieve a consent and authenticate the user
 * consent.
 */
class GoogleCalendarAuthentication extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'syncal';
  }

  /**
   * Callback method that handles the Google Calendar API redirect URI upon user
   * consent.
   */
  function validateRedirect() {

    if (isset($_GET['code'])) {

      $plugin_id = 'google_calendar';
      $manager = \Drupal::service('plugin.manager.syncal');
      $instance = $manager->createInstance($plugin_id);

      $instance->getAPIClientConsentVerify();
      \Drupal::messenger()->addMessage(t('Google calendar is now active on your account.'), 'status');

      // Redirect the user back to their profile page.
      $url = Url::fromRoute('entity.user.edit_form', ['user' => \Drupal::currentUser()->id()]);
      $destination = $url->toString();
      $redirect = new RedirectResponse($destination, 301);
      $redirect->send();
    }
  }
}
