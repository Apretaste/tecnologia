<h1>Categor&iacute;a: {$category}</h1>

{if $articles|count eq 0}
	<p>Lo siento, a&uacute;n no tenemos historias para &eacute;sta categor&iacute;a :'-(</p>
{/if}

{foreach from=$articles item=article}
	<b>{link href="tecnologia historia {$article['link']}" caption="{$article['title']}"}</b><br/>
	{space5}
	{$article['description']|strip_tags|truncate:200:"..."}</br>
	<small>
		<font color="gray">{$article['author']} — {$article['pubDate']|date_format}</font>
	</small>
	{space15}
{/foreach}
{space5}
<center>
	{button href="tecnologia" caption="M&aacute;s noticias"}
</center>
