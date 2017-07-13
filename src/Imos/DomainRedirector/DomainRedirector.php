<?php

namespace Imos\DomainRedirector;

use Symfony\Component\HttpFoundation\Request;

/**
 * DomainRedirector
 *
 * @package Imos\DomainRedirector
 * @author Michael Mezger
 */
class DomainRedirector implements DomainRedirectorInterface {

    /**
     * @var array
     */
    private $primaryDomains = array();

    /**
     * @var array
     */
    private $secondaryDomains = array();

    /**
     * @var string
     */
    private $fallbackDomain;

    /**
     * @param $domain
     * @param bool|false $ssl
     * @return DomainRedirector
     */
    public function addPrimaryDomain($domain, $ssl = false)
    {
        // domain parsen
        $parsedDomain = $this->parseDomain($domain, $ssl);

        // primary-domain hinzufuegen
        $this->primaryDomains[$parsedDomain['host']] = [
            'domain' => $parsedDomain['host'],
            'ssl' => $ssl
        ];

        // falls wir eine www-domain haben, dann ist die nicht-www-domain automatisch eine sekundär-domain
        if (isset($parsedDomain['has_www']) && $parsedDomain['has_www']) {
            $this->addSecondaryDomain($parsedDomain['host_without_www'], $parsedDomain['host']);
        }

        // fluent interface
        return $this;
    }

    /**
     * PrimaryDomain getter
     *
     * @param string $domain
     * @return array
     * @throws Exception\MissingPrimaryDomainException falls die Domain nicht existiert
     */
    public function getPrimaryDomain($domain)
    {
        if (!$this->isPrimaryDomain($domain)) {
            throw new Exception\MissingPrimaryDomainException(sprintf('domain=(%s) does not exist', $domain));
        }

        return $this->primaryDomains[$domain];
    }

    /**
     * Alle Primary Domains
     *
     * @return array
     */
    public function getPrimaryDomains()
    {
        return $this->primaryDomains;
    }

    /**
     * Pruefe ob Primary Domain existiert
     * @param $domain
     * @return bool
     */
    public function isPrimaryDomain($domain)
    {
        return isset($this->primaryDomains[$domain]);
    }

    /**
     * Secondary Domain hinzufuegen
     *
     * @param string $domain Sekundärdomain
     * @param string $redirectDomain Domain, auf die redirectet werden soll. Hier nur den host angeben (ohne scheme).
     * @param bool $autoAddWww Falls true, wird beim hinzufuegen einer www-Domain automatisch auch None-WWW hinzugefügt
     * @return DomainRedirector
     * @throws Exception\MissingPrimaryDomainException Falls die PrimaryDomain noch nicht existiert
     */
    public function addSecondaryDomain($domain, $redirectDomain, $autoAddWww = true)
    {
        // domain parsen
        $parsedDomain = $this->parseDomain($domain);

        // teste ob die Primary domain schon existiert
        if (!$this->isPrimaryDomain($redirectDomain)) {
            throw new Exception\MissingPrimaryDomainException(sprintf('domain=(%s) does not exist', $redirectDomain));
        }

        // secondary-domain addedn
        $this->secondaryDomains[$domain] = [
            'domain' => $domain,
            'redirectDomain' => $redirectDomain
        ];

        // falls wir eine www-domain haben, dann ist die nicht-www-domain automatisch eine weitere sekundär-domain
        if ($autoAddWww && isset($parsedDomain['has_www']) && $parsedDomain['has_www']) {
            $this->secondaryDomains[$parsedDomain['host_without_www']] = [
                'domain' => $parsedDomain['host_without_www'],
                'redirectDomain' => $redirectDomain
            ];
        }

        // fluent interface
        return $this;
    }

    /**
     * @return array
     */
    public function getSecondaryDomains()
    {
        return $this->secondaryDomains;
    }

