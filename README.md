# Akamai

This module provides a Drupal 8 service to interact with the [Akamai Content
Control Utility](https://developer.akamai.com/api/purge/ccu/overview.html).

## Installation and configuration

Install the module as usual.

Go to /admin/config/akamai and enter your Akamai credentials.

## Testing and development

Akamai provides a Mock API endpoint at
http://private-250a0-akamaiopen2purgeccuproduction.apiary-mock.com/ccu/v2/queues/default

You can enable development mode in the config form to use this instead of a live URL.


## Akamai Credentials

Follow the instructions here to set up the client credentials.
https://developer.akamai.com/introduction/Prov_Creds.html

You will need admin access to the Luna control panel to create credentials.
