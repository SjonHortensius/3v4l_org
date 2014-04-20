<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">google.load('visualization', '1', {packages: ['corechart', 'table']});</script>

<table>
<thead>
	<tr>
		<th>name</th>
		<th># opcodes</th>
		<th>performance</th>
	</tr>
</thead>
<tbody>
<? foreach ($this->data as $input){?>
	<tr>
		<td><a href="/<?=$input->short?>"><?=$input->short?></a></td>
		<td><?=isset($input->operationCount)?$input->operationCount:'?'?></td>
		<td>
			<div id="chart_<?=$input->short?>"></div>
			<script>events.push(function(){
				this.drawPerformanceGraphs([<?php foreach ($input->perf as $result){ ?>['<?=$result->version?>',<?=round($result->system, 3)?>,<?=round($result->user, 3)?>,<?=round($result->memory/1024, 3)?>],<? }?>], $('chart_<?=$input->short?>'));
			});</script>
		</td>
	</tr>
<? }?>
</tbody>
</table>