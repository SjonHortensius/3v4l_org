<form method="POST" action="/new">
	<h1>3v4l.org<small> - online PHP shell, execute code in 100+ different PHP versions!</small></h1>
	<textarea name="code"><?=htmlspecialchars("<?php\n\n")?></textarea>
	<input type="submit" value="eval();" />
</form>

<div class="col">
	<h2>About</h2>
	<p>3v4l.org is an online shell that allows you to evaluate (hence 3v4l) your code on our servers. We support more then 100 different PHP versions (every version released since 4.3.0) for you to use. For every script you submit, we return:
		<ul>
			<li>Output from all released PHP versions</li>
			<li><a href="http://derickrethans.nl/projects.html#vld">VLD</a> opcode output</li>
			<li>Performance (time and memory) of every version</li>
			<li>Contextual links to documentation and php sourcecode</li>
		</ul>
		We're on Twitter <a class="twitter-timeline" data-dnt="true" href="https://twitter.com/3v4l_org" data-widget-id="386450309075050496">@3v4l_org</a> and <a href="https://www.gittip.com/3v4l.org/">Gittip</a>
	</p>
</div>
<div class="col">
	<h2>Examples:</h2>
	<ul>
		<li><a href="/am3S3/perf">Performance problems in array_diff</a></li>
		<li><a href="/S2AFZ">Booleans can be changed within a namespace</a></li>
		<li><a href="/XiQG0">A resource which is cast to an object will result in a key 'scalar'</a></li>
		<li><a href="/11Ltt"> __toString evolves when used in comparisons</a></li>
		<li><a href="/gaLMA">New binary implementation and its problems</a></li>
		<li><a href="/uNUDC">Overwriting $this when using references</a></li>
		<li><a href="/DgUUQ">Broken formatting in DateTime</a></li>
	</ul>
</div>

<?/*
<div style="float: right; width: 350px;">
	<a class="twitter-timeline" data-dnt="true" href="https://twitter.com/3v4l_org" data-widget-id="386450309075050496">Tweets by @3v4l_org</a>
	<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+"://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
</div>

<script data-gittip-username="3v4l.org" src="//gttp.co/v1.js"></script>
*/?>
