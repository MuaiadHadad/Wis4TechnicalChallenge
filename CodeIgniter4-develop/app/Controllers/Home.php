<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * PT: Controlador raiz que expõe um endpoint de estado da API.
 * EN: Root controller exposing a simple API health/status endpoint.
 */
class Home extends Controller
{
    /**
     * PT: Devolve uma resposta JSON com mensagem e timestamp.
     * EN: Returns a JSON response with message and timestamp.
     */
    public function index(): string
    {
        return json_encode([
            'success' => true,
            'message' => 'WIS4 Document Workflow API is running',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
