# Akamai

[![Build
Status](https://travis-ci.org/d8-contrib-modules/akamai.svg?branch=8.x-3.x)](https://travis-ci.org/d8-contrib-modules/akamai)
[![Scrutinizer Code
Quality](https://scrutinizer-ci.com/g/d8-contrib-modules/akamai/badges/quality-score.png?b=8.x-3.x)](https://scrutinizer-ci.com/g/d8-contrib-modules/akamai/?bra
nch=8.x-3.x)

This module provides a Drupal 8 service to interact with the [Akamai Content
Control Utility](https://developer.akamai.com/api/purge/ccu/overview.html).

While the service can be used by developers in isolation, most users should
install the [Purge](http://drupal.org/project/purge) module. Purge will take
care of invalidating caches automatically when content is updated.

It incorporates the
[AkamaiOPEN-edgegrid-php](https://github.com/akamai-open/AkamaiOPEN-edgegrid-php)
library.

Development is presently based on Github at
https://github.com/d8-contrib-modules/akamai, with all changes synced to
Drupal.org. This may change in the future.

## Installation and configuration

Download the module with drush or otherwise, add it to the `modules` folder.

You will need to download
[akamai-open/edgrid-client](https://packagist.org/packages/akamai-open/edgegrid-
client). The recommended way to do that is by installing [Composer
Manager](https://www.drupal.org/project/composer_manager), and following its
instructions to update your site's `vendor` directory.

### With Purge

Make sure `purge_ui` is enabled.

Go to `admin/config/development/performance/purge` and enable the Akamai Purger
in the list of Purger plugins.

Configure your Akamai credentials via the 'Config' dropdown in the Purge UI
interface.

### Without Purge

Go to `/admin/config/akamai/config` and enter your Akamai credentials.

Go to `/admin/config/akamai/cache-control` to clear URLs manually.

## Akamai Credentials

Follow the instructions here to set up the client credentials.
https://developer.akamai.com/introduction/Prov_Creds.html

You will need admin access to the Luna control panel to create credentials.


## Usage

### With Purge

You will need to make sure that you have necessary Purge plugins enabled and
configured:

*  a queuer (at present, purge_queuer_url is the only queuer supported by this
module)
*  a queue
*  a processor

Purge will queued URLs that need to be cleared from Akamai automatically.

### Without Purge

There are two ways to clear URLs without Purge:

1. Via the form at `admin/config/akamai/cache-clear`, which allows you to enter
lists of URLs to clear.
2. You can enable a block, 'Akamai Cache Clear', which will allow you to clear
the page you are currently viewing.

### Purge Status Tracking

When you send a cache clear request, Akamai queues it for clearing, but this
clearing may not happen instantly. Akamai's API allows for checking of
purge statuses. This module keeps track of purge statuses so you can monitor
them from within the Drupal admin UI.

You can view purge status tracking at `admin/config/akamai/list`. Purges not
yet complete will have a status of 'In-Progress', while completed purges will
have a status of 'Done'.

Purge status logs are deleted after 2 weeks. You can configure this expiry
via the module's config form.

## Testing and development

Akamai provides a Mock API endpoint at
http://private-250a0-akamaiopen2purgeccuproduction.apiary-mock.com/ccu/v2

You can enable development mode in the config form to use this instead of a
live URL.
