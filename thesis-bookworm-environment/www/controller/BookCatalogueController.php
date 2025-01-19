<?php

namespace Bookworm\controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response as SlimResponse;
use Bookworm\service\TwigRenderer;
use Bookworm\service\BookCatalogueService;
use Bookworm\service\AuthService;
use Psr\SimpleCache\CacheInterface;

class BookCatalogueController
{
    private $twig;
    private $service;
    private $authController;
    private $authService;
    private $cache;

    public function __construct(TwigRenderer $twig, BookCatalogueService $service, AuthController $authController, AuthService $authService, CacheInterface $cache)
    {
        $this->twig = $twig;
        $this->service = $service;
        $this->authController = $authController;
        $this->authService = $authService;
        $this->cache = $cache;
    }

    public function getUserIdFromSession(): ?int
    {
        session_start();
        return $_SESSION['user_id'] ?? null;
    }

    public function showAddBookForm(Request $request, Response $response): Response
    {
        $userId = $this->getUserIdFromSession();

        if (!$userId) {
            return $response->withHeader('Location', '/sign-in')->withStatus(302);
        }

        $isLoggedIn = isset($_SESSION['user_id']);
        $searchResults = $this->fetchBookSearchResults();

        $htmlContent = $this->twig->render('catalogue.twig', [
            'searchResults' => $searchResults,
            'isLoggedIn' => $isLoggedIn,
            'userId' => $userId
        ]);

        $htmlResponse = new SlimResponse();
        $htmlResponse->getBody()->write($htmlContent);

        $htmlResponse = $htmlResponse->withHeader('Content-Type', 'text/html');

        return $htmlResponse;
    }

    private function fetchBookSearchResults()
    {
        // $cacheKey = 'book_search_results';
        // $cachedResults = $this->cache->get($cacheKey);
    
        // Return cached results if available - functionality suspended/obsolete since the app was moved from open library to google books API which is quick enough and doesn't require caching and it was just overcomplicating adding new books
        // if ($cachedResults) {
        //     return $cachedResults;
        // }
    
        $categories = ['action', 'adventure', 'mystery', 'fantasy', 'romance'];
        $allSearchResults = [];
        $maxBooksPerCategory = 15;  // Adjust based on API and hardware limits
    
        $apiKey = 'AIzaSyBPGOtKJrNyfaRKbpnPhNuPfTUmxfkro9Y';
    
        foreach ($categories as $category) {
            $category_url = "https://www.googleapis.com/books/v1/volumes?q={$category}&maxResults={$maxBooksPerCategory}&key={$apiKey}";
    
            $json = file_get_contents($category_url);
    
            if ($json === false) {
                error_log("Failed to fetch data for category: $category");
                continue;
            }
    
            $data = json_decode($json, true);
            $searchResults = [];
    
            if (isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    $volumeInfo = $item['volumeInfo'];
    
                    $book = [
                        'title' => $volumeInfo['title'] ?? 'Unknown Title',
                        'author_names' => $volumeInfo['authors'] ?? ['Unknown'],
                        'cover_url' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
                        'description' => $volumeInfo['description'] ?? '',
                        'pages' => $volumeInfo['pageCount'] ?? 0,
                    ];
    
                    $bookId = $this->service->getBookId($book['title'], implode(', ', $book['author_names']));

                    //default if the cover url is null
                    $coverUrl = $book['cover_url'] ?? "https://static.wikia.nocookie.net/gijoe/images/b/bf/Default_book_cover.jpg/revision/latest?cb=20240508080922";
    
                    if (!$bookId) {
                        

                        $bookId = $this->service->saveBook(
                            $book['title'],
                            implode(', ', $book['author_names']),
                            $book['description'],
                            $book['pages'],
                            $coverUrl,
                            $category
                        );
                    }
    
                    $searchResults[] = [
                        'id' => $bookId,
                        'title' => $book['title'],
                        'author_names' => $book['author_names'],
                        'cover_url' => $coverUrl,
                        'description' => $book['description'],
                        'pages' => $book['pages'],
                    ];
                }
    
                $allSearchResults[$category] = $searchResults;
            }
        }
    
        $alreadyCreatedBooks = $this->service->getAllBooks();
    
        foreach ($alreadyCreatedBooks as $existingBook) {
            $book = [
                'id' => $existingBook['id'],
                'title' => $existingBook['title'],
                'author_names' => explode(', ', $existingBook['author']),
                'cover_url' => $existingBook['cover'],
                'description' => $existingBook['description'],
                'pages' => $existingBook['pages'],
            ];
    
            // Since database was already created without accounting for category and API books are fetched per category, for user added books a shortcut was used and category is stored as a first string of the description field in the database
            $allSearchResults[strtok($existingBook['description'], " ")][] = $book;
        }
    
        // // Cache the results for 1 hour
        // $this->cache->set($cacheKey, $allSearchResults, 3600);
        // caching obsolete due to the change in external api used
    
        return $allSearchResults;
    }


    // private function getBookDescription($key)
    // {
    //     if ($key === null) {
    //         return '';
    //     }

    //     $bookUrl = "https://openlibrary.org{$key}.json";
    //     $bookJson = file_get_contents($bookUrl);
    //     $bookData = json_decode($bookJson, true);

    //     if ($bookData !== null && isset($bookData['description'])) {
    //         return is_array($bookData['description']) ? $bookData['description']['value'] : $bookData['description'];
    //     }

    //     return '';
    // }

    // private function getBookPage($key)
    // {
    //     if ($key === null) {
    //         return 0;
    //     }

    //     $bookUrl = "https://openlibrary.org{$key}.json";
    //     $bookJson = file_get_contents($bookUrl);
    //     $bookData = json_decode($bookJson, true);

    //     if ($bookData !== null && isset($bookData['number_of_pages'])) {
    //         return $bookData['number_of_pages'];
    //     }

    //     return 0;
    // }

    public function addBookToCatalogue(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
    
            $bookId = $this->service->addBookToCatalogue($data);
    
            if ($bookId !== null) {
                $searchResults = $this->fetchBookSearchResults();
                $responseData = ['message' => 'Book created successfully', 'book_id' => $bookId, 'searchResults' => $searchResults];
                $statusCode = 201;

                
            } else {
                $responseData = ['error' => 'Failed to create book'];
                $statusCode = 400;
            }
    
            $jsonResponse = new SlimResponse();
            $jsonResponse->getBody()->write(json_encode($responseData));
            $jsonResponse = $jsonResponse->withHeader('Content-Type', 'application/json');
    
            return $jsonResponse->withStatus($statusCode);

        } catch (\Exception $e) {
            return $response->withStatus(500);
        }
    }

    public function getBookDetails(Request $request, Response $response, $args): Response
    {
        $bookId = $args['id'];
        $bookDetails = $this->service->getBookDetails($bookId);

        $userId = $this->getUserIdFromSession();

        if (!$userId) {
            return $response->withHeader('Location', '/sign-in')->withStatus(302);
        }

        $isLoggedIn = isset($_SESSION['user_id']);

        $htmlContent = $this->twig->render('book_details.twig', [
            'book' => $bookDetails,
            'isLoggedIn' => $isLoggedIn,
            'userId' => $userId
        ]);

        $htmlResponse = new SlimResponse();
        $htmlResponse->getBody()->write($htmlContent);
        return $htmlResponse->withHeader('Content-Type', 'text/html');
    }
}
