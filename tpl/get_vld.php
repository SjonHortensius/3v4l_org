<?php
if (empty($this->data))
	print('No VLD output found, please wait for process to complete');
else {
?><pre><?=$this->data?></pre>
<p>Generated using <a href="http://derickrethans.nl/projects.html#vld">Vulcan Logic Dumper</a>, using php 5.4.0</p>
<? } ?>