<?php
/*
**	Rose\Ext\Cache
**
**	Copyright (c) 2020-2021, RedStar Technologies, All rights reserved.
**	https://rsthn.com/
**
**	THIS LIBRARY IS PROVIDED BY REDSTAR TECHNOLOGIES "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
**	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A 
**	PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL REDSTAR TECHNOLOGIES BE LIABLE FOR ANY
**	DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT 
**	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
**	OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, 
**	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
**	USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace Rose\Ext;

use Rose\Errors\Error;
use Rose\Errors\ArgumentError;

use Rose\IO\Path;
use Rose\IO\File;
use Rose\IO\Directory;
use Rose\DateTime;
use Rose\Configuration;
use Rose\Extensions;
use Rose\Expr;
use Rose\Map;
use Rose\Arry;

use Rose\Ext\Wind;

if (!Extensions::isInstalled('Wind'))
	return;

/*
**	Generic cache extension for Wind.
*/

class Cache
{
	public static $cacheDataFolder;
	public static $cacheTagsFolder;
	public static $cacheTTL;

	/*
	**	Initializes the cache class.
	*/
	public static function init()
	{
		self::$cacheDataFolder = 'volatile/cache.data';
		self::$cacheTagsFolder = 'volatile/cache.tags';

		self::$cacheTTL = 3600;

		if (!Path::exists(self::$cacheDataFolder))
			Directory::create(self::$cacheDataFolder, true);

		if (!Path::exists(self::$cacheTagsFolder))
			Directory::create(self::$cacheTagsFolder, true);
	}

	/*
	**	Returns true if a cache entry exists and is still valid.
	*/
	public static function valid (string $id, string $tag, int $ttl=0)
	{
		if ($ttl == 0) $ttl = self::$cacheTTL;

		$file = Path::append(self::$cacheDataFolder, $id);
		if (!Path::exists($file)) return false;

		$ctag = Path::append(self::$cacheTagsFolder, $id);
		if (!Path::exists($ctag)) return false;

		if (File::getContents($ctag) != $tag || (new DateTime())->sub(File::mtime($file)) > $ttl)
			return false;

		return true;
	}

	/*
	**	Sets the modified-time of a cache entry to the current time.
	*/
	public static function touch (string $id, string $tag)
	{
		$file = Path::append(self::$cacheDataFolder, $id);
		if (!Path::exists($file)) return;

		$ctag = Path::append(self::$cacheTagsFolder, $id);
		if (!Path::exists($ctag)) return;

		if (File::getContents($ctag) != $tag)
			File::setContents($ctag, $tag);

		File::touch($file);
	}

	/*
	**	Sets the value of a cache entry, if the entry is not valid it will be created with the value returned by the given getValue function.
	*/
	public static function put (string $id, string $tag, string $rawValue, int $ttl, object $getValue)
	{
		$file = Path::append(self::$cacheDataFolder, $id);
		$ctag = Path::append(self::$cacheTagsFolder, $id);

		if (self::valid($id, $tag, $ttl))
		{
			$value = File::getContents($file);

			if ($rawValue != true)
				$value = unserialize($value);

			if ($value !== false && $value !== null)
				return;
		}

		$value = $getValue();

		if ($rawValue != true)
			File::setContents($file, serialize($value));
		else
			File::setContents($file, (string)$value);

		File::setContents($ctag, $tag);
		File::touch($file);
	}

	/*
	**	Returns the value of a cache entry, if the entry is not valid it will be created with the value returned by the given getValue function.
	*/
	public static function get (string $id, string $tag, string $rawValue, int $ttl, object $getValue)
	{
		$file = Path::append(self::$cacheDataFolder, $id);
		$ctag = Path::append(self::$cacheTagsFolder, $id);

		if (self::valid($id, $tag, $ttl))
		{
			if ($rawValue == true)
			{
				$lastModified = filemtime($file);
				$etagFile = md5_file($ctag);

				$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false;
				$etagHeader = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false;

				header("Last-Modified: ".gmdate("D, d M Y H:i:s", $lastModified)." GMT");
				header("Etag: $etagFile");
				header('Cache-Control: public');
				//header('Expires: '.gmdate("D, d M Y H:i:s", $lastModified+86400*15)." GMT");

				if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModified || $etagHeader == $etagFile)
				{
					header("HTTP/1.1 304 Not Modified");
					exit;
				}
			}

			$value = File::getContents($file);

			if ($rawValue != true)
				$value = unserialize($value);

			if ($value !== false && $value !== null)
				return $value;
		}

