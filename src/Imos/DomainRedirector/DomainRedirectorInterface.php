<?php
namespace Imos\DomainRedirector;

use Symfony\Component\HttpFoundation\Request;

interface DomainRedirectorInterface
{
    public function addPrimaryDomain($domain, $ssl);

    public function addSecondaryDomain($domain, $redirectDomain);

    public function getRedirect(Request $request);

    public function setFallbackDomain($domain);
}