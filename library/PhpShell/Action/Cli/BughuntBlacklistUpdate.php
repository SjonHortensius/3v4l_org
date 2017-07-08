<?php

class PhpShell_Action_Cli_BughuntBlacklistUpdate extends PhpShell_Action_Cli
{
	public function run()
	{
		$vars = get_class_vars(PhpShell_Action_Bughunt);

		echo "UPDATE input SET bughuntIgnore = true WHERE id IN (SELECT input FROM operations WHERE operation IN('DO_FCALL', 'INIT_FCALL') AND operand IN ('". implode($vars['blackList'], "', '"). "'));";
	}
}
