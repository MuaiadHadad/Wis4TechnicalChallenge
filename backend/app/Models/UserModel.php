<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * PT: Modelo de utilizadores: autenticação e consultas por papel.
 * EN: User model: authentication and role-based queries.
 */
class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['email', 'password', 'name', 'role'];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'email' => 'required|valid_email|is_unique[users.email]',
        'password' => 'required|min_length[6]',
        'name' => 'required|min_length[3]',
        'role' => 'required|in_list[administrator,collaborator]'
    ];

    /**
     * PT: Autentica por email e palavra‑passe.
     * EN: Authenticates by email and password.
     *
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->where('email', $email)->first();

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }

        return null;
    }

    /**
     * PT: Lista utilizadores por papel.
     * EN: Lists users by role.
     *
     * @param string $role
     * @return array
     */
    public function getUsersByRole(string $role): array
    {
        return $this->where('role', $role)->findAll();
    }

    /**
     * PT: Determina se o utilizador é administrador.
     * EN: Determines if the user is an administrator.
     *
     * @param int $userId
     * @return bool
     */
    public function isAdministrator(int $userId): bool
    {
        $user = $this->find($userId);
        return $user && $user['role'] === 'administrator';
    }

    /**
     * PT: Determina se o utilizador é colaborador.
     * EN: Determines if the user is a collaborator.
     *
     * @param int $userId
     * @return bool
     */
    public function isCollaborator(int $userId): bool
    {
        $user = $this->find($userId);
        return $user && $user['role'] === 'collaborator';
    }

    /**
     * PT: Antes de inserir, cifra a palavra‑passe se existir.
     * EN: Before insert, hashes the password if present.
     *
     * @param array $data
     * @return array
     */
    protected function beforeInsert(array $data): array
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_DEFAULT);
        }
        return $data;
    }

    /**
     * PT: Antes de atualizar, cifra a palavra‑passe se existir.
     * EN: Before update, hashes the password if present.
     *
     * @param array $data
     * @return array
     */
    protected function beforeUpdate(array $data): array
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_DEFAULT);
        }
        return $data;
    }
}
