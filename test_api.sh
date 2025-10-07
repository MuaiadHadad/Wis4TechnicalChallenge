#!/bin/bash

echo "=== Testando API do Backend ==="
echo ""

echo "1. Testando GET /api/auth/check"
curl -X GET http://localhost/api/auth/check -w "\nStatus: %{http_code}\n\n" 2>/dev/null || echo "Falhou"

echo "2. Testando POST /api/auth/login (sem credenciais)"
curl -X POST http://localhost/api/auth/login -w "\nStatus: %{http_code}\n\n" 2>/dev/null || echo "Falhou"

echo "3. Verificando se o container backend está rodando"
docker ps | grep wis4_backend || docker ps | grep backend

echo ""
echo "=== Fim dos testes ==="

