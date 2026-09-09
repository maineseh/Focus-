-- Schema SQLite do Focus (convertido do MySQL original)

CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    username VARCHAR(50) UNIQUE,
    foto_perfil VARCHAR(255) DEFAULT 'img/ex1.png',
    remember_token VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    codigo_recuperacao VARCHAR(255),
    codigo_expiracao DATETIME,
    perfil_cognitivo VARCHAR(50) DEFAULT 'Nenhum',
    nivel_energia INTEGER DEFAULT 0,
    bio VARCHAR(280) DEFAULT '',
    nome_exibicao VARCHAR(100) DEFAULT '',
    pronomes VARCHAR(50) DEFAULT '',
    mostrar_nome_real BOOLEAN DEFAULT 0,
    status_disponibilidade VARCHAR(20) DEFAULT 'online'
);

CREATE TABLE IF NOT EXISTS modulos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INTEGER NOT NULL,
    nome VARCHAR(100) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS disciplinas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INTEGER NOT NULL,
    id_modulo INTEGER,
    nome VARCHAR(100) NOT NULL,
    dificuldade INTEGER DEFAULT 1,
    anotacoes TEXT DEFAULT '',
    desenho TEXT DEFAULT '',
    minutos_estudados INTEGER DEFAULT 0,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_modulo) REFERENCES modulos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tarefas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_disciplina INTEGER NOT NULL,
    nome VARCHAR(255) NOT NULL,
    concluida BOOLEAN DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_disciplina) REFERENCES disciplinas(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sessoes_estudo (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_disciplina INTEGER NOT NULL,
    duracao_minutos INTEGER NOT NULL,
    concluida_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_disciplina) REFERENCES disciplinas(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS agenda_metas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    esforco INTEGER NOT NULL,
    dia_semana INTEGER NOT NULL,
    concluida BOOLEAN DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
