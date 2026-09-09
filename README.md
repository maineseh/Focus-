# Focus (versão Python/Flask + SQLite)

Esta é a migração do projeto PHP + MySQL "Focus" (pasta `Inter26.2 -Versao Apresentada`)
para **Python (Flask) + SQLite**. As páginas, o visual e o comportamento foram mantidos
iguais — só a base tecnológica mudou.

## O que foi migrado

- **Login, Cadastro, Logout** — com senha criptografada (agora usando `werkzeug.security`
  em vez do `password_hash` do PHP).
- **"Lembrar de mim"** — cookie de 30 dias, igual ao original.
- **Recuperação de senha** (`esqueceu_senha` → `nova_senha`) — o link de redefinição.
- **Setup de perfil / Editar perfil** — escolha de nome de usuário e avatar.
- **Dashboard, Agenda, Meus Estudos, Meu Desempenho, Configurações** — todas as telas,
  com o mesmo HTML/CSS/JS de antes.
- **Banco de dados**: só a tabela `usuarios` era realmente usada pelo PHP (as outras —
  `disciplinas`, `tarefas`, `agenda_metas` — existiam no `banco de dados.sql` mas na
  prática as telas de Agenda e Meus Estudos guardam os dados no `localStorage` do
  navegador, não no banco). Mantive esse mesmo comportamento para não mudar a
  experiência; as tabelas extras já estão criadas no SQLite caso você queira migrar
  essa parte para o banco de verdade no futuro.
- **`meu_desempenho`**: os gráficos usam dados simulados (gerados aleatoriamente),
  exatamente como no PHP original — não é um cálculo real de horas estudadas.

## Como rodar no seu computador

Pré-requisito: ter o **Python 3** instalado (baixe em python.org se não tiver).

1. Abra o terminal (Prompt de Comando/PowerShell no Windows, Terminal no Mac/Linux)
   dentro da pasta `focus_py`.
2. Instale as dependências:
   ```
   pip install -r requirements.txt
   ```
3. Rode o site:
   ```
   python app.py
   ```
4. Abra o navegador em: **http://127.0.0.1:5000**

O banco de dados SQLite (`instance/focus.db`) é criado automaticamente na primeira
execução — não precisa instalar MySQL nem nada parecido.

## Estrutura do projeto

```
focus_py/
├── app.py              -> todas as rotas (equivalente aos .php de cada página)
├── db.py                -> conexão com o SQLite (equivalente ao conexao.php)
├── schema.sql            -> estrutura das tabelas (equivalente ao banco de dados.sql)
├── requirements.txt
├── templates/            -> as páginas HTML (equivalentes aos .php, agora com Jinja2)
├── static/img/           -> as imagens (avatares, logo do gato)
└── instance/focus.db      -> banco de dados (criado automaticamente)
```

## Diferenças importantes em relação ao PHP

- As senhas antigas do banco MySQL **não funcionam aqui** — é um banco novo, então
  você precisa criar uma conta nova pelo `/cadastro`.
- O hash de senha usado é diferente do PHP (`password_hash`), mas igualmente seguro.
- Os links `pagina.php` viraram rotas Flask como `/login`, `/dashboard`, etc.
