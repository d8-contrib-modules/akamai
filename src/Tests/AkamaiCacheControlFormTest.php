<?php

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

    $edit['basepath'] = 'http://www.example.com';
    $this->drupalPostForm('admin/config/akamai/config', $edit, t('Save configuration'));
  }

  /**
   * Tests manual purging via Akamai Cache Clear form.
   */
  public function testValidUrlPurging() {
    $edit['paths'] = 'node/1';
    $edit['domain_override'] = 'staging';
    $edit['action'] = 'invalidate';
    $this->drupalPostForm('admin/config/akamai/cache-clear', $edit, t('Start Refreshing Content'));
    $this->assertText(t('Requested invalidate of the following URLs: /node/1'), 'Valid URL purged correctly.');

    $edit['paths'] = 'links';
    $edit['domain_override'] = 'staging';
    $edit['action'] = 'invalidate';
    $this->drupalPostForm('admin/config/akamai/cache-clear', $edit, t('Start Refreshing Content'));
    $this->assertText(t('Please provide at least one valid URL for purging.'), 'Invalid URL rejected.');

    $edit['paths'] = 'https://www.google.com';
    $edit['domain_override'] = 'staging';
    $edit['action'] = 'invalidate';
    $this->drupalPostForm('admin/config/akamai/cache-clear', $edit, t('Start Refreshing Content'));
    $this->assertText(t('Please enter only relative paths, not full URLs'), 'External URL rejected.');
  }

}
