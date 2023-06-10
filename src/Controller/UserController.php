<?php

namespace App\Controller;

use App\Entity\User;
use App\Factory\JsonResponseFactory;
use App\Repository\UserRepository;
use App\Services\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/user')]
class UserController extends AbstractController
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
            'new' => [
                'query' => [],
                'body' => ['firstName', 'lastName', 'email', 'password']
            ],
            'show' => [
                'query' => [],
                'body' => []
            ],
            'edit' => [
                'query' => [],
                'body' => ['firstName', 'lastName', 'email']
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
            'new' => [
                'query' => [],
                'body' => ['firstName', 'lastName', 'email', 'password']
            ],
            'show' => [
                'query' => [],
                'body' => []
            ],
            'edit' => [
                'query' => [],
                'body' => ['firstName', 'lastName', 'email']
            ],
            'delete' => [
                'query' => [],
                'body' => []
            ]
        ];
    }
    
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(#[MapQueryParameter] int $page,  #[MapQueryParameter] int $limit, UserRepository $userRepository, Request $request ): Response
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
            $users = $userRepository->findBy([], [], $limit, ($page - 1) * $limit);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_OK],
                    'code' => Response::HTTP_OK,
                    'datas' => $users,
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $userRepository->count([]),
                    'totalPages' => (int) ceil($userRepository->count([]) / $limit)
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

    #[Route('/new', name: 'app_user_new', methods: ['POST'])]
    public function new(Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $requestValidation = $this->apiService->hasValidBodyAndQueryParameters(
            $request,
            $this->allowedProperties['new']['body'],
            $this->requiredFields['new']['body'],
            $this->allowedProperties['new']['query'],
            $this->requiredFields['new']['query'],
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


        $user = new User();
        $user->setFirstName($bodyData['firstName'] ?? null);
        $user->setLastName($bodyData['lastName'] ?? null);
        $user->setEmail($bodyData['email'] ?? null);
        //$user->setUsername($bodyData['email'] ?? null);
        $user->setPassword($passwordHasher->hashPassword($user, $bodyData['password'] ?? null));



        try {
            $userRepository->save($user, true);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_CREATED],
                    'description' => 'The resource has been created.',
                    'code' => Response::HTTP_CREATED,
                    'datas' => $user
                ],
                Response::HTTP_CREATED,
            );
        } catch (\Throwable $th) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
                    'description' => $th->getMessage(),
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'datas' => $user
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user = null, Request $request): Response
    {

        
        if (!$user) {
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
            $this->allowedProperties['show']['body'],
            $this->requiredFields['show']['body'],
            $this->allowedProperties['show']['query'],
            $this->requiredFields['show']['query'],
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
                'datas' => $user
            ],
            Response::HTTP_OK,
        );
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['PUT'])]
    public function edit(Request $request, User $user = null, UserRepository $userRepository): Response
    {
        if (!$user) {
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

        // Only update the fields that have been changed (not null and present in the request)
        foreach ($bodyData as $key => $value) {
            if ($value !== null) {
                $setter = 'set' . ucfirst($key);
                $user->$setter($value);
            }
        }

        try {
            $userRepository->save($user, true);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_OK],
                    'description' => 'The resource has been updated.',
                    'code' => Response::HTTP_OK,
                    'datas' => $user
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


    #[Route('/{id}', name: 'app_user_delete', methods: ['DELETE'])]
    public function delete(Request $request, User $user = null, UserRepository $userRepository): Response
    {
        
        // Ensure the user exists in the database
        if (!$user) {
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
            $userRepository->remove($user, true);
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
