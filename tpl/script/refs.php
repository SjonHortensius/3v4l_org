<?
if (!isset($this->data))
	return print('No VLD output found, please wait for process to complete');

?><ul><? foreach ($this->data as $reference) {?>
<li><a href="<?=htmlspecialchars($reference->link)?>" rel="external"><?=htmlspecialchars($reference->name)?></a></li>
<? } ?>
</ul>