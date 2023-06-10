<?php

namespace App\Controller;

use App\Entity\PasswordRequestToken;
use App\Factory\JsonResponseFactory;
use App\Repository\UserRepository;
use App\Services\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\Repository\PasswordRequestTokenRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/api/v1/auth')]
class SecurityController extends AbstractController
{
    private array $allowedProperties;
    private array $requiredFields;
    public function __construct(private JsonResponseFactory $jsonResponseFactory, private ApiService $apiService)
    {
        $this->allowedProperties = [
            'register' => [
                'query' => [],
                'body' => ['email', 'password', 'firstName', 'lastName']
            ],
            'password_request' => [
                'query' => [],
                'body' => ['email']
            ],
            'password_reset' => [
                'query' => [],
                'body' => ['token', 'password']
            ],
        ];
        $this->requiredFields = [
            'register' => [
                'query' => [],
                'body' => ['email', 'password']
            ],
            'password_request' => [
                'query' => [],
                'body' => ['email']
            ],
            'password_reset' => [
                'query' => [],
                'body' => ['token', 'password']
            ],
        ];
    }

    #[Route('/register', name: 'app_security_register', methods: ['POST'])]
    public function index(Request $request, UserRepository $userRepository,  UserPasswordHasherInterface $passwordHasher): Response
    {

        $requestValidation = $this->apiService->hasValidBodyAndQueryParameters(
            $request,
            $this->allowedProperties['register']['body'],
            $this->requiredFields['register']['body'],
            $this->allowedProperties['register']['query'],
            $this->requiredFields['register']['query'],
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

        $existingUser = $userRepository->findOneBy(['email' => $bodyData['email']]);
        if ($existingUser) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_CONFLICT],
                    'description' => 'The account already exists.',
                    'code' => Response::HTTP_CONFLICT,
                    'datas' => $existingUser
                ],
                Response::HTTP_CONFLICT,
            );
        }


        $user = new User();
        $user->setEmail($bodyData['email']);
        $user->setPassword($passwordHasher->hashPassword($user, $bodyData['password']));
        
        if(isset($bodyData['firstName'])){
            $user->setFirstName($bodyData['firstName']);
        }
        
        if(isset($bodyData['lastName'])){
            $user->setLastName($bodyData['lastName']);
        }

        $user->setUsername($bodyData['email']);

        try {
            $userRepository->save($user, true);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_CREATED],
                    'description' => 'The account is successfully created.',
                    'code' => Response::HTTP_OK,
                    'datas' => $user
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
                    'datas' => $user
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/password-change-request', name: 'app_security_password-change-request', methods: ['POST'])]
    public function passwordChangeRequest(User $user, Request $request, PasswordRequestTokenRepository $passwordRequestTokenRepository, UserRepository $userRepository, MailerInterface $mailer): Response
    {

       

        $requestValidation = $this->apiService->hasValidBodyAndQueryParameters(
            $request,
            $this->allowedProperties['password_request']['body'],
            $this->requiredFields['password_request']['body'],
            $this->allowedProperties['password_request']['query'],
            $this->requiredFields['password_request']['query'],
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
        $user = $userRepository->findOneBy(['email' => $bodyData['email']]);

        if (!$user) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_NOT_FOUND],
                    'description' => 'The user does not exist.',
                    'code' => Response::HTTP_NOT_FOUND,
                    'datas' => $user
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $passwordRequestToken = $passwordRequestTokenRepository->findOneBy(['user' => $user]);

        if ($passwordRequestToken) {
            $passwordRequestTokenRepository->delete($passwordRequestToken);
        }

        $passwordRequestToken = new PasswordRequestToken();
        $passwordRequestToken->setOwner($user);
        $passwordRequestToken->setToken($this->apiService->generateRandomString(6));
        $passwordRequestToken->setExpirationDate(new \DateTimeImmutable('+1 day'));
        $passwordRequestToken->setCreatedAt(new \DateTimeImmutable());

        try {
            $passwordRequestTokenRepository->save($passwordRequestToken, true);
            $html = $this->renderView('emails/password_request.html.twig', [
                'token' => $passwordRequestToken->getToken(),
                'user' => $user
            ]);
            $email = (new Email())
                ->from($this->getParameter('admin_email'))
                ->to($user->getEmail())
                ->subject('Password change request')
                ->html($html);

            $mailer->send($email);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_OK],
                    'description' => 'The password change request is successfully created.',
                    'code' => Response::HTTP_OK,
                    'datas' => $passwordRequestToken
                ],
                Response::HTTP_OK,
            );
        } catch (\Throwable $th) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
                    'description' => 'The password change request has not been created.',
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'datas' => $passwordRequestToken
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/password-change', name: 'app_security_password-change', methods: ['POST'])]
    public function passwordChange(Request $request, PasswordRequestTokenRepository $passwordRequestTokenRepository, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $requestValidation = $this->apiService->hasValidBodyAndQueryParameters(
            $request,
            $this->allowedProperties['password_change']['body'],
            $this->requiredFields['password_change']['body'],
            $this->allowedProperties['password_change']['query'],
            $this->requiredFields['password_change']['query'],
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
        $user = $userRepository->findOneBy(['email' => $bodyData['email']]);

        if (!$user) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_NOT_FOUND],
                    'description' => 'The user does not exist.',
                    'code' => Response::HTTP_NOT_FOUND,
                    'datas' => $user
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $passwordRequestToken = $passwordRequestTokenRepository->findOneBy(['user' => $user]);

        if (!$passwordRequestToken) {
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_NOT_FOUND],
                    'description' => 'The password change request does not exist.',
                    'code' => Response::HTTP_NOT_FOUND,
                    'datas' => $passwordRequestToken
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($passwordRequestToken->getToken() !== $bodyData['token']) {
            return $this->jsonResponseFactory->create(
                (object) [ 
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'description' => 'The token is invalid.',
                    'code' => Response::HTTP_BAD_REQUEST,
                    'datas' => $passwordRequestToken
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if ($passwordRequestToken->getExpirationDate() < new \DateTimeImmutable()) {
            $passwordRequestTokenRepository->delete($passwordRequestToken);
            return $this->jsonResponseFactory->create(
                (object) [ 
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'description' => 'The token is expired.',
                    'code' => Response::HTTP_BAD_REQUEST,
                    'datas' => $passwordRequestToken
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!$this->apiService->isValidPassword($bodyData['password'])) {
            return $this->jsonResponseFactory->create(
                (object) [ 
                    'error' => true,
                    'message' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'description' => 'The password is invalid. It must contain at least 6 characters with uppercase, lowercase, symbol and digit included.',
                    'code' => Response::HTTP_BAD_REQUEST,
                    'datas' => $passwordRequestToken
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $user->setPassword($passwordHasher->hashPassword($user, $bodyData['password']));
        $passwordRequestTokenRepository->delete($passwordRequestToken);

        try {
            $userRepository->save($user, true);
            $html = $this->renderView('emails/password_changed.html.twig', [
                'user' => $user
            ]);
            $email = (new Email())
                ->from($this->getParameter('admin_email'))
                ->to($user->getEmail())
                ->subject('Password change confirmation')
                ->html($html);

            $mailer->send($email);
            return $this->jsonResponseFactory->create(
                (object) [
                    'error' => false,
                    'message' => Response::$statusTexts[Response::HTTP_OK],
                    'description' => 'The password is successfully changed.',
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
                    'description' => 'The password has not been changed.',
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'datas' => $user
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

    }
}
