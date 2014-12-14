<?php

/**
 * This file is part of RawContainer library.
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
 * @package   RawPHP\RawContainer
 * @author    Taylor Otwell <taylorotwell@gmail.com>
 * @author    Tom Kaczocha <tom@crazydev.org>
 * @copyright 2014 Tom Kaczocha
 * @license   http://rawphp.org/licenses/mpl.txt MPL
 * @link      http://rawphp.org/
 */

namespace RawPHP\RawContainer;

use Closure;
use RawPHP\RawContainer\Contract\IBindingsBuilder;
use RawPHP\RawContainer\Contract\IContainer;

/**
 * Class BindingsBuilder
 *
 * @package RawPHP\RawContainer
 */
class BindingsBuilder implements IBindingsBuilder
{
    /** @var IContainer */
    protected $container;
    /** @var string */
    protected $concrete;
    /** @var string */
    protected $needs;

    /**
     * @param IContainer $container
     * @param string     $concrete
     */
    public function __construct( IContainer $container, $concrete )
    {
        $this->container = $container;
        $this->concrete  = $concrete;
    }

    /**
     * Define the target that depends on the context.
     *
     * @param  string $abstract
     *
     * @return IBindingsBuilder
     */
    public function needs( $abstract )
    {
        $this->needs = $abstract;

        return $this;
    }

    /**
     * Define the implementation for the contextual binding.
     *
     * @param  Closure|string $implementation
     */
    public function give( $implementation )
    {
        $this->container->addContextualBindings( $this->concrete, $this->needs, $implementation );
    }
}
