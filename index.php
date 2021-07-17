<?php
/*
* TinyBlog - A tiny drop in place markdown blog. Uses a single file to setup a simple blog with very basic Markdown capability.
* Author: Cory Borrow
* Created: Jul 17, 2021
* Revsion: 1
*/

define( 'DATADIR', dirname(__FILE__) . '/posts');
define( 'VIEWSDIR', dirname(__FILE__) . '/views');
define( 'BASEURL', 'http://localhost/tinyblog' );
define( 'BASEURLPATH', '/tinyblog/');

if(!function_exists('str_starts_with')) {
	function str_starts_with($needle, $haystack) {
		if(substr($haystack, 0, strlen($needle)) == $needle)
			return true;
		return false;
	}
}

if(!function_exists('str_ends_with')) {
	function str_ends_with($needle, $haystack) {
		if(substr($haystack, -(strlen($needle))) == $needle)
			return true;
		return false;
	}
}

class TinyBlog {
	protected $posts = [];
	protected $actions = [];

	public function __construct() {
		$this->actions = [
			'index' => 'showIndexPage',
			'posts' => 'showPost'
		];
	}

	public function totalPosts() {
		return count($this->posts);
	}

	public function showIndexPage() {
		if(count($this->posts) == 0) {
			echo "<h3>No posts yet exist, create some by adding markdown files in your configured posts folder</h3>";
		}
		else {
			$page = 1;

			if(isset($_GET['page']) && is_numeric($_GET['page']))
				$page = $_GET['page'];

			$postNames = $this->getPostsByPage($page);
			$posts = [];

			foreach($postNames as $post) {
				$content = $this->getPostContent($post);

				if($content != null) {
					$content = $this->parseMarkdown($content);
					$posts[] = $content;
				}
			}

			echo $this->renderView('index', ['posts' => $posts, 'page' => $page, 'blog' => $this]);
		}
	}

	public function showPost($name) {
		$post = $this->getPostByName($name);

		if($post != null) {
			//We will be converting the markdown file to HTML and displaying in a view
			$path = DATADIR . "/" . trim($post) . ".md";
			echo $this->parseMarkdown(file_get_contents($path));
		}
		else {
			echo "<h1>404 Page Not Found :(</h1>";
			echo "<p>The page or post you are looking for does not exist";
		}
	}

	public function getPostsByPage($page = 1, $count = 10) {
		$page = ($page > 0) ? ($page - 1) : $page;
		$firstPage = ($page * $count);
		$lastPage = $firstPage + $count;

		if($firstPage > $this->totalPosts())
			return null;
		
		if($lastPage > $this->totalPosts())
			$lastPage = $this->totalPosts() - $firstPage;

		return array_slice($this->posts, $firstPage, ($lastPage - $firstPage));
	}

	public function run() {
		$this->getFileList();

		$uri = $this->getRouteableUri();
		$route = $this->parseUri($uri);

		if(array_key_exists($route['action'], $this->actions)) {
			call_user_func_array([$this, $this->actions[$route['action']]], $route['args']);
		}
	}

	protected function getRouteableUri() {
		if(isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO']))
			return $_SERVER['PATH_INFO'];
		else {
			$uri = $_SERVER['REQUEST_URI'];
			
			if(strpos($uri, BASEURL) !== false)
				return substr($uri, strlen(BASEURL));
			else if(strpos($uri, BASEURLPATH) !== false)
				return substr($uri, strlen(BASEURLPATH));
			else
				return $uri;
		}
	}

	protected function parseUri($uri) {
		if(strlen($uri) == 0) {
			return ['action' => 'index', 'args' => []];
		}
		else {
			$uriParts = explode('/', $uri);
			$route = ['action' => 'index', 'args' => []];

			if(count($uriParts) > 0) {
				$route['action'] = $uriParts[0];

				if(count($uriParts) > 1)
					$route['args'] = array_slice($uriParts, 1);
				else
					$route['args'] = null;
			}

			return $route;
		}
	}

	protected function getFileList() {
		if(file_exists("posts.cache") && (time() - filemtime("posts.cache")) < 86400) {
			$this->posts = file("posts.cache");
		}
		else {
			$files = scandir(DATADIR);

			foreach($files as $file) {
				if(is_dir($file)) {
					//Do nothing for now, not yet sure what I want to do for directories. Maybe leave it simple, all files in one dir
					//$this->posts[] = $this->getFileList($file);
				}
				else {
					if(str_ends_with(".md", $file)) {
						$this->posts[] = explode(".", $file)[0];
					}
				}
			}

			if(file_exists("posts.cache")) { unlink("posts.cache"); }
			file_put_contents("posts.cache", implode("\n", $this->posts));
		}
	}

	protected function getPostByName($name) {
		foreach($this->posts as $post) {
			if(stristr($post, $name)) {
				return $post;
			}
		}
		return null;
	}

	protected function getPostContent($name) {
		$post = $this->getPostByName($name);
		$path = DATADIR . "/" . trim($post) . ".md";

		if(file_exists($path))
			return file_get_contents($path);
		return null;
	}

	protected function renderView($viewName, $data) {
		$view = VIEWSDIR . "/{$viewName}" . ".php";

		if(file_exists($view)) {
			ob_start();

			extract($data);

			include $view;

			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}
		return "<h1>Error 404. :(</h1> <p>Sorry the page you were looking for couldn't be found</p>";
	}

	protected function parseMarkdown($content) {
		$lines = explode("\n", $content);

		$output = "";
		$inParagraph = false;

		foreach($lines as $line) {
			//Headings
			$line = preg_replace("/###### (.*)/", "<h6>$1</h6>", $line);
			$line = preg_replace("/##### (.*)/", "<h5>$1</h5>", $line);
			$line = preg_replace("/#### (.*)/", "<h4>$1</h4>", $line);
			$line = preg_replace("/### (.*)/", "<h3>$1</h3>", $line);
			$line = preg_replace("/## (.*)/", "<h2>$1</h2>", $line);
			$line = preg_replace("/# (.*)/", "<h1>$1</h1>", $line);
			
			//Horizontal Rules
			$line = preg_replace("/^---$/", "<hr />", $line);

			//Paragraphs
			if(!str_starts_with('<', $line)) {
				if(strlen($line) <= 1) {
					if($inParagraph) {
						$line = "</p>";
						$inParagraph = false;
					}	
				}
				else {
					if(!$inParagraph) {
						$line = "<p>" . $line;
						$inParagraph = true;
					}
				}
			}

			$output .= $line;
		}

		//Bold and Italic
		$output = preg_replace("/\*\*\*(.*)\*\*\*/", "<strong><em>$1</em></strong>", $output);
		$output = preg_replace("/\*\*(.*)\*\*/", "<strong>$1</strong>", $output);
		$output = preg_replace("/\*(.*)\*/", "<em>$1</em>", $output);

		//Links
		$output = preg_replace("/\[(.*)\]\((.*)\)/", "<a href=\"$2\">$1</a>", $output);

		//Images
		$output = preg_replace("/!\[(.*)!\]\((.*)\)/", "<img src=\"$2\" alt=\"$1\" />", $output);

		//Code and Pre
		//$output = preg_replace("/```(.*)```/", "<pre><code>$1</code></pre>", $output);
		$output = preg_replace("/``(.*)``/", "<pre>$1</pre>", $output);
		$output = preg_replace("/`(.*)`/", "<code>$1</code>", $output);

		if($inParagraph)
			$output .= "</p>";

		return $output;
	}
}

$tb = new TinyBlog();
$tb->run();
?>