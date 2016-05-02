<?php

namespace Eccube\Tests\Repository;

use Eccube\Application;
use Eccube\Tests\EccubeTestCase;

/**
 * Class PointCustomerRepositoryTest
 *
 * @package Eccube\Tests\Repository
 */
class PointCustomerRepositoryTest extends EccubeTestCase
{
    public function testSavePoint(){
        $Customer = $this->createCustomer();
        $PointCustomer = $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(100, $Customer);
        $this->expected = 100;
        $this->actual = $PointCustomer->getPlgPointCurrent();
        $this->verify();
    }

    public function testGetLastPointById(){
        $Customer = $this->createCustomer();
        $PointCustomer = $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(101, $Customer);
        $point = $this->app['eccube.plugin.point.repository.pointcustomer']->getLastPointById($Customer->getId());
        $this->expected = 101;
        $this->actual = $point;
        $this->verify();
    }



}

