<!DOCTYPE html>
<html dir="ltr" lang="en-US">
<head>
	<title>3v4l <?=(isset($this->title)?' :: '.$this->title : '')?> - EvAluate your code in our online PHP &amp; hhvm shell (100+ versions)</title>
	<meta name="keywords" content="php,codepad,fiddle,phpfiddle,shell,xdebug,vld,performance,hhvm,online,shell"/>
	<meta name="author" content="Sjon Hortensius - sjon@hortensius.net" />
	<link href="/favicon.ico" rel="shortcut icon" type="image/x-icon" />
	<link rel="stylesheet" href="/s/c.css"/>
	<script>var events = events || [];</script>
</head>
<body<?=isset($this->tab)?' class="'.$this->tab.'"':''?>>
<?php
if (isset($this->input, $this->code))
{
	?><form method="POST" action="/new">
	<h1>3v4l.org<small> - online PHP & HHVM shell, execute code in 100+ different versions!</small></h1>
	<textarea name="code"><?=htmlspecialchars($this->code)?></textarea>
<? if (!empty($this->input->source)){ ?>
	<a href="/<?=$this->input->source?>">based on <?=$this->input->source?></a>
<? } ?>
<? if (in_array($this->input->state, array('new', 'done', 'busy'))){ ?>
	<input type="submit" value="eval();" class="<?=$this->input->state?>" title="shortcut: ctrl+enter" />
<? } ?>
</form>
<?
}
?><?=$this->content?>

	<script src="//ajax.googleapis.com/ajax/libs/mootools/1.4.5/mootools-yui-compressed.js"></script>
	<script src="/s/c.js" async="true"></script>
	<script src="//widget.uservoice.com/BTnh9yuv0Uphlcf09sRNoA.js" async="true"></script>
</body>
</html>
