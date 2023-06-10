<?php

namespace App\Controller;

use App\Entity\Article;
use App\Factory\JsonResponseFactory;
use App\Repository\ArticleRepository;
use App\Services\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/article')]
class ArticleController extends AbstractController
{
    private array $allowedProperties;
    private array $requiredFields;
    public function __construct(private JsonResponseFactory $jsonResponseFactory, private ApiService $apiService)
    {
        $this->allowedProperties = [
            'find' => [
                'query' => ['page', 'limit'],
                'body' => []
            ],
            'create' => [
                'query' => [],
                'body' => ['title', 'short', 'content', 'comments'],
                'files' => ['thumbnail']
            ],
            'get' => [
                'query' => [],
                'body' => []
            ],
            'edit' => [
                'query' => [],
                'body' => ['title', 'short', 'content', 'comments'],
                'files' => ['thumbnail']
            ],
            'delete' => [
                'query' => [],
                'body' => []
            ]
        ];
        $this->requiredFields = [
            'find' => [
                'query' => [],
                'body' => []
            ],
            'create' => [
                'query' => [],
                'body' => ['title', 'short', 'content'],
                'files' => []
            ],
            'get' => [
                'query' => [],
                'body' => []
            ],
            'edit' => [
                'query' => [],
                'body' => [],
                'files' => []
            ],
            'delete' => [
                'query' => [],
                'body' => []
            ]
        ];
    }

