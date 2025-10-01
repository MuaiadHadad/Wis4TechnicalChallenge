let currentUser = null;

/**
 * PT: Mostra um toast Bootstrap com estilo consoante o tipo.
 * EN: Shows a Bootstrap toast styled according to the type.
 * @param {string} message
 * @param {'info'|'success'|'error'|'warning'} [type='info']
 */
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) { alert(message); return; }
    const id = 't' + Date.now() + Math.random().toString(16).slice(2);
    const bg = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : type === 'warning' ? 'bg-warning text-dark' : 'bg-primary';
    const el = document.createElement('div');
    el.className = `toast align-items-center text-white ${bg} border-0 animate__animated animate__fadeInDown`;
    el.id = id;
    el.role = 'alert';
    el.ariaLive = 'assertive';
    el.ariaAtomic = 'true';
    el.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>`;
    container.appendChild(el);
    const toast = new bootstrap.Toast(el, { delay: 3500 });
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

/**
 * PT: Mostra/oculta o indicador de carregamento global.
 * EN: Shows/hides the global page loading spinner.
 * @param {boolean} loading
 */
function setPageLoading(loading) {
    const spinner = document.getElementById('pageSpinner');
    if (!spinner) return;
    if (loading) spinner.classList.add('show'); else spinner.classList.remove('show');
}

/**
 * PT: Renderiza esqueleto de tabela para carregamento.
 * EN: Renders table skeleton while loading.
 * @param {number} [rows=6]
 * @returns {string}
 */
function renderTasksSkeleton(rows = 6) {
    let html = '<div class="table-skeleton">';
    for (let i = 0; i < rows; i++) {
        html += `
          <div class="row-skeleton">
            <div class="skeleton skeleton-line" style="width: 50px"></div>
            <div class="skeleton skeleton-line lg" style="width: 80%"></div>
            <div class="skeleton skeleton-line" style="width: 60%"></div>
            <div class="skeleton skeleton-line" style="width: 90%"></div>
            <div class="skeleton skeleton-line sm" style="width: 80px"></div>
            <div class="skeleton skeleton-line sm" style="width: 100px"></div>
            <div class="skeleton skeleton-line sm" style="width: 120px"></div>
          </div>`;
    }
    html += '</div>';
    return html;
}

/**
 * PT: Renderiza cartões de esqueleto para "As Minhas Tarefas".
 * EN: Renders skeleton cards for "My Tasks".
 * @param {number} [cards=3]
 * @returns {string}
 */
function renderMyTasksSkeleton(cards = 3) {
    let html = '';
    for (let i = 0; i < cards; i++) {
        html += `
          <div class="card task-card mb-3 card-hover">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="skeleton skeleton-line lg" style="width: 200px"></div>
                <div class="skeleton skeleton-line sm" style="width: 100px"></div>
            </div>
            <div class="card-body">
                <div class="skeleton skeleton-line" style="width: 95%"></div>
                <div class="skeleton skeleton-line" style="width: 90%"></div>
                <div class="skeleton skeleton-line" style="width: 85%"></div>
                <div class="mt-3">
                    <div class="skeleton skeleton-line sm" style="width: 140px"></div>
                </div>
            </div>
          </div>`;
    }
    return html;
}

document.addEventListener('DOMContentLoaded', async function() {
    currentUser = await requireAuth();
    if (!currentUser) return;

    initializeDashboard();
    setupEventListeners();
});

/**
 * PT: Inicializa o dashboard conforme o papel do utilizador.
 * EN: Initializes the dashboard according to the user's role.
 */
function initializeDashboard() {
    document.getElementById('userInfo').textContent = `${currentUser.name} (${currentUser.role})`;

    if (currentUser.role === 'administrator') {
        document.getElementById('taskManagementNav').style.display = 'block';
        loadCollaborators();
    } else if (currentUser.role === 'collaborator') {
        document.getElementById('myTasksNav').style.display = 'block';
    }

    if (currentUser.role === 'administrator') {
        showSection('taskManagement');
    } else {
        showSection('myTasks');
    }
}

/**
 * PT: Liga listeners de formulários e upload.
 * EN: Wires up form and upload listeners.
 */
function setupEventListeners() {
    const newTaskForm = document.getElementById('newTaskForm');
    if (newTaskForm) {
        newTaskForm.addEventListener('submit', handleNewTaskSubmit);
    }

    const taskExecutionForm = document.getElementById('taskExecutionForm');
    if (taskExecutionForm) {
        taskExecutionForm.addEventListener('submit', handleTaskExecutionSubmit);
    }

    setupFileUpload();
}

/**
 * PT: Alterna a secção visível e carrega dados relevantes.
 * EN: Switches the visible section and loads relevant data.
 * @param {'dashboard'|'taskManagement'|'myTasks'} sectionName
 * @param {Event} [ev]
 */
function showSection(sectionName, ev) {
    const sections = ['dashboardSection', 'taskManagementSection', 'myTasksSection'];
    sections.forEach(section => {
        document.getElementById(section).style.display = 'none';
    });

    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    document.getElementById(sectionName + 'Section').style.display = 'block';
    if (ev && ev.target) {
        ev.target.classList.add('active');
    }

    switch(sectionName) {
        case 'taskManagement':
            loadTasks();
            break;
        case 'myTasks':
            loadMyTasks();
            break;
    }
}

/**
 * PT: Carrega todas as tarefas (admin) e renderiza a tabela.
 * EN: Loads all tasks (admin) and renders the table.
 * @returns {Promise<void>}
 */
async function loadTasks() {
    const tasksTable = document.getElementById('tasksTable');
    if (tasksTable) tasksTable.innerHTML = renderTasksSkeleton();
    try {
        const response = await fetch('/api/tasks/', {
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            displayTasks(data.data);
        } else {
            showToast('Failed to load tasks', 'error');
            if (tasksTable) tasksTable.innerHTML = '<div class="alert alert-danger">Failed to load tasks</div>';
        }
    } catch (error) {
        showToast('Network error loading tasks', 'error');
        console.error('Error loading tasks:', error);
        if (tasksTable) tasksTable.innerHTML = '<div class="alert alert-danger">Network error loading tasks</div>';
    }
}

/**
 * PT: Constrói HTML da tabela de tarefas.
 * EN: Builds the HTML for the tasks table.
 * @param {Array<Object>} tasks
 */
function displayTasks(tasks) {
    const tasksTable = document.getElementById('tasksTable');

    if (!tasks || tasks.length === 0) {
        tasksTable.innerHTML = '<div class="alert alert-info">No tasks found.</div>';
        return;
    }

    let html = `
        <div class="table-responsive animate__animated animate__fadeInUp">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Assigned To</th>
                        <th>Task Type</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;

    tasks.forEach(task => {
        const statusBadge = getStatusBadge(task.status);
        const createdDate = new Date(task.created_at).toLocaleDateString();

        html += `
            <tr>
                <td>${task.id}</td>
                <td>${task.user_name}</td>
                <td>${task.task_type}</td>
                <td>${task.description.substring(0, 50)}${task.description.length > 50 ? '...' : ''}</td>
                <td>${statusBadge}</td>
                <td>${createdDate}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewTask(${task.id})">
                        <i class="bi bi-eye"></i> View
                    </button>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    tasksTable.innerHTML = html;
}

/**
 * PT: Carrega tarefas do colaborador atual e renderiza cartões.
 * EN: Loads current collaborator's tasks and renders cards.
 * @returns {Promise<void>}
 */
async function loadMyTasks() {
    const myTasksList = document.getElementById('myTasksList');
    if (myTasksList) myTasksList.innerHTML = renderMyTasksSkeleton();
    try {
        const response = await fetch('/api/tasks/my-tasks', {
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            displayMyTasks(data.data);
        } else {
            showToast('Failed to load your tasks', 'error');
            if (myTasksList) myTasksList.innerHTML = '<div class="alert alert-danger">Failed to load your tasks</div>';
        }
    } catch (error) {
        showToast('Network error loading tasks', 'error');
        console.error('Error loading my tasks:', error);
        if (myTasksList) myTasksList.innerHTML = '<div class="alert alert-danger">Network error loading tasks</div>';
    }
}

/**
 * PT: Constrói HTML dos cartões de "As Minhas Tarefas".
 * EN: Builds HTML for "My Tasks" cards.
 * @param {Array<Object>} tasks
 */
function displayMyTasks(tasks) {
    const myTasksList = document.getElementById('myTasksList');

    if (!tasks || tasks.length === 0) {
        myTasksList.innerHTML = '<div class="alert alert-info">No tasks assigned to you.</div>';
        return;
    }

    let html = '';

    tasks.forEach(task => {
        const statusBadge = getStatusBadge(task.status);
        const createdDate = new Date(task.created_at).toLocaleDateString();

        html += `
            <div class="card task-card mb-3 card-hover animate__animated animate__fadeInUp">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">${task.task_type}</h5>
                    ${statusBadge}
                </div>
                <div class="card-body">
                    <p class="card-text">${task.description}</p>
                    <small class="text-muted">Created: ${createdDate}</small>
                    <div class="mt-3">
                        ${task.status === 'pending' ? 
                            `<button class="btn btn-success" onclick="showTaskExecutionModal(${task.id}, '${task.task_type}', '${task.description.replace(/'/g, "\\'")}')">
                                <i class="bi bi-play-circle"></i> Submit Execution
                            </button>` : 
                            '<span class="text-success"><i class="bi bi-check-circle"></i> Task completed</span>'
                        }
                    </div>
                </div>
            </div>
        `;
    });

    myTasksList.innerHTML = html;
}

/**
 * PT: Devolve um badge HTML para o estado da tarefa.
 * EN: Returns an HTML badge for the given task status.
 * @param {'pending'|'in_progress'|'completed'|string} status
 * @returns {string}
 */
function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning">Pending</span>',
        'in_progress': '<span class="badge bg-info">In Progress</span>',
        'completed': '<span class="badge bg-success">Completed</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

/**
 * PT: Carrega colaboradores para o seletor de atribuição (admin).
 * EN: Loads collaborators into the assignment select (admin).
 * @returns {Promise<void>}
 */
async function loadCollaborators() {
    try {
        const response = await fetch('/api/tasks/collaborators', {
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('taskUser');
            select.innerHTML = '<option value="">Select a collaborator...</option>';

            data.data.forEach(user => {
                select.innerHTML += `<option value="${user.id}">${user.name} (${user.email})</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading collaborators:', error);
    }
}

