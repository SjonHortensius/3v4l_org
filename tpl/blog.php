<form method="POST" action="/new">
	<h1>3v4l.org<small> - online PHP shell, execute code in 100+ different PHP versions!</small></h1>
	<textarea name="code"><?=htmlspecialchars("<?php\n\n")?></textarea>
	<input type="submit" value="eval();" />
</form>

<div class="col">
<? print $this->content; ?>
</div>
