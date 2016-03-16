# Akamai

[![Build Status](https://travis-ci.org/d8-contrib-modules/akamai.svg?branch=8.x-3.x)](https://travis-ci.org/d8-contrib-modules/akamai)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/d8-contrib-modules/akamai/badges/quality-score.png?b=8.x-3.x)](https://scrutinizer-ci.com/g/d8-contrib-modules/akamai/?branch=8.x-3.x)

This module provides a Drupal 8 service to interact with the [Akamai Content
Control Utility](https://developer.akamai.com/api/purge/ccu/overview.html).

While the service can be used by developers in isolation, most users should
install the [Purge](http://drupal.org/project/purge) module. Purge will take
care of invalidating caches automatically when content is updated.

It incorporates the [AkamaiOPEN-edgegrid-php](https://github.com/akamai-open/AkamaiOPEN-edgegrid-php) library.

Development is presently based on Github at https://github.com/d8-contrib-modules/akamai, with all changes synced to Drupal.org. This may change in the future.

## Installation and configuration

Install the module as usual.

### With Purge

Make sure `purge_ui` is enabled.

Go to `admin/config/development/performance/purge` and enable the Akamai Purger
in the list of Purger plugins.

Configure your Akamai credentials via the 'Config' dropdown in the Purge UI
interface.

### Without Purge

Go to `/admin/config/akamai` and enter your Akamai credentials.

Go to `/admin/config/akamai/cache-control` to clear URLs manually.

## Testing and development

Akamai provides a Mock API endpoint at
http://private-250a0-akamaiopen2purgeccuproduction.apiary-mock.com/ccu/v2/queues/default

You can enable development mode in the config form to use this instead of a live URL.

=======

## Akamai Credentials

Follow the instructions here to set up the client credentials.
https://developer.akamai.com/introduction/Prov_Creds.html

You will need admin access to the Luna control panel to create credentials.