    /**
     * Getter fuer Seondary Domain
     * @param $domain
     * @return mixed
     * @throws Exception\MissingSecondaryDomainException Falls die Domain nicht existiert
     */
    public function getSecondaryDomain($domain)
    {
        if (!$this->isSecondaryDomain($domain)) {
            throw new Exception\MissingSecondaryDomainException(sprintf('domain=(%s) does not exist', $domain));
        }

        return $this->secondaryDomains[$domain];
    }

    /**
     * Pruefe ob Secondary Domain existiert
     * @param $domain
     * @return bool
     */
    public function isSecondaryDomain($domain)
    {
        return isset($this->secondaryDomains[$domain]);
    }

    /**
     * Ermittelt einen möglichen Redirect anhand eines Requests
     *
     * @param Request $request
     * @return bool|string FALSE falls nichts zu tun ist, ansonsten der redirect als string
     * @throws Exception\MissingPrimaryDomainException
     * @throws Exception\MissingSecondaryDomainException
     */
    public function getRedirect(Request $request)
    {
        // domain aus request ziehen
        $domain = $request->getHost();
        // falls wir ueber eine secondary domain kommen
        if ($this->isSecondaryDomain($domain)) {
            $secondaryDomain = $this->getSecondaryDomain($domain);
            $primaryDomain = $this->getPrimaryDomain($secondaryDomain['redirectDomain']);

            return $this->buildRedirectUrl($primaryDomain['domain'], $primaryDomain['ssl'], $request->server->get('REQUEST_URI'));
        }

        // wir kommen ueber eine primary-domain, und SSL matched nicht
        if ($this->isPrimaryDomain($domain) && $this->getPrimaryDomain($domain)['ssl'] != $request->isSecure()) {
            $primaryDomain = $this->getPrimaryDomain($domain);

            return $this->buildRedirectUrl($primaryDomain['domain'], $primaryDomain['ssl'], $request->server->get('REQUEST_URI'));
        }

        // wenn wir eine fallback domain konfiguriert haben, dann evtl. fallback
        if (!$this->isPrimaryDomain($domain) && strlen($this->getFallbackDomain())) {
            $primaryDomain = $this->getPrimaryDomain($this->getFallbackDomain());

            return $this->buildRedirectUrl($primaryDomain['domain'], $primaryDomain['ssl'], $request->server->get('REQUEST_URI'));
        }

        // nichts zu tun
        return false;
    }

    /**
     * Set the Fallback Domain, if no secondary domain matches
     *
     * @param $domain
     * @return DomainRedirector
     * @throws Exception\MissingPrimaryDomainException
     */
    public function setFallbackDomain($domain)
    {
        // teste ob die Primary domain schon existiert
        if (!$this->isPrimaryDomain($domain)) {
            throw new Exception\MissingPrimaryDomainException(sprintf('domain=(%s) does not exist', $domain));
        }

        $this->fallbackDomain = $domain;

        return $this;
    }

    /**
     * getter FallbackDomain
     * @return string
     */
    public function getFallbackDomain()
    {
        return $this->fallbackDomain;
    }

    /**
     * Erstellt die Redirect-URL
     *
     * @param string $host
     * @param boolean $ssl
     * @return string
     */
    protected function buildRedirectUrl($host, $ssl, $path = null)
    {
        $parts = [];

        $parts['scheme'] = $ssl ? 'https://' : 'http://';
        $parts['host'] = $host . '/';

        if (strlen(ltrim($path, '/'))) {
            $parts['path'] = ltrim($path, '/');
        }

        return implode(NULL, $parts);
    }

    /**
     * @param $domain
     * @param bool|false $ssl
     * @return mixed
     */
    protected function parseDomain ($domain, $ssl = false)
    {
        // falls wir kein schema haben, hinzufuegen
        if (strpos($domain, 'https://') === false && strpos($domain, 'http://') === false) {
            $domain = ($ssl ? 'https://' : 'http://') . $domain;
        }

        $data = parse_url($domain);
        $data['has_www'] = (strpos($data['host'], 'www.') === 0);

        if ($data['has_www']) {
            $data['host_without_www'] = substr($data['host'], 4);
        }

        return $data;
    }
}