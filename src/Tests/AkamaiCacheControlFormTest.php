<?php

/**
 * @file
 * Simpletest test for Akamai cache control form clearing tests.
 */

namespace Drupal\akamai\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Test the Akamai Homepage Clearing.
 *
 * @description Test Akamai cache clearings of the site homepage.
 *
 * @group Akamai
 */
class AkamaiCacheControlFormTest extends WebTestBase {

  /**
   * User with admin rights.
   */
  protected $node;

  /**
   * User with admin rights.
   */
  protected $homepage;

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
    $this->config = \Drupal::configFactory()->getEditable('akamai.settings');
    $this->privilegedUser = $this->drupalCreateUser(array(
      'purge akamai cache',
      'administer akamai',
      'purge akamai cache',
    ));
    $this->drupalLogin($this->privilegedUser);
    $this->drupalCreateContentType(['type' => 'article']);
    $this->node = $this->drupalCreateNode(['type' => 'article']);
    $this->homepage = "/node/{$this->node->id()}";

    // Make node page default.
    $this->config('system.site')->set('page.front', $this->homepage)->save();
  }

  /**
   * Tests that manual Akamai Cache Clear page.
   */
  public function testUrlsPurging() {

    $edit['paths'] = 'node/1';
    $edit['domain_override'] = 'staging';
    $edit['action'] = 'invalidate';

    $this->drupalPostForm('admin/config/development/performance/akamai-cache-clear', $edit, t('Start Refreshing Content'));
    $this->assertText(t('Requested invalidate of the following URLs: /node/1'), t('node/1 URLs purged'));
  }

}
