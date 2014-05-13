<form>
	<h1>3v4l.org<small> - online PHP shell, test in 80+ different PHP versions!</small></h1>

</form>

<ul id="tabs">
	<li class="active"><a>Latest submissions</a></li>
	<li><a>Search results</a></li>
</ul>

<div>
<table>
<tr>
	<th>name</th>
	<th>userTime</th>
	<th>systemTime</th>
	<th>maxMemory</th>
	<th># runs</th>
	<th>variance</th>
</tr>
<?php
	foreach ($this->last as $result)
	{
?>
<tr>
	<td><a href="/<?=$result->input?>"><?=$result->input?></a></td>
	<td><?=round($result->userTime, 2)?> s</td>
	<td><?=round($result->systemTime, 2)?> s</td>
	<td><?=round($result->maxMemory/1024)?> MiB</td>
	<td><?=$result->run?></td>
	<td><?=$result->nrOutput?></td>
</tr>
<?
	}
?>
</table>

</div>