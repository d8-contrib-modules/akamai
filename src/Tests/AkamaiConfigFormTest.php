<?php

/**
 * @file
 * Simpletest test for Akamai cache control form clearing tests.
 */

namespace Drupal\akamai\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\Url;

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
    $edit['basepath'] = 'http://www.example.com';
    $edit['timeout'] = 20;
    $edit['domain'] = 'staging';
    $edit['action'] = 'invalidate';
    $edit['devel_mode'] = 1;
    $edit['mock_endpoint'] = 'http://private-250a0-akamaiopen2purgeccuproduction.apiary-mock.com';

    $this->drupalPostForm('admin/config/akamai/config', $edit, t('Save configuration'));
    $this->assertText(t('Authenticated to Akamai.'), t('Authenticated to Akamai.'));

    // Tests that we can't save non-integer status expire periods.
    $edit['status_expire'] = 'lol';
    $this->drupalPostForm(Url::fromRoute('akamai.settings')->getInternalPath(), $edit, t('Save configuration'));
    $this->assertText(t('Please enter only integer values in this field.'), 'Allowed only integer expiry values');
    $edit['status_expire'] = 1;

    // Tests that we can't save non-integer timeouts.
    $edit['timeout'] = 'lol';
    $this->drupalPostForm(Url::fromRoute('akamai.settings')->getInternalPath(), $edit, t('Save configuration'));
    $this->assertText(t('Please enter only integer values in this field.'), 'Allowed only integer timeout values');
  }

}
