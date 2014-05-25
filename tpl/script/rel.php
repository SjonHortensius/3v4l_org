<table>
<tr>
	<th>name</th>
	<th>userTime</th>
	<th>systemTime</th>
	<th>maxMemory</th>
	<th># runs</th>
	<th># operations</th>
	<th>% variance</th>
</tr>
<?php
	foreach ($this->data as $result)
	{
?>
<tr>
	<td><a href="/<?=$result->input?>"><?=$result->input?></a></td>
	<td><?=round($result->userTime, 2)?> s</td>
	<td><?=round($result->systemTime, 2)?> s</td>
	<td><?=round($result->maxMemory/1024)?> MiB</td>
	<td><?=$result->run?></td>
	<td><?=$result->operationCount?></td>
	<td><?=$result->variance?></td>
</tr>
<?
	}
?>
</table>