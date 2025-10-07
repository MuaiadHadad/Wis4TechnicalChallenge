<?php

namespace App\Controllers;

use App\Models\TaskModel;
use App\Models\UserModel;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

/**
 * PT: Controlador de tarefas: listar, criar e consultar tarefas atribuídas.
 * EN: Tasks controller: list, create and fetch assigned tasks.
 */
class TaskController extends ResourceController
{
    use ResponseTrait;

    protected $taskModel;
    protected $userModel;

    public function __construct()
    {
        $this->taskModel = new TaskModel();
        $this->userModel = new UserModel();
    }

    /**
     * PT: Garante que existe um utilizador autenticado.
     * EN: Ensures a user is authenticated.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|null 401 response when unauthenticated, otherwise null.
     */
    private function checkAuthentication()
    {
        if (!session()->get('logged_in')) {
            return $this->fail('Not authenticated', 401);
        }
        return null;
    }

    /**
     * PT: Verifica se o utilizador tem papel de administrador.
     * EN: Verifies that the user has the administrator role.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|null 403 response when forbidden, otherwise null.
     */
    private function checkAdminRole()
    {
        if (session()->get('role') !== 'administrator') {
            return $this->fail('Insufficient permissions. Administrator role required.', 403);
        }
        return null;
    }

    /**
     * PT: Lista todas as tarefas (apenas administradores).
     * EN: Lists all tasks (administrators only).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with tasks list.
     */
    public function index()
    {
        $authCheck = $this->checkAuthentication();
        if ($authCheck) return $authCheck;

        $adminCheck = $this->checkAdminRole();
        if ($adminCheck) return $adminCheck;

        $tasks = $this->taskModel->getTasksWithUsers();

        return $this->respond([
            'success' => true,
            'data' => $tasks
        ]);
    }

    /**
     * PT: Cria uma nova tarefa e atribui-a a um colaborador.
     * EN: Creates a new task and assigns it to a collaborator.
     *
     * Expects POST fields: user_id, task_type, description.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with created task or error.
     */
    public function create()
    {
        log_message('info', '========== CREATE TASK STARTED ==========');

        $authCheck = $this->checkAuthentication();
        if ($authCheck) {
            log_message('error', 'Authentication failed');
            return $authCheck;
        }

        $adminCheck = $this->checkAdminRole();
        if ($adminCheck) {
            log_message('error', 'Admin role check failed');
            return $adminCheck;
        }

        $userId = $this->request->getPost('user_id');
        $taskType = $this->request->getPost('task_type');
        $description = $this->request->getPost('description');

        log_message('info', 'Received data - user_id: ' . $userId . ', task_type: ' . $taskType . ', description length: ' . strlen($description));

        if (!$userId || !$taskType || !$description) {
            log_message('error', 'Missing required fields');
            return $this->fail('User ID, task type, and description are required');
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            log_message('error', 'User not found: ' . $userId);
            return $this->fail('User not found');
        }

        log_message('info', 'User found: ' . $user['email'] . ', role: ' . $user['role']);

        if ($user['role'] !== 'collaborator') {
            log_message('error', 'User is not a collaborator: ' . $user['role']);
            return $this->fail('Tasks can only be assigned to collaborators');
        }

        $taskData = [
            'user_id' => $userId,
            'task_type' => $taskType,
            'description' => $description,
            'status' => 'pending'
        ];

        log_message('info', 'Attempting to insert task: ' . json_encode($taskData));

        $insertResult = $this->taskModel->insert($taskData);

        log_message('info', 'Insert result: ' . var_export($insertResult, true));

        if ($insertResult) {
            $taskId = $this->taskModel->getInsertID();
            log_message('info', 'Task inserted with ID: ' . $taskId);

            $task = $this->taskModel->find($taskId);
            log_message('info', 'Task retrieved: ' . json_encode($task));

            return $this->respondCreated([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => $task
            ]);
        }

        $errors = $this->taskModel->errors();
        log_message('error', 'Failed to insert task. Errors: ' . json_encode($errors));

        return $this->fail('Failed to create task: ' . json_encode($errors));
    }

    /**
     * PT: Mostra detalhes de uma tarefa pelo seu ID.
     * EN: Shows details of a task by its ID.
     *
     * @param int|string|null $id Task ID
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with task details or 404.
     */
    public function show($id = null)
    {
        $authCheck = $this->checkAuthentication();
        if ($authCheck) return $authCheck;

        if (!$id) {
            return $this->fail('Task ID is required');
        }

        // Join with task_execution to get file information
        $task = $this->taskModel
                    ->select('tasks.*, users.name as user_name, users.email as user_email,
                             task_execution.id as execution_id,
                             task_execution.file_name as execution_file_name,
                             task_execution.file_path as execution_file_path,
                             task_execution.description as execution_description,
                             task_execution.status as execution_status')
                    ->join('users', 'users.id = tasks.user_id')
                    ->join('task_execution', 'task_execution.task_id = tasks.id', 'left')
                    ->find($id);

        if (!$task) {
            return $this->failNotFound('Task not found');
        }

        return $this->respond([
            'success' => true,
            'data' => $task
        ]);
    }

    /**
     * PT: Obtém a lista de utilizadores com o papel "collaborator" (apenas administradores).
     * EN: Retrieves users with the "collaborator" role (administrators only).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with collaborators list.
     */
    public function getCollaborators()
    {
        $authCheck = $this->checkAuthentication();
        if ($authCheck) return $authCheck;

        $adminCheck = $this->checkAdminRole();
        if ($adminCheck) return $adminCheck;

        $collaborators = $this->userModel->getUsersByRole('collaborator');

        return $this->respond([
            'success' => true,
            'data' => $collaborators
        ]);
    }

    /**
     * PT: Lista as tarefas atribuídas ao colaborador autenticado.
     * EN: Lists tasks assigned to the authenticated collaborator.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with tasks list for the collaborator.
     */
    public function myTasks()
    {
        $authCheck = $this->checkAuthentication();
        if ($authCheck) return $authCheck;

        $userId = session()->get('user_id');
        $userRole = session()->get('role');

        if ($userRole !== 'collaborator') {
            return $this->fail('Only collaborators can view assigned tasks', 403);
        }

        $tasks = $this->taskModel->getTasksForCollaborator($userId);

        return $this->respond([
            'success' => true,
            'data' => $tasks
        ]);
    }
}
