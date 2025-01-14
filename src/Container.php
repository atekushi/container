<?php

namespace Atekushi\Container;

use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    /**
     * An array to store service bindings.
     *
     * @var array $bindings
     */
    protected array $bindings = [];

    /**
     * Retrieves a service by its identifier from the container.
     *
     * If the service is bound as a callable, it will resolve it by invoking the callable.
     * Otherwise, it will resolve the service via the resolve method.
     *
     * @param string $id The identifier of the service.
     *
     * @return object The resolved service.
     */
    public function get(string $id): object
    {
        if ($this->has($id)) {
            $resolver = $this->bindings[$id];

            if (is_callable($resolver)) {
                return $resolver();
            }

            /**
             * If the resolver is not callable, then it's must be another service ID.
             * Recursively fetch the service associated with this ID.
             * Example:
             * If 'A' depends on 'B', and 'B' depends on 'C':
             * - get('A') will first call get('B')
             * - get('B') will call get('C') if it's not callable
             */
            return $this->get($resolver);
        }

        return $this->resolve($id);
    }

    /**
     * Checks if a service is bound in the container.
     *
     * @param string $id The identifier of the service.
     *
     * @return bool Returns true if the service is bound, false otherwise.
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
     * Resolves a service by its identifier.
     *
     * This method should contain the logic for resolving the service
     * when it is not found in the bindings. It could, for example,
     * involve instantiating a class or looking up other sources.
     *
     * @param string $id The identifier of the service.
     *
     * @return object The resolved service.
     */
    public function resolve(string $id): object
    {
        // Todo: Add service resolving implementation
    }

    /**
     * Binds a service or class to the container.
     *
     * @param string $id The identifier for the service.
     * @param callable|string $implementation The service or class (can be callable or class name).
     * @return void
     */
    public function set(string $id, callable|string $implementation): void
    {
        $this->bindings[$id] = $implementation;
    }
}