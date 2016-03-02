<?php

/**
 * @file
 * Simpletest tests for Akamai Homepage cache clearing tests.
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
class AkamaiHomepageTest extends WebTestBase {

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
  public static $modules = ['system_test', 'block', 'node', 'akamai'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Create and log in our privileged user.
    $this->privilegedUser = $this->drupalCreateUser(array(
      'administer blocks',
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
   * Tests clear of homepage and rendering of block
   */
  public function testHomepageClear() {
    // Test availability of the twitter block in the admin "Place blocks" list.
    \Drupal::service('theme_handler')->install(['bartik', 'seven', 'stark']);
    $theme_settings = $this->config('system.theme');
    foreach (['bartik', 'seven', 'stark'] as $theme) {
      $this->drupalGet('admin/structure/block/list/' . $theme);
      $this->assertTitle(t('Block layout') . ' | Drupal');
      // Configure and save the block.
      $this->drupalPlaceBlock('akamai_cache_clear_block', array(
        'region' => 'content',
        'theme' => $theme,
      ));
      // Set the default theme and ensure the block is placed.
      $theme_settings->set('default', $theme)->save();
      $this->drupalGet($this->homepage);
      $this->assertText($this->homepage, 'The Akamai path field is set correctly');
    }
  }

}