<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * PT: Modelo de tarefas: fornece consultas auxiliares e atualização de estado.
 * EN: Task model: provides helper queries and status updates.
 */
class TaskModel extends Model
{
    protected $table = 'tasks';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['user_id', 'task_type', 'description', 'status'];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'user_id' => 'required|integer',
        'task_type' => 'required|min_length[3]',
        'description' => 'required|min_length[10]',
        'status' => 'in_list[pending,in_progress,completed]'
    ];

    /**
     * PT: Lista todas as tarefas com dados do utilizador associado.
     * EN: Lists all tasks with associated user details.
     *
     * @return array
     */
    public function getTasksWithUsers(): array
    {
        return $this->select('tasks.*, users.name as user_name, users.email as user_email')
                   ->join('users', 'users.id = tasks.user_id')
                   ->findAll();
    }

    /**
     * PT: Obtém tarefas por ID de utilizador.
     * EN: Retrieves tasks by user ID.
     *
     * @param int $userId
     * @return array
     */
    public function getTasksByUser(int $userId): array
    {
        return $this->where('user_id', $userId)->findAll();
    }

    /**
     * PT: Obtém tarefas atribuídas a um colaborador ainda não concluídas.
     * EN: Gets tasks assigned to a collaborator that are not completed.
     *
     * @param int $collaboratorId
     * @return array
     */
    public function getTasksForCollaborator(int $collaboratorId): array
    {
        return $this->select('tasks.*, users.name as assigned_by')
                   ->join('users', 'users.id = tasks.user_id')
                   ->where('tasks.user_id', $collaboratorId)
                   ->where('tasks.status !=', 'completed')
                   ->findAll();
    }

    /**
     * PT: Atualiza o estado de uma tarefa.
     * EN: Updates the status of a task.
     *
     * @param int $taskId
     * @param string $status
     * @return bool
     */
    public function updateTaskStatus(int $taskId, string $status): bool
    {
        return $this->update($taskId, ['status' => $status]);
    }
}
