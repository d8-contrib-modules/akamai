<?php

/**
 * @file
 * Contains Drupal\Tests\akamai\Unit\AkamaiClientTest.
 */

namespace Drupal\Tests\akamai\Unit;

use Drupal\akamai\AkamaiClient;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\akamai\AkamaiClient
 *
 * @group Akamai
 */
class AkamaiClientTest extends UnitTestCase {

  /**
   * Creates a client to test.
   *
   * @param array $config
   *   An array of client configuration.
   *
   * @return \Drupal\akamai\AkamaiClient
   *   An AkamaiClient to test.
   */
  protected function getClient(array $config = []) {
    // Ensure some sane defaults.
    $config = $config + [
      'domain' => [
        'production' => TRUE,
        'staging' => FALSE,
      ],
      'action' => [
        'remove' => TRUE,
        'invalidate' => FALSE,
      ],
    ];
    $logger = $this->prophesize(LoggerInterface::class)->reveal();
    return new AkamaiClient($this->getConfigFactoryStub(['akamai.settings' => $config]), $logger);
  }

  /**
   * Tests the setting of a queue.
   *
   * @covers ::setQueue
   */
  public function testSetQueue() {
    $akamai_client = $this->getClient();
    $akamai_client->setQueue('test_queue');
    $this->assertAttributeEquals('test_queue', 'queue', $akamai_client);
  }

  /**
   * Tests the setting of a asset type to clear.
   *
   * @covers ::setType
   */
  public function testSetType() {
    $akamai_client = $this->getClient();
    $akamai_client->setType('cpcode');
    $this->assertAttributeEquals('cpcode', 'type', $akamai_client);
  }

  /**
   * Tests exception on incorrect asset type set.
   *
   * @covers ::setType
   */
  public function testSetTypeException() {
    $this->setExpectedException(\InvalidArgumentException::class, 'Type must be one of: cpcode, arl');
    $akamai_client = $this->getClient();
    $akamai_client->setType('wrong');
    $this->assertAttributeEquals('arl', 'type', $akamai_client);
  }

  /**
   * Tests setting of a purge action type.
   *
   * @covers ::setAction
   */
  public function testSetAction() {
    $akamai_client = $this->getClient();
    $akamai_client->setAction('invalidate');
    $this->assertAttributeEquals('invalidate', 'action', $akamai_client);
  }

  /**
   * Tests exception on incorrect action type set.
   *
   * @covers ::setAction
   */
  public function testSetActionException() {
    $this->setExpectedException(\InvalidArgumentException::class, 'Action must be one of: remove, invalidate');
    $akamai_client = $this->getClient();
    $akamai_client->setAction('wrong');
    $this->assertAttributeEquals('production', 'action', $akamai_client);
  }

  /**
   * Tests setting of a clearing domain.
   *
   * @covers ::setDomain
   */
  public function testSetDomain() {
    $akamai_client = $this->getClient();
    $akamai_client->setDomain('staging');
    $this->assertAttributeEquals('staging', 'domain', $akamai_client);
  }

  /**
   * Tests exception when setting invalid domain.
   *
   * @covers ::setDomain
   */
  public function testSetDomainException() {
    $this->setExpectedException(\InvalidArgumentException::class, 'Domain must be one of: staging, production');
    $akamai_client = $this->getClient();
    $akamai_client->setDomain('wrong');
    $this->assertAttributeEquals('production', 'domain', $akamai_client);
  }

}
