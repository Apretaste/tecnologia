<h1>Buscando: {$search|ucfirst}</h1>

{foreach from=$articles item=article name=arts}
	<small><font color="gray">{$article["author"]} - {$article['pubDate']|date_format|capitalize}</font></small><br/>
	<b>{link href="tecnologia historia {$article['link']}" caption="{$article['title']}"}</b><br/>
	{$article['description']|truncate:200:" ..."}<br/>
	{space15}
{/foreach}

{space5}

<center>
	{button href="tecnologia" caption="Titulares"}
</center>
