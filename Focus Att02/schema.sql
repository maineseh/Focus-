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
    ordem INTEGER DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS disciplinas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INTEGER NOT NULL,
    id_modulo INTEGER,
    nome VARCHAR(100) NOT NULL,
    dificuldade INTEGER DEFAULT 1,
    ordem INTEGER DEFAULT 0,
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

-- Controle Parental -----------------------------------------------------

CREATE TABLE IF NOT EXISTS responsaveis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vínculo entre um responsável e um filho. O "token" é o link mágico:
-- quem tiver o link cai direto no painel daquele responsável (sem senha).
-- "status" controla a aprovação: vínculo criado pelo FILHO (link) já nasce
-- 'ativo'; vínculo criado pelo RESPONSÁVEL (por e-mail) nasce 'pendente' até
-- o filho aprovar em Controle Parental.
CREATE TABLE IF NOT EXISTS vinculos_parentais (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_responsavel INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    ativo BOOLEAN DEFAULT 1,
    status VARCHAR(20) DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_responsavel) REFERENCES responsaveis(id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tarefas do dia definidas pelo responsável pra um filho específico.
CREATE TABLE IF NOT EXISTS tarefas_responsavel (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INTEGER NOT NULL,
    id_responsavel INTEGER NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    data_alvo DATE NOT NULL,
    concluida BOOLEAN DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_responsavel) REFERENCES responsaveis(id) ON DELETE CASCADE
);

-- Minutos de uso do site por dia (heartbeat enviado pelo navegador enquanto
-- a aba está visível), pra alimentar "tempo total no site" no painel do
-- responsável — independente do tempo de estudo (Pomodoro) por disciplina.
CREATE TABLE IF NOT EXISTS atividade_site (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INTEGER NOT NULL,
    data DATE NOT NULL,
    minutos INTEGER DEFAULT 0,
    UNIQUE(id_usuario, data),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Comunidades ------------------------------------------------------------

-- Pedido/relação de amizade entre dois usuários. Uma linha por par, sempre
-- na direção de quem pediu (solicitante -> destinatario).
CREATE TABLE IF NOT EXISTS amizades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    solicitante_id INTEGER NOT NULL,
    destinatario_id INTEGER NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente', -- pendente, aceito
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(solicitante_id, destinatario_id),
    FOREIGN KEY (solicitante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Bloqueio é sempre unidirecional: "usuario_id" bloqueou "bloqueado_id".
-- motivo 'manual' = o usuário bloqueou por conta própria; 'automatico' =
-- o sistema bloqueou sozinho por comportamento suspeito repetido.
CREATE TABLE IF NOT EXISTS bloqueios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    bloqueado_id INTEGER NOT NULL,
    motivo VARCHAR(50) DEFAULT 'manual',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_id, bloqueado_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (bloqueado_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS grupos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(280) DEFAULT '',
    foto VARCHAR(255) DEFAULT '',
    criador_id INTEGER NOT NULL,
    permitir_links BOOLEAN DEFAULT 0,
    chat_bloqueado BOOLEAN DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criador_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS grupo_membros (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    grupo_id INTEGER NOT NULL,
    usuario_id INTEGER NOT NULL,
    papel VARCHAR(20) DEFAULT 'membro', -- criador, admin, membro
    entrou_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(grupo_id, usuario_id),
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Membro "de castigo" fica mudo no chat do grupo até expira_em (aplicado
-- por um admin ou pelo criador). Não impede de ler, só de mandar mensagem.
CREATE TABLE IF NOT EXISTS grupo_castigos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    grupo_id INTEGER NOT NULL,
    usuario_id INTEGER NOT NULL,
    expira_em TIMESTAMP NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(grupo_id, usuario_id),
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Mensagens de DM (destinatario_id preenchido) OU de grupo (grupo_id
-- preenchido) — nunca os dois ao mesmo tempo.
CREATE TABLE IF NOT EXISTS mensagens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    remetente_id INTEGER NOT NULL,
    destinatario_id INTEGER,
    grupo_id INTEGER,
    tipo VARCHAR(20) NOT NULL DEFAULT 'texto', -- texto, imagem, link
    conteudo TEXT NOT NULL,
    fixada BOOLEAN DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (remetente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
);

-- Registro de conteúdo sinalizado pelo filtro de segurança (nunca guarda o
-- conteúdo impróprio em si, só o motivo/categoria), pra alimentar o
-- monitoramento do Controle Parental.
CREATE TABLE IF NOT EXISTS alertas_seguranca (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INTEGER NOT NULL,
    autor_id INTEGER,
    motivo VARCHAR(150) NOT NULL,
    contexto VARCHAR(20) NOT NULL, -- dm, grupo
    grupo_id INTEGER, -- preenchido só quando contexto = 'grupo', pra poder contar por grupo
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revisado BOOLEAN DEFAULT 0,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
);

-- Quem foi removido automaticamente de um grupo por comportamento suspeito
-- repetido fica banido daquele grupo (não pode ser readicionado) até um
-- admin desbanir explicitamente.
CREATE TABLE IF NOT EXISTS grupo_banidos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    grupo_id INTEGER NOT NULL,
    usuario_id INTEGER NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(grupo_id, usuario_id),
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
