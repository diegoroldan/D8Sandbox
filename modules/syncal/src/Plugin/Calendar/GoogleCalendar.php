<?php

/**
 * @file
 * Contains \Drupal\syncal\Plugin\Calendar\GoogleCalendar.
 *
 * Main file to handle Google Calendar third-party app. Provides support for
 *   the following operations:
 *
 *   Insert: when an event is created, an event is created in Google Calendar.
 *   Update: When an event is updated, the event is updated in Google Calendar
 *     by it event ID.
 *
 * @references
 *   https://developers.google.com/calendar/quickstart/php?pli=1&authuser=5#step_3_set_up_the_sample
 *   https://github.com/gsuitedevs/php-samples/tree/master/calendar/quickstart
 */

namespace Drupal\syncal\Plugin\Calendar;

use Drupal\syncal\CalendarBase;
use Drupal\node\NodeInterface;
use Drupal\Core\URL;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;

/**
 * Provides calendar 'google_calendar'.
 *
 * @Calendar(
 *   id = "google_calendar",
 *   name = @Translation("Google Calendar"),
 * )
 */
class GoogleCalendar extends CalendarBase {

  /**
   * Performs the connection to the Google API Client by prompting the user with
   * a list of of Google accounts to user for the connection or the ability to add
   * an account.
   *
   * @return \Google_Client
   * @throws \Exception
   * @throws \Google_Exception
   */
  function getAPIClient($user) {

    $auth_fid = \Drupal::config('syncal.settings')->get('syncal_google_calendar_auth_file_id');
    $auth_file = \Drupal\file\Entity\File::load($auth_fid);

    $route_name = 'syncal.google_calendar.auth_redirect_uri';
    $route_parameters = [];
    $route_options = ['absolute' => TRUE];

    $setRedirectUri = Url::fromRoute($route_name, $route_parameters, $route_options);

    $config = \Drupal::config('system.site');

    $client = new Google_Client();
    $client->setApplicationName($config->get('name'));
    $client->setScopes(Google_Service_Calendar::CALENDAR);
    $client->setAuthConfig($auth_file->getFileUri());
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    $client->setRedirectUri($setRedirectUri->toString());
    $client->setApprovalPrompt('force');

    // Load previously authorized token from the user profile.
    $tokenPath = $user->get('field_google_calendar_token')->value;
    if ($tokenPath) {
      $accessToken = Json::decode($tokenPath);
      $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
      // Refresh the token if possible, else fetch a new one.
      if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
      }
      else {
        if (isset($_GET['code'])) {
          $authCode = $_GET['code'];
        }
        else {
          // Request authorization from the user.
          $authUrl = $client->createAuthUrl();
          $redirect = new RedirectResponse($authUrl, 301, []);
          $redirect->send();
        }

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
        $client->setAccessToken($accessToken);

        // Check to see if there was an error.
        if (array_key_exists('error', $accessToken)) {
          throw new \Exception(join(', ', $accessToken));
        }
      }
      // Save the token.
      $user->set('field_google_calendar_token', Json::encode($client->getAccessToken()));
      // Save profile.
      $user->save();
    }

