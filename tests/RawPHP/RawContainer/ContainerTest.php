<?php

/**
 * This file is part of Step in Deals application.
 *
 * Copyright (c) 2014 Tom Kaczocha
 *
 * This Source Code is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, you can obtain one at http://mozilla.org/MPL/2.0/.
 *
 * PHP version 5.4
 *
 * @category  PHP
 * @package   RawPHP\RawContainer\Tests
 * @author    Tom Kaczocha <tom@crazydev.org>
 * @copyright 2014 Tom Kaczocha
 * @license   http://crazydev.org/licenses/mpl.txt MPL
 * @link      http://crazydev.org/
 */

namespace RawPHP\RawContainer\Tests;

use PHPUnit_Framework_TestCase;
use RawPHP\RawContainer\Container;
use RawPHP\RawContainer\Contract\IContainer;
use RawPHP\RawDateTime\DateTime;

/**
 * Class ContainerTests
 *
 * @package RawPHP\RawContainer\Tests
 */
class ContainerTest extends PHPUnit_Framework_TestCase
{
    /** @var  IContainer */
    protected $container;

    /**
     * Setup before each test.
     */
    public function setUp()
    {
        $this->container = new Container();
    }

    /**
     * Test container is valid.
     */
    public function testContainerInstantiated()
    {
        $this->assertNotNull( $this->container );
    }

    /**
     * Test binding an item.
     */
    public function testBindItem()
    {
        $this->container->bind( 'RawPHP\RawDateTime\Contract\IDateTime', function ()
        {
            return new DateTime();
        }
        );

        $item1 = $this->container->make( 'RawPHP\RawDateTime\Contract\IDateTime' );

        $this->assertNotNull( $item1 );
        $this->assertInstanceOf( 'RawPHP\RawDateTime\Contract\IDateTime', $item1 );
        $this->assertInstanceOf( 'RawPHP\RawDateTime\DateTime', $item1 );

        $item2 = $this->container->make( 'RawPHP\RawDateTime\Contract\IDateTime' );

        $this->assertNotSame( $item1, $item2 );
    }

    /**
     * Test binding a shared object.
     */
    public function testBindSharedItem()
    {
        $this->container->bindShared( 'RawPHP\RawDateTime\Contract\IDateTime', function ()
        {
            return new DateTime();
        }
        );

        $item1 = $this->container->make( 'RawPHP\RawDateTime\Contract\IDateTime' );

        $this->assertNotNull( $item1 );
        $this->assertInstanceOf( 'RawPHP\RawDateTime\Contract\IDateTime', $item1 );
        $this->assertInstanceOf( 'RawPHP\RawDateTime\DateTime', $item1 );

        $item2 = $this->container->make( 'RawPHP\RawDateTime\Contract\IDateTime' );

        $this->assertSame( $item1, $item2 );
    }

    /**
     * Test set alias.
     */
    public function testAliasItem()
    {
        $this->container->bindShared( 'RawPHP\RawDateTime\Contract\IDateTime', function ()
        {
            return new DateTime();
        }
        );

        $this->container->alias( 'RawPHP\RawDateTime\Contract\IDateTime', 'date' );

        $item = $this->container->make( 'date' );

        $this->assertNotNull( $item );
        $this->assertInstanceOf( 'RawPHP\RawDateTime\Contract\IDateTime', $item );
        $this->assertInstanceOf( 'RawPHP\RawDateTime\DateTime', $item );
    }
}
