<?
if (empty($this->data))
	return print('No HHVM analyzer output found, please wait for process to complete. Parser errors might also cause this');

$this->data = json_decode($this->data);

if (empty($this->data))
	return print('No messages from hhvm analyzer');

usort($this->data, function($a, $b){
	return $a[1]->c1[1] - $b[1]->c1[1];
});
?>
<table>
	<thead>
		<th>Line #</th>
		<th>Message</th>
		<th>Target</th>
	</thead>
<tbody>
<? foreach ($m as $v) {?>
	<tr>
		<td><?=$v[1]->c1[1]?></td>
		<td><?=$v[0]?></td>
		<td><?=$v[1]->d?></td>
	</tr>
<? } ?>
</tbody>
</table>
<p>Generated using <a href="http://hhvm.com/">Hhvm analyze</a>, using hhvm 3.0.1</p>
