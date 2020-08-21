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
	public static $cacheFolder;
	public static $cacheTTL;

	/*
	**	Initializes the cache class.
	*/
	public static function init()
	{
		self::$cacheFolder = 'resources/.cache';
		self::$cacheTTL = 3600;
	}

	/*
	**	Returns true if a cache entry exists and is still valid.
	*/
	public static function valid (string $id, int $ttl=0)
	{
		if ($ttl == 0) $ttl = self::$cacheTTL;

		$id = Path::append(self::$cacheFolder, $id);
		if (!Path::exists($id)) return false;

		if ((new DateTime())->sub(File::mtime($id)) > $ttl)
			return false;

		return true;
	}

	/*
	**	Sets the modified-time of a cache entry to the current time.
	*/
	public static function touch (string $id)
	{
		$id = Path::append(self::$cacheFolder, $id);
		if (!Path::exists($id)) return;

		File::touch($id);
	}

	/*
	**	Returns the value of a cache entry, if the entry is not valid it will be created with the value returned by the given getValue function.
	*/
	public static function get (string $id, int $ttl, object $getValue)
	{
		$path = Path::append(self::$cacheFolder, $id);

		if (self::valid($id, $ttl))
		{
			$value = unserialize(File::getContents($path));

			if ($value !== false && $value !== null)
				return $value;
		}

		$value = $getValue();
		File::setContents($path, serialize($value));

		return $value;
	}
};

/**
**	Returns the boolean indicating if the contents of a cache entry are valid.
**
**	cache::valid <id>
**	cache::valid <id> <ttl>
*/
Expr::register('_cache::valid', function($parts, $data)
{
	if ($parts->length == 3)
		return Cache::valid(Expr::value($parts->get(1), $data), (int)Expr::value($parts->get(2), $data));
	else
		return Cache::valid(Expr::value($parts->get(1), $data), 0);
});

/**
**	Sets the modified time of a cache entry to the current time to prevent cache invalidation.
**
**	cache::touch <id>
*/
Expr::register('_cache::touch', function($parts, $data)
{
	return Cache::touch (Expr::value($parts->get(1)));
});

/**
**	Returns the contents of a cache entry given its id or creates it with the specified value.
**
**	cache::get <id> <value>
**	cache::get <id> <ttl> <value>
*/
Expr::register('_cache::get', function($parts, $data)
{
	if ($parts->length == 4)
		return Cache::get(Expr::value($parts->get(1), $data), (int)Expr::value($parts->get(2), $data), function() use (&$parts, &$data) { return Expr::value($parts->get(3), $data); });
	else
		return Cache::get(Expr::value($parts->get(1), $data), 0, function() use (&$parts, &$data) { return Expr::value($parts->get(2), $data); });
});

/**
**	Uses similar syntax as cache::get but does not actually use the cache, directly returns the value.
**
**	cache::pass <id> <value>
**	cache::pass <id> <ttl> <value>
*/
Expr::register('_cache::pass', function($parts, $data)
{
	if ($parts->length == 4)
		return Expr::value($parts->get(3), $data);
	else
		return Expr::value($parts->get(2), $data);
});

/*
**	Initialize cache class.
*/
Cache::init();
