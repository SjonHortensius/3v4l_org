<?php

class PhpShell_Action_OAuthCallback extends PhpShell_Action
{
	protected $_client;
	public $result;

	public function init()
	{
		spl_autoload_register(array('OpenReact_Exception', 'autocreate'), true, true);

		$this->_client = new OpenReact_XmlRpc_ServicesClient(
			['http://social.react.com/XmlRpc_v2' => ['OAuthServer', 'Twitter']],
			null,
			[Basic::$config->React->apiKey, Basic::$config->React->apiSecret]
		);

		parent::init();
	}

	public function run()
	{
		if (!isset($_SESSION['reactOAuthSession']))
			throw new PhpShell_Action_OAuthCallback_InconsistentSessionsStateException('Inconsistency detected; make sure you enabled cookies');

		$result = $this->_client->OAuthServer->tokenAccess($_GET, true);

		if (!isset($this->user))
		{
			if (empty($result['applicationUserId']))
			{
				$user = PhpShell_User::create(['name' => $result['profile']['user_name'], 'created' => date('r'), 'last_login' => date('r'), 'id' => dechex(mt_rand())]);
				$this->_client->OAuthServer->tokenSetUserId($user->id, $_SESSION['reactOAuthSession']);
				$user->login();

				$this->result = 'registered';
			}
			else
			{
				PhpShell_User::get($result['applicationUserId'])->login();

				Basic::$controller->redirect();
			}
		}
		else
		{
			if (empty($result['applicationUserId']))
			{
				$this->_client->OAuthServer->tokenSetUserId($user->id, $_SESSION['reactOAuthSession']);
				$this->result = 'login-new-provider';
			}
			elseif ($result['applicationUserId'] == $this->user->name)
				$this->result = 'login-already';
			else
				$this->result = 'login-conflict';
		}

		parent::run();
	}
}