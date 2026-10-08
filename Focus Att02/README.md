# Focus

Focus é um site de organização de estudos: ajuda a planejar o que estudar,
acompanhar quanto tempo foi dedicado a cada matéria, manter contato com
amigos e colegas de estudo, e — pra quem tem um responsável acompanhando —
oferece um painel separado pra pais/responsáveis com controles de
segurança. É construído em Python (Flask) com banco SQLite, pensado pra
rodar tanto no computador quanto no celular.

## O que dá pra fazer

### Estudos
- **Meus Estudos**: organiza o conteúdo em **módulos** (ex: "Vestibular",
  "Concurso") e, dentro de cada módulo, **disciplinas** (ex: "Biologia",
  "Direito Constitucional").
- Em cada disciplina:
  - **Checklist** — quebra a matéria em tarefas pequenas e marca conforme
    vai concluindo.
  - **Cronômetro** — ciclos de foco (pomodoro) com tempos prontos (pausa
    curta, pausa longa, foco de 25min) ou um tempo personalizado; continua
    contando mesmo se a pessoa sair da página.
  - **Anotações** — resumos em texto, ou um **Caderno Virtual** pra
    desenhar/escrever à mão (com cores, borracha e controle de espessura
    do traço).
- **Agenda** — visão dos prazos e metas de estudo ao longo do tempo.
- **Meu Desempenho** — gráficos com o histórico de minutos estudados,
  disciplinas mais trabalhadas e evolução de humor durante os estudos.

### Comunidades
- Lista de amigos (pedidos de amizade, bloqueios), conversas diretas (DM)
  e grupos de estudo com papéis (criador, admin, membro), moderação
  (silenciar, colocar de castigo, expulsar) e um filtro automático de
  mensagens que barra links não permitidos e conteúdo sinalizado como
  arriscado (esse filtro é por padrão de texto — o Focus ainda não tem
  moderação automática de imagem, então fotos enviadas não são analisadas
  hoje).

### Perfil e Configurações
- Foto de perfil (avatares prontos ou foto da galeria, com recorte),
  pronomes, status de disponibilidade.
- **13 temas de cor** pra escolher, e uma seção de **Acessibilidade** com:
  alto contraste, fonte pensada pra dislexia (OpenDyslexic), tamanho de
  texto (padrão/médio/grande), redução de movimento/animações, e um
  overlay opcional que reflete o humor registrado na tela.
- Nada dessas escolhas é salvo até a pessoa clicar em **Sincronizar e
  Voltar** — fechar a tela sem sincronizar descarta o que foi alterado.

### Painel do Responsável
Um portal separado (login próprio, sem precisar da senha da conta do
filho/aluno) onde um responsável pode:
- Vincular-se à conta de um estudante (por convite/link).
- Acompanhar tempo de estudo, humor registrado e atividade recente.
- Ver e revisar **alertas de segurança** — mensagens sinalizadas pelo
  filtro automático (linguagem de risco, links suspeitos) — sem ler o
  histórico inteiro das conversas.
- Atribuir tarefas de estudo pro filho/aluno.

## Como é feito

- **Back-end**: Python + [Flask](https://flask.palletsprojects.com/),
  com SQLite como banco de dados (fica em `instance/focus.db`, criado
  automaticamente na primeira execução a partir de `schema.sql`).
- **Front-end**: HTML renderizado pelo servidor (Jinja2) + CSS/JS puro
  por página — sem framework de front-end. A navegação entre páginas usa
  [htmx](https://htmx.org/) (`hx-boost`) pra trocar de tela sem recarregar
  o site inteiro, mantendo a sensação de um app.
- Sem dependência de servidor externo pra funcionar: fontes e ícones vêm
  de CDN (Google Fonts, Font Awesome), mas todo o resto roda localmente.

## Rodando localmente

```bash
pip install -r requirements.txt
python app.py
```

O site sobe em `http://127.0.0.1:5000`. Na primeira execução, o banco
SQLite é criado automaticamente (a partir de `schema.sql`) dentro da
pasta `instance/`.

## Estrutura do projeto

```
app.py              # rotas e lógica do back-end (Flask)
db.py                # conexão com o banco SQLite
schema.sql            # estrutura de todas as tabelas
templates/            # páginas (HTML + Jinja2)
static/css/            # um arquivo de estilo por página/seção
static/js/              # scripts compartilhados (recorte de imagem, trilha
                          # sonora, acessibilidade, navegação)
static/img/               # imagens estáticas (avatares, logo)
teste_*.py                  # scripts de teste automatizado (ponta a ponta,
                              # cobrindo permissões de grupo, uploads,
                              # correções de bugs feitas ao longo do projeto)
```

## Testes

O projeto tem scripts de teste automatizado (`teste_*.py`) que sobem o
servidor localmente e simulam o uso real do site (cadastro, login,
criação de grupos, permissões, uploads etc.) pra evitar regressão a cada
mudança:

```bash
python teste_permissoes_grupo.py
python teste_ajustes_att48.py
# ...e os demais teste_*.py
```
