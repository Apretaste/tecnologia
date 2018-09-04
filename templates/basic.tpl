<table width="100%">
	<tr>
		<td><h1>Noticias de tecnologia de hoy</h1></td>
	</tr>
</table>
<center>
{foreach from=$articles item=article name=arts}
<div style="text-align:justify; width:95%;">
<b>{link href="tecnologia historia {$article['link']}" caption="{$article['title']}" style='text-decoration:none;'}</b><br/>
	{if $article['category']}
	{$article['description']}<br/>
	<small>
	Categor&iacute;a:
	{link href="tecnologia categoria {$article['category']}" caption="{$article['category']}"}
	</small>
	{/if}
	{space5}
</div>
{/foreach}
</center>