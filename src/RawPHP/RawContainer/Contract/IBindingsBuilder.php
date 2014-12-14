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
 * @package   RawPHP\RawContainer\Contract
 * @author    Taylor Otwell <taylorotwell@gmail.com>
 * @author    Tom Kaczocha <tom@crazydev.org>
 * @copyright 2014 Tom Kaczocha
 * @license   http://rawphp.org/licenses/mpl.txt MPL
 * @link      http://rawphp.org/
 */

namespace RawPHP\RawContainer\Contract;

use Closure;

/**
 * Interface IBindingsBuilder
 *
 * @package RawPHP\RawContainer\Contract
 */
interface IBindingsBuilder
{
    /**
     * Define the abstract target that depends on the context.
     *
     * @param  string $abstract
     *
     * @return IBindingsBuilder
     */
    public function needs( $abstract );

    /**
     * Define the implementation for the contextual binding.
     *
     * @param  Closure|string $implementation
     */
    public function give( $implementation );
}
