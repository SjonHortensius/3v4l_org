<?php

class PhpShell_Action_CspReport extends PhpShell_Action
{
	public function run()
	{
		$report = file_get_contents('php://input');

		print 'ok';
		fastcgi_finish_request();

#		mail('root@3v4l.org', '3v4l - cspReport', $report);
		if (!is_dir(APPLICATION_PATH.'/cache/cspReports'))
			mkdir(APPLICATION_PATH.'/cache/cspReports');
		file_put_contents(APPLICATION_PATH.'/cache/cspReports/'.uniqid(), $report);
	}
}