/**
 * PT: Abre a modal de criação de nova tarefa.
 * EN: Opens the new task creation modal.
 */
function showNewTaskModal() {
    const modal = new bootstrap.Modal(document.getElementById('newTaskModal'));
    modal.show();
}

/**
 * PT: Prepara e abre a modal de submissão de execução de tarefa.
 * EN: Prepares and opens the task execution submission modal.
 * @param {number} taskId
 * @param {string} taskType
 * @param {string} taskDescription
 */
function showTaskExecutionModal(taskId, taskType, taskDescription) {
    document.getElementById('executionTaskId').value = taskId;
    document.getElementById('executionTaskType').textContent = taskType;
    document.getElementById('executionTaskDescription').textContent = taskDescription;

    document.getElementById('taskExecutionForm').reset();
    document.getElementById('selectedFile').style.display = 'none';

    const modal = new bootstrap.Modal(document.getElementById('taskExecutionModal'));
    modal.show();
}

/**
 * PT: Mostra detalhes de uma tarefa em modal.
 * EN: Displays task details in a modal view.
 * @param {number} taskId
 * @returns {Promise<void>}
 */
async function viewTask(taskId) {
    try {
        const res = await fetch(`/api/tasks/show/${taskId}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Failed to load task', 'error');
            return;
        }
        const t = data.data;
        document.getElementById('viewTaskId').textContent = t.id;
        document.getElementById('viewTaskAssigned').textContent = `${t.user_name} (${t.user_email})`;
        document.getElementById('viewTaskType').textContent = t.task_type;
        document.getElementById('viewTaskDescription').textContent = t.description;
        document.getElementById('viewTaskStatus').innerHTML = getStatusBadge(t.status);
        document.getElementById('viewTaskCreated').textContent = new Date(t.created_at).toLocaleString();
        const fileEl = document.getElementById('viewTaskFile');
        const href = t.execution_file_url || t.execution_file_path;
        if (href) {
            fileEl.innerHTML = `<a href="${href}" target="_blank" rel="noopener">${t.execution_file_name || 'View file'}</a>`;
        } else {
            fileEl.textContent = '\u2014';
        }
        const modal = new bootstrap.Modal(document.getElementById('taskViewModal'));
        modal.show();
    } catch (err) {
        showToast('Failed to load task', 'error');
        console.error('viewTask error:', err);
    }
}

/**
 * PT: Submete o formulário de nova tarefa.
 * EN: Submits the new task form.
 * @param {SubmitEvent} e
 * @returns {Promise<void>}
 */
async function handleNewTaskSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);

    try {
        setPageLoading(true);
        const response = await fetch('/api/tasks/', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            showToast('Task created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('newTaskModal')).hide();
            e.target.reset();
            loadTasks();
        } else {
            showToast(data.message || 'Failed to create task', 'error');
        }
    } catch (error) {
        showToast('Network error creating task', 'error');
        console.error('Error creating task:', error);
    } finally {
        setPageLoading(false);
    }
}

/**
 * PT: Configura a UI de upload por seleção e drag&drop.
 * EN: Sets up the upload UI for selection and drag&drop.
 */
function setupFileUpload() {
    const fileInput = document.getElementById('executionFile');
    const uploadArea = document.getElementById('fileUploadArea');
    const selectedFileDiv = document.getElementById('selectedFile');
    const selectedFileName = document.getElementById('selectedFileName');

    const MAX_BYTES = 100 * 1024 * 1024;
    const allowedExts = ['txt','pdf','doc','docx','xls','xlsx','csv','exl'];

    function validateAndShow(file) {
        if (!file) return false;
        const name = file.name || '';
        const size = file.size || 0;
        const ext = name.split('.').pop().toLowerCase();
        if (!allowedExts.includes(ext)) {
            showToast('Formato inválido. Permitidos: .txt, .pdf, .doc, .docx, .xls, .xlsx, .csv, .exl', 'warning');
            fileInput.value = '';
            selectedFileDiv.style.display = 'none';
            return false;
        }
        if (size > MAX_BYTES) {
            showToast('Ficheiro demasiado grande. Máximo 100MB.', 'warning');
            fileInput.value = '';
            selectedFileDiv.style.display = 'none';
            return false;
        }
        selectedFileName.textContent = name;
        selectedFileDiv.style.display = 'block';
        return true;
    }

    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        validateAndShow(file);
    });

    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            if (validateAndShow(files[0])) {
                fileInput.files = files;
            }
        }
    });
}

/**
 * PT: Valida e submete a execução da tarefa com ficheiro.
 * EN: Validates and submits task execution with file.
 * @param {SubmitEvent} e
 * @returns {Promise<void>}
 */
async function handleTaskExecutionSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const fileInput = document.getElementById('executionFile');
    const file = fileInput && fileInput.files && fileInput.files[0];
    const MAX_BYTES = 100 * 1024 * 1024;
    const allowedExts = ['txt','pdf','doc','docx','xls','xlsx','csv','exl'];

    if (!file) {
        showToast('Selecione um ficheiro para enviar.', 'warning');
        return;
    }

    if (file) {
        const name = file.name || '';
        const size = file.size || 0;
        const ext = name.split('.').pop().toLowerCase();
        if (!allowedExts.includes(ext)) {
            showToast('Formato inválido. Permitidos: .txt, .pdf, .doc, .docx, .xls, .xlsx, .csv, .exl', 'warning');
            return;
        }
        if (size > MAX_BYTES) {
            showToast('Ficheiro demasiado grande. Máximo 100MB.', 'warning');
            return;
        }
    }

    const formData = new FormData(form);

    try {
        setPageLoading(true);
        const response = await fetch('/api/executions/submit', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            showToast('Task execution submitted successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('taskExecutionModal')).hide();
            form.reset();
            document.getElementById('selectedFile').style.display = 'none';
            loadMyTasks();
        } else {
            showToast(data.message || 'Failed to submit execution', 'error');
        }
    } catch (error) {
        showToast('Network error submitting execution', 'error');
        console.error('Error submitting execution:', error);
    } finally {
        setPageLoading(false);
    }
}
