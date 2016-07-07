<h1>Buscando: {$search|ucfirst}</h1>

{foreach from=$articles item=article name=arts}
	<b>{link href="tecnologia historia {$article['link']}" caption="{$article['title']}"}</b><br/>
  {space5}
	{$article['description']|strip_tags|truncate:200:"..."}<br/>
	<small>
    <font color="gray">{$article["author"]} — {$article['pubDate']|date_format|capitalize}</font>
  </small>
	{space15}
{/foreach}
{space5}
<center>
	{button href="tecnologia" caption="M&aacute;s noticias"}
</center>