		$value = $getValue();

		if ($rawValue != true)
			File::setContents($file, serialize($value));
		else
			File::setContents($file, $value);

		File::setContents($ctag, $tag);
		File::touch($file);

		return $value;
	}

	/*
	**	Returns the path to a cache entry, regardless if it exists or not.
	*/
	public static function path (string $id)
	{
		return Path::append(self::$cacheDataFolder, $id);
	}
};

/**
**	Returns the boolean indicating if the contents of a cache entry are valid.
**
**	cache::valid <id> <tag>
**	cache::valid <id> <tag> <ttl>
*/
Expr::register('_cache::valid', function($parts, $data)
{
	if ($parts->length == 4)
		return Cache::valid(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), (int)Expr::value($parts->get(3), $data));
	else
		return Cache::valid(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), 0);
});

/**
**	Updates the modified time and tag of a cache entry to prevent cache invalidation.
**
**	cache::touch <id> <tag>
*/
Expr::register('_cache::touch', function($parts, $data)
{
	return Cache::touch (Expr::value($parts->get(1)));
});

/**
**	Returns the contents of a cache entry given its id or creates it with the specified value.
**
**	cache::get <id> <tag> <value>
**	cache::get <id> <tag> <ttl> <value>
*/
Expr::register('_cache::get', function($parts, $data)
{
	if ($parts->length == 5)
		return Cache::get(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), false, (int)Expr::value($parts->get(3), $data), function() use (&$parts, &$data) { return Expr::value($parts->get(4), $data); });
	else
		return Cache::get(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), false, 0, function() use (&$parts, &$data) { return Expr::value($parts->get(3), $data); });
});

/**
**	Returns the raw contents of a cache entry given its id or creates it with the specified value.
**
**	cache::get:raw <id> <tag> <value>
**	cache::get:raw <id> <tag> <ttl> <value>
*/
Expr::register('_cache::get:raw', function($parts, $data)
{
	if ($parts->length == 5)
		return Cache::get(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), true, (int)Expr::value($parts->get(3), $data), function() use (&$parts, &$data) { return Expr::value($parts->get(4), $data); });
	else
		return Cache::get(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), true, 0, function() use (&$parts, &$data) { return Expr::value($parts->get(3), $data); });
});

/**
**	Sets the contents of a cache entry given its id or creates it with the specified value.
**
**	cache::put <id> <tag> <value>
**	cache::put <id> <tag> <ttl> <value>
*/
Expr::register('_cache::put', function($parts, $data)
{
	if ($parts->length == 5)
		Cache::put(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), false, (int)Expr::value($parts->get(3), $data), function() use (&$parts, &$data) { return Expr::value($parts->get(4), $data); });
	else
		Cache::put(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), false, 0, function() use (&$parts, &$data) { return Expr::value($parts->get(3), $data); });
});

/**
**	Sets the raw contents of a cache entry given its id or creates it with the specified value.
**
**	cache::put:raw <id> <tag> <value>
**	cache::put:raw <id> <tag> <ttl> <value>
*/
Expr::register('_cache::put:raw', function($parts, $data)
{
	if ($parts->length == 5)
		return Cache::put(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), true, (int)Expr::value($parts->get(3), $data), function() use (&$parts, &$data) { return Expr::value($parts->get(4), $data); });
	else
		return Cache::put(Expr::value($parts->get(1), $data), Expr::value($parts->get(2), $data), true, 0, function() use (&$parts, &$data) { return Expr::value($parts->get(3), $data); });
});

/**
**	Returns the path to a cache entry given its id.
**
**	cache::path <id>
*/
Expr::register('_cache::path', function($parts, $data)
{
	return Cache::path(Expr::value($parts->get(1), $data));
});

/**
**	Uses similar syntax as cache::get but does not actually use the cache, directly returns the value.
**
**	cache::pass <id> <tag> <value>
**	cache::pass <id> <tag> <ttl> <value>
*/
Expr::register('_cache::pass', function($parts, $data)
{
	if ($parts->length == 5)
		return Expr::value($parts->get(4), $data);
	else
		return Expr::value($parts->get(3), $data);
});

/*
**	Initialize cache class.
*/
Cache::init();
