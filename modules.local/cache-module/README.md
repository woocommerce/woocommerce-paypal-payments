# Cache Module

This module provides a [PSR compatible](https://github.com/php-fig/simple-cache) integration of the WordPress caching system.

## How to use it
`composer require inpsyde/cache-module`

Lets say, you class requires a PSR CacheInterface. In you build factory, you could do something like the following:

```
'app.foo' => function(ContainerInterface $container) : Foo {
    $provider = $container->get('cache.provider');
    $cache = $provider->cacheForKey('your-group-key');
    return new Foo($cache);
}
```

## The Provider

The provider comes with three methods:

`CacheProviderInterface::cacheForKey(string $key)`:

Returns an implementation for `wp_cache_get()` etc. `$key` acts
as the group.

`CacheProviderInterface::transientForKey(string $key)`:

Returns an implementation for `set_transient()` etc. `$key` acts
as a prefix for the cache keys.

`CacheProviderInterface::cacheOrTransientForKey(string $key)`:

There are cases, where you want the object cache, but you can't rely
on its present. While `set_transient()` falls back to the object cache,
where it is present, you might want to be more specific. This method
would allow you to get an interface back, which itself relies on
object cache (if that is possible) or returns the transient implementation.

