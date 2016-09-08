<?php

class PhpShell_Action extends Basic_Action
{
	public $encoding = 'UTF-8';
	public $user;
	public $bodyClass;
	public $adminMessage;

	public function init()
	{
		if (isset($_SESSION['userId']))
			$this->user = PhpShell_User::get($_SESSION['userId']);
		elseif (!empty($_COOKIE))
		{
			foreach (array_keys($_COOKIE) as $name)
				setcookie($name, '', strtotime('-1 day'));
		}

		header('Strict-Transport-Security: max-age=31536000');
		$cspDirectives = [
			'script-src' => [
				"'self'",
				'cdn.jsdelivr.net',
				'www.google-analytics.com',
				'blob:', # for ace worker
				"'unsafe-inline'", # tmp for /perf, /search and tagcloud
				"data:", # for google-analytics
			],
			'child-src' => [
				"'self'",
				'cdn.jsdelivr.net',
				'blob:', # for ace worker @ chrome
			],
			'connect-src' => ["'self'"],
			'img-src' => ["'self'", 'www.google-analytics.com', 'data:',],
			'style-src' => [
				"'self'",
				"'unsafe-inline'", # for ace-editor
			]
		];
		$cspDirectives['frame-src'] = $cspDirectives['child-src']; # b/c

		$csp = "default-src 'none'; ";
		foreach ($cspDirectives as $directive => $settings)
			$csp .= $directive .' '.implode(' ', $settings). '; ';

#		header('Content-Security-Policy-Report-Only: '. $csp .'report-uri /cspReport');

		if (0 && $_GET['waa']=='meukee')
		{
			$wasOn = Basic::$config->PRODUCTION_MODE;
			Basic::$config->PRODUCTION_MODE = false;

			if ($wasOn)
				Basic::$log->start(get_class(Basic::$action) .'::init');
		}

		if ($_GET['resetOpcache'] == sha1_file(APPLICATION_PATH .'/htdocs/index.php'))
			die(print_r(opcache_get_status(false)+['RESET' => opcache_reset()]));

		if ('application/json' == $_SERVER['HTTP_ACCEPT'])
			$this->contentType = 'application/json';
		elseif ('text/plain' == $_SERVER['HTTP_ACCEPT'])
			$this->contentType = 'text/plain';

		// Since we resolve everything to 'script'; prevent random strings in bodyClass
		if (! Basic::$action instanceof PhpShell_Action_Script)
			$this->bodyClass = trim($this->bodyClass .' '.Basic::$userinput['action']);

		try
		{
			$this->adminMessage = Basic::$cache->get('banMessage::'. $_SERVER['REMOTE_ADDR']);
			header('X-Accel-Expires: 0');
		}
		catch (Basic_Memcache_ItemNotFoundException $e)
		{
			#care
		}

		parent::init();
	}

	protected function _handleLastModified()
	{
		if (isset($_SESSION['userId']))
			return false;

		parent::_handleLastModified();
	}

	public static function resolve($action, $hasClass, $hasTemplate)
	{
		if ($hasClass)
			return;

		return 'script';
	}
}