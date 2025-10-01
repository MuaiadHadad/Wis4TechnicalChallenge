const API_BASE_URL = '/api';

document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname === '/' || window.location.pathname === '/index.html') {
        checkAuthStatus();
    }
});

/**
 * PT: Verifica o estado de autenticação e redireciona para o dashboard se já estiver autenticado.
 * EN: Checks authentication status and redirects to the dashboard if already authenticated.
 * @returns {Promise<void>}
 */
async function checkAuthStatus() {
    try {
        const response = await fetch(`${API_BASE_URL}/auth/check`, {
            method: 'GET',
            credentials: 'include'
        });
        const data = await response.json();
        if (data.authenticated) {
            window.location.href = 'dashboard.html';
        }
    } catch (error) {
        console.error('Auth check failed:', error);
    }
}

if (document.getElementById('loginForm')) {
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        /**
         * PT: Submete o formulário de login e autentica o utilizador.
         * EN: Submits the login form and authenticates the user.
         */
        e.preventDefault();

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const errorDiv = document.getElementById('errorMessage');

        try {
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);

            const response = await fetch(`${API_BASE_URL}/auth/login`, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                localStorage.setItem('user', JSON.stringify(data.user));
                if (typeof showToast === 'function') showToast('Login successful', 'success');
                window.location.href = 'dashboard.html';
            } else {
                const msg = data.message || 'Login failed';
                if (errorDiv) {
                    errorDiv.textContent = msg;
                    errorDiv.style.display = 'block';
                }
                if (typeof showToast === 'function') showToast(msg, 'error');
            }
        } catch (error) {
            const msg = 'Network error. Please try again.';
            if (errorDiv) {
                errorDiv.textContent = msg;
                errorDiv.style.display = 'block';
            }
            if (typeof showToast === 'function') showToast(msg, 'error');
            console.error('Login error:', error);
        }
    });
}

/**
 * PT: Encerra a sessão no servidor e redireciona para a página de login.
 * EN: Logs out on the server and redirects to the login page.
 * @returns {Promise<void>}
 */
async function logout() {
    try {
        await fetch(`${API_BASE_URL}/auth/logout`, {
            method: 'POST',
            credentials: 'include'
        });
        localStorage.removeItem('user');
        window.location.href = 'index.html';
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = 'index.html';
    }
}

/**
 * PT: Garante que a página é acedida por um utilizador autenticado; redireciona se não for.
 * EN: Ensures the page is accessed by an authenticated user; redirects otherwise.
 * @returns {Promise<object|null>} Utilizador autenticado ou null se redirecionado.
 */
async function requireAuth() {
    try {
        const response = await fetch(`${API_BASE_URL}/auth/check`, {
            method: 'GET',
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.authenticated) {
            window.location.href = 'index.html';
            return null;
        }

        return data.user;
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = 'index.html';
        return null;
    }
}
