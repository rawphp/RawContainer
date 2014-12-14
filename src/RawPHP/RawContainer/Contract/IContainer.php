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
use InvalidArgumentException;
use RawPHP\RawContainer\Exception\BindingResolutionException;

/**
 * Interface IContainer
 *
 * @package RawPHP\RawContainer\Contract
 */
interface IContainer
{
    /**
     * Define a contextual binding.
     *
     * @param  string $concrete
     *
     * @return IBindingsBuilder
     */
    public function when( $concrete );

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string $abstract
     *
     * @return bool
     */
    public function bound( $abstract );

    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param  string $abstract
     *
     * @return bool
     */
    public function resolved( $abstract );

    /**
     * Determine if a given string is an alias.
     *
     * @param  string $name
     *
     * @return bool
     */
    public function isAlias( $name );

    /**
     * Register a binding with the container.
     *
     * @param  string|array        $abstract
     * @param  Closure|string|null $concrete
     * @param  bool                $shared
     *
     * @return IContainer
     */
    public function bind( $abstract, $concrete = NULL, $shared = FALSE );

    /**
     * Add a contextual binding to the container.
     *
     * @param  string         $concrete
     * @param  string         $abstract
     * @param  Closure|string $implementation
     *
     * @return IContainer
     */
    public function addContextualBindings( $concrete, $abstract, $implementation );

    /**
     * Register a binding if it hasn't already been registered.
     *
     * @param  string              $abstract
     * @param  Closure|string|null $concrete
     * @param  bool                $shared
     *
     * @return IContainer
     */
    public function bindIf( $abstract, $concrete = NULL, $shared = FALSE );

    /**
     * Register a shared binding in the container.
     *
     * @param  string              $abstract
     * @param  Closure|string|null $concrete
     *
     * @return IContainer
     */
    public function singleton( $abstract, $concrete = NULL );

    /**
     * Wrap a Closure such that it is shared.
     *
     * @param  Closure $closure
     *
     * @return Closure
     */
    public function share( Closure $closure );

    /**
     * Bind a shared Closure into the container.
     *
     * @param  string  $abstract
     * @param  Closure $closure
     *
     * @return IContainer
     */
    public function bindShared( $abstract, Closure $closure );

    /**
     * "Extend" an abstract type in the container.
     *
     * @param  string  $abstract
     * @param  Closure $closure
     *
     * @return IContainer
     *
     * @throws InvalidArgumentException
     */
    public function extend( $abstract, Closure $closure );

    /**
     * Register an existing instance as shared in the container.
     *
     * @param  string $abstract
     * @param  mixed  $instance
     *
     * @return IContainer
     */
    public function instance( $abstract, $instance );

    /**
     * Alias a type to a different name.
     *
     * @param  string $abstract
     * @param  string $alias
     *
     * @return IContainer
     */
    public function alias( $abstract, $alias );

    /**
     * Bind a new callback to an abstract's rebind event.
     *
     * @param  string  $abstract
     * @param  Closure $callback
     *
     * @return mixed
     */
    public function rebinding( $abstract, Closure $callback );

    /**
     * Refresh an instance on the given target and method.
     *
     * @param  string $abstract
     * @param  mixed  $target
     * @param  string $method
     *
     * @return mixed
     */
    public function refresh( $abstract, $target, $method );

    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  callable|string $callback
     * @param  array           $parameters
     * @param  string|null     $defaultMethod
     *
     * @return mixed
     */
    public function call( $callback, array $parameters = [ ], $defaultMethod = NULL );

    /**
     * Resolve the given type from the container.
     *
     * @param  string $abstract
     * @param  array  $parameters
     *
     * @return mixed
     */
    public function make( $abstract, $parameters = [ ] );

    /**
     * Instantiate a concrete instance of the given type.
     *
     * @param  string $concrete
     * @param  array  $parameters
     *
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    public function build( $concrete, $parameters = [ ] );

    /**
     * Register a new resolving callback.
     *
     * @param  string  $abstract
     * @param  Closure $callback
     *
     * @return IContainer
     */
    public function resolving( $abstract, Closure $callback );

    /**
     * Register a new resolving callback for all types.
     *
     * @param  Closure $callback
     *
     * @return IContainer
     */
    public function resolvingAny( Closure $callback );

    /**
     * Determine if a given type is shared.
     *
     * @param  string $abstract
     *
     * @return bool
     */
    public function isShared( $abstract );

    /**
     * Get the container's bindings.
     *
     * @return array
     */
    public function getBindings();

    /**
     * Remove a resolved instance from the instance cache.
     *
     * @param  string $abstract
     *
     * @return IContainer
     */
    public function forgetInstance( $abstract );

    /**
     * Clear all of the instances from the container.
     *
     * @return IContainer
     */
    public function forgetInstances();

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return IContainer
     */
    public function flush();

    /**
     * Determine if a given offset exists.
     *
     * @param  string $key
     *
     * @return bool
     */
    public function offsetExists( $key );

    /**
     * Get the value at a given offset.
     *
     * @param  string $key
     *
     * @return mixed
     */
    public function offsetGet( $key );

    /**
     * Set the value at a given offset.
     *
     * @param  string $key
     * @param  mixed  $value
     *
     * @return IContainer
     */
    public function offsetSet( $key, $value );

    /**
     * Unset the value at a given offset.
     *
     * @param  string $key
     *
     * @return IContainer
     */
    public function offsetUnset( $key );

    /**
     * Set the globally available instance of the container.
     *
     * @return static
     */
    public static function getInstance();

    /**
     * Set the shared instance of the container.
     *
     * @param  IContainer $container
     *
     * @return IContainer
     */
    public static function setInstance( IContainer $container );
}
