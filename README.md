# imos/domain-redirector

[![Build Status](https://api.travis-ci.org/imosnet/domain-redirector.svg)](https://travis-ci.org/imosnet/domain-redirector)

Websites sometimes have many secondary domains. To satisfy seo requirements and make it the right way, every website
should only have one primary domain. This library is designed to manage the domain configuration for a site. Special
feature is, that you can configure multiple primary domains (that's useful for multi-site environments).

## Usage

```php
require 'vendor/autoload.php';
use Imos\DomainRedirector\DomainRedirector;
use Symfony\Component\HttpFoundation\Request;

// create object
$domainRedirector = new DomainRedirector();

// add primary domain and the corresponding secondary domains
$domainRedirector->addPrimaryDomain('www.imos.net', true); // second argument = ssl
$domainRedirector->addSecondaryDomain('www.imosnet.de', 'www.imos.net'); // www.imosnet.de should redirect to www.imos.net
$domainRedirector->addSecondaryDomain('www.anotherdomain.de', 'www.imos.net'); // ...

// if we have another primary domain, we could add it here
$domainRedirector->addPrimaryDomain('www.another-domain.de');
$domainRedirector->addSecondaryDomain('www.foobar.de', 'www.another-domain.de');

// we can even add a fallback, for requests that does not match any domain
$domainRedirector->setFallbackDomain('www.imos.net');

// pass a symfony request object to $domainRedirector. For example we fake some requests:

$request = Request::create('/de/test.html?x=y', 'GET', [], [], [], ['HTTP_HOST' => 'www.imosnet.de']);
$redirectUrl = $domainRedirector->getRedirect($request); // https://www.imos.net/de/test.html?x=y

$request = Request::create('/de/test.html?x=y', 'GET', [], [], [], ['HTTP_HOST' => 'www.imos.net', 'HTTPS' => 'ON']);
$redirectUrl = $domainRedirector->getRedirect($request); // false (everything is fine, we dont need any redirect)

$request = Request::create('/de/test.html?x=y', 'GET', [], [], [], ['HTTP_HOST' => 'www.foobar.de']);
$redirectUrl = $domainRedirector->getRedirect($request); // http://www.another-domain.de/de/test.html?x=y

// fallback example
$request = Request::create('/de/test.html?x=y', 'GET', [], [], [], ['HTTP_HOST' => 'www.xyz.de']);
$redirectUrl = $domainRedirector->getRedirect($request); // https://www.imos.net/de/test.html?x=y

```

## License

This is open-sourced software licensed under the [MIT license](LICENSE)
