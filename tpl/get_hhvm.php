<div class="alert">
	<h2>Beta hhvm output</h2>
	<p>It took a lot of effort to get hhvm working; please let me know what you think!<br/>Ideas are also welcome; my ToDo list currently includes:</p>
	<ul>
		<li>Add <i>hhvm</i> tab / merge with PHP output</li>
		<li>Add <i>hhvm</i> timings to performance tab</li>
		<li>Detect &lt;?hh prefix and skip php &amp; vld</li>
	</ul>
</div>
<?php
if (empty($this->data))
	print('No hhvm output found!');
else
{
?><dl><dt id="v301">hhvm-3.0.1</dt><dd><?=$this->data?></dd></dl>
<p>Generated using <a href="http://hhvm.com/">HHVM</a></p>
<? } ?>

