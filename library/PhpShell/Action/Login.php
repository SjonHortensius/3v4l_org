<?php

class PhpShell_Action_Login extends PhpShell_Action
{
	protected $_userinputConfig = array(
		'provider' => [
			'source' => ['superglobal' => 'MULTIVIEW', 'key' => 1],
			'values' => [],
			'required' => true,
			'options' => ['valuesToKeys'],
		],
	);
	protected $_client;

	public function init()
	{
		spl_autoload_register(array('OpenReact_Exception', 'autocreate'));

		$this->_client = new OpenReact_XmlRpc_ServicesClient(
			['http://social.react.com/XmlRpc_v2' => ['OAuthServer', 'Twitter']],
			null,
			[Basic::$config->React->apiKey, Basic::$config->React->apiSecret]
		);

		$this->_userinputConfig['provider']['values'] = $this->_client->OAuthServer->getProviders();

		parent::init();
	}

	public function run()
	{
		if (isset($this->user) && empty(array_diff($_SESSION['providers'], Basic::$userinput->provider->values)))
			throw new PhpShell_Action_Login_AlreadyConnectedException('You are logged in and already connected to all known providers', null, 410);

		$result = $this->_client->OAuthServer->tokenRequest(Basic::$userinput['provider']);

		if (1 == $_SESSION['hits'])
			throw new PhpShell_Action_Login_AlreadyConnectedException('It seems you are not accepting cookies; please fix that', null, 400);

		$_SESSION['reactOAuthSession'] = $result['reactOAuthSession'];

		Basic::$controller->redirect($result['redirectUrl']);

		parent::run();
	}
}