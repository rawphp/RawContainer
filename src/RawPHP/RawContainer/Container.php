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

use ArrayAccess;
use Closure;
use Exception;
use InvalidArgumentException;
use RawPHP\RawContainer\Contract\IBindingsBuilder;
use RawPHP\RawContainer\Contract\IContainer;
use RawPHP\RawContainer\Exception\BindingResolutionException;
use RawPHP\RawSupport\Util;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Class Container
 *
 * @package RawPHP\RawContainer
 */
class Container implements ArrayAccess, IContainer
{
    /** @var static */
    protected static $instance;
    /** @var array */
    protected $resolved = [ ];
    /** @var array */
    protected $bindings = [ ];
    /** @var array */
    protected $instances = [ ];
    /** @var array */
    protected $aliases = [ ];
    /** @var array */
    protected $buildStack = [ ];
    /** @var array */
    protected $contextual = [ ];
    /** @var array */
    protected $reboundCallbacks = [ ];
    /** @var array */
    protected $resolvingCallbacks = [ ];
    /** @var array */
    protected $globalResolvingCallbacks = [ ];
    /** @var array */
    protected $globalAfterResolvingCallbacks = [ ];

    /**
     * Define a contextual binding.
     *
     * @param  string $concrete
     *
     * @return IBindingsBuilder
     */
    public function when( $concrete )
    {
        return new BindingsBuilder( $this, $concrete );
    }

    /**
     * Determine if the given type has been bound.
     *
     * @param  string $abstract
     *
     * @return bool
     */
    public function bound( $abstract )
    {
        return isset( $this->bindings[ $abstract ] ) || isset( $this->instances[ $abstract ] );
    }

