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
	<th># operations</th>
</tr>
<?php
	foreach ($this->last as $input)
	{
?>
<tr>
<td><?=$input->input?></td>
<td><?=$input->operationCount?></td>
</tr>
<?
	}
?>
</table>

</div>