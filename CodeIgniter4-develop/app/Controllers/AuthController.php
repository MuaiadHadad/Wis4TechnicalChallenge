<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

/**
 * PT: Controlador responsável pela autenticação de utilizadores (login, logout, perfil e verificação).
 * EN: Controller responsible for user authentication (login, logout, profile and status check).
 */
class AuthController extends ResourceController
{
    use ResponseTrait;

    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        helper('jwt');
    }

    /**
     * PT: Autentica um utilizador com email e palavra‑passe e inicia sessão.
     * EN: Authenticates a user with email and password and starts a session.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response with user info or error.
     */
    public function login()
    {
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        if (!$email || !$password) {
            return $this->fail('Email and password are required');
        }

        $user = $this->userModel->authenticate($email, $password);

        if (!$user) {
            return $this->fail('Invalid credentials', 401);
        }

        $sessionData = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'logged_in' => true
        ];

        session()->set($sessionData);

        return $this->respond([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role']
            ]
        ]);
    }

    /**
     * PT: Encerra a sessão do utilizador atual.
     * EN: Logs out the current user session.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response confirming logout.
     */
    public function logout()
    {
        session()->destroy();
        return $this->respond([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * PT: Retorna o perfil do utilizador autenticado.
     * EN: Returns the profile of the authenticated user.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with user data or 401 if unauthenticated.
     */
    public function profile()
    {
        if (!session()->get('logged_in')) {
            return $this->fail('Not authenticated', 401);
        }

        return $this->respond([
            'success' => true,
            'user' => [
                'id' => session()->get('user_id'),
                'email' => session()->get('email'),
                'name' => session()->get('name'),
                'role' => session()->get('role')
            ]
        ]);
    }

    /**
     * PT: Verifica se existe um utilizador autenticado e devolve o seu estado.
     * EN: Checks if a user is authenticated and returns the status.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with authentication status and optional user.
     */
    public function checkAuth()
    {
        return $this->respond([
            'authenticated' => session()->get('logged_in') ?? false,
            'user' => session()->get('logged_in') ? [
                'id' => session()->get('user_id'),
                'email' => session()->get('email'),
                'name' => session()->get('name'),
                'role' => session()->get('role')
            ] : null
        ]);
    }
}
