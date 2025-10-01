<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    public string $baseURL = 'http://localhost/api/';
    public string $allowedHostnames = '';
    public string $indexPage = '';
    public string $uriProtocol = 'REQUEST_URI';
    public string $defaultLocale = 'en';
    public bool $negotiateLocale = false;
    public array $supportedLocales = ['en'];
    public string $appTimezone = 'America/Chicago';
    public string $charset = 'UTF-8';
    public bool $forceGlobalSecureRequests = false;
    public array $proxyIPs = [];
    public ?string $CSRFTokenName = 'csrf_test_name';
    public ?string $CSRFHeaderName = 'X-CSRF-TOKEN';
    public ?string $CSRFCookieName = 'csrf_cookie_name';
    public int $CSRFExpire = 7200;
    public bool $CSRFRegenerate = true;
    public bool $CSRFRedirect = true;
    public ?string $CSRFSameSite = 'Lax';
    public bool $CSPEnabled = false;
}
