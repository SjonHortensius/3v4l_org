<?php
if (empty($this->data))
	print('No VLD output found, please wait for process to complete. Parser errors will also stop VLD from functioning');
else {
?><pre><?=$this->data?></pre>
<p>Generated using <a href="http://derickrethans.nl/projects.html#vld">Vulcan Logic Dumper</a>, using php 5.5.0</p>
<? } ?>
