<?php

class PhpShell_Result extends PhpShell_Entity
{
	protected static $_relations = [
		'input' => PhpShell_Input::class,
		'output' => PhpShell_Output::class,
		'version' => PhpShell_Version::class,
	];
	protected static $_numerical = ['maxMemory', 'run'];
	protected static $_exitCodes = [
		139 => 'Segmentation Fault',
		137 => 'Process was killed',
		255 => 'Generic Error',
	];

	public function getHtml(): string
	{
		$html = htmlspecialchars($this->output->getRaw($this->input, $this->version), ENT_SUBSTITUTE);

		if ($this->exitCode > 0)
		{
			$title = isset(self::$_exitCodes[ $this->exitCode ]) ? ' title="'. self::$_exitCodes[ $this->exitCode ] .'"' : '';
			$html .= '<br/><i>Process exited with code <b'. $title .'>'. $this->exitCode .'</b>.</i>';
		}

		return $html;
	}

	public function delete(): void
	{
		$this->_checkPermissions('delete');
		Basic::$database->q("DELETE FROM \"result\" WHERE input = ? AND version= ?", [$this->input, $this->version]);

		$this->output->removeCached();
		$this->removeCached();
	}
}