<?
$tabs = [
	'vld' => 'VLD opcodes',
	'perf' => 'Performance',
	'refs' => 'References',
//	'rel' => 'Related',
	'segfault' => 'Segmentation fault',
	'analyze' => 'HHVM analyze',
];

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
?><ul id="tabs">
	<li<?= ('output' == $this->tab ? ' class="active"' : '') ?>><a href="/<?=$this->input->short?>#tabs">Output</a></li>
<?foreach ($tabs as $link => $name)
{
	$disable = isset($this->showTab[ $link ]) && false == $this->showTab[ $link ];
?>
	<li<?= ($link == $this->tab ? ' class="active"' : ($disable ? ' class="disabled" title="not available"' : '')) ?>><a href="/<?=$this->input->short .'/'. $link?>#tabs"><?=$name?></a></li>
<?
}
?>
</ul>

<div><?=$this->get('script/'. $this->tab)?></div>