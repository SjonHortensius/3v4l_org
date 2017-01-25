<?php
/*
	This is a nginx error_page
*/
class PhpShell_Action_Error extends PhpShell_Action
{
	public $title = 'Error';
	public $userinputConfig = array(
		'code' => [
			'source' => ['superglobal' => 'REQUEST', 'key' => 1],
			'valueType' => 'integer',
			'required' => true,
		],
	);

	public function run()
	{
		switch (Basic::$userinput['code'])
		{
			case 400:	throw new PhpShell_BadRequestException('Your browser sent a request this server did not understand', [], 400);
			case 402:	throw new PhpShell_IpBlockedException('This service is provided free of charge and we expect you not to abuse it. Please contact us to get your IP unblocked', [], 402);
			case 404:	throw new PhpShell_NotFoundException('You requested a non-existing resource', [], 404);
			case 405:	throw new PhpShell_MethodNotAllowedException('Method not allowed.', [], 405);
			case 503:	throw new PhpShell_RateLimitingReachedException('Please refrain from hammering this service. You are limited to 5 POST requests per minute', [], 503);
			default:	throw new PhpShell_GenericErrorException('An error has occured', [], 500);
		}
	}
}