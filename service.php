<?php

use Goutte\Client;

class tecnologia extends Service
{
	public function _main(Request $request)
	{
		try{
			$allStories = $this->allStories();
		} catch(Exception $e) {
			return $this->respondWithError();
		}

		$response = new Response();
		$response->setCache("day");
		$response->setResponseSubject("Noticias de tecnología");
		$response->createFromTemplate("basic.tpl", $allStories);
		return $response;
	}

	/**
	 * Subservice _historia to call the view of
	 * a single post()
	 *
	 * @see tecnologia::post()
	 * @param Request
	 * @return Response
	 */

	public function _historia(Request $request)
	{
		// entries must have content
		if (empty($request->query))
		{
			$response = new Response();
			$response->setResponseSubject("Búsqueda en blanco");
			$response->createFromText("Su búsqueda parece estar en blanco, debe decirnos qué artículo desea leer");
			return $response;
		}

		// explode the query string every '/'
		$pieces = explode("/", $request->query);

		// call to parse the post
		try{
			$responseContent = $this->post($request->query);
		} catch(Exception $e) {
			return $this->respondWithError();
		}

		// subject changes when user comes from the main menu or from buscar
		if(strlen($pieces[1]) > 5) $subject = str_replace("-", " ", ucfirst($pieces[1]));
		else $subject = "La historia que pidió";

		// send the response
		$response = new Response();
		$response->setCache();
		$response->setResponseSubject($subject);
		$response->createFromTemplate("historia.tpl", $responseContent);
		return $response;
	}

	/**
	 * Subservice to search articles
	 *
	 * @see tecnologia::search()
	 * @param Request
	 * @return Response
	 */

	public function _buscar(Request $request)
	{
		// don't allow empty entries
		if (empty($request->query))
		{
			$response = new Response();
			$response->setResponseSubject("Búsqueda en blanco");
			$response->createFromText("Su búsqueda parece estar en blanco, debe decirnos sobre qué tema desea leer");
			return $response;
		}

		// search by the query
		try{
			$articles = $this->search($request->query);
		} catch(Exception $e) {
			return $this->respondWithError();
		}

		// if the search is empty
		if(empty($articles))
		{
			$failed[] = array();
			$response = new Response();
			$response->setResponseSubject("Su búsqueda no generó resultados");
			$response->createFromTemplate("noArticles.tpl", $failed);
			return $response;
		}

		$responseContent = array(
			"articles" => $articles,
			"search" => $request->query
		);

		$response = new Response();
		$response->setResponseSubject("Buscar: " . $request->query);
		$response->createFromTemplate("searchArticles.tpl", $responseContent);
		return $response;
	}

	/**
	 * Subservice to get a list of articles by category
	 *
	 * @see tecnologia::listByCategory()
	 * @param Request
	 * @return Response
	 */
	public function _categoria(Request $request)
	{
		if (empty($request->query))
		{
			$response = new Response();
			$response->setResponseSubject("Categoría en blanco");
			$response->createFromText("Su búsqueda parece estar en blanco, debe decirnos sobre qué categoría desea leer");
			return $response;
		}

		// search by the query
		try{
			$articles = $this->listByCategory($request->query);
		} catch(Exception $e) {
			return $this->respondWithError();
		}

		$responseContent = array(
			"articles" => $articles["articles"],
			"category" => $request->query
		);

		$response = new Response();
		$response->setResponseSubject("Categor&iacute;a: " . $request->query);
		$response->createFromTemplate("catArticles.tpl", $responseContent);
		return $response;
	}

	/**
	 * Get all stories from Conectica.com, a
	 * tech blog from Latin America.
	 *
	 * @link http://conectica.com/
	 * @return Array
	 */
	private function allStories()
	{
		// create a new Client
		$client = new Client();
		$guzzle = $client->getClient();
		$client->setClient($guzzle);

		// create a crawler
		$crawler = $client->request('GET', "http://feeds.feedburner.com/feedconectica?format=xml");

		$nodeCount = 0;
		$articles = array();
		$crawler->filter('item')->each(function($item, $i) use (&$articles, &$nodeCount)
		{
			// we only want the first 15 nodes
			while ($nodeCount < 15)
			{
				// count a post
				$nodeCount++;

				// the link to the article
				$link = $this->urlSplit($item->filter('feedburner|origLink')->text());

				// show only the content part of the link
				$pieces = explode("/", $link);

				// get title, description, pubDate, and category
				$title = $item->filter('title')->text();

				// get the text of the description
				$text = $item->filter('content|encoded')->text();
				// strip all images and videos from the description string
				$description = strip_tags($text, '<p>');

				// get the publication date
				$pubDate = $item->filter('pubDate')->text();

				// get category
				$category = $item->filter('category')->each(
				function ($category, $j) {
				return $category->text();
				});

				// get the author
				$authorSel = 'dc|creator';
				if ($item->filter($authorSel)->count() == 0) $author = "Desconocido";
				else
				{
				$author = $item->filter($authorSel)->text();
				}

				// traverse and show all the categories of the <item>
				$categoryLink = array();
				foreach($category as $currCategory)
				{
				$categoryLink[] = $currCategory;
				}

				// finally set everything
				$articles[] = array(
					"title" => $title,
					"link" => $link,
					"pubDate" => $pubDate,
					"description" => $description,
					"category" => $category,
					"categoryLink" => $categoryLink,
					"author" => $author
				);
				return;
			}
		});

		return array("articles" => $articles);
	}

