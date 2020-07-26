<?php

use Apretaste\Level;
use Apretaste\Request;
use Apretaste\Response;
use Framework\Alert;
use Framework\Crawler;
use Framework\Database;
use Apretaste\Challenges;

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
		$sourceWhere = $selectedSource ? "WHERE A.source_id = $selectedSource" : "";
		$articles = Database::query("SELECT A.id, A.title, A.pubDate, A.author, A.image, A.imageLink, A.description, A.comments, B.name AS source FROM _tecnologia_articles A LEFT JOIN _tecnologia_sources B ON A.source_id = B.id $sourceWhere ORDER BY pubDate DESC LIMIT 20");

		$inCuba = $request->input->inCuba ?? false;
		$serviceImgPath = SERVICE_PATH . "tecnologia/images";
		$images = ["$serviceImgPath/no-image.png"];
		$techImgDir = SHARED_PUBLIC_PATH . 'content/tecnologia';

		foreach ($articles as $article) {
			$article->title = quoted_printable_decode($article->title);
			$article->pubDate = self::toEspMonth(date('j F, Y', strtotime($article->pubDate)));
			$article->description = quoted_printable_decode($article->description);

			if (!$inCuba) {
				$source = str_replace(' ', '_', $article->source);
				$imgPath = "$techImgDir/{$source}/{$article->image}";

				if (!file_exists($imgPath)) {
					$image = Crawler::get($article->imageLink, 'GET', null, [], [], $info);

					if ($info['http_code'] ?? 404 === 200) {
						if (!empty($image)) {
							file_put_contents($imgPath, $image);
						}
					}
				} else {
					$image = file_get_contents($imgPath);
				}

				if (!empty($image)) {
					$images[] = $imgPath;
				}
			} else {
				$article->image = "no-image.png";
			}
		}

		$content = [
			"articles" => $articles, "selectedSource" => $selectedSource,
			'isGuest' => $request->person->isGuest, 'barTitle' => "Noticias"
		];

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
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 * @throws Alert
	 */
	public function _historia(Request $request, Response $response)
	{
		// get link to the article
		$id = $request->input->data->id ?? false;

		if ($id) {
			$article = Database::query("SELECT A.*, B.name AS source FROM _tecnologia_articles A LEFT JOIN _tecnologia_sources B ON A.source_id = B.id WHERE A.id='$id'")[0];

			$article->title = quoted_printable_decode($article->title);
			$article->pubDate = self::toEspMonth((date('j F, Y', strtotime($article->pubDate))));
			$article->description = quoted_printable_decode($article->description);
			$article->content = quoted_printable_decode($article->content);
			$article->imageCaption = quoted_printable_decode($article->imageCaption);
			$article->comments = Database::query("SELECT A.*, B.username, B.avatar, B.avatarColor, B.gender FROM _tecnologia_comments A LEFT JOIN person B ON A.id_person = B.id WHERE A.id_article='{$article->id}' ORDER BY A.id DESC");

			foreach ($article->comments as $comment) {
				$comment->inserted = date('d/m/Y · h:i a', strtotime($comment->inserted));
				$comment->avatar = $comment->avatar ?? ($comment->gender === 'F' ? 'chica' : 'hombre');
			}

			$article->isGuest = $request->person->isGuest;
			$article->barTitle = "Noticias";
			$article->username = $request->person->username;
			$article->avatar = $request->person->avatar;
			$article->avatarColor = $request->person->avatarColor;

			$images = [];

			// get the image if exist
			$source = str_replace(' ', '_', $article->source);
			$techImgDir = SHARED_PUBLIC_PATH . 'content/tecnologia';
			if (!empty($article->image)) {
				$images[] = "$techImgDir/{$source}/{$article->image}";
			}

			// challenges
			Challenges::track($request->person->id, 'tecnologia-5', [], static function ($track) use ($request) {
				if (!is_array($track)) {
					$track = [];
				}
				$track[$request->input->data->id] = true;
				if (count($track) >= 5) {
					return 5;
				}
				return $track + 1;
			});

			// send info to the view
			$response->setCache('30');
			$response->setLayout('tecnologia.ejs');
			$response->setTemplate('story.ejs', $article, $images);
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
		return $response->setTemplate('message.ejs', ["header" => $title, "text" => $desc, 'barTitle' => "Lo sentimos"]);
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
		$comments = Database::query("SELECT A.*, B.username, B.avatar, B.avatarColor, B.gender, C.title, C.pubDate, C.author FROM _tecnologia_comments A LEFT JOIN person B ON A.id_person = B.id LEFT JOIN _tecnologia_articles C ON C.id = A.id_article ORDER BY A.id DESC LIMIT 20");

		foreach ($comments as $comment) {
			$comment->inserted = date('d/m/Y · h:i a', strtotime($comment->inserted));
			$comment->pubDate = self::toEspMonth(date('j F, Y', strtotime($comment->pubDate)));
			$comment->title = quoted_printable_decode($comment->title);
			$comment->avatar = $comment->avatar ?? ($comment->gender === 'F' ? 'chica' : 'hombre');
		}

		$content = [
			"comments" => $comments,
			"isGuest" => $request->person->isGuest,
			'barTitle' => "Comentarios",
			'username' => $request->person->username,
			'avatar' => $request->person->avatar,
			'avatarColor' => $request->person->avatarColor
		];

		// send info to the view
		$response->setLayout('tecnologia.ejs');
		$response->setTemplate("comments.ejs", $content);
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
			if ($article[0]->total == "0") {
				return;
			}

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
