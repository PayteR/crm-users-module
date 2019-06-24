<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Email\EmailValidator;
use Nette\Http\Response;
use Nette\Security\Passwords;
use Nette\Utils\Validators;

class UsersEmailHandler extends ApiHandler
{
    private $userManager;

    private $emailValidator;

    public function __construct(UserManager $userManager, EmailValidator $emailValidator)
    {
        $this->userManager = $userManager;
        $this->emailValidator = $emailValidator;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'password', InputParam::OPTIONAL),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());

        $params = $paramsProcessor->getValues();

        if (!$params['email']) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'No valid email', 'code' => 'email_missing']);
            $response->setHttpCode(Response::S200_OK);
            return $response;
        }

        $status = 'available';
        $passwordStatus = null;
        $user = $this->userManager->loadUserByEmail($params['email']);
        $id = null;
        if ($user) {
            $status = 'taken';
            $id = $user->id;

            if ($params['password']) {
                $passwordStatus = Passwords::verify($params['password'], $user->password);
            }
            // Validate email format only if user email does not exist in our DB, since external services may be slow
        } elseif (!Validators::isEmail($params['email']) || !$this->emailValidator->isValid($params['email'])) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid email format', 'code' => 'invalid_email']);
            $response->setHttpCode(Response::S200_OK);
            return $response;
        }

        $result = [
            'email' => $params['email'],
            'id' => $id,
            'status' => $status,
            'password' => $passwordStatus,
        ];

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