    return $client;
  }

  /**
   * @param \Drupal\node\NodeInterface $node
   *   The node being viewed.
   * @return mixed
   *    A Google Calendar ID if one exists, empty value otherwise.
   */
  function getCalendarID(NodeInterface $node) {
    $user = \Drupal\user\Entity\User::load($node->getOwnerId());
    $calendarId = $user->get('field_google_calendar_settings')->value;
    return $calendarId;
  }

  /**
   * Retrieve all the calendars the user has access to.
   *
   * @param $user
   *   A Drupal core user account object.
   * @return array|bool
   *   A list of calendars the user has in their Google calendar, false if the
   *   connection to the API fails.
   * @throws \Google_Exception
   */
  function getCalendars($user) {
    $calendars = array();

    // Get the API client and construct the service object.
    $client = $this->getAPIClient($user);
    $service = new Google_Service_Calendar($client);

    try {
      $calendar_list = $service->calendarList->listCalendarList();
    } catch (Exception $e) {
      return FALSE;
    }

    foreach ($calendar_list as $calendar) {
      $suffix = '';
      if ($calendar->primary) {
        $suffix = ' (Primary)';
      }
      $calendars[$calendar->id] = $calendar->summary . $suffix;
    }

    return $calendars;
  }

  /**
   * Revoke access permission to connect and sync events to the user's Google
   * calendar.
   */
  function revokeAccess($type = 'revoke') {

    // Delete the value stored in the user's Google calendar token field and the
    // customized settings stored in the Google calendar configuration field.
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    // Save the token.
    $user->set('field_google_calendar_token', NULL);
    $user->set('field_google_calendar_settings', NULL);
    // Save profile.
    $user->save();

    switch ($type) {
      case 'expired':
        \Drupal::messenger()
          ->addMessage(
            t('Your Google Calendar access token has expired. Reconnect your Google Calendar.'),
            'error'
          );

        break;

      case 'revoke':
      default:

        $config = \Drupal::config('system.site');

        \Drupal::messenger()
          ->addMessage(
            t('You have successfully revoke @sitename access to connect and sync events on your Google calendar',
              array(
                '@sitename' => $config->get('name')
              )),
            'status'
          );

        break;
    }
  }

  /**
   * Verify Google Calendar consent after getting the access token.
   *
   * @param null $account
   * @return bool
   * @throws \Exception
   * @throws \Google_Exception
   */
  function getAPIClientConsentVerify() {
    try {
      $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      // Get the API client and construct the service object.
      $client = $this->getAPIClient($user);
      $service = new Google_Service_Calendar($client);

      if ($service) {
        return TRUE;
      }
    } catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Prepare the event update operation by instantiating connecting to the Google
   * API Client and instantiating a Google Service Calendar object.
   *
   * @param $node
   *   A Drupal properly formed node object.
   * @param $op
   *   The operation to be performed.
   * @return bool|\Google_Service_Calendar_Event
   *   An event object as returned by the Google API upon successful update of the
   *   event, false if it fails to update the event.
   * @throws \Exception
   * @throws \Google_Exception
   */
  function eventPrepareOperation(NodeInterface $node, $op) {
    // Retrieve calendar event's owner Google token.
    $user = \Drupal\user\Entity\User::load($node->getOwnerId());

    try {
      // Get the API client and construct the service object.
      $client = $this->getAPIClient($user);
      // Get Google Service Calendar object.
      $service = new Google_Service_Calendar($client);

      switch($op) {
        case 'insert':
          $event = $this->setEvent($service, $node);
          break;
        case 'update':
          $event = $this->setEventUpdate($service, $node);
          break;
        case 'delete':
          $event = $this->setEventDelete($service, $node);
      }

    } catch (\Exception $e) {
      \Drupal::logger('syncal')->notice($e);
      $event = FALSE;
    }

    return $event;
  }

  /**
   * Creates an event on the Google Calendar the user has granted consent to.
   *
   * @param $service
   *   An object of type Google Calendar Service.
   * @param \Drupal\node\NodeInterface $node
   *   A Drupal properly formed node object.
   * @return bool|\Google_Service_Calendar_Event
   */
  function setEvent($service, NodeInterface $node) {

    $daterange = \Drupal::config('syncal.settings')->get("{$node->getType()}_daterange");
    $dates = $node->get($daterange)->getValue();

    $summary = $node->get('title')->value;
    $description = $node->get('body')->value ? $node->get('body')->value : '';

    $timezone = drupal_get_user_timezone();

    $event = new Google_Service_Calendar_Event(array(
      'summary' => $summary,
      'description' => $description,
      'start' => array(
        'dateTime' => $dates[0]['value'],
        'timeZone' => $timezone,
      ),
      'end' => array(
        'dateTime' => $dates[0]['end_value'],
        'timeZone' => $timezone,
      ),
      'recurrence' => array(
        'RRULE:FREQ=DAILY;COUNT=1'
      ),
    ));

    $calendarId = $this->getCalendarID($node);

    try {
      $event = $service->events->insert($calendarId, $event);
      \Drupal::messenger()->addMessage(t('Google calendar event has been created.'), 'status');
      return $event;
    } catch (\Exception $e) {
      \Drupal::messenger()->addMessage(t('Google calendar event could not be created.'), 'error');
      return FALSE;
    }
  }

  /**
   * Updates an event on the Google Calendar by its event ID.
   *
   * @param $service
   *   An object of type Google Calendar Service.
   * @param $node
   *   A Drupal properly formed node object.
   * @return \Google_Service_Calendar_Event
   */
  function setEventUpdate($service, NodeInterface $node) {

    $field_name = "field_{$this->getPluginId()}_event_id";
    $event_id = $node->get($field_name)->value;
    $calendarId = $this->getCalendarID($node);
    // Retrieve the Google calendar event by its ID.

    /** @var Google_Service_Calendar_Event $event */
    $event = $service->events->get($calendarId, $event_id);

    // Set the title
    $event->setSummary($node->get('title')->value);
    // Set the description
    if ($node->get('body')->value) {
      $event->setDescription($node->get('body')->value);
    }
    $daterange = \Drupal::config('syncal.settings')->get("{$node->getType()}_daterange");
    $dates = $node->get($daterange)->getValue();
    $start_date = date('Y-m-d\TH:i:s', strtotime($dates[0]['value'])+date('Z'));
    $end_date = date('Y-m-d\TH:i:s', strtotime($dates[0]['end_value'])+date('Z'));
    $timezone = drupal_get_user_timezone();

    // Set the start date
    $start = new Google_Service_Calendar_EventDateTime();
    $start->setDateTime($start_date);
    $start->setTimeZone($timezone);
    $event->setStart($start);

    // Set the end date
    $end = new Google_Service_Calendar_EventDateTime();
    $end->setDateTime($end_date);
    $end->setTimeZone($timezone);
    $event->setEnd($end);

    $event->setDescription($this->getNotes());
    $event->setAttendees($this->getAttendees());
    $event->setLocation($this->getLocation());

    $calendarId = $this->getCalendarID($node);

    // Update the Google instance.
    $event = $service->events->update($calendarId, $event->getId(), $event);

    \Drupal::messenger()->addMessage(t('Google calendar event has been updated'), 'status');

    return $event;
  }

  /**
   * Deletes an event on the Google Calendar by its event ID.
   *
   * @param $service
   *   An object of type Google Calendar Service.
   * @param $node
   *   A Drupal properly formed node object.
   * @return bool
   */
  function setEventDelete($service, NodeInterface $node) {

    $field_name = "field_{$this->getPluginId()}_event_id";
    $event_id = $node->get($field_name)->value;
    $calendarId = $this->getCalendarID($node);

    /** @var Google_Service_Calendar_Event $event */
    if($service->events->delete($calendarId, $event_id)) {
      \Drupal::messenger()->addMessage(t('Google calendar event has been deleted'), 'status');
      return TRUE;
    }

    \Drupal::messenger()->addMessage(t('Google calendar event could not be deleted'), 'error');
    return FALSE;
  }

  /**
   * Allow other modules to append to the notes.
   *
   * @return array
   *   An object prepared to be passed to Google Calendar.
   */
  function getNotes() {
    // Create the Data that will be passed to ModuleHandler::alter().
    $data = array();

    // Allow other modules to alter the $data invoking: hook_TYPE_alter().
    // In our case, the TYPE will be 'syncal_google_calendar_notes'.
    \Drupal::moduleHandler()->alter('syncal_google_calendar_notes', $data);

    return $data;
  }

  /**
   * Allow other modules to append to the list of attendees.
   *
   * @param array
   *   An object prepared to be passed to Google Calendar.
   */
  function getAttendees() {
    // Create the Data that will be passed to ModuleHandler::alter().
    $data = array();

    // Allow other modules to alter the $data invoking: hook_TYPE_alter().
    // In our case, the TYPE will be 'syncal_google_calendar_attendees'.
    \Drupal::moduleHandler()->alter('syncal_google_calendar_attendees', $data);

    return $data;
  }

  /**
   * Allow other modules to append a location.
   *
   * @return array
   *    An object prepared to be passed to Google Calendar.
   */
  function getLocation() {

    // Create the Data that will be passed to ModuleHandler::alter().
    $data = array();

    // Allow other modules to alter the $data invoking: hook_TYPE_alter().
    // In our case, the TYPE will be 'syncal_google_calendar_location'.
    \Drupal::moduleHandler()->alter('syncal_google_calendar_location', $data);

    return $data;
  }

}
