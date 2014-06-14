<dl>
<? foreach ($this->data as $versions => $output)
{
	$id = array_shift(explode(' ', str_replace(',', ' ', $versions)));
?>
	<dt id="v<?= str_replace('.', '', $id) ?>">Output for <?=$versions?><a class="inactive" title="Tag this output as correct" href="/assert/<?=$this->input->short?>/<?=$id?>/<?=$this->input->run?>"><i class="icon-ok"></i></a></dt><dd><?=$output?></dd>
<? } ?>
</dl>