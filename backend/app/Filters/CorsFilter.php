<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * PT: Filtro CORS para permitir chamadas entre origens.
 * EN: CORS filter to allow cross-origin requests.
 */
class CorsFilter implements FilterInterface
{
    /**
     * PT: Define cabeçalhos CORS e responde a preflight (OPTIONS).
     * EN: Sets CORS headers and responds to preflight (OPTIONS).
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return ResponseInterface|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $response = service('response');

        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->setHeader('Access-Control-Max-Age', '86400');

        if ($request->getMethod() === 'OPTIONS') {
            return $response->setStatusCode(200);
        }
    }

    /**
     * PT: Pós-processamento da resposta (não utilizado).
     * EN: Post-processing of the response (not used).
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
