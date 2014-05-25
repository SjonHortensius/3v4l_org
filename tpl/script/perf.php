<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">google.load('visualization', '1', {packages: ['corechart', 'table']});</script>

<script type="text/javascript">
var perfData = [
<?php
$hhvm = [];
foreach ($this->data as $result)
{
	# Fix Google-charts ordering 9>10
	if (preg_match('~^(\d\.\d\.)(\d)$~', $result->version, $m))
		$result->version = $m[1].'0'.$m[2];
	elseif (substr($result->version, 0, 5) == 'hhvm-')
	{
		if ($result->version != 'hhvm-analyze')
			array_push($hhvm, $result);
		continue;
	}

	?>['<?=$result->version?>',<?=round($result->system, 3)?>,<?=round($result->user, 3)?>,<?=round($result->memory/1024, 3)?>],<?
}
?>
];

events.push(function(){ this.drawPerformanceGraphs(perfData, $('chart'), $('data')); });
</script>
<?php foreach ($hhvm as $result){?>
<ul class="hhvm">
	<li>Hhvm statistics: <?=$result->version?></li>
	<li>System time: <b><?=round($result->system, 3)?> s</b></li>
	<li>User time: <b><?=round($result->user, 3)?> s</b></li>
	<li>Memory: <b><?=round($result->memory/1024, 3)?> MiB</b></li>
</ul>
<? } ?>
<div id="chart"></div>
<div id="data"></div>