    #[Route('/', name: 'app_article_index', methods: ['GET'])]
    public function index(#[MapQueryParameter] int $page,  #[MapQueryParameter] int $limit, ArticleRepository $articleRepository, Request $request): Response
    {

        $requestValidation = $this->apiService->hasValidBodyAndQueryParameters(
            $request,
            $this->allowedProperties['find']['body'],
            $this->requiredFields['find']['body'],
            $this->allowedProperties['find']['query'],
            $this->requiredFields['find']['query'],
        );


        if (!$requestValidation['yes']) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'description' => 'The request has invalid query parameters or body fields.',
                    'code' => Response::HTTP_BAD_REQUEST,
                    'body' => $requestValidation['body'],
                    'params' => $requestValidation['params']
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }


        try {
            $articles = $articleRepository->findBy([], [], $limit, ($page - 1) * $limit);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_OK],
                    'code' => Response::HTTP_OK,
                    'datas' => $articles,
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $articleRepository->count([]),
                    'totalPages' => (int) ceil($articleRepository->count([]) / $limit)
                ],
                Response::HTTP_OK,
            );
        } catch (\Throwable $th) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
                    'description' => $th->getMessage(),
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/create', name: 'app_article_create', methods: ['POST'])]
    public function create(Request $request, ArticleRepository $articleRepository): JsonResponse
    {
        $requestValidation = $this->apiService->hasValidBodyAndQueryParameters(
            $request,
            $this->allowedProperties['create']['body'],
            $this->requiredFields['create']['body'],
            $this->allowedProperties['create']['query'],
            $this->requiredFields['create']['query'],
        );

        if (!$requestValidation['yes']) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'description' => 'The request has invalid query parameters or body fields.',
                    'code' => Response::HTTP_BAD_REQUEST,
                    'body' => $requestValidation['body'],
                    'params' => $requestValidation['params']
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $bodyData = json_decode($request->getContent(), true);

        $currentUser = $this->getUser();
        $article = new Article();
        $article->setTitle($bodyData['title']);
        $article->setShort($bodyData['short']);
        $article->setContent($bodyData['content']);
        $article->setPublishedAt(new \DateTimeImmutable());
        $article->setPublishedBy($currentUser);
        if(isset($bodyData['comments'])){
            $article->setComments($bodyData['comments']);
        }

        $thumbnail = $request->files->get('thumbnail');
        if ($thumbnail) {

            $uploadThumbnailPathData = $this->apiService->uploadSingleFile($request, 'thumbnail', 'article_thumbnail');

            if ($uploadThumbnailPathData['error']) {
                return $this->jsonResponseFactory->create(
                    (object) [
                        'error' => true,
                        'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                        'description' => 'Thumbnail uploaded failed: ' . $uploadThumbnailPathData['message'],
                        'code' => Response::HTTP_BAD_REQUEST,
                        'body' => null,
                        'params' => null
                    ],
                    Response::HTTP_BAD_REQUEST,
                );
            }

            $article->setThumbnail($uploadThumbnailPathData['datas']);
        }


        try {
            $articleRepository->save($article, true);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_CREATED],
                    'description' => 'The resource has been created.',
                    'code' => Response::HTTP_CREATED,
                    'datas' => $article
                ],
                Response::HTTP_CREATED,
            );
        } catch (\Throwable $th) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
                    'description' => 'The resource has not been created.',
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'datas' => $article
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/get/{id}', name: 'app_article_get', methods: ['GET'])]
    public function get(Article $article = null, Request $request): Response
    {


        if (!$article) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_NOT_FOUND],
                    'description' => 'The requested resource was not found.',
                    'code' => Response::HTTP_NOT_FOUND,
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $requestValidation = $this->apiService->hasValidBodyAndQueryParameters(
            $request,
            $this->allowedProperties['get']['body'],
            $this->requiredFields['get']['body'],
            $this->allowedProperties['get']['query'],
            $this->requiredFields['get']['query'],
        );

        if (!$requestValidation['yes']) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'description' => 'The request has invalid query parameters or body fields.',
                    'code' => Response::HTTP_BAD_REQUEST,
                    'body' => $requestValidation['body'],
                    'params' => $requestValidation['params']
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }



        return $this->jsonResponseFactory->create(
            (object) [
                'error' => false,
                'message' => Response::$statusTexts[Response::HTTP_OK],
                'code' => Response::HTTP_OK,
                'datas' => $article
            ],
            Response::HTTP_OK,
        );
    }

    #[Route('/edit/{id}', name: 'app_article_edit', methods: ['PUT', 'PATCH'])]
    public function edit(Request $request, Article $article = null, ArticleRepository $articleRepository): Response
    {
        if (!$article) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_NOT_FOUND],
                    'description' => 'The requested resource was not found.',
                    'code' => Response::HTTP_NOT_FOUND,
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $requestValidation = $this->apiService->hasValidBodyAndQueryParameters(
            $request,
            $this->allowedProperties['edit']['body'],
            $this->requiredFields['edit']['body'],
            $this->allowedProperties['edit']['query'],
            $this->requiredFields['edit']['query'],
            false
        );

        if (!$requestValidation['yes']) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'description' => 'The request has invalid query parameters or body fields.',
                    'code' => Response::HTTP_BAD_REQUEST,
                    'body' => $requestValidation['body'],
                    'params' => $requestValidation['params']
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $bodyData = json_decode($request->getContent(), true);

        // Ensure at least one field has been provided
        if (count($bodyData) === 0) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'description' => 'At least one field is required to update the resource.',
                    'code' => Response::HTTP_BAD_REQUEST,
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Only update the fields that have been changed (not null and present in the request)
        foreach ($bodyData as $key => $value) {
            if ($value !== null) {
                $setter = 'set' . ucfirst($key);
                $article->$setter($value);
            }
        }

        $thumbnail = $request->files->get('thumbnail');
        if ($thumbnail) {

            $uploadThumbnailPathData = $this->apiService->uploadSingleFile($request, 'thumbnail', 'article_thumbnail');

            if ($uploadThumbnailPathData['error']) {
                return $this->jsonResponseFactory->create(
                    (object) [
                        'error' => true,
                        'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                        'description' => 'Thumbnail uploaded failed: ' . $uploadThumbnailPathData['message'],
                        'code' => Response::HTTP_BAD_REQUEST,
                        'body' => null,
                        'params' => null
                    ],
                    Response::HTTP_BAD_REQUEST,
                );
            }

            $article->setThumbnail($uploadThumbnailPathData['datas']);
        }

        $currentUser = $this->getUser();
        $article->setUpdatedAt(new \DateTimeImmutable());
        $article->setUpdatedBy($currentUser);

        try {
            $articleRepository->save($article, true);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_OK],
                    'description' => 'The resource has been updated.',
                    'code' => Response::HTTP_OK,
                    'datas' => $article
                ],
                Response::HTTP_OK,
            );
        } catch (\Throwable $th) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
                    'description' => 'An error occured while updating the resource.',
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }


    #[Route('/delete/{id}', name: 'app_article_delete', methods: ['DELETE'])]
    public function delete(Request $request, Article $article = null, ArticleRepository $articleRepository): Response
    {

        // Ensure the article exists in the database
        if (!$article) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_NOT_FOUND],
                    'description' => 'The requested resource was not found.',
                    'code' => Response::HTTP_NOT_FOUND,
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $requestValidation = $this->apiService->hasValidBodyAndQueryParameters(
            $request,
            $this->allowedProperties['delete']['body'],
            $this->requiredFields['delete']['body'],
            $this->allowedProperties['delete']['query'],
            $this->requiredFields['delete']['query'],
        );

        if (!$requestValidation['yes']) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'description' => 'The request has invalid query parameters or body fields.',
                    'code' => Response::HTTP_BAD_REQUEST,
                    'body' => $requestValidation['body'],
                    'params' => $requestValidation['params']
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }


        try {
            $articleRepository->remove($article, true);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_OK],
                    'description' => 'The resource has been deleted.',
                    'code' => Response::HTTP_OK,
                ],
                Response::HTTP_OK,
            );
        } catch (\Throwable $th) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
                    'description' => 'An error occured while deleting the resource.',
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
