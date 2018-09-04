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
		$response->setCache(720);
		$response->setResponseSubject("Noticias de tecnología");
		$response->createFromTemplate("basic.tpl", $allStories);
		return $response;
	}

	/**
	 * Subservice _historia to call the view of a single post
	 *
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

		$page = stripos($request->query,"hipertextual")?1:(stripos($request->query,"xataka")?2:false);

		if(!$page){
			$response = new Response();
			$response->setResponseSubject("Busqueda invalida");
			$response->createFromText("Esta intentando buscar un articulo que no es parte de las publicaciones listadas en el servicio");
			return $response;
		}

		// call to parse the post
		try{
			$responseContent = $this->post($request->query, $page);
		} catch(Exception $e) {
			return $this->respondWithError();
		}

		// send the response
		$response = new Response();
		$response->setCache();
		$response->setResponseSubject($responseContent['title']);
		$response->createFromTemplate("historia.tpl", $responseContent);
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
	 * Get all stories from https://www.infotechnology.com/ and https://hipertextual.com/
	 * @return Array
	 */
	private function allStories(){
		// load from cache if exists
		$cacheFile = $this->utils->getTempDir() . date("Ymd") . "_tecnologia.tmp";

		if (file_exists($cacheFile) && (time()-filemtime($cacheFile))<12*60*60) {
			$articles = json_decode(file_get_contents($cacheFile),true);
		}else{
			$client = new Client();
			$crawler = $client->request('GET', "https://hipertextual.com/");

			$crawler->filter('div.wrapperDestacados > div.destacado, div.destacados--sidebar > div.destacado--sidebar')->each(function($item) use(&$articles){
				$articles[]=['title' => $item->filter('a')->attr('title'),
							 'link' => $item->filter('a')->attr('href'),
							 'category' => false, 'categoryLink' => false, 'description' => false];
			});

			$crawler = $client->request('GET', "https://www.xataka.com/");

			$crawler->filter('section:nth-child(2) div.section-recent-list div.abstract-content')->each(function($item) use(&$articles){
				$articles[]=['title' => $item->filter('header > h2.abstract-title')->text(),
							 'link' => $item->filter('h2.abstract-title > a')->attr('href'),
							 'category' => $item->filter('a.abstract-taxonomy')->text(),
							 'categoryLink' => "https://www.xataka.com".$item->filter('header > a')->attr('href'),
							 'description' => $item->filter('div.abstract-excerpt > p')->text()];
			});
			shuffle($articles);
			// save cache file for today
			file_put_contents($cacheFile, json_encode($articles));
		}
		return array("articles" => $articles);
	}

	/**
	 * Collect the array of news by category
	 *
	 * @param String
	 * @return Array
	 */
	private function listByCategory(String $category)
	{
		$tildes = ['Á','É','Í','Ó','Ú','á','é','í','ó','ú'];
		$replace = ['A','E','I','O','U','a','e','i','o','u'];
		$category = str_replace($tildes, $replace, $category);
		$client = new Client();
		$articles = array();
		$crawler = $client->request('GET', "https://www.xataka.com/categoria/".$category);
		$crawler->filter('div.section-recent-list div.abstract-content')->each(function($item) use(&$articles){
			$articles[]=['title' => $item->filter('header > h2.abstract-title')->text(),
						 'link' => $item->filter('h2.abstract-title > a')->attr('href'),
						 'category' => $item->filter('a.abstract-taxonomy')->text(),
						 'categoryLink' => "https://www.xataka.com".$item->filter('header > a')->attr('href'),
						 'description' => $item->filter('div.abstract-excerpt > p')->text()];
		});
		shuffle($articles);

		return array("articles" => $articles);
	}

	/**
	 * Parse individual posts
	 *
	 * @param String
	 * @return Array
	 */

	private function post(String $url, $page)
	{
		// create a new Client and the crawler
		$client = new Client();
		$crawler = $client->request('GET', $url);

		switch ($page) {
			case 1:
				// the title
				$title = $crawler->filter('h1.headlineSingle__title')->text();

				// the text
				$crawler->filter('div.historia > p, div.historia > blockquote, div.historia > ul')->each(function($item) use (&$text){
					$text .= $item->html()."<br>";
				});

				$description = strip_tags($text, '<strong><br><h1><h2><h3><h4><ul><li>');

				// the author's info
				$author = $crawler->filter('a.author__name')->text();

				return array(
					'title' => $title,
					'author' => $author,
					'description' => $description,
					'url' => $url
				);
				break;
			case 2:
				// the title
				$title = $crawler->filter('header > h1')->text();
				
				// the text
				$crawler->filter('div.article-content p:not(:last-child)')->each(function($item) use (&$text){
					$text .= $item->html()."<br><br>";
				});
				
				$description = strip_tags($text, '<strong><br><h1><h2><h3><h4><ul><li>');

				// the author's info
				$author = $crawler->filter('a.article-author-link')->text();

				return array(
					'title' => $title,
					'author' => $author,
					'description' => $description,
					'url' => $url
				);
				break;
			default:
				# code...
				break;
		}
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
