<?php
  use Goutte\Client;

  class tecnologia extends Service {
    
    public function _main(Request $request)
    {
      $response = new Response();
      $response->setResponseSubject("Noticias de tecnología");
      $response->createFromTemplate("basic.tpl", $this->allStories());
      return $response;
    }

    /* 
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
        $response->setResponseSubject("B&uacute;squeda en blanco");
        $response->createFromText("Su b&uacute;squeda parece estar en blanco, debe decirnos qu&eacute; art&iacute;culo desea leer");
        return $response;
      }

      // explode the query string every '/' 
      $pieces = explode("/", $request->query);

      // call to parse the post
      $responseContent = $this->post($request->query);

      // subject changes when user comes from the main menu or from buscar 
      if(strlen($pieces[1]) > 5) $subject = str_replace("-", " ", ucfirst($pieces[1]));
      else $subject = "La historia que pidi&oacute;";

      // send the response
      $response = new Response();
      $response->setResponseSubject($subject);
      $response->createFromTemplate("historia.tpl", $responseContent, $images);
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

      $articles = array();
      $crawler->filter('item')->each(function($item, $i) use (&$articles)
      {
        // the link to the article
        $link = $this->urlSplit($item->filter('feedburner|origLink')->text());

        // show only the content part of the link
        $pieces = explode("/", $link);

        // get title, description, pubDate, and category
        $title = $item->filter('title')->text();

        // get the description
        $description = $item->filter('content|encoded')->text();

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
      });

      return array("articles" => $articles);
    }

    /*
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
      $title = $crawler->filter('h1')->text();

      // the text
      $text = $crawler->filter('#the_content')->html();

      return array(
        'title' => $title,
        'text' => $text,
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
  }
?>
