# SynCal
Provides the ability to synchronize events to third-party calendars such Google 
Calendar. Supports insert, update and delete operations for node entities that
have a date range field. 

Requires the Google API Client PHP Library; be sure to download it from 
[here](https://github.com/googleapis/google-api-php-client) or by invoking 
composer install (Drupal 8).

Since the module requires an external library, Composer must be used.

composer require "drupal/syncal"

## Official Beta release
See official Drupal 8 Beta v.1 module release on Drupal.org at [SynCal](https://www.drupal.org/project/syncal)

## Composer
### Dependencies
[Managing dependencies for a custom project](https://www.drupal.org/docs/develop/using-composer/managing-dependencies-for-a-custom-project)
### Installation
1. Run `composer require "drupal/syncal"`

## Private Directory Setup

> *IMPORTANT and highly recommended to use private directory which is how the 
module is packaged to help protect stored Google API tokens. 

Setup the path to the private directory in the settings.php
```php
$settings['file_private_path'] = 'sites/default/files/private';
```

## Google Calendar API Setup
### Developer Console setup
1. Go to https://console.developers.google.com
2. Create a project
3. Enable "Google Calendar API" for the new project
4. Back on the main "Dashboard" page, under the main menu, click on "Credentials"
5. Create "Create credentials"
6. Click "OAuth client ID"
7. Select "Web application"
8. Give it a name, e.g. "SynCal Connect OAuth"
9. Under "Authorized redirect URIs" type `LOCAL_DOMAIN://syncal/user/syncal/google_calendar`
Replace LOCAL_DOMAIN with your local environment, development or production domain
e.g. http://localhost/syncal/user/syncal/google_calendar
10. Click "Save"
11. Under "OAuth 2.0 client IDs" you should see the Web application client ID 
created on the previous step, click on its name
12. At the top, click on "DOWNLOAD JSON". This file will be required on a later step

## Local & other environments setup
1. In order to test on a local environment, the domain must name **_localhost_**, e.g.

> `http://dev.localhost/syncal`

2. Go to `/admin/config/syncal/settings`
3. Select the content types(s) that should sync up to Google Calendar
4. Select the `Date range field` that will be used as the date value
5. Click on "Save configuration"
6. Click on the "Google Calendar" tab
7. Upload the JSON filed downloaded on step 12 from the _Developer Console setup_ section 

## Available Hook Alters
##### Appending location details

Hook: `hook_syncal_google_calendar_location_alter().`

Example:
```php
/**
 * Implements hook_syncal_google_calendar_location_alter().
 */
function example_syncal_google_calendar_location_alter(&$data) {
  // Event address.
  $address = [];
  $address['venue'] = t('Madison Square Garden'); // Name of venue
  $address['thoroughfare'] = t('4 Pennsylvania Plaza'); // Address 1
  $address['premise'] = t('2nd Floor'); // Address 2
  $address['locality'] = t('New York City'); // City
  $address['administrative_area'] = t('New York');
  $address['postal_code'] = 10001;

  $data = implode(', ', $address);
}
```

##### Appending notes details

Hook: `hook_syncal_google_calendar_notes_alter().`

Example:
```php
/**
 * Implements hook_syncal_google_calendar_notes_alter().
 */
function example_syncal_google_calendar_notes_alter(&$data) {
  $data = t('<h2>Event details</h2>');
  $data .= t('Put this on my Google Calendar event entry notes section.');
  $data .= '<br>';
  $data .= t('Another note here...');
}
```

##### Appending attendees details

Hook: `hook_syncal_google_calendar_attendees_alter().`

Example:
```php
/**
 * Implements hook_syncal_google_calendar_attendees_alter().
 */
function example_syncal_google_calendar_attendees_alter(&$data) {
  $attendees[] = array('email' => 'syncal@example.com');

  $data = $attendees;
}
```

## Upcoming features
1. New plugin integration, such _iCal_
2. Support other entity types.

git checkout -b 8.x-1.x

echo "SynCal" > README.txt

git add README.txt

git commit -m "Initial commit."

git remote add origin git@git.drupal.org:project/syncal.git

git push origin 8.x-1.x
