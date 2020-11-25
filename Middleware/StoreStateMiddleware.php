<?php

declare(strict_types=1);


namespace Andreo\OAuthClientBundle\Middleware;


use Andreo\OAuthClientBundle\Client\AuthorizationUri\State;
use Andreo\OAuthClientBundle\Client\ClientContext;
use Andreo\OAuthClientBundle\Client\HTTPContext;
use Symfony\Component\HttpFoundation\Response;

final class StoreStateMiddleware implements MiddlewareInterface
{
    public function __invoke(HTTPContext $httpContext, ClientContext $clientContext, MiddlewareStackInterface $stack): Response
    {
        if ($httpContext->isCallback()) {
            return $stack->next()($httpContext, $clientContext, $stack);
        }

        $request = $httpContext->getRequest();
        $request->getSession()->set(
            State::getKey($clientContext->getClientName()),
            $clientContext->getAuthorizationUri()->getState()->encrypt()
        );

        return $stack->next()($httpContext, $clientContext, $stack);
    }
}
