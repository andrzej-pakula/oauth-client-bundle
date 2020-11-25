<?php

declare(strict_types=1);


namespace Andreo\OAuthClientBundle\Middleware;


use Andreo\OAuthClientBundle\Client\ClientContext;
use Andreo\OAuthClientBundle\Client\HTTPContext;
use Andreo\OAuthClientBundle\Exception\MissingZoneException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class SetZoneResponseMiddleware implements MiddlewareInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function __invoke(HTTPContext $httpContext, ClientContext $clientContext, MiddlewareStackInterface $stack): Response
    {
        if (!$clientContext->hasZones()) {
            return $stack->next()($httpContext, $clientContext, $stack);
        }

        $parameters = $httpContext->getParameters();
        if (!$parameters->hasZoneId()) {
            throw new MissingZoneException();
        }

        $zone = $clientContext->getZone($parameters->getZoneId());
        $uri = $this->router->generate($zone->getSuccessfulResponseUri());

        $httpContext = $httpContext->withResponse(new RedirectResponse($uri));

        return $stack->next()($httpContext, $clientContext, $stack);
    }
}
