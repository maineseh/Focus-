CREATE DATABASE IF NOT EXISTS sistema_estudos;
USE sistema_estudos;

-- 1. TABELA DE USUÁRIOS
-- Contém todos os campos identificados nos seus logs (Energia, Perfil Cognitivo, etc)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    username VARCHAR(50) UNIQUE,
    foto_perfil VARCHAR(255) DEFAULT 'img/padrao.png',
    remember_token VARCHAR(255) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    codigo_recuperacao VARCHAR(255) NULL,
    codigo_expiracao DATETIME NULL,
    perfil_cognitivo VARCHAR(50) DEFAULT 'Nenhum',
    nivel_energia INT DEFAULT 0
) ENGINE=InnoDB;

-- 2. TABELA DE DISCIPLINAS
CREATE TABLE IF NOT EXISTS disciplinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    dificuldade INT DEFAULT 1,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. TABELA DE TAREFAS
CREATE TABLE IF NOT EXISTS tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_disciplina INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    concluida BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_disciplina) REFERENCES disciplinas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. TABELA DE METAS DA AGENDA
-- Atualizada com os campos que você definiu acima
CREATE TABLE IF NOT EXISTS agenda_metas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    esforco INT NOT NULL, -- 1: Leve, 3: Médio, 5: Intenso
    dia_semana INT NOT NULL, -- 0 (Dom) a 6 (Sáb)
    concluida BOOLEAN DEFAULT FALSE, -- Adicionei para o botão de check funcionar
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;