<table width="100%">
	<tr>
		<td><h1>Noticias de tecnologia de hoy</h1></td>
		<td align="right" valign="top">
			{button href="TECNOLOGIA BUSCAR" popup="true" size="small" desc="Inserte una palabra o frase a buscar" caption="&#10004; Buscar"}
		</td>
	</tr>
</table>

{foreach from=$articles item=article name=arts}
	<b>{link href="tecnologia historia {$article['link']}" caption="{$article['title']}"}</b><br/>
	{space5}
	{$article['description']|strip_tags|truncate:200:"..."}<br/>
	<small>
		<font color="gray">{$article['author']} — {$article['pubDate']|date_format}</font>
		<br/>
		Categor&iacute;as:
		{foreach from=$article['category'] item=category name=cats}
			{link href="tecnologia categoria {$category}" caption="{$category}"}
			{if not $smarty.foreach.cats.last}{separator}{/if}
		{/foreach}
	</small>
	{space15}
{/foreach}