    /**
     * Determine if the given type has been resolved.
     *
     * @param  string $abstract
     *
     * @return bool
     */
    public function resolved( $abstract )
    {
        return isset( $this->resolved[ $abstract ] ) || isset( $this->instances[ $abstract ] );
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param  string $name
     *
     * @return bool
     */
    public function isAlias( $name )
    {
        return isset( $this->aliases[ $name ] );
    }

    /**
     * Register a binding with the container.
     *
     * @param  string|array        $abstract
     * @param  Closure|string|null $concrete
     * @param  bool                $shared
     *
     * @return IContainer
     */
    public function bind( $abstract, $concrete = NULL, $shared = FALSE )
    {
        if ( is_array( $abstract ) )
        {
            list( $abstract, $alias ) = $this->extractAlias( $abstract );

            $this->alias( $abstract, $alias );
        }

        $this->dropStaleInstances( $abstract );

        if ( is_null( $concrete ) )
        {
            $concrete = $abstract;
        }

        if ( !$concrete instanceof Closure )
        {
            $concrete = $this->getClosure( $abstract, $concrete );
        }

        $this->bindings[ $abstract ] = compact( 'concrete', 'shared' );

        if ( $this->resolved( $abstract ) )
        {
            $this->rebound( $abstract );
        }

        return $this;
    }

    /**
     * Add a contextual binding to the container.
     *
     * @param  string         $concrete
     * @param  string         $abstract
     * @param  Closure|string $implementation
     *
     * @return IContainer
     */
    public function addContextualBindings( $concrete, $abstract, $implementation )
    {
        $this->contextual[ $concrete ][ $abstract ] = $implementation;

        return $this;
    }

    /**
     * Register a binding if it hasn't already been registered.
     *
     * @param  string              $abstract
     * @param  Closure|string|null $concrete
     * @param  bool                $shared
     *
     * @return IContainer
     */
    public function bindIf( $abstract, $concrete = NULL, $shared = FALSE )
    {
        if ( !$this->bound( $abstract ) )
        {
            $this->bind( $abstract, $concrete, $shared );
        }

        return $this;
    }

    /**
     * Register a shared binding in the container.
     *
     * @param  string              $abstract
     * @param  Closure|string|null $concrete
     *
     * @return IContainer
     */
    public function singleton( $abstract, $concrete = NULL )
    {
        $this->bind( $abstract, $concrete, TRUE );

        return $this;
    }

    /**
     * Wrap a Closure such that it is shared.
     *
     * @param  Closure $closure
     *
     * @return Closure
     */
    public function share( Closure $closure )
    {
        return function ( $container ) use ( $closure )
        {
            static $object;

            if ( is_null( $object ) )
            {
                $object = $closure( $container );
            }

            return $object;
        };
    }

    /**
     * Bind a shared Closure into the container.
     *
     * @param  string  $abstract
     * @param  Closure $closure
     *
     * @return IContainer
     */
    public function bindShared( $abstract, Closure $closure )
    {
        $this->bind( $abstract, $this->share( $closure ), TRUE );

        return $this;
    }

    /**
     * "Extend" an type in the container.
     *
     * @param  string  $abstract
     * @param  Closure $closure
     *
     * @return IContainer
     *
     * @throws InvalidArgumentException
     */
    public function extend( $abstract, Closure $closure )
    {
        if ( !isset( $this->bindings[ $abstract ] ) )
        {
            throw new InvalidArgumentException( sprintf( 'Type %s is not bound', $abstract ) );
        }

        if ( isset( $this->instances[ $abstract ] ) )
        {
            $this->instances[ $abstract ] = $closure( $this->instances[ $abstract ], $this );

            $this->rebound( $abstract );
        }
        else
        {
            $extender = $this->getExtender( $abstract, $closure );

            $this->bind( $abstract, $extender, $this->isShared( $abstract ) );
        }

        return $this;
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param  string $abstract
     * @param  mixed  $instance
     *
     * @return IContainer
     */
    public function instance( $abstract, $instance )
    {
        if ( is_array( $abstract ) )
        {
            list( $abstract, $alias ) = $this->extractAlias( $abstract );

            $this->alias( $abstract, $alias );
        }

        unset( $this->aliases[ $abstract ] );

        $bound = $this->bound( $abstract );

        $this->instances[ $abstract ] = $instance;

        if ( $bound )
        {
            $this->rebound( $abstract );
        }

        return $this;
    }

    /**
     * Alias a type to a different name.
     *
     * @param  string $abstract
     * @param  string $alias
     *
     * @return IContainer
     */
    public function alias( $abstract, $alias )
    {
        $this->aliases[ $alias ] = $abstract;

        return $this;
    }

    /**
     * Bind a new callback to an abstract's rebind event.
     *
     * @param  string  $abstract
     * @param  Closure $callback
     *
     * @return mixed
     */
    public function rebinding( $abstract, Closure $callback )
    {
        $this->reboundCallbacks[ $abstract ][ ] = $callback;

        if ( $this->bound( $abstract ) )
        {
            return $this->make( $abstract );
        }

        return $this;
    }

    /**
     * Refresh an instance on the given target and method.
     *
     * @param  string $abstract
     * @param  mixed  $target
     * @param  string $method
     *
     * @return mixed
     */
    public function refresh( $abstract, $target, $method )
    {
        return $this->rebinding( $abstract, function ( $app, $instance ) use ( $target, $method )
        {
            $target->{$method}( $instance );
        }
        );
    }

    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  callable|string $callback
     * @param  array           $parameters
     * @param  string|null     $defaultMethod
     *
     * @return mixed
     */
    public function call( $callback, array $parameters = [ ], $defaultMethod = NULL )
    {
        if ( is_string( $callback ) )
        {
            return $this->callClass( $callback, $parameters, $defaultMethod );
        }

        $dependencies = $this->getMethodDependencies( $callback, $parameters );

        return call_user_func_array( $callback, $dependencies );
    }

    /**
     * Resolve the given type from the container.
     *
     * @param  string $abstract
     * @param  array  $parameters
     *
     * @return mixed
     */
    public function make( $abstract, $parameters = [ ] )
    {
        $abstract = $this->getAlias( $abstract );

        if ( isset( $this->instances[ $abstract ] ) )
        {
            return $this->instances[ $abstract ];
        }

        $concrete = $this->getConcrete( $abstract );

        if ( $this->isBuildable( $concrete, $abstract ) )
        {
            $object = $this->build( $concrete, $parameters );
        }
        else
        {
            $object = $this->make( $concrete, $parameters );
        }

        if ( $this->isShared( $abstract ) )
        {
            $this->instances[ $abstract ] = $object;
        }

        $this->fireResolvingCallbacks( $abstract, $object );

        $this->resolved[ $abstract ] = TRUE;

        return $object;
    }

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
    public function build( $concrete, $parameters = [ ] )
    {
        if ( $concrete instanceof Closure )
        {
            return $concrete( $this, $parameters );
        }

        $reflector = new ReflectionClass( $concrete );

        if ( !$reflector->isInstantiable() )
        {
            throw new BindingResolutionException( sprintf( 'Target %s is not instantiable.', $concrete ) );
        }

        $this->buildStack[ ] = $concrete;

        $constructor = $reflector->getConstructor();

        if ( is_null( $constructor ) )
        {
            array_pop( $this->buildStack );

            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        $parameters = $this->keyParametersByArgument( $dependencies, $parameters );

        $instances = $this->getDependencies( $dependencies, $parameters );

        array_pop( $this->buildStack );

        return $reflector->newInstance( $instances );
    }

    /**
     * Register a new resolving callback.
     *
     * @param  string  $abstract
     * @param  Closure $callback
     *
     * @return IContainer
     */
    public function resolving( $abstract, Closure $callback )
    {
        $this->resolvingCallbacks[ $abstract ][ ] = $callback;

        return $this;
    }

    /**
     * Register a new resolving callback for all types.
     *
     * @param  Closure $callback
     *
     * @return IContainer
     */
    public function resolvingAny( Closure $callback )
    {
        $this->globalResolvingCallbacks[ ] = $callback;

        return $this;
    }

    /**
     * Determine if a given type is shared.
     *
     * @param  string $abstract
     *
     * @return bool
     */
    public function isShared( $abstract )
    {
        if ( isset( $this->bindings[ $abstract ][ 'shared' ] ) )
        {
            $shared = $this->bindings[ $abstract ][ 'shared' ];
        }
        else
        {
            $shared = FALSE;
        }

        return isset( $this->instances[ $abstract ] ) || TRUE === $shared;
    }

    /**
     * Get the container's bindings.
     *
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Remove a resolved instance from the instance cache.
     *
     * @param  string $abstract
     *
     * @return IContainer
     */
    public function forgetInstance( $abstract )
    {
        unset( $this->instances[ $abstract ] );

        return $this;
    }

    /**
     * Clear all of the instances from the container.
     */
    public function forgetInstances()
    {
        $this->instances = [ ];

        return $this;
    }

    /**
     * Flush the container of all bindings and resolved instances.
     */
    public function flush()
    {
        $this->aliases   = [ ];
        $this->resolved  = [ ];
        $this->bindings  = [ ];
        $this->instances = [ ];
    }

    /**
     * Determine if a given offset exists.
     *
     * @param  string $key
     *
     * @return bool
     */
    public function offsetExists( $key )
    {
        return isset( $this->bindings[ $key ] );
    }

    /**
     * Get the value at a given offset.
     *
     * @param  string $key
     *
     * @return mixed
     */
    public function offsetGet( $key )
    {
        return $this->make( $key );
    }

    /**
     * Set the value at a given offset.
     *
     * @param  string $key
     * @param  mixed  $value
     *
     * @return IContainer
     */
    public function offsetSet( $key, $value )
    {
        if ( !$value instanceof Closure )
        {
            $value = function () use ( $value )
            {
                return $value;
            };
        }

        $this->bind( $key, $value );

        return $this;
    }

    /**
     * Unset the value at a given offset.
     *
     * @param  string $key
     *
     * @return IContainer
     */
    public function offsetUnset( $key )
    {
        unset( $this->bindings[ $key ], $this->instances[ $key ] );
    }

    /**
     * Set the globally available instance of the container.
     *
     * @return static
     */
    public static function getInstance()
    {
        return static::$instance;
    }

    /**
     * Set the shared instance of the container.
     *
     * @param  IContainer $container
     *
     * @return IContainer
     */
    public static function setInstance( IContainer $container )
    {
        static::$instance = $container;
    }

    /**
     * Extract the type and alias from a given definition.
     *
     * @param array $definition
     *
     * @return array
     */
    protected function extractAlias( array $definition )
    {
        return [ key( $definition ), current( $definition ) ];
    }

    /**
     * Drop all of the stale instances and aliases.
     *
     * @param $abstract
     */
    protected function dropStaleInstances( $abstract )
    {
        unset( $this->instances[ $abstract ], $this->aliases[ $abstract ] );
    }

    /**
     * Get a Closure to be used when building a type.
     *
     * @param string $abstract
     * @param string $concrete
     *
     * @return Closure
     */
    protected function getClosure( $abstract, $concrete )
    {
        return function ( $c, $parameters = [ ] ) use ( $abstract, $concrete )
        {
            $method = ( $abstract == $concrete ) ? 'build' : 'make';

            return $c->$method( $concrete, $parameters );
        };
    }

    /**
     * Fire the 'rebound' callbacks for the given abstract type.
     *
     * @param string $abstract
     */
    protected function rebound( $abstract )
    {
        $instance = $this->make( $abstract );

        foreach ( $this->getReboundCallbacks( $abstract ) as $callback )
        {
            call_user_func( $callback, $this, $instance );
        }
    }

    /**
     * Get the rebound callbacks for a given type.
     *
     * @param string $abstract
     *
     * @return array
     */
    protected function getReboundCallbacks( $abstract )
    {
        if ( isset( $this->reboundCallbacks[ $abstract ] ) )
        {
            return $this->reboundCallbacks[ $abstract ];
        }

        return [ ];
    }

    /**
     * Get an extender Closure for resolving a type.
     *
     * @param string   $abstract
     * @param callable $closure
     *
     * @return Closure
     */
    protected function getExtender( $abstract, Closure $closure )
    {
        $resolver = $this->bindings[ $abstract ][ 'concrete' ];

        return function ( $container ) use ( $resolver, $closure )
        {
            return $closure( $resolver( $container ), $container );
        };
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param string $target
     * @param array  $parameters
     * @param string $defaultMethod
     *
     * @return mixed
     */
    protected function callClass( $target, array $parameters = [ ], $defaultMethod = NULL )
    {
        $segments = explode( '@', $target );

        $method = count( $segments ) === 2 ? $segments[ 1 ] : $defaultMethod;

        if ( is_null( $method ) )
        {
            throw new InvalidArgumentException( 'Method not provided.' );
        }

        return $this->call( [ $this->make( $segments[ 0 ] ), $method ], $parameters );
    }

    /**
     * Get all dependencies for a given call method.
     *
     * @param mixed $callback
     * @param array $parameters
     *
     * @return array
     */
    protected function getMethodDependencies( $callback, $parameters = [ ] )
    {
        $dependencies = [ ];

        foreach ( $this->getCallReflector( $callback )->getParameters() as $key => $parameter )
        {
            $this->addDependencyForCallParameter( $parameter, $parameters, $dependencies );
        }

        return array_merge( $dependencies, $parameters );
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param mixed $callback
     *
     * @return ReflectionFunction|ReflectionMethod
     */
    protected function getCallReflector( $callback )
    {
        if ( is_array( $callback ) )
        {
            return new ReflectionMethod( $callback[ 0 ], $callback[ 1 ] );
        }

        return new ReflectionFunction( $callback );
    }

    /**
     * Get the dependency for the given call parameter.
     *
     * @param ReflectionParameter $parameter
     * @param array               $parameters
     * @param array               $dependencies
     */
    protected function addDependencyForCallParameter( ReflectionParameter $parameter, array &$parameters, array &$dependencies )
    {
        if ( array_key_exists( $parameter->name, $parameters ) )
        {
            $dependencies[ ] = $parameters[ $parameter->name ];
        }
        elseif ( $parameter->getClass() )
        {
            $dependencies[ ] = $this->make( $parameter->getClass()->name );
        }
        elseif ( $parameter->isDefaultValueAvailable() )
        {
            $dependencies[ ] = $parameter->getDefaultValue();
        }
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param string $abstract
     *
     * @return string
     */
    protected function getAlias( $abstract )
    {
        return isset( $this->aliases[ $abstract ] ) ? $this->aliases[ $abstract ] : $abstract;
    }

    /**
     * Get the concrete type for a given abstract.
     *
     * @param $abstract
     *
     * @return string
     */
    protected function getConcrete( $abstract )
    {
        if ( !is_null( $concrete = $this->getContextualConcrete( $abstract ) ) )
        {
            return $concrete;
        }

        if ( !isset( $this->bindings[ $abstract ] ) )
        {
            if ( $this->missingLeadingSlash( $abstract ) && isset( $this->bindings[ '\\' . $abstract ] ) )
            {
                $abstract = '\\' . $abstract;
            }

            return $abstract;
        }

        return $this->bindings[ $abstract ][ 'concrete' ];
    }

    /**
     * Get the contextual concrete binding for the given abstract.
     *
     * @param string $abstract
     *
     * @return string
     */
    public function getContextualConcrete( $abstract )
    {
        if ( isset( $this->contextual[ Util::last( $this->buildStack ) ][ $abstract ] ) )
        {
            return $this->contextual[ Util::last( $this->buildStack ) ][ $abstract ];
        }

        return '';
    }

    /**
     * Determine if the given abstract has a leading slash.
     *
     * @param string $abstract
     *
     * @return bool
     */
    protected function missingLeadingSlash( $abstract )
    {
        return is_string( $abstract ) && strpos( $abstract, '\\' ) !== 0;
    }

    /**
     * Determine if the given concrete is buildable.
     *
     * @param mixed  $concrete
     * @param string $abstract
     *
     * @return bool
     */
    protected function isBuildable( $concrete, $abstract )
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * Fire all of the resolving callbacks.
     *
     * @param string $abstract
     * @param mixed  $object
     */
    protected function fireResolvingCallbacks( $abstract, $object )
    {
        if ( isset( $this->resolvingCallbacks[ $abstract ] ) )
        {
            $this->fireCallbackArray( $object, $this->resolvingCallbacks[ $abstract ] );
        }

        $this->fireCallbackArray( $object, $this->globalResolvingCallbacks );

        $this->fireCallbackArray( $object, $this->globalAfterResolvingCallbacks );
    }

    /**
     * Fire an array of callbacks with an object.
     *
     * @param mixed $object
     * @param array $callbacks
     */
    protected function fireCallbackArray( $object, array $callbacks )
    {
        foreach ( $callbacks as $callback )
        {
            $callback( $object, $this );
        }
    }

    /**
     * If extra parameters are passed by numeric ID, rekey them by argument name.
     *
     * @param array $dependencies
     * @param array $parameters
     *
     * @return array
     */
    protected function keyParametersByArgument( array $dependencies, array $parameters )
    {
        foreach ( $parameters as $key => $value )
        {
            unset( $parameters[ $key ] );

            $parameters[ $dependencies[ $key ]->name ] = $value;
        }

        return $parameters;
    }

    /**
     * Resolve all the dependencies from the ReflectionParameters.
     *
     * @param array $parameters
     * @param array $primitives
     *
     * @return array
     *
     * @throws BindingResolutionException
     * @throws Exception
     */
    protected function getDependencies( array $parameters, array $primitives = [ ] )
    {
        $dependencies = [ ];

        /** @var ReflectionParameter $parameter */
        foreach ( $parameters as $parameter )
        {
            $dependency = $parameter->getClass();

            if ( array_key_exists( $parameter->name, $primitives ) )
            {
                $dependencies[ ] = $primitives[ $parameter->name ];
            }
            elseif ( is_null( $dependency ) )
            {
                $dependencies[ ] = $this->resolveNonClass( $parameter );
            }
            else
            {
                $dependencies[ ] = $this->resolveClass( $parameter );
            }
        }

        return ( array ) $dependencies;
    }

    /**
     * Resolve a non-class hinted dependency.
     *
     * @param ReflectionParameter $parameter
     *
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    protected function resolveNonClass( ReflectionParameter $parameter )
    {
        if ( $parameter->isDefaultValueAvailable() )
        {
            return $parameter->getDefaultValue();
        }

        throw new BindingResolutionException( sprintf( 'Unresolvable dependency resolving %s in class %s.',
                                                       $parameter->name, $parameter->getDeclaringClass()->name
                                              )
        );
    }

    /**
     * Resolve a class based dependency from the container.
     *
     * @param ReflectionParameter $parameter
     *
     * @return mixed
     *
     * @throws BindingResolutionException
     * @throws Exception
     */
    protected function resolveClass( ReflectionParameter $parameter )
    {
        try
        {
            return $this->make( $parameter->getClass()->name );
        }
        catch ( BindingResolutionException $e )
        {
            if ( $parameter->isOptional() )
            {
                return $parameter->getDefaultValue();
            }

            throw $e;
        }
    }
}
