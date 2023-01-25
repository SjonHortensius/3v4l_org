<?php
// required so we don't interpret 'about' as a script
class PhpShell_Action_About extends PhpShell_Action {
	public $title = 'About this site';
	public $colors = [
		'hits' => '#109618',
		'bots' => '#dc3912',
		'submits'  => '#3366cc',
	];
	public $hitsPerYear = [
		# the /new filter only returns full submits, not previews, by filtering for 302
		# the dashes exist once for every entry, making a good sum
		# xzcat access_log | grep -aEo '\(compatible;|POST /new HTTP/..." 302| - - ' | sort | uniq -c
		2012 => ['hits' => 1097507,  'bots' => 112563,   'submits' =>  399 +  11076],
		2013 => ['hits' => 4816096,  'bots' => 1507185,  'submits' => 3861 +  99432],
		2014 => ['hits' => 10701014, 'bots' => 7624321,  'submits' => 2411 + 200793],
		2015 => ['hits' => 18296556, 'bots' => 10248788, 'submits' => 1438 + 313391],
		2016 => ['hits' => 27683883, 'bots' => 18984187, 'submits' =>   23 + 156616 + 284600],
		2017 => ['hits' => 28271126, 'bots' => 18213318, 'submits' =>   94 +  21508 + 536935],
		2018 => ['hits' => 36453914, 'bots' => 27431680, 'submits' =>   17 +  21596 + 558238],
		2019 => ['hits' => 25932010, 'bots' => 18783313, 'submits' =>    1 +  24770 + 684733],
		2020 => ['hits' => 23959191, 'bots' => 15450842, 'submits' =>        795605 + 34182],
		2021 => ['hits' => 38599592, 'bots' => 19969238, 'submits' =>        473029 + 475795],
		2022 => ['hits' => 26644798, 'bots' => 13791022, 'submits' =>           494 + 1075201],
	];

	public function run(): void
	{
		foreach ($this->hitsPerYear as $y => &$d)
			$d['hits'] = $d['hits'] - $d['bots'] - $d['submits'];

		parent::run();
	}
}