# 🎨 SPACE Customizer Loader

![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-blue)
![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.0-green)
![License](https://img.shields.io/badge/License-GPL2-blue)

---

## 📌 Descrição

O **SPACE Customizer Loader** é um sistema que permite carregar e atualizar automaticamente funcionalidades personalizadas do WordPress a partir de um repositório remoto no GitHub.

Ele baixa o arquivo `spaceCustomizer.php` e o executa dinamicamente dentro do WordPress, garantindo que suas personalizações fiquem sempre atualizadas sem precisar editar manualmente os arquivos do plugin.

---

## ⚙️ Funcionamento

### 1. Download automático do arquivo
O loader busca o arquivo remoto:

https://raw.githubusercontent.com/equipewebnauta/spaceCustomizer/main/spaceCustomizer.php

E salva em cache no servidor:



---

### 2. Sistema de Cache Inteligente
Para evitar requisições constantes ao GitHub:

- Tempo de cache: **6 horas**
- Atualização automática após expiração
- Atualização manual via painel administrativo

---

### 3. Execução Dinâmica
Após o download, o arquivo é carregado automaticamente via WordPress, permitindo que todas as funções, hooks e integrações sejam executadas normalmente.

---

## 🖥 Painel Administrativo

O plugin adiciona o menu:


No painel é possível:

- Forçar atualização do arquivo remoto
- Verificar status do arquivo baixado
- Visualizar data da última atualização
- Ver tamanho do arquivo
- Consultar caminho do arquivo
- Visualizar conteúdo do arquivo

---

## 📁 Estrutura do Sistema

WordPress
│
├── Plugin Loader
│ └── WPLMS Customizer
│
├── Runtime
│ └── /wp-content/space-runtime/
│ └── spaceCustomizer.php
│
└── GitHub
└── spaceCustomizer Repository
└── spaceCustomizer.php


Fluxo de funcionamento:

GitHub → Download → Cache → Execução no WordPress




---

## ✅ Vantagens

- Atualização de código sem FTP
- Separação entre loader e código principal
- Redução de risco de quebra do plugin
- Organização para projetos grandes
- Funcionalidades customizadas isoladas

---

## 🔒 Segurança

- Verificação de erros HTTP
- Validação de conteúdo antes de salvar
- Execução em diretório isolado
- Atualização manual restrita a administradores

---

## 🛠 Requisitos

- WordPress 6.x ou superior
- Permissão de escrita na pasta `/wp-content`
- Conexão ativa com internet
- `wp_remote_get()` habilitado no servidor

---

## 👨‍💻 Autor

Desenvolvido por **Miguel Ferreira**

Sistema utilizado em projetos WPLMS e personalizações avançadas de WordPress.
