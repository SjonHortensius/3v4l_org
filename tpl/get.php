<?
switch ($this->input->state)
{
	case 'misbehaving':
	case 'abusive':
?>
	<div class="alert error">
		<h2>Abusive script</h2>
		<p>This script was stopped while abusing our resources</p>
	</div>
<? break;
	case 'verbose':
?>
	<div class="alert warning">
		<h2>Verbose script</h2>
		<p>This script was stopped because it was generating too much output</p>
	</div>
<? break;
}
?>
<? if (0&& $opcode == "DO_FCALL" && $operand == "memory_get_usage" )
{
?>
	<div class="alert">
		<h2>Interested in performance?</h2>
		<p>There is no need to compare memory-usage manually, just look at the <a>performance tab</a></p>
	</div>
<?
}
?>
<form method="POST" action="/new">
	<h1>3v4l.org<small> - online PHP shell, execute code in 100+ different PHP versions!</small></h1>
	<textarea name="code"><?=htmlspecialchars($this->code)?></textarea>
<? if (!empty($this->input->source)){ ?>
	<a href="/<?=$this->input->source?>">based on <?=$this->input->source?></a>
<? } ?>
<? if (in_array($this->input->state, array('new', 'done', 'busy'))){ ?>
	<input type="submit" value="eval();"<?=($this->input->state == 'busy' ? ' class="busy"' : '')?> title="shortcut: ctrl+enter" />
<? } ?>
</form>

<ul id="tabs">
	<li<?= ('output' == $this->tab ? ' class="active"' : '') ?>><a href="/<?=$this->input->short?>#tabs">Output</a></li>
<? foreach (['vld' => 'VLD opcodes', 'perf' => 'Performance', 'refs' => 'References', /*'rel' => 'Related'*/ 'segfault' => 'Segmentation fault'] as $link => $name)
{
	$disable = (false === $this->showTab[ $link ]);
?>
	<li<?= ($link == $this->tab ? ' class="active"' : ($disable ? ' class="disabled" title="not available"' : '')) ?>><a href="/<?=$this->input->short .'/'. $link?>#tabs"><?=$name?></a></li>
<?
}
?>
</ul>

<div><?=$this->get('get_'. $this->tab)?></div>