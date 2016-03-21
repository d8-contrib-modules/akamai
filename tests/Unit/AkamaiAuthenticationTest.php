<?php

/**
 * @file
 * Contains Drupal\Tests\akamai\Unit\AkamaiClientTest.
 */

namespace Drupal\Tests\akamai\Unit;

use Drupal\akamai\AkamaiAuthentication;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\akamai\AkamaiAuthentication
 *
 * @group Akamai
 */
class AkamaiAuthenticationTest extends UnitTestCase {

  /**
   * Tests that we can authorise as in debug mode.
   *
   * @covers ::create
   */
  public function testSetDebugMode() {
    $config = $this->getDevelConfig();
    $auth = AkamaiAuthentication::create($this->getConfigFactoryStub(['akamai.settings' => $config]));
    $this->assertEquals($auth->getHost(), $config['mock_endpoint']);
  }

  /**
   * Tests that we can authorise when specifying authentication keys.
   *
   * @covers ::create
   * @covers ::getAuth
   */
  public function testSetupClient() {
    $config = $this->getLiveConfig();
    $auth = AkamaiAuthentication::create($this->getConfigFactoryStub(['akamai.settings' => $config]));
    $expected = $config;
    unset($expected['rest_api_url']);
    $this->assertEquals($expected, $auth->getAuth());
    $this->assertEquals(get_class($auth), 'Drupal\akamai\AkamaiAuthentication');
  }

  /**
   * Returns config for development mode.
   *
   * @return array
   *   An array of config values.
   */
  protected function getDevelConfig() {
    return [
      'devel_mode' => TRUE,
      'mock_endpoint' => 'example.com',
    ];
  }

  /**
   * Returns config for live mode.
   *
   * @return array
   *   An array of config values.
   */
  protected function getLiveConfig() {
    return [
      'rest_api_url' => 'example.com',
      'client_token' => 'test',
      'client_secret' => 'test',
      'access_token' => 'test',
    ];
  }

}
