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
        // Read configuration from environment (docker-compose passes these)
        $endpoint      = getenv('S3_ENDPOINT') ?: 'http://s3ninja:9000';
        $region        = getenv('S3_REGION') ?: 'us-east-1';
        $key           = getenv('S3_KEY') ?: 'AKIAIOSFODNN7EXAMPLE';
        $secret        = getenv('S3_SECRET') ?: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
        $bucket        = getenv('S3_BUCKET') ?: 'wis4-documents';
        $usePathStyleEnv = getenv('S3_USE_PATH_STYLE');
        $usePathStyle  = $usePathStyleEnv === false ? true : filter_var($usePathStyleEnv, FILTER_VALIDATE_BOOLEAN);

        $this->s3Bucket = $bucket;

        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => $usePathStyle,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
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
                $bucketName = $this->s3Bucket;

                try {
                    $this->s3Client->headBucket(['Bucket' => $bucketName]);
                } catch (AwsException $e) {
                    if ($e->getStatusCode() === 404) {
                        $this->s3Client->createBucket(['Bucket' => $bucketName]);
                    }
                }

                // Upload com ACL público para permitir acesso direto do browser
                $result = $this->s3Client->putObject([
                    'Bucket' => $bucketName,
                    'Key' => 'task-executions/' . $fileName,
                    'Body' => file_get_contents($file->getTempName()),
                    'ContentType' => $file->getMimeType(),
                ]);

                // Armazena apenas o nome do ficheiro; o download será feito via API
                $filePath = 'task-executions/' . $fileName;

                log_message('info', 'File uploaded to S3: ' . $filePath);
            } catch (AwsException $e) {
                log_message('error', 'S3 upload error: ' . $e->getMessage());
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
            $bucketName = $this->s3Bucket;
            $filePath = $execution['file_path'];

            // Se o file_path é uma URL completa (formato antigo), extrair apenas a chave S3
            if (strpos($filePath, 'http://') === 0 || strpos($filePath, 'https://') === 0) {
                // Extrair a parte após o nome do bucket
                // Ex: http://localhost:9000/wis4-documents/task-executions/file.pdf -> task-executions/file.pdf
                $parts = explode('/' . $bucketName . '/', $filePath);
                if (count($parts) > 1) {
                    $objectKey = $parts[1];
                } else {
                    // Fallback: tentar extrair usando o nome do ficheiro
                    $objectKey = 'task-executions/' . $execution['file_name'];
                }
            } else {
                // Formato novo: já é apenas a chave S3
                $objectKey = $filePath;
            }

            log_message('info', "Downloading file from S3 - Bucket: {$bucketName}, Key: {$objectKey}");

            $result = $this->s3Client->getObject([
                'Bucket' => $bucketName,
                'Key' => $objectKey
            ]);

            // Obter o conteúdo do ficheiro como string
            $fileContent = $result['Body']->getContents();

            // Determinar o Content-Type correto
            $contentType = $result['ContentType'] ?? 'application/octet-stream';

            log_message('info', "File downloaded successfully - Size: " . strlen($fileContent) . " bytes, ContentType: {$contentType}");

            // Usar DownloadResponse do CodeIgniter para garantir download correto
            return $this->response
                ->setStatusCode(200)
                ->setContentType($contentType)
                ->setHeader('Content-Disposition', 'attachment; filename="' . $execution['file_name'] . '"')
                ->setHeader('Content-Length', (string)strlen($fileContent))
                ->setHeader('Cache-Control', 'must-revalidate')
                ->setHeader('Pragma', 'public')
                ->setBody($fileContent);

        } catch (AwsException $e) {
            log_message('error', 'S3 download error: ' . $e->getMessage());
            return $this->fail('Failed to download file: ' . $e->getMessage());
        }
    }
}
