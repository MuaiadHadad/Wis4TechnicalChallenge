<?php

namespace App\Controllers;

use App\Models\TaskExecutionModel;
use App\Models\TaskModel;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * PT: Controla submissões de execução de tarefas e gestão de ficheiros em S3.
 * EN: Handles task execution submissions and S3 file management.
 */
class TaskExecutionController extends ResourceController
{
    use ResponseTrait;

    protected $taskExecutionModel;
    protected $taskModel;
    protected $s3Client;

    public function __construct()
    {
        $this->taskExecutionModel = new TaskExecutionModel();
        $this->taskModel = new TaskModel();
        $this->initializeS3Client();
    }

    /**
     * PT: Inicializa o cliente S3 (compatível com S3Ninja).
     * EN: Initializes the S3 client (compatible with S3Ninja).
     */
    private function initializeS3Client()
    {
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => 'http://s3ninja:9000',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'
            ]
        ]);
    }

    /**
     * PT: Verifica se o pedido está autenticado.
     * EN: Checks whether the request is authenticated.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|null 401 response or null if OK.
     */
    private function checkAuthentication()
    {
        if (!session()->get('logged_in')) {
            return $this->fail('Not authenticated', 401);
        }
        return null;
    }

    /**
     * PT: Garante que o utilizador tem papel de colaborador.
     * EN: Ensures the user has the collaborator role.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|null 403 response or null if OK.
     */
    private function checkCollaboratorRole()
    {
        if (session()->get('role') !== 'collaborator') {
            return $this->fail('Insufficient permissions. Collaborator role required.', 403);
        }
        return null;
    }

    /**
     * PT: Submete a execução de uma tarefa, opcionalmente com ficheiro para S3, e atualiza o estado da tarefa.
     * EN: Submits a task execution, optionally uploads a file to S3, and updates task status.
     *
     * Expects POST fields: task_id, description, file (optional).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with execution data or error.
     */
    public function submit()
    {
        $authCheck = $this->checkAuthentication();
        if ($authCheck) return $authCheck;

        $collaboratorCheck = $this->checkCollaboratorRole();
        if ($collaboratorCheck) return $collaboratorCheck;

        $taskId = $this->request->getPost('task_id');
        $description = $this->request->getPost('description');
        $file = $this->request->getFile('file');

        if (!$taskId || !$description) {
            return $this->fail('Task ID and description are required');
        }

        $collaboratorId = session()->get('user_id');

        $task = $this->taskModel->find($taskId);
        if (!$task) {
            return $this->failNotFound('Task not found');
        }

        if ($task['user_id'] != $collaboratorId) {
            return $this->fail('You can only submit executions for tasks assigned to you', 403);
        }

        if ($this->taskExecutionModel->hasExecutionForTask($taskId, $collaboratorId)) {
            return $this->fail('Task execution already submitted');
        }

        $filePath = null;
        $fileName = null;

        if ($file && $file->isValid()) {
            try {
                $fileName = $file->getRandomName();
                $bucketName = 'wis4-documents';

                try {
                    $this->s3Client->headBucket(['Bucket' => $bucketName]);
                } catch (AwsException $e) {
                    if ($e->getStatusCode() === 404) {
                        $this->s3Client->createBucket(['Bucket' => $bucketName]);
                    }
                }

                $result = $this->s3Client->putObject([
                    'Bucket' => $bucketName,
                    'Key' => 'task-executions/' . $fileName,
                    'Body' => file_get_contents($file->getTempName()),
                    'ContentType' => $file->getMimeType()
                ]);

                $filePath = $result['ObjectURL'];
            } catch (AwsException $e) {
                return $this->fail('File upload failed: ' . $e->getMessage());
            }
        }

        $executionData = [
            'task_id' => $taskId,
            'collaborator_id' => $collaboratorId,
            'description' => $description,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'status' => 'submitted'
        ];

        if ($this->taskExecutionModel->insert($executionData)) {
            $this->taskModel->updateTaskStatus($taskId, 'in_progress');

            $executionId = $this->taskExecutionModel->getInsertID();
            $execution = $this->taskExecutionModel->find($executionId);

            return $this->respondCreated([
                'success' => true,
                'message' => 'Task execution submitted successfully',
                'data' => $execution
            ]);
        }

        return $this->fail('Failed to submit task execution');
    }

    /**
     * PT: Lista execuções de tarefas do colaborador autenticado ou todas (administrador).
     * EN: Lists task executions for the authenticated collaborator or all (administrator).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with executions list.
     */
    public function index()
    {
        $authCheck = $this->checkAuthentication();
        if ($authCheck) return $authCheck;

        $userId = session()->get('user_id');
        $userRole = session()->get('role');

        if ($userRole === 'collaborator') {
            $executions = $this->taskExecutionModel->getExecutionsByCollaborator($userId);
        } else {
            $executions = $this->taskExecutionModel->select('task_execution.*, tasks.task_type, tasks.description as task_description, users.name as collaborator_name')
                                                 ->join('tasks', 'tasks.id = task_execution.task_id')
                                                 ->join('users', 'users.id = task_execution.collaborator_id')
                                                 ->findAll();
        }

        return $this->respond([
            'success' => true,
            'data' => $executions
        ]);
    }

    /**
     * PT: Mostra uma execução específica com detalhes e valida permissões.
     * EN: Shows a specific execution with details and validates permissions.
     *
     * @param int|string|null $id Execution ID
     * @return \CodeIgniter\HTTP\ResponseInterface JSON with execution details or 404/403.
     */
    public function show($id = null)
    {
        $authCheck = $this->checkAuthentication();
        if ($authCheck) return $authCheck;

        if (!$id) {
            return $this->fail('Execution ID is required');
        }

        $execution = $this->taskExecutionModel->getExecutionWithDetails($id);

        if (!$execution) {
            return $this->failNotFound('Task execution not found');
        }

        $userId = session()->get('user_id');
        $userRole = session()->get('role');

        if ($userRole === 'collaborator' && $execution['collaborator_id'] != $userId) {
            return $this->fail('You can only view your own task executions', 403);
        }

        return $this->respond([
            'success' => true,
            'data' => $execution
        ]);
    }

    /**
     * PT: Descarrega o ficheiro associado a uma execução, com verificação de permissões.
     * EN: Downloads the file associated with an execution, with permission checks.
     *
     * @param int|string|null $executionId Execution ID
     * @return \CodeIgniter\HTTP\ResponseInterface Binary file response or error.
     */
    public function downloadFile($executionId = null)
    {
        $authCheck = $this->checkAuthentication();
        if ($authCheck) return $authCheck;

        if (!$executionId) {
            return $this->fail('Execution ID is required');
        }

        $execution = $this->taskExecutionModel->find($executionId);
        if (!$execution || !$execution['file_path']) {
            return $this->failNotFound('File not found');
        }

        $userId = session()->get('user_id');
        $userRole = session()->get('role');

        if ($userRole === 'collaborator' && $execution['collaborator_id'] != $userId) {
            return $this->fail('You can only download your own files', 403);
        }

        try {
            $bucketName = 'wis4-documents';
            $objectKey = 'task-executions/' . $execution['file_name'];

            $result = $this->s3Client->getObject([
                'Bucket' => $bucketName,
                'Key' => $objectKey
            ]);

            return $this->response->setHeader('Content-Type', $result['ContentType'])
                                 ->setHeader('Content-Disposition', 'attachment; filename="' . $execution['file_name'] . '"')
                                 ->setBody($result['Body']);

        } catch (AwsException $e) {
            return $this->fail('Failed to download file: ' . $e->getMessage());
        }
    }
}
