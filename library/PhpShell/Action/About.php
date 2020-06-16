<?php
// required so we don't interpret 'about' as a script
class PhpShell_Action_About extends PhpShell_Action {
	public $title = 'About this site';
	public $colors = [
		'homepage' => '#109618',
		'submits'  => '#3366cc',
		'uncategorized' => '#990099',
		'bots' => '#dc3912',
	];
	public $hitsPerYear = [
		# xzcat access_log | grep -Eo '\(compatible;|POST /new|GET /| - - ' | sort | uniq -c
		2012 => ['sum' => 1097507,  'bots' => 112563,   'homepage' => 27142,   'submits' => 13636],
		2013 => ['sum' => 4816096,  'bots' => 1507185,  'homepage' => 85295,   'submits' => 122993],
		2014 => ['sum' => 10701014, 'bots' => 7624321,  'homepage' => 211191,  'submits' => 225718],
		2015 => ['sum' => 18296556, 'bots' => 10248788, 'homepage' => 575802,  'submits' => 348736],
		2016 => ['sum' => 27683883, 'bots' => 18984187, 'homepage' => 341811,  'submits' => 562122],
		2017 => ['sum' => 28271126, 'bots' => 18213318, 'homepage' => 464842,  'submits' => 863803],
		2018 => ['sum' => 36453914, 'bots' => 27431680, 'homepage' => 456999,  'submits' => 1059907],
		2019 => ['sum' => 25932010, 'bots' => 18783313, 'homepage' => 646845,  'submits' => 1281584],
		2020 => ['sum' => 7818016,  'bots' => 4578324,  'homepage' => 7113240, 'submits' => 633025],
	];

	public function run(): void
	{
		foreach ($this->hitsPerYear as $y => &$d)
		{
			$d['uncategorized'] = array_sum($d) - $d['sum'];
			unset($d['sum']);
		}

		parent::run();
	}
}