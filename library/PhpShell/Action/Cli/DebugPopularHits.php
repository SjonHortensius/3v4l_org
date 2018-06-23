<?php

class PhpShell_Action_Cli_DebugPopularHits extends PhpShell_Action_Cli
{
	public function run(): void
	{
		foreach (Basic::$cache->get('Hits:popular') as $short => $hits)
		{
			$prevHits = Basic::$cache->get('Hits:'. $short .':'.((date('w')+5)%6), function(){ return 0; });

			echo "{$short} - {$hits} (of which {$prevHits} yesterday)\n";
		}
	}
}
