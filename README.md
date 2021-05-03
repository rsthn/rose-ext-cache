# Cache Extension

This extension adds cache features to [Wind](https://github.com/rsthn/rose-ext-wind).

> **NOTE:** The extension detects the presence of Wind, when not installed, this extension will simply not be loaded.

Cache entries can contain any type of object, each entry is identified by an `id` which is just a string identifier used as a filename. Cache entries are stored in the `resources/.cache` directory.

The default TTL (time-to-live) of the cache entries is 3600 seconds, equivalent to 1 hour.


# Installation

```sh
composer require rsthn/rose-ext-cache
```


## Expression Functions

### `cache::valid` id:string tag:string [ttl:integer]

Determines if the cache entry identified by `id` is valid for the specified TTL (in seconds). Note that if no TTL is specified the default will be used.

### `cache::touch` id:string tag:string 

Sets the modified time of a cache entry identified by `id` to the current time to prevent cache invalidation.

### `cache::get` id:string tag:string [ttl:integer] value:object

Returns the contents of a cache entry given its `id` or creates it with the specified value if the entry is no longer valid or does not exist.

### `cache::path` id:string

Returns the path to a cache entry given its id, regardless if it exists or is valid or not.

### `cache::pass` id:string tag:string [ttl:integer] value:object

Uses similar syntax as `cache::get` but does not actually use the cache, it simply directly returns the value. Used as a quick way to bypass cache to run tests while maintaining the `cache::get` syntax.
