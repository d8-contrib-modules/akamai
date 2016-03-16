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
   * Node created.
   */
  protected $node;

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
    $this->drupalCreateContentType(['type' => 'article']);
    $this->node = $this->drupalCreateNode(['type' => 'article']);
  }

  /**
   * Tests manual purging via Akamai Cache Clear form.
   */
  public function testValidUrlPurging() {
    $edit['paths'] = 'node/1';
    $edit['domain_override'] = 'staging';
    $edit['action'] = 'invalidate';

    $this->drupalPostForm('admin/config/akamai/cache-clear', $edit, t('Start Refreshing Content'));
    $this->assertText(t('Requested invalidate of the following URLs: /node/1'), t('node/1 URLs purged'));
  }

  public function testInvalidUrlPurging() {
    $edit['paths'] = 'links';
    $edit['domain_override'] = 'staging';
    $edit['action'] = 'invalidate';

    $this->drupalPostForm('admin/config/akamai/cache-clear', $edit, t('Start Refreshing Content'));
    $this->assertText(t('Please enter at least one valid path for URL purging'), t('Invalid URL found'));
  }

  public function testExternalUrlPurging() {
    $edit['paths'] = 'https://www.google.com';
    $edit['domain_override'] = 'staging';
    $edit['action'] = 'invalidate';

    $this->drupalPostForm('admin/config/akamai/cache-clear', $edit, t('Start Refreshing Content'));
    $this->assertText(t('Please enter only relative paths, not full URLs'), t('External URL found '));
  }
}
