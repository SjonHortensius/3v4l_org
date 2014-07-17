<?php
/**
	OpenReact

  	LICENSE:
  	This source file is subject to the Simplified BSD license that is
  	bundled	with this package in the file LICENSE.txt.
	It is also available through the world-wide-web at this URL:
	http://account.react.com/license/simplified-bsd
	If you did not receive a copy of the license and are unable to
	obtain it through the world-wide-web, please send an email
	to openreact-license@react.com so we can send you a copy immediately.

	Copyright (c) 2011 React B.V. (http://www.react.com)
*/
/**
	Manages multiple XML-RPC service endpoints, mapping services to easily accessable objects for each endpoint.
	Also supports prepended authentication data, as used by React.com XML-RPC services.
*/
class OpenReact_XmlRpc_ServicesClient extends OpenReact_XmlRpc_Client
{
	/** (array) Default service the client will connect to (method name prefix) */
	protected $_defaultService = null;
	/** (array) XML-RPC client for different services */
	protected $_serviceClients = array();
	/** (array) Authentication by prepending parameters */
	protected $_prependAuthentication = array();


	/**
		Parameters:
			services - (array) array of endpoint => array of available services.
			defaultService - (string|null) Service to use for this client, if NULL is passed, sevices can be accessed as properties on this instance.
			prependAuthentication - (array) Authentication parameters to prepend to RPC calls
	*/
	public function __construct(array $endpoints = array(), $defaultService = null, $prependAuthentication = array())
	{
		$this->_prependAuthentication = $prependAuthentication;

		$this->_defaultService = $defaultService;

		if ($defaultService === null)
		{
			$this->_setEndpoints($endpoints);
		}
		else
		{
			foreach ($endpoints as $endpointUrl => $services)
			{
				if (in_array($this->_defaultService, $services))
				{
					parent::__construct($endpointUrl);
					// Only map 'System' and Default service
					$this->_addEndpoint($endpointUrl, array($defaultService, 'System'));
					break;
				}
			}

			// No endpoint set, service mapping failed.
			if (empty($this->_endpoint))
				throw new OpenReact_XmlRpc_ServicesClient_UnknownDefaultServiceException('Service `%s` is not known in any endpoint.', array($this->_defaultService));
		}
	}

	/**
	 	Execute an XML-RPC call to the endpoint.

	 	Parameters:
	 		methodName - (string) name of the XML-RPC method to call
	 		parameters - (array) parameters for the call

	 	Returns:
	 		(mixed) The return value
	*/
	public function __call($method, $parameters)
	{
		if (!$this->_defaultService)
		{
			if (preg_match('~^([^\.]+)\.([^\.]+)$~i', $method, $match))
				return $this->{$match[1]}->__call($match[2], $parameters);

			throw new OpenReact_XmlRpc_ServicesClient_NoDefaultServiceException('No default service configured, please call a sub-service.');
		}

		return parent::__call($method, $parameters);
	}


	/**
		Set all current available services, and map all services contained within.

		Parameters:
			endpoints - (array) array of endpoint => array of available services.

		Returns:
			(self)
	*/
	protected function _setEndpoints(array $endpoints)
	{
		$this->_serviceClients = array();

		foreach ($endpoints as $endpointUrl => $services)
			$this->_addEndpoint($endpointUrl, (array)$services);

		return $this;
	}

	/**
		Build an XML-RPC HTTP request for an XML-RPC call.
		(Will add prepend authentication if needed)

		Parameters:
	 		methodName - (string) name of the XML-RPC method to call
	 		parameters - (array) parameters for the call

		Returns:
			(string) HTTP header + body
	*/
	protected function _buildRequest($method, array $parameters = array())
	{
		if ($this->_defaultService != 'System' && $this->_prependAuthentication)
			$parameters = array_merge($this->_prependAuthentication, $parameters);

		$method = (isset($this->_defaultService) ? $this->_defaultService .'.' : '') . $method;

		return parent::_buildRequest($method, $parameters);
	}

	/**
		Adds the services of an XML-RPC service to the list of available services.

		Parameters:
			endpoint - (string) The URL of the XML-RPC endpoint of the service
			services - (array) A list of services the endpoint offers

		Returns:
			(self)
	*/
	protected function _addEndpoint($endpoint, array $services)
	{
		foreach ($services as $service)
		{
			if ($service !== $this->_defaultService)
				$this->_serviceClients[$service] = new self(array($endpoint => $services), $service, $this->_prependAuthentication);
		}

		return $this;
	}

	/**
		Get a XML-RPC client for a configured service.

		Parameters:
			serviceName - (string) service

		Returns:
			(<OpenReact_XmlRpc_Client>) Client for the service
	*/
	public function __get($serviceName)
	{
		if (!isset($this->_serviceClients[$serviceName]))
			throw new OpenReact_XmlRpc_ServicesClient_UnknownServiceException('Service `%s` is not available in any configured endpoint.', array($serviceName));

		$className = get_class($this);

		if (!isset($this->_serviceClients[$serviceName]))
			$this->_serviceClients[$serviceName] = new self($this->_endpoint, $serviceName, $this->_prependAuthentication);

		return $this->_serviceClients[$serviceName];
	}

	/**
		Get a XML-RPC client for a configured service.

		Parameters:
			serviceName - (string) service

		Returns:
			(boolean) If the service is known
	*/
	public function __isset($serviceName)
	{
		return isset($this->_serviceClients[$serviceName]);
	}
}