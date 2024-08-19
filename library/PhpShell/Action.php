<?php

class PhpShell_Action extends Basic_Action
{
	public $encoding = 'UTF-8';
	public $title = 'Test code in 250+ PHP versions';
	public $user;
	public $bodyClass;
	public $adminMessage;
	public $cspDirectives = [
		'script-src' => [
			"'self'",
			"'unsafe-eval'", # for live-preview
			'cdn.jsdelivr.net',
			'blob:', # required by ace on FF < 58
		],
		'manifest-src' => ["'self'"],
		'frame-ancestors' => ["https://www.php.net"],
		'worker-src' => [ # valid sources for web-workers
			"'self'",
			'cdn.jsdelivr.net',
			'blob:', # required by ace
		],
		'connect-src' => ["'self'", "https://cdn.jsdelivr.net/"], # for xhr | php-wasm
		'base-uri' => ["'self'"],
		'form-action' => ["'self'"],
		'img-src' => ["'self'", 'data:',],
		'style-src' => [
			"'self'",
			"'unsafe-inline'", # for ace-editor & tagcloud
		]
	];
	public $httpPreloads = [
			'/s/my.js' => 'preload',
			'https://cdn.jsdelivr.net/npm/php-wasm/PhpBase.mjs' => 'modulepreload',
			'https://cdn.jsdelivr.net/npm/php-wasm/php-web.mjs' => 'modulepreload',
#			'https://cdn.jsdelivr.net/npm/php-wasm/php-web.mjs.wasm' => 'modulepreload', # Loading module from “https://cdn.jsdelivr.net/npm/php-wasm/php-web.mjs.wasm” was blocked because of a disallowed MIME type (“application/wasm”).
	];
	public $aceScripts = [
		// curl -s URL | openssl dgst -sha384 -binary | openssl base64 -A
		'ace'				=> '/HiYf7uts/FC/PC50yfG3bXnxyMdlMFQpgaXPNOqiTJkQIbiBeth3J86SYJowDdK',
		'ext-language_tools'=> 'pjdIm81c7GuHpSIwd3CpO3BgZx2B+hNtSvqcFnk7DCwPdEmf0xBCZX5i7jMj/ib6',
		'mode-php'			=> 'EO1oIq3Wru7Pa4jrHft9hrjz2SKGcYx1J/BteienRTZbIRvIWRADNyaNMCV4AmNN',
		'theme-chrome'		=> 'uOlVPZfQXFZofTCU/B1H8M3c6hww7F3VOufsGRLzlK4l9blvVqfJeONjYJM5+tnb',
		'theme-chaos'		=> 'mfoITkRp/u4qWConw/ovtCjvymPkNZiOj9csanbxBv8rdPa/v7wiZdnwF8i+M3UE',
	];

	public function init(): void
	{
		Basic::$database->exec("SET statement_timeout TO 5000;");

		// For now; don't autoStart sessions
		if (isset(Basic::$config->Session, $_COOKIE[ Basic::$config->Session->name ]))
		{
			session_name(Basic::$config->Session->name);
			session_set_cookie_params(Basic::$config->lifetime, Basic::$config->Site->baseUrl);
			session_start();
		}

		if (isset($_SESSION['userId']))
			$this->user = PhpShell_User::get($_SESSION['userId']);
		elseif (!empty($_COOKIE))
		{
			foreach (array_keys($_COOKIE) as $name)
				setcookie($name, '', strtotime('-1 day'));
		}

		header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
		header('X-Xss-Protection: 1; mode=block');
		header('X-Content-Type-Options: nosniff');
		header('Permissions-Policy: fullscreen=(), geolocation=()');
		header('Referrer-Policy: origin-when-cross-origin');

		// https://bugs.chromium.org/p/chromium/issues/detail?id=686369
		if (isset($_SERVER['HTTP_USER_AGENT']) && (str_contains($_SERVER['HTTP_USER_AGENT'], 'Chrome/') || str_contains($_SERVER['HTTP_USER_AGENT'], 'ChriOS/')))
			$this->cspDirectives['script-src'] []= "'unsafe-eval'";

		$csp = "default-src 'none'; ";
		foreach ($this->cspDirectives as $directive => $settings)
			$csp .= $directive .' '.implode(' ', $settings). '; ';

		header('Content-Security-Policy: '. $csp);

		if (isset($_GET['resetOpcache']) && $_GET['resetOpcache'] == sha1_file(APPLICATION_PATH .'/htdocs/index.php'))
			die(print_r(opcache_get_status(false)+['RESET' => opcache_reset()]));

		if (isset($_SERVER['HTTP_ACCEPT']) && 'application/json' == $_SERVER['HTTP_ACCEPT'])
			$this->contentType = 'application/json';
		elseif (isset($_SERVER['HTTP_ACCEPT']) && 'text/plain' == $_SERVER['HTTP_ACCEPT'])
			$this->contentType = 'text/plain';

		if (Basic::$config->PRODUCTION_MODE && 'text/html' == $this->contentType)
		{
			$preloads = Basic::$cache->lockedGet(__CLASS__.'::staticPreloads', function(){
				// dynamically fetch correct version
				$aceBase = str_replace('worker-php.js', '', explode("'", file_get_contents(APPLICATION_PATH .'/htdocs/s/worker-php.js'))[1]);

				$p = [];
				foreach ($this->aceScripts as $name => $hash)
					$p[$aceBase . $name .'.js'] = 'script';

				return [
					// match hashes put in tpls by update-online
					'/s/c.'. substr(hash('sha256', file_get_contents(APPLICATION_PATH .'/htdocs/s/c.css')), 0, 8). '.css' => 'preload',
					$aceBase .'worker-php.js' => 'modulepreload',
				] + $p;
			}, 3600) + $this->httpPreloads;

			foreach ($preloads as $link => $rel)
				header('Link: <'. $link .'>; rel='. $rel .'; as='. (str_ends_with($link, '.css')?'style':'script'), false);
		}

		// Since we resolve everything to 'script'; prevent random strings in bodyClass
		if (! Basic::$action instanceof PhpShell_Action_Script)
			$this->bodyClass = trim($this->bodyClass .' '.Basic::$userinput['action']);

		parent::init();
	}

	protected function _handleLastModified(): void
	{
		if (isset($_SESSION['userId']) || 'text/html' != $this->contentType)
			$this->_cacheLength = 0;

		parent::_handleLastModified();
	}

	public static function resolve(string $action, bool $hasClass, bool $hasTemplate): ?string
	{
		if ($hasClass)
			return null;

		return 'script';
	}
}