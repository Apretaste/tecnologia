<?php

use Apretaste\Level;
use Apretaste\Request;
use Apretaste\Response;
use Framework\Alert;
use Framework\Crawler;
use Framework\Database;

class Service
{
	// TODO add https://es.digitaltrends.com/fuentes-rss/

	/**
	 * Get the list of news
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author ricardo
	 */
	public function _main(Request $request, Response &$response)
	{
		$selectedSource = $request->input->data->source ?? false;
		$categoryWhere = $selectedSource ? "WHERE A.source_id = $selectedSource" : "";
		$articles = Database::query("SELECT A.id, A.title, A.pubDate, A.author, A.image, A.imageLink, A.description, A.comments, B.name AS category FROM _tecnologia_articles A LEFT JOIN _tecnologia_sources B ON A.source_id = B.id $categoryWhere ORDER BY pubDate DESC LIMIT 20");

		$inCuba = $request->input->inCuba ?? false;
		$serviceImgPath = SERVICE_PATH . "tecnologia/images";
		$images = ["$serviceImgPath/no-image.png"];
		$techImgDir = SHARED_PUBLIC_PATH . 'content/tecnologia';

		foreach ($articles as $article) {
			$article->title = quoted_printable_decode($article->title);
			$article->pubDate = self::toEspMonth(date('j F, Y', strtotime($article->pubDate)));
			$article->description = quoted_printable_decode($article->description);

			if (!$inCuba) {
				$imgPath = "$techImgDir/{$article->category}/{$article->image}";

				if (!file_exists($imgPath)) {
					$image = Crawler::get($article->imageLink, 'GET', null, [], [], $info);

					if ($info['http_code'] ?? 404 === 200)
						if (!empty($image))
							file_put_contents($imgPath, $image);
				} else {
					$image = file_get_contents($imgPath);
				}

				if (!empty($image)) $images[] = $imgPath;
			} else {
				$article->image = "no-image.png";
			}
		}

		$content = ["articles" => $articles, "selectedSource" => $selectedSource];

		// send data to the view
		$response->setCache(60);
		$response->setLayout('tecnologia.ejs');
		$response->setTemplate("stories.ejs", $content, $images);
	}

	private static function toEspMonth(string $date)
	{
		$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
		$espMonths = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

		return str_replace($months, $espMonths, $date);
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @param Response
	 * @return Response
	 * @throws Exception
	 */
	public function _historia(Request $request, Response $response)
	{
		// get link to the article
		$id = $request->input->data->id ?? false;

		if ($id) {
			$article = Database::query("SELECT * FROM _tecnologia_articles WHERE id='$id'")[0];

			$article->title = quoted_printable_decode($article->title);
			$article->pubDate = self::toEspMonth((date('j F, Y', strtotime($article->pubDate))));
			$article->description = quoted_printable_decode($article->description);
			$article->content = quoted_printable_decode($article->content);
			$article->imageCaption = quoted_printable_decode($article->imageCaption);
			$article->comments = Database::query("SELECT A.*, B.username FROM _tecnologia_comments A LEFT JOIN person B ON A.id_person = B.id WHERE A.id_article='{$article->id}' ORDER BY A.id DESC");
			$article->myUsername = $request->person->username;

			// any global var in js named location changes the location of the url
			$article->artLocation = $article->location;
			unset($article->location);

			foreach ($article->comments as $comment) {
				$comment->inserted = date('d/m/Y · h:i a', strtotime($comment->inserted));
			}

			$images = [];

			// get the image if exist
			$techImgDir = SHARED_PUBLIC_PATH . 'content/tecnologia';
			if (!empty($article->image)) $images[] = "$techImgDir/{$article->image}";

			// send info to the view
			$response->setCache('30');
			$response->setLayout('tecnologia.ejs');
			$response->setTemplate('stories.ejs', $article, $images);
		} else {
			return $this->error($response, "Articulo no encontrado", "No sabemos que articulo estas buscando");
		}
	}

	/**
	 * Return an error message
	 *
	 * @param Response $response
	 * @param String $title
	 * @param String $desc
	 * @return Response
	 * @throws Alert
	 * @author ricardo
	 */
	private function error(Response $response, $title, $desc)
	{
		// display show error in the log
		error_log("[TECNOLOGIA] $title | $desc");

		// return error template
		$response->setLayout('tecnologia.ejs');
		return $response->setTemplate('message.ejs', ["header" => $title, "text" => $desc]);
	}

	/**
	 * Watch the last comments in articles or with no article
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 */

	public function _comentarios(Request $request, Response $response)
	{
		$comments = Database::query("SELECT A.*, B.username, C.title, C.pubDate, C.author FROM _tecnologia_comments A LEFT JOIN person B ON A.id_person = B.id LEFT JOIN _tecnologia_articles C ON C.id = A.id_article ORDER BY A.id DESC LIMIT 20");

		foreach ($comments as $comment) {
			$comment->inserted = date('d/m/Y · h:i a', strtotime($comment->inserted));
			$comment->pubDate = self::toEspMonth(date('j F, Y', strtotime($comment->pubDate)));
		}
		// send info to the view
		$response->setLayout('tecnologia.ejs');
		$response->setTemplate("comments.ejs", ["comments" => $comments, "myUsername" => $request->person->username]);
	}

	/**
	 * Comment an article
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Exception
	 * @author ricardo
	 *
	 */
	public function _comentar(Request $request, Response $response)
	{
		// do not allow guest comments
		if ($request->person->isGuest) {
			return;
		}

		// get comment data
		$comment = $request->input->data->comment;
		$articleId = $request->input->data->article ?? false;

		if ($articleId) {
			// check the note ID is valid
			$article = Database::query("SELECT COUNT(*) AS total FROM _tecnologia_articles WHERE id='$articleId'");
			if ($article[0]->total == "0") return;

			// save the comment
			$comment = Database::escape($comment, 255);
			Database::query("
				INSERT INTO _tecnologia_comments (id_person, id_article, content) VALUES ('{$request->person->id}', '$articleId', '$comment');
				UPDATE _tecnologia_articles SET comments = comments+1 WHERE id='$articleId';");

			// add the experience
			Level::setExperience('NEWS_COMMENT_FIRST_DAILY', $request->person->id);
		} else {
			Database::query("INSERT INTO _tecnologia_comments (id_person, content) VALUES ('{$request->person->id}', '$comment')");
		}
	}
}