	/**
	 * Search articles matching the $query
	 *
	 * @param String
	 * @return Array
	 */

	private function search($query)
	{
		// create a new Client
		$client = new Client();
		$url = "http://www.conectica.com/?s=".urlencode($query);
		$crawler = $client->request('GET', $url);

		// collect the posts that match with the search
		$articles = array();
		$crawler->filter('.grid_post')->each(function ($item, $i) use (&$articles)
		{
			// get data from the posts
			$date = $item->filter('.authorDate')->text();
			$date = strip_tags($date, '<a>');

			// get title of the post
			$title = $item->filter('.grid_post_title a')->text();

			// get the author
			$author = $item->filter('.authorDate a')->text();

			// the URL
			$preLink = $item->filter('.grid_post_title a')->attr('href');

			// the actual service-friendly link
			$link = $this->urlSplit($preLink);

			// store the article
			$articles[] = array(
				"pubDate" => $date,
				"title" => $title,
				"author" => $author,
				"link" => $link
			);
		});
		return $articles;
	}

	/**
	 * Collect the array of news by category
	 *
	 * @param String
	 * @return Array
	 */
	private function listByCategory($query)
	{
		// setup new Client
		$client = new Client();
		$crawler = $client->request('GET', "http://feeds.feedburner.com/feedconectica?format=xml");

		// filter every item
		$articles = array();
		$crawler->filter('channel item')->each(function ($item, $i) use (&$articles, $query)
		{
			// filter by category, and add it to the list of articles to show
			$item->filter('category')->each(function ($cat, $i) use (&$articles, &$query, &$item)
			{
				if($cat->text() == $query)
				{
					// get the title
					$title = $item->filter('title')->text();

					// get the the link, then urlSplit()-it
					$link = $this->urlSplit($item->filter('feedburner|origLink')->text());

					// get the publication date
					$pubDate = $item->filter('pubDate')->text();

					// get the description of the item
					$text = $item->filter('content|encoded')->text();
					$description = strip_tags($text, '<p>');

					// get the author, else unknow
					$authorSel = 'dc|creator';
					if ($item->filter($authorSel)->count() == 0) $author = "Desconocido";
					else
					{
						$author = $item->filter($authorSel)->text();
					}

					$articles[] = array(
						"title" => $title,
						"link" => $link,
						"pubDate" => $pubDate,
						"description" => $description,
						"author" => $author
					);
				}
			});
		});

		// Return Response array
		return array("articles" => $articles);
	}

	/**
	 * Parse individual posts
	 *
	 * @param String
	 * @return Array
	 */

	private function post($query)
	{
		// create a new Client
		$client = new Client();
		$guzzle = $client->getClient();
		$guzzle->setDefaultOption('verify', true);
		$client->setClient($guzzle);

		// the crawler
		$crawler = $client->request('GET', "http://www.conectica.com/$query");

		// the title
		$title = $crawler->filter('.singleHeader h1')->text();

		// the text
		$text = $crawler->filter('.postContent')->html();

		// get the description of the item
		$text = preg_replace('@<(h3|a)[^>]*class\s*=[^>]*>.*?</\1>@is', "", $text);
		$description = strip_tags($text, '<p><strong><h1><h2><h3><h4>');

		// the author's info
		$author = $crawler->filter('address > a')->text();

		return array(
			'title' => $title,
			'author' => $author,
			'description' => $description,
			'url' => "http://conectica.com/$query"
		);
	}

	/**
	 * Get the link to the news starting from the /content part
	 *
	 * @param String
	 * @return String
	 */

	private function urlSplit($url)
	{
		$url = explode("/", trim($url));
		for ($i=0; $i < 3; $i++) {
		unset($url[$i]);
		}
		return implode("/", $url);
	}

	/**
	 * Return a generic error email, usually for try...catch blocks
	 *
	 * @auhor salvipascual
	 * @return Respose
	 */
	private function respondWithError()
	{
		error_log("WARNING: ERROR ON SERVICE TECNOLOGIA");

		$response = new Response();
		$response->setResponseSubject("Error en peticion");
		$response->createFromText("Lo siento pero hemos tenido un error inesperado. Enviamos una peticion para corregirlo. Por favor intente nuevamente mas tarde.");
		return $response;
	}
}
?>
