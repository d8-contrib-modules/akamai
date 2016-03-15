<?php

/**
 * @file
 * Simpletest test for Akamai cache control form clearing tests.
 */

namespace Drupal\akamai\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Test the Akamai Config Form.
 *
 * @group Akamai
 */
class AkamaiConfigFormTest extends WebTestBase {

  /**
   * User with admin rights.
   */
  protected $privilegedUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system_test', 'node', 'user', 'akamai'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->config = \Drupal::configFactory()->getEditable('sharethis.settings');
    // Create and log in our privileged user.
    $this->privilegedUser = $this->drupalCreateUser(array(
      'purge akamai cache',
      'administer akamai',
      'purge akamai cache',
    ));
    $this->drupalLogin($this->privilegedUser);
  }

  /**
   * Tests that Akamai Configuration Form.
   */
  public function testConfigForm() {
    $edit['basepath'] = 'node/1';
    $edit['timeout'] = 20;
    $edit['domain'] = 'staging';
    $edit['action'] = 'invalidate';
    $edit['devel_mode'] = 1;
    $edit['mock_endpoint'] = 'http://private-250a0-akamaiopen2purgeccuproduction.apiary-mock.com/ccu/v2/queues/default';

    $this->drupalPostForm('admin/config/akamai', $edit, t('Save configuration'));
    $this->assertText(t('Authenticated to Akamai.'), t('Authenticated to Akamai.'));
  }

}
