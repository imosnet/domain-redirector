<?php

namespace Imos\Tests\DomainRedirector;

use Imos\DomainRedirector;
use Symfony\Component\HttpFoundation\Request;

class DomainRedirectorTest extends \PHPUnit_Framework_TestCase {

    public function setUp() {
        $this->fixture = new DomainRedirector\DomainRedirector();
    }

    public function testAddingPrimaryWwwDomainCreatesAutomaticallySecondaryDomain()
    {
        // vorbereitung
        $this->fixture->addPrimaryDomain('www.imos.net', true);
        $secondaryDomains = $this->fixture->getSecondaryDomains();

        // asserts
        $this->assertArrayHasKey('imos.net', $secondaryDomains);
    }

    /**
     * @expectedException \Imos\DomainRedirector\Exception\MissingPrimaryDomainException
     */
    public function testAddingSecondaryDomainWhosePrimaryDomainDoesNotExistCausesException()
    {
        $this->fixture->addSecondaryDomain('imos.net', 'www.imos.net');
    }

    public function testSecondaryDomainRedirectsToPrimary()
    {
        // vorbereitung
        $this->fixture->addPrimaryDomain('www.imos.net', true);
        $this->fixture->addSecondaryDomain('imosnet.de', 'www.imos.net');
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'imosnet.de']);

        // asserts
        $redirect = $this->fixture->getRedirect($request);
        $this->assertEquals('https://www.imos.net', rtrim($redirect, '/'));
    }

    public function testSecondaryDomainWithoutWwwRedirectsToPrimary()
    {
        // vorbereitung
        $this->fixture->addPrimaryDomain('www.imos.net', true);
        $this->fixture->addSecondaryDomain('www.imosnet.de', 'www.imos.net');
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'imosnet.de']);

        // asserts
        $redirect = $this->fixture->getRedirect($request);
        $this->assertEquals('https://www.imos.net', rtrim($redirect, '/'));
    }

    public function testPrimaryDomainWithoutWwwRedirectsToWww()
    {
        // vorbereitung
        $this->fixture->addPrimaryDomain('www.imos.net', false);
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'imos.net']);

        // asserts
        $redirect = $this->fixture->getRedirect($request);
        $this->assertEquals('http://www.imos.net', rtrim($redirect, '/'));
    }

    public function testPrimaryDomainWithoutSslRedirectsToSsl()
    {
        // vorbereitung
        $this->fixture->addPrimaryDomain('www.imos.net', true);
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'www.imos.net']);

        // asserts
        $redirect = $this->fixture->getRedirect($request);
        $this->assertEquals('https://www.imos.net', rtrim($redirect, '/'));
    }

    public function testPrimaryDomainWithMatchingRequestDoesNotRedirect()
    {
        // vorbereitung (SSL)
        $this->fixture->addPrimaryDomain('www.imos.net', true);
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'www.imos.net', 'HTTPS' => 'on']);

        // asserts (SSL)
        $redirect = $this->fixture->getRedirect($request);
        $this->assertFalse($redirect);

        // vorbereitung (NONE-SSL)
        $this->fixture->addPrimaryDomain('www.imos.net', false);
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'www.imos.net']);

        // asserts (NONE-SSL)
        $redirect = $this->fixture->getRedirect($request);
        $this->assertFalse($redirect);
    }

    public function testRequestWithPathAndParametersRedirects()
    {
        // vorbereitung
        $this->fixture->addPrimaryDomain('www.imos.net', true);
        $this->fixture->addSecondaryDomain('imosnet.de', 'www.imos.net');
        $request = Request::create('/de/test.html?x=y', 'GET', [], [], [], ['HTTP_HOST' => 'imosnet.de']);

        // asserts
        $redirect = $this->fixture->getRedirect($request);
        $this->assertEquals('https://www.imos.net/de/test.html?x=y', rtrim($redirect, '/'));
    }

    public function testRequestForNonExistentDomainFallsBackOnDefaultDomain()
    {
        // vorbereitung
        $this->fixture->addPrimaryDomain('www.imos.net', true);
        $this->fixture->setFallbackDomain('www.imos.net');
        $request = Request::create('/de/test.html?x=y', 'GET', [], [], [], ['HTTP_HOST' => 'nonexistent.de']);

        // asserts
        $redirect = $this->fixture->getRedirect($request);
        $this->assertEquals('https://www.imos.net/de/test.html?x=y', rtrim($redirect, '/'));
    }

    public function testSecondarySubDomainWithWwwRedirectsToPrimary()
    {
        // vorbereitung
        $this->fixture->addPrimaryDomain('shop.imos.net', true);
        $this->fixture->addSecondaryDomain('www.shop.imos.net', 'shop.imos.net', false);

        // asserts
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'www.shop.imos.net']);
        $redirect = $this->fixture->getRedirect($request);
        $this->assertEquals('https://shop.imos.net', rtrim($redirect, '/'));

        // asserts
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'shop.imos.net', 'HTTPS' => 'on']);
        $redirect = $this->fixture->getRedirect($request);
        $this->assertFalse($redirect);
    }
}
