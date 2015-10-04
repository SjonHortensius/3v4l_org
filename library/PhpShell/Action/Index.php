<?php

class PhpShell_Action_Index extends PhpShell_Action_New
{
	public $bodyClass = 'script';
	public $lastModified = 'now';
	protected $_cacheLength = '45 seconds';

	public function init()
	{
		parent::init();

		$this->last = new PhpShell_LastScriptsList(PhpShell_Input, 'input.run > 0', [], ['id' => false]);
		$this->last->addJoin('result', "result.input = input.id AND result.version >= 32");

		$slots = [];
		for ($slot = date('B'); $slot--; $slot > date('B', strtotime('-1 hour')))
			array_push($slots, 'hits::'. $slot);

		$this->active = [];
		foreach (Basic::$cache->getMulti($slots) as $key => $slot)
			foreach ($slot as $short => $hits)
			{
				// Cleanup slots that contain only 1-hit scripts
				if (false && $key != 'hits::'. date('B') && array_sum($hits) == count($hits))
				{
					Basic::$cache->delete($key);
					continue;
				}

				// 1000 slots per day, recent hit count 10x hits from a day ago
				$this->active[ $short ] = $hits * (substr($key, 6)/100);
			}

		arsort($this->active);

		$this->popular = PhpShell_Input::find("short IN ('". implode("','", array_slice($this->active, 0, 5)) ."')");
	}
}