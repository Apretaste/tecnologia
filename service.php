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

    /**
     * Get all stories from Conectica.com, a
     * tech blog from Latin America. 
     *
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
        $link = $this->urlSplit($item->filter('link')->text());

        // show only the content part of the link
        $pieces = explode("/", $link);

        // get title, description, pubDate, and category
        $title = $item->filter('title')->text();

        // get the description
        $description = $item->filter('content|encoded')->text();

        // get the publication date
        $pubDate = $item->filter('pubDate')->text();

        /*
         * get category
         * TODO: improve this
         */
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

        /* traverse and show all the categories of the <item>
         * TODO: fix this
         */
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
