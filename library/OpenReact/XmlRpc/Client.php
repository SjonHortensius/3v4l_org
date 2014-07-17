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
	PHP based XML-RPC client. Currently only supports UTF-8 as character encoding.
*/
class OpenReact_XmlRpc_Client
{
	/** (string) Version number of the client, will be communicated as client-server to the XML-RPC server */
	const VERSION = '1.0';
	/** (string) Character enconding used */
	const ENCODING = 'UTF-8';

	/** (string) Endpoint URL of the XML-RPC service */
	protected $_endpoint;

	/**
		Construct the XML-RPC client.

		Parameters:
			endpoint - (string) URL to the XML-RPC endpoint
	*/
	public function __construct($endpoint)
	{
		$this->_endpoint = $endpoint;
	}

	/**
	 	Execute an XML-RPC call to the endpoint.

	 	Parameters:
	 		methodName - (string) name of the XML-RPC method to call
	 		parameters - (array) parameters for the call

	 	Returns:
	 		(mixed) The return value
	*/
	public function call($methodName, array $parameters = array())
	{
		return $this->__call($methodName, $parameters);
	}

	/**
	 	Magic PHP method for catching method calls. Execute an XML-RPC call to the endpoint.

	 	Parameters:
	 		methodName - (string) name of the XML-RPC method to call
	 		parameters - (array) parameters for the call

	 	Returns:
	 		(mixed) The return value
	*/
	public function __call($methodName, array $parameters = array())
	{
		$request = $this->_buildRequest($methodName, $parameters);
		$response = $this->_sendRequest($request);

		return $this->_processResponse($response);
	}

	/**
		Split the HTTP headers and HTTP body in a HTTP response.

		Parameters:
			response - (string) HTTP response

		Returns:
			(array)
				0 - (string) Headers
				1 - (string) Body
	*/
	protected function _splitHttpResponse($response)
	{
		list($_headers, $body) = explode("\r\n\r\n", $response, 2);

		$headers = array();
		foreach (explode("\r\n", $_headers) as $header)
		{
			if (false === strpos($header, ': '))
				array_push($headers, $header);
			else
			{
				list($key, $value) = explode(': ', $header, 2);
				$headers[$key] = $value;
			}
		}

		return array($headers, $body);
	}

	/**
		Build an XML-RPC HTTP request for an XML-RPC call.

		Parameters:
	 		methodName - (string) name of the XML-RPC method to call
	 		parameters - (array) parameters for the call

		Returns:
			(string) HTTP header + body
	*/
	protected function _buildRequest($methodName, array $parameters = array())
	{
		if (!preg_match('~^[a-z0-9_.:/]+$~i', $methodName))
			throw new OpenReact_XmlRpc_Client_InvalidMethodNameException('The method name `%s` contains invalid characters.', array($methodName));

		$methodCall = new SimpleXmlElement('<?xml version="1.0" encoding="'. self::ENCODING .'"?><methodCall></methodCall>');
		$methodCall->addChild('methodName', $methodName);
		$methodCall->addChild('params');

		foreach ($parameters as $value)
		{
			$param = $methodCall->params->addChild('param');
			$this->_encodeParam($param, $value);
		}

		$endpoint = parse_url($this->_endpoint);

		$body = $methodCall->asXml();

		$headers = array(
			'Host' 				=> $endpoint['host'],
			'User-Agent'		=> 'OpenReact/XmlRpcClient ' . self::VERSION,
			'Content-Type'		=> 'text/xml;chartype='. self::ENCODING,
			'Content-Length' 	=> strlen($body),
		);

		$request = 'POST ' . $endpoint['path'] . ' HTTP/1.0' ."\r\n";

		foreach ($headers as $name => $value)
			$request .= sprintf("%s: %s\r\n", $name, $value);

		$request .= "\r\n" . $body;

		return $request;
	}

	/**
		Send an HTTP request.

		Parameters:
	 		request - (string) HTTP request

		Returns:
			(string) HTTP response
	*/
	protected function _sendRequest($request)
	{
		$endpoint = parse_url($this->_endpoint);

		if (!isset($endpoint['port']))
			$endpoint['port'] = 80;

		$fp = @fsockopen($endpoint['host'], $endpoint['port'], $errno, $errmsg);

		if (!$fp)
			throw new OpenReact_XmlRpc_Client_FailedConnectException('Failed to connect to endpoint `%s`.', array($this->_endpoint));

		fputs($fp, $request);

		$response = '';
		while (!feof($fp))
			$response .= fgets($fp, 8192);

		return $response;
	}

	/**
		Process a HTTP response as XML-RPC response.
		Will thrown exceptions if the HTTP response is an invalid XML-RPC response, or if it contains an XML-RPC fault result.

		Parameters:
	 		response - (string) HTTP response

		Returns:
			(mixed) The result value of the XML-RCP response
	*/
	protected function _processResponse($response)
	{
		list($headers, $body) = $this->_splitHttpResponse($response);

		if (!isset($headers['Content-Type']) || 0 !== strpos($headers['Content-Type'], 'text/xml'))
			throw new OpenReact_XmlRpc_Client_InvalidResponseContentTypeHeaderException('Missing required Content-Type text/xml.');

		// Suppress PHP warnings, exception will also be thrown
		libxml_use_internal_errors(true);
		try
		{
			$responseXml = new SimpleXmlElement($body);
		}
		catch (Exception $e)
		{
			throw new OpenReact_XmlRpc_Client_InvalidXmlResponseException('Could not parse body `%s` as XML.', array($body), $e);
		}

		if (isset($responseXml->fault))
		{
			$code = (int)$responseXml->fault->value->struct->member[0]->value->int;
			$message = (string)$responseXml->fault->value->struct->member[1]->value->string;

			throw new OpenReact_XmlRpc_Client_ResponseFaultException('Response Fault (%s): %s ', array($code, $message), null, (int)$code);
		}

		if (!isset($responseXml->params, $responseXml->params->param, $responseXml->params->param[0], $responseXml->params->param[0]->value))
			throw new OpenReact_XmlRpc_Client_MalformedXmlRpcResponseException('XML malformed, missing XML-RPC return value.');

		return $this->_decodeParam($responseXml->params->param[0]->value);
	}

