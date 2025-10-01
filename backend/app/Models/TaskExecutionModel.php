<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * PT: Modelo de execuções de tarefas: consulta e gestão de estados/ficheiros.
 * EN: Task execution model: querying and managing statuses/files.
 */
class TaskExecutionModel extends Model
{
    protected $table = 'task_execution';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['task_id', 'collaborator_id', 'description', 'file_path', 'file_name', 'status'];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'submitted_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'task_id' => 'required|integer',
        'collaborator_id' => 'required|integer',
        'description' => 'required|min_length[10]',
        'status' => 'in_list[submitted,approved,rejected]'
    ];

    /**
     * PT: Obtém uma execução com detalhes de tarefa e colaborador.
     * EN: Gets an execution with task and collaborator details.
     *
     * @param int $executionId
     * @return array|null
     */
    public function getExecutionWithDetails(int $executionId): ?array
    {
        return $this->select('task_execution.*, tasks.task_type, tasks.description as task_description, users.name as collaborator_name, users.email as collaborator_email')
                   ->join('tasks', 'tasks.id = task_execution.task_id')
                   ->join('users', 'users.id = task_execution.collaborator_id')
                   ->where('task_execution.id', $executionId)
                   ->first();
    }

    /**
     * PT: Lista execuções por ID de tarefa.
     * EN: Lists executions by task ID.
     *
     * @param int $taskId
     * @return array
     */
    public function getExecutionsByTask(int $taskId): array
    {
        return $this->select('task_execution.*, users.name as collaborator_name, users.email as collaborator_email')
                   ->join('users', 'users.id = task_execution.collaborator_id')
                   ->where('task_id', $taskId)
                   ->findAll();
    }

    /**
     * PT: Lista execuções submetidas por um colaborador.
     * EN: Lists executions submitted by a collaborator.
     *
     * @param int $collaboratorId
     * @return array
     */
    public function getExecutionsByCollaborator(int $collaboratorId): array
    {
        return $this->select('task_execution.*, tasks.task_type, tasks.description as task_description')
                   ->join('tasks', 'tasks.id = task_execution.task_id')
                   ->where('collaborator_id', $collaboratorId)
                   ->findAll();
    }

    /**
     * PT: Verifica se já existe execução para uma tarefa e colaborador.
     * EN: Checks if an execution already exists for a task and collaborator.
     *
     * @param int $taskId
     * @param int $collaboratorId
     * @return bool
     */
    public function hasExecutionForTask(int $taskId, int $collaboratorId): bool
    {
        return $this->where('task_id', $taskId)
                   ->where('collaborator_id', $collaboratorId)
                   ->countAllResults() > 0;
    }

    /**
     * PT: Atualiza o estado de uma execução.
     * EN: Updates the status of an execution.
     *
     * @param int $executionId
     * @param string $status
     * @return bool
     */
    public function updateExecutionStatus(int $executionId, string $status): bool
    {
        return $this->update($executionId, ['status' => $status]);
    }
}
