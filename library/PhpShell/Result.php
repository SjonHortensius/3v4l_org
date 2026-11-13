<?php

class PhpShell_Result extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input::class,
		'output' => PhpShell_Output::class,
		'minVersion' => PhpShell_Version::class,
		'maxVersion' => PhpShell_Version::class,
	];
	protected static $_numerical = ['maxMemory', 'run'];
	protected static $_exitCodes = [
		139 => 'Segmentation Fault',
		137 => 'Process was killed',
		255 => 'Generic Error',
	];

	public function getHtml(PhpShell_Version $version): string
	{
		$html = htmlspecialchars($this->output->getRaw($this->input, $version), ENT_SUBSTITUTE);

		if ($this->output->exitCode > 0)
		{
			$title = isset(self::$_exitCodes[ $this->output->exitCode ]) ? ' title="'. self::$_exitCodes[ $this->output->exitCode ] .'"' : '';
			$html .= '<br/><i>Process exited with code <b'. $title .'>'. $this->output->exitCode .'</b>.</i>';
		}

		return $html;
	}

	public static function getTable(): string
	{
		return 'result_new';
	}
}