	/**
		Check if an array is a simple numeric incremental-key array.

		Parameters:
			array - (array) array to test

		Returns:
			(boolean) if the array is a simple numeric incremental-key array.
	*/
	protected function _isSimpleArray(array $array)
	{
		$i = 0;
		foreach ($array as $key => $value)
			if ($key !== $i++)
				return false;

		return true;
	}

	/**
		Set a PHP value variable into an XML-RPC value element.

		Parameters:
			param - (SimpleXmlElement) XML element to add the value element to
			value - (mixed) Value to set

		Returns:
			(boolean) if the array is a simple numeric incremental-key array.
	*/
	protected function _encodeParam(SimpleXmlElement $param, $value)
	{
		$paramValue = $param->addChild('value');

		$valueType = gettype($value);

		switch(gettype($value))
		{
			case 'array':
				if ($this->_isSimpleArray($value))
				{
					$paramValue->addChild('array');
					$data = $paramValue->array->addChild('data');

					foreach ($value as $_value)
						$this->_encodeParam($data, $_value);
				}
				else
				{
					$paramValue->addChild('struct');

					foreach ($value as $key => $_value)
					{
						$member = $paramValue->struct->addChild('member');
						$member->addChild('name', $key);

						$this->_encodeParam($member, $_value);
					}
				}
				break;
			case 'string':
				if (function_exists('mb_check_encoding'))
				{
					if (mb_internal_encoding() !== self::ENCODING)
						$value = mb_convert_encoding($value, self::ENCODING);
					else if (!mb_check_encoding($value, self::ENCODING))
						throw new OpenReact_XmlRpc_Client_InvalidEncodingException('Parameter is not valid UTF-8.');
				}
				else
				{
					/*
						Find overlong UTF-8 characters, UTF-16 surrogates and character points above U+10000.
						character.
						(Only match U+0000...U+FFFF = the Basic Multilingual Plane.)
						From <http://webcollab.sourceforge.net/unicode.html>
						"
							In the above algorithm the first preg_replace() only allows well formed Unicode
							(and rejects overly long 2 byte sequences, as well as characters above U+10000).
							The second preg_replace() removes overly long 3 byte sequences and UTF-16
							surrogates.
						"
					*/

					if (preg_match(
						'/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' . // Invalid characters below codepoint 127
						'|[\x00-\x7F][\x80-\xBF]+' . // ASCII char (< 127) followed by multibyte sequence(s)
						'|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' . // Overly long 2 byte sequence start, or 4+byte sequence start, or FE/FF illegal chars, optionally followed by one or more multibyte seq. characters
						'|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' . // Two byte sequence starter not followed by a non-multibyte seq, or more then 1 multibyte seq
						'|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S', // Three byte sequence starter not followed by a non-multibyte seq, or more then 2 multibyte seq
						$value
					))
					{
						throw new OpenReact_XmlRpc_Client_InvalidEncodingException('Parameter is not valid UTF-8.');
					}
				}

				$value = htmlspecialchars($value, ENT_NOQUOTES, self::ENCODING);
				// FALLTHROUGH
			case 'boolean':
			case 'double':
				$paramValue->addChild($valueType, $value);
				break;
			case 'NULL':
				$paramValue->addChild('nil');
				break;
			case 'integer':
				$paramValue->addChild('int', $value);
				break;
			default:
				throw new OpenReact_XmlRpc_Client_UnsupportedPhpValueTypeException('Cannot convert unsupported PHP value type `%s` to a XML-RPC value type.', array($child->getName()));
		}
	}

	/**
		Convert an XML-RPC value element to a PHP value

		Parameters:
			param - (SimpleXmlElement) XML element containing the XML-RPC 'value' node

		Returns:
			(mixed) The PHP value
	*/
	protected function _decodeParam(SimpleXmlElement $param)
	{
		$children = $param->children();
		$child = $children[0];

		switch ($child->getName())
		{
			case 'struct':
				$values = array();
				foreach ($child->member as $member)
					$values[(string)$member->name] = $this->_decodeParam($member->value);

				return $values;
			case 'array':
				$values = array();
				foreach ($child->data->value as $value)
					$values[] = $this->_decodeParam($value);

				return $values;
			case 'nil':
				return null;
			case 'i4':
			case 'int':
				return (int)$child;
			case 'double':
				return (float)$child;
			case 'boolean':
				return (boolean)$child;
			case 'base64':
			case 'dateTime.iso8601':
			case 'string':
				return (string)$child;
			default:
				throw new OpenReact_XmlRpc_Client_UnsupportedXmlRpcValueTypeException('Unsupported XML-RPC value type `%s` found.', array($child->getName()));
		}
	}
}