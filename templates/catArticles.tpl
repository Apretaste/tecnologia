<h1>Categor&iacute;a: {$category}</h1>

{if $articles|count eq 0}
	<p>Lo siento, a&uacute;n no tenemos historias para &eacute;sta categor&iacute;a :'-(</p>
{/if}

{foreach from=$articles item=article name=arts}
<div style="text-align:justify; width:95%;">
<b>{link href="tecnologia historia {$article['link']}" caption="{$article['title']}" style='text-decoration:none;'}</b><br/>
	{if $article['category']}
	{$article['description']}<br/>
	<small>
	Categor&iacute;a:
	{link href="tecnologia categoria {$article['categoryLink']}" caption="{$article['category']}"}
	</small>
	{/if}
	{space5}
</div>
{/foreach}
<center>
	{button href="tecnologia" caption="M&aacute;s noticias"}
</center>
