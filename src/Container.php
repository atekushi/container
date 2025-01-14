<?php

namespace Atekushi\Container;

use Atekushi\Container\Exceptions\ContainerException;
use Atekushi\Container\Exceptions\NotFoundException;
use Atekushi\Singleton\Singleton;
use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;

class Container extends Singleton implements ContainerInterface
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
     *
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
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
     * @throws NotFoundException
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function resolve(string $id): object
    {
        try {
            $reflector = new ReflectionClass($id);
        } catch (Throwable $e) {
            throw new NotFoundException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException('Class "' . $id . '" is not instantiable');
        }

        $constructor = $reflector->getConstructor();

        if (!$constructor) {
            return $this->set($id, $this->createResolverClosure($id))->get($id);
        }

        $parameters = $constructor->getParameters();

        if (!$parameters) {
            return $this->set($id, $this->createResolverClosure($id))->get($id);
        }

        $dependencies = $this->resolveDependencies($id, $parameters);

        return $this->set($id, $this->createResolverClosure($id, $dependencies))->get($id);
    }

    /**
     * Binds a service or class to the container.
     *
     * @param string $id The identifier for the service.
     * @param callable|string $implementation The service or class (can be callable or class name).
     * @return self
     */
    public function set(string $id, callable|string $implementation): self
    {
        $this->bindings[$id] = $implementation;

        return $this;
    }

    /**
     * Creates a resolver closure for instantiating a class with its dependencies.
     *
     * @param string $class_name The fully-qualified class name to resolve.
     * @param array $dependencies An array of dependencies to be passed to the class constructor.
     *
     * @return Closure A closure that resolves the class instance with the provided dependencies.
     */
    protected function createResolverClosure(string $class_name, array $dependencies = []): Closure
    {
        if (Singleton::isSingleton($class_name)) {
            return fn() => call_user_func([$class_name, 'getInstance'], $dependencies);
        } else {
            return function () use ($class_name, $dependencies) {
                $reflection = new ReflectionClass($class_name);
                return $reflection->newInstanceArgs($dependencies);
            };
        }
    }

    /**
     * Resolve class dependency
     *
     * @param string $id
     * @param array $parameters
     * @return array
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function resolveDependencies(string $id, array $parameters): array
    {
        return array_map(
            function (ReflectionParameter $param) use ($id) {
                $name = $param->getName();
                $type = $param->getType();

                if (!$type) {
                    throw new ContainerException("Can't resolve parameter '$name' on class '$id' because it don't have any types ",);
                }

                if ($type instanceof ReflectionUnionType) {
                    throw new ContainerException("Can't resolve parameter '$name' on class '$id' because parameter have multiple types ",);
                }

                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    return $this->get($type->getName());
                }

                throw new ContainerException("Failed to resolve class '$id' because invalid param '$name'");
            },
            $parameters
        );
    }
}