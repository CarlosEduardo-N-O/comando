"""Gera docs/openapi.json (OpenAPI 3.0) da API do Comando.

Somente mantenedores precisam executar, e sem instalar Python localmente:

    docker run --rm -v "$PWD/docs:/docs" python:3.13-alpine python /docs/generate-openapi.py

O arquivo gerado é copiado para a imagem "web" e servido em /docs/.
"""
import json
from pathlib import Path

STATUS = ['aberta', 'em_andamento', 'aguardando_solicitante', 'resolvida', 'concluida', 'cancelada']
PRIORIDADES = ['baixa', 'normal', 'alta', 'urgente']
PERFIS = ['colaborador', 'gestor', 'administrador']
ACOES = ['comentar', 'assumir', 'atribuir', 'alterar_prioridade', 'transferir', 'definir_prazo', 'iniciar',
         'solicitar_informacoes', 'resolver', 'retomar', 'concluir', 'rejeitar', 'cancelar']


def obj(props, required=None, **extra):
    schema = {'type': 'object', 'properties': props, **extra}
    if required:
        schema['required'] = required
    return schema


def ref(nome):
    return {'$ref': f'#/components/schemas/{nome}'}


def s(tipo='string', **kw):
    return {'type': tipo, **kw}


resumo_usuario = obj({'id': s('integer'), 'name': s()}, nullable=True)
resumo_nome = obj({'id': s('integer'), 'nome': s()}, nullable=True)
data_hora = s(format='date-time', description='ISO 8601 em UTC', nullable=True)

schemas = {
    'Erro': obj({'message': s(example='Você não tem permissão para realizar esta ação.')}),
    'ErroValidacao': obj({
        'message': s(example='Verifique os dados informados.'),
        'errors': {'type': 'object', 'additionalProperties': {'type': 'array', 'items': s()},
                   'example': {'responsavel_id': ['O responsável deve ser um usuário ativo do setor de destino.']}},
    }),
    'Meta': obj({'current_page': s('integer'), 'last_page': s('integer'), 'per_page': s('integer'),
                 'total': s('integer'), 'from': s('integer', nullable=True), 'to': s('integer', nullable=True)}),
    'Links': obj({'first': s(nullable=True), 'last': s(nullable=True), 'prev': s(nullable=True), 'next': s(nullable=True)}),
    'Usuario': obj({
        'id': s('integer', example=7), 'name': s(example='Ana Silva'), 'email': s(format='email', example='ana@empresa.com.br'),
        'perfil': s(enum=PERFIS), 'ativo': s('boolean'), 'setor_id': s('integer'), 'setor': resumo_nome,
        'created_at': data_hora, 'updated_at': data_hora,
    }),
    'CadastroBasico': obj({'id': s('integer'), 'nome': s(example='Financeiro'), 'descricao': s(nullable=True),
                           'ativo': s('boolean'), 'created_at': data_hora, 'updated_at': data_hora}),
    'Anexo': obj({'id': s('integer'), 'nome': s(example='nota-fiscal.pdf'), 'mime': s(example='application/pdf'),
                  'tamanho': s('integer', description='bytes'), 'comentario_id': s('integer', nullable=True), 'created_at': data_hora}),
    'Demanda': obj({
        'id': s('integer', example=42), 'protocolo': s(example='CMD-000042'), 'titulo': s(example='Configurar acesso ao ERP'),
        'status': s(enum=STATUS), 'prioridade': s(enum=PRIORIDADES),
        'categoria': resumo_nome, 'solicitante': resumo_usuario, 'responsavel': resumo_usuario,
        'setor_origem': resumo_nome, 'setor_destino': resumo_nome,
        'prazo_desejado': s(format='date', nullable=True), 'prazo_atendimento': data_hora,
        'atrasada': s('boolean', description='Prazo de atendimento vencido em Aberta, Em andamento ou Aguardando solicitante'),
        'created_at': data_hora, 'updated_at': data_hora, 'resolvida_em': data_hora, 'concluida_em': data_hora, 'cancelada_em': data_hora,
    }),
    'DemandaDetalhe': {'allOf': [ref('Demanda'), obj({
        'descricao': s(),
        'anexos': {'type': 'array', 'items': ref('Anexo'), 'description': 'Anexos da abertura (os de comentários vêm em /comentarios)'},
        'informacao_solicitada': s(nullable=True, description='Última solicitação de informações (status Aguardando solicitante)'),
        'solucao': s(nullable=True, description='Última solução informada (Resolvida/Concluída)'),
        'acoes': {'type': 'array', 'items': s(enum=ACOES), 'description': 'Ações permitidas ao usuário atual (o servidor revalida tudo)'},
    })]},
    'DemandaFila': obj({
        'id': s('integer'), 'protocolo': s(), 'titulo': s(), 'categoria': resumo_nome, 'prioridade': s(enum=PRIORIDADES),
        'created_at': data_hora, 'pode_assumir': s('boolean'),
    }, description='Visão resumida da fila do setor (sem descrição, solicitante ou anexos).'),
    'Comentario': obj({'id': s('integer'), 'mensagem': s(description='Texto puro; nunca interpretado como HTML'),
                       'autor': resumo_usuario, 'anexos': {'type': 'array', 'items': ref('Anexo')}, 'created_at': data_hora}),
    'Historico': obj({
        'id': s('integer'), 'acao': s(enum=['criacao', 'atribuicao', 'reatribuicao', 'assuncao', 'transferencia', 'prioridade', 'prazo',
                                            'inicio_atendimento', 'retomada_atendimento', 'solicitacao_informacoes', 'resolucao',
                                            'rejeicao', 'conclusao', 'cancelamento']),
        'autor': resumo_usuario, 'justificativa': s(nullable=True),
        'antes': {'type': 'object', 'nullable': True, 'description': 'Valores anteriores dos campos alterados'},
        'depois': {'type': 'object', 'nullable': True, 'description': 'Novos valores dos campos alterados'},
        'created_at': data_hora,
    }),
    'Notificacao': obj({
        'id': s(format='uuid'), 'tipo': s(enum=['atribuida', 'nova_no_setor', 'reatribuida', 'responsavel_alterado', 'transferida', 'comentario',
                                                 'solicitacao_informacoes', 'resolvida', 'rejeitada', 'concluida', 'cancelada']),
        'mensagem': s(example='A demanda CMD-000042 foi atribuída a você.'), 'protocolo': s(), 'demanda_id': s('integer'),
        'demanda_disponivel': s('boolean', description='Falso quando o usuário perdeu o acesso à demanda'),
        'lida': s('boolean'), 'read_at': data_hora, 'created_at': data_hora,
    }),
    'Dashboard': obj({k: s('integer') for k in ['minhas_abertas', 'atribuidas_a_mim', 'aguardando_minha_resposta',
                                                 'aguardando_minha_confirmacao', 'atrasadas', 'fila_do_setor']}),
    # Entradas
    'Login': obj({'email': s(format='email', example='ana@empresa.com.br'), 'password': s(format='password')}, ['email', 'password']),
    'PerfilInput': obj({'name': s(maxLength=255)}, ['name']),
    'SenhaInput': obj({'current_password': s(format='password'), 'password': s(format='password', minLength=10, description='Letras e números'),
                       'password_confirmation': s(format='password')}, ['current_password', 'password', 'password_confirmation']),
    'UsuarioInput': obj({'name': s(), 'email': s(format='email'), 'password': s(format='password', minLength=10, description='Obrigatória na criação; na alteração redefine a senha e encerra as sessões'),
                         'setor_id': s('integer'), 'perfil': s(enum=PERFIS), 'ativo': s('boolean')},
                        description='POST exige name, email, password, setor_id e perfil. PATCH aceita qualquer subconjunto.'),
    'CadastroBasicoInput': obj({'nome': s(maxLength=120), 'descricao': s(nullable=True), 'ativo': s('boolean')}),
    'DemandaInput': obj({
        'titulo': s(maxLength=200, example='Configurar acesso ao ERP'), 'descricao': s(maxLength=20000),
        'categoria_id': s('integer'), 'setor_destino_id': s('integer'),
        'responsavel_id': s('integer', nullable=True, description='Opcional: colaborador ativo do setor de destino, diferente do solicitante'),
        'prioridade': s(enum=PRIORIDADES), 'prazo_desejado': s(format='date', nullable=True),
        'anexos[]': {'type': 'array', 'maxItems': 5, 'items': s(format='binary'),
                     'description': 'Até 5 arquivos de 10 MB: pdf, jpg, jpeg, png, webp, txt, docx, xlsx'},
    }, ['titulo', 'descricao', 'categoria_id', 'setor_destino_id', 'prioridade'],
        description='Solicitante, setor de origem, status e protocolo são definidos pelo servidor e ignorados se enviados.'),
    'ComentarioInput': obj({'mensagem': s(maxLength=20000), 'anexos[]': {'type': 'array', 'maxItems': 5, 'items': s(format='binary')}}, ['mensagem']),
    'AtribuicaoInput': {'oneOf': [
        obj({'assumir': s('boolean', enum=[True])}, ['assumir'], description='Colaborador do setor de destino assume a demanda sem responsável'),
        obj({'responsavel_id': s('integer', nullable=True)}, ['responsavel_id'], description='Gestor do destino ou administrador distribui/reatribui; null somente em demandas abertas'),
    ]},
    'DestinoInput': obj({'setor_destino_id': s('integer'), 'responsavel_id': s('integer', nullable=True), 'justificativa': s()},
                        ['setor_destino_id', 'justificativa']),
    'PrioridadeInput': obj({'prioridade': s(enum=PRIORIDADES), 'justificativa': s(nullable=True)}, ['prioridade']),
    'PrazoInput': obj({'prazo_atendimento': s(format='date-time', nullable=True, example='2026-12-01T18:00:00-03:00'),
                       'justificativa': s(nullable=True)}, ['prazo_atendimento']),
    'StatusInput': obj({
        'status': s(enum=['em_andamento', 'aguardando_solicitante', 'resolvida', 'concluida', 'cancelada']),
        'justificativa': s(description='Obrigatória ao solicitar informações, resolver (solução), rejeitar ou cancelar'),
    }, ['status']),
}

DESCRICAO = """API REST do **Comando** — gestão de demandas corporativas.

**Autenticação (Sanctum SPA, sessão por cookie):**
1. `GET /sanctum/csrf-cookie` (fora do prefixo `/api/v1`) define o cookie `XSRF-TOKEN`.
2. `POST /api/v1/auth/login` cria a sessão em cookie HttpOnly. Nenhum token é devolvido.
3. Em toda mutação envie o cabeçalho `X-XSRF-TOKEN` com o valor (URL-decodificado) do cookie `XSRF-TOKEN`.
   Esta página faz isso automaticamente. Entre primeiro pelo frontend ou pelo endpoint de login.

**Convenções:** JSON; datas ISO 8601 em UTC; listagens paginadas com `data`, `meta` e `links`;
erros com `message` e, em validações/regras de negócio, `errors` por campo.

**Códigos:** 200/201 sucesso · 401 sem sessão · 403 sem permissão · 404 inexistente/sem acesso ao recurso ·
409 conflito (demanda já assumida) · 419 CSRF/sessão expirada · 422 validação ou regra de negócio · 429 limite de tentativas.

**Permissões** são sempre verificadas no servidor por perfil (colaborador, gestor, administrador) e participação na demanda.
"""

doc = {
    'openapi': '3.0.3',
    'info': {'title': 'Comando API', 'version': '1.0.0', 'description': DESCRICAO},
    'servers': [{'url': '/api/v1'}],
    'security': [{'sessao': []}],
    'tags': [{'name': n} for n in ['autenticacao', 'demandas', 'interacoes', 'notificacoes', 'dashboard', 'opcoes', 'administracao', 'sistema']],
    'components': {
        'securitySchemes': {'sessao': {'type': 'apiKey', 'in': 'cookie', 'name': 'comando-session',
                                       'description': 'Cookie HttpOnly de sessão criado no login'}},
        'schemas': schemas,
        'responses': {
            'NaoAutenticado': {'description': 'Sessão ausente, expirada ou usuário inativo', 'content': {'application/json': {'schema': ref('Erro')}}},
            'SemPermissao': {'description': 'Sem permissão', 'content': {'application/json': {'schema': ref('Erro')}}},
            'NaoEncontrado': {'description': 'Registro não encontrado', 'content': {'application/json': {'schema': ref('Erro')}}},
            'Validacao': {'description': 'Dados inválidos ou regra de negócio violada', 'content': {'application/json': {'schema': ref('ErroValidacao')}}},
            'Conflito': {'description': 'Conflito de concorrência', 'content': {'application/json': {'schema': ref('ErroValidacao')}}},
            'Csrf': {'description': 'Token CSRF ausente/inválido ou sessão expirada', 'content': {'application/json': {'schema': ref('Erro')}}},
        },
    },
    'paths': {},
}


def resposta(schema=None, lista=False, descricao='Sucesso'):
    if schema is None:
        return {'description': descricao}
    corpo = {'data': {'type': 'array', 'items': ref(schema)} if lista else ref(schema)}
    if lista:
        corpo.update({'meta': ref('Meta'), 'links': ref('Links')})
    return {'description': descricao, 'content': {'application/json': {'schema': obj(corpo)}}}


def op(caminho, metodo, resumo, permissao, tag, corpo=None, multipart=False, params=None, ok=None, codigo='200', extras=()):
    respostas = {codigo: ok or resposta()}
    if caminho != '/auth/login' and caminho != '/health':
        respostas['401'] = {'$ref': '#/components/responses/NaoAutenticado'}
    for c in extras:
        respostas[c] = {'$ref': '#/components/responses/' + {'403': 'SemPermissao', '404': 'NaoEncontrado', '409': 'Conflito',
                                                              '419': 'Csrf', '422': 'Validacao'}[c]}
    if metodo != 'get':
        respostas.setdefault('419', {'$ref': '#/components/responses/Csrf'})
    entrada = {'summary': resumo, 'description': permissao, 'tags': [tag], 'responses': respostas}
    path_params = [p[1:-1] for p in caminho.split('/') if p.startswith('{')]
    entrada['parameters'] = [{'name': p, 'in': 'path', 'required': True,
                              'schema': {'type': 'string', 'format': 'uuid'} if p == 'id' and 'notificacoes' in caminho else {'type': 'integer'}}
                             for p in path_params] + (params or [])
    if corpo:
        tipo = 'multipart/form-data' if multipart else 'application/json'
        entrada['requestBody'] = {'required': True, 'content': {tipo: {'schema': ref(corpo)}}}
    doc['paths'].setdefault(caminho, {})[metodo] = entrada


def q(nome, tipo='string', descricao=None, **kw):
    p = {'name': nome, 'in': 'query', 'required': False, 'schema': {'type': tipo, **kw}}
    if descricao:
        p['description'] = descricao
    return p


# --- Sistema e autenticação
op('/health', 'get', 'Saúde da aplicação', 'Público. Sem dados sensíveis. 503 quando o banco está indisponível.', 'sistema',
   ok={'description': 'Saudável', 'content': {'application/json': {'example': {'status': 'ok', 'banco': 'ok'}}}})
doc['paths']['/health']['get']['security'] = []
op('/auth/login', 'post', 'Entrar', 'Público. Somente usuários ativos. Limite: 5 tentativas por e-mail+IP por minuto (429).', 'autenticacao',
   'Login', ok=resposta('Usuario'), extras=('422',))
doc['paths']['/auth/login']['post']['security'] = []
doc['paths']['/auth/login']['post']['responses']['429'] = {'description': 'Muitas tentativas', 'content': {'application/json': {'schema': ref('ErroValidacao')}}}
op('/auth/logout', 'post', 'Sair', 'Invalida a sessão atual.', 'autenticacao', codigo='204', ok={'description': 'Sessão encerrada'})
op('/auth/me', 'get', 'Usuário autenticado', 'Qualquer usuário autenticado.', 'autenticacao', ok=resposta('Usuario'))
op('/auth/me', 'patch', 'Alterar o próprio nome', 'Somente o nome. Perfil, setor e situação enviados são ignorados.', 'autenticacao',
   'PerfilInput', ok=resposta('Usuario'), extras=('422',))
op('/auth/password', 'put', 'Alterar a própria senha', 'Exige a senha atual. Encerra as demais sessões do usuário.', 'autenticacao',
   'SenhaInput', extras=('422',), ok={'description': 'Senha alterada', 'content': {'application/json': {'example': {'message': 'Senha alterada. As demais sessões foram encerradas.'}}}})

# --- Administração
for recurso, schema, entrada, extra in [
    ('usuarios', 'Usuario', 'UsuarioInput', [q('setor_id', 'integer'), q('perfil', enum=PERFIS)]),
    ('setores', 'CadastroBasico', 'CadastroBasicoInput', []),
    ('categorias', 'CadastroBasico', 'CadastroBasicoInput', []),
]:
    regras = ('Somente administrador. Não há exclusão física: use ativo=false. Bloqueia mudança de setor/inativação de quem é responsável '
              'por demandas não encerradas e a remoção do último administrador ativo. Inativação e nova senha encerram as sessões.'
              if recurso == 'usuarios' else 'Somente administrador. Itens inativos permanecem no histórico, mas não podem ser usados em novas demandas.')
    filtros = [q('busca', descricao='Nome' + (' ou e-mail' if recurso == 'usuarios' else '')), q('ativo', enum=['0', '1']),
               q('page', 'integer'), q('por_pagina', 'integer', minimum=1, maximum=100)] + extra
    op(f'/{recurso}', 'get', f'Listar {recurso}', regras, 'administracao', params=filtros, ok=resposta(schema, True), extras=('403',))
    op(f'/{recurso}', 'post', f'Criar em {recurso}', regras, 'administracao', entrada, ok=resposta(schema), codigo='201', extras=('403', '422'))
    op(f'/{recurso}/{{id}}', 'get', f'Consultar em {recurso}', regras, 'administracao', ok=resposta(schema), extras=('403', '404'))
    op(f'/{recurso}/{{id}}', 'patch', f'Alterar em {recurso}', regras, 'administracao', entrada, ok=resposta(schema), extras=('403', '404', '422'))

# --- Opções para formulários
lista_simples = lambda props: {'description': 'Itens ativos', 'content': {'application/json': {'schema': obj({'data': {'type': 'array', 'items': obj(props)}})}}}
op('/opcoes/setores', 'get', 'Setores ativos', 'Qualquer usuário autenticado. Somente id e nome.', 'opcoes', ok=lista_simples({'id': s('integer'), 'nome': s()}))
op('/opcoes/categorias', 'get', 'Categorias ativas', 'Qualquer usuário autenticado. Somente id e nome.', 'opcoes', ok=lista_simples({'id': s('integer'), 'nome': s()}))
op('/opcoes/usuarios', 'get', 'Usuários ativos', 'Qualquer usuário autenticado. Somente id, nome e setor (sem e-mail ou perfil).', 'opcoes',
   params=[q('setor_id', 'integer')], ok=lista_simples({'id': s('integer'), 'name': s(), 'setor_id': s('integer')}))
op('/opcoes/configuracao', 'get', 'Configuração de exibição', 'Fuso de exibição e limites de anexos.', 'opcoes',
   ok={'description': 'Configuração', 'content': {'application/json': {'example': {'data': {'fuso': 'America/Sao_Paulo', 'anexos': {'max_arquivos': 5, 'max_mb': 10, 'extensoes': ['pdf', 'jpg']}}}}}})

op('/dashboard', 'get', 'Indicadores', 'Calculados apenas sobre demandas visíveis ao usuário. Atrasadas: prazo de atendimento vencido em '
   'Aberta, Em andamento ou Aguardando solicitante (o prazo desejado não conta).', 'dashboard', ok=resposta('Dashboard'))

# --- Demandas
filtros = [
    q('contexto', enum=['minhas', 'atendimento', 'fila', 'setor', 'todas'],
      descricao='minhas: solicitante · atendimento: responsável · fila: resumo das demandas sem responsável do próprio setor · '
                'setor: gestor (destino = seu setor) · todas: administrador. Padrão: minhas.'),
    q('busca', descricao='Protocolo ou título'), q('status', enum=STATUS), q('prioridade', enum=PRIORIDADES),
    q('categoria_id', 'integer'), q('setor_destino_id', 'integer'), q('setor_origem_id', 'integer'),
    q('responsavel_id', descricao='ID ou "nenhum"'), q('criado_de', format='date', descricao='AAAA-MM-DD no fuso de exibição'),
    q('criado_ate', format='date'), q('atrasadas', enum=['0', '1']),
    q('ordem', enum=['created_at', 'updated_at', 'prazo_atendimento', 'protocolo', 'prioridade', 'status']),
    q('direcao', enum=['asc', 'desc']), q('page', 'integer'), q('por_pagina', 'integer', minimum=1, maximum=100),
]
op('/demandas', 'get', 'Listar demandas', 'Filtros, ordenação e paginação no servidor; nunca ultrapassa a visibilidade do usuário. '
   'No contexto "fila" cada item segue o schema DemandaFila.', 'demandas', params=filtros, ok=resposta('Demanda', True), extras=('403', '422'))
op('/demandas', 'post', 'Abrir demanda', 'Qualquer usuário ativo, para o próprio setor ou outro. Notifica o responsável ou os gestores do destino.',
   'demandas', 'DemandaInput', multipart=True, ok=resposta('DemandaDetalhe'), codigo='201', extras=('422',))
op('/demandas/{demanda}', 'get', 'Detalhar demanda', 'Solicitante, responsável, gestor do setor de destino ou administrador.', 'demandas',
   ok=resposta('DemandaDetalhe'), extras=('403', '404'))
for acao, entrada, regra, extras in [
    ('atribuicao', 'AtribuicaoInput', 'Gestor do destino/administrador distribuem e reatribuem; colaborador do destino assume (operação atômica: '
     'o segundo recebe 409). Somente em Aberta, Em andamento ou Aguardando solicitante.', ('403', '404', '409', '422')),
    ('destino', 'DestinoInput', 'Somente administrador. Justificativa obrigatória. Volta a demanda para Aberta e limpa o prazo de atendimento.', ('403', '404', '422')),
    ('prioridade', 'PrioridadeInput', 'Gestor do destino ou administrador. Registrada no histórico.', ('403', '404', '422')),
    ('prazo', 'PrazoInput', 'Responsável, gestor do destino ou administrador. null remove o prazo.', ('403', '404', '422')),
    ('status', 'StatusInput', 'Transições: Aberta→Em andamento (exige responsável); Em andamento→Aguardando solicitante (mensagem) ou Resolvida (solução); '
     'Aguardando→Em andamento; Resolvida→Concluída ou Em andamento (rejeição com justificativa), somente pelo solicitante; '
     'Aberta/Em andamento/Aguardando→Cancelada (justificativa; solicitante, gestor do destino ou administrador). Encerradas são imutáveis.', ('403', '404', '422')),
]:
    op(f'/demandas/{{demanda}}/{acao}', 'patch', f'Alterar {acao}', regra, 'demandas', entrada, ok=resposta('DemandaDetalhe'), extras=extras)

op('/demandas/{demanda}/comentarios', 'get', 'Listar comentários', 'Exige acesso completo à demanda. 50 por página.', 'interacoes',
   params=[q('page', 'integer')], ok=resposta('Comentario', True), extras=('403', '404'))
op('/demandas/{demanda}/comentarios', 'post', 'Comentar', 'Participantes da demanda; não altera o status. Bloqueado em demandas concluídas/canceladas.',
   'interacoes', 'ComentarioInput', multipart=True, ok=resposta('Comentario'), codigo='201', extras=('403', '404', '422'))
op('/demandas/{demanda}/historico', 'get', 'Histórico', 'Exige acesso completo. Somente leitura, mais recentes primeiro.', 'interacoes',
   params=[q('page', 'integer')], ok=resposta('Historico', True), extras=('403', '404'))
op('/demandas/{demanda}/anexos/{anexo}/download', 'get', 'Baixar anexo', 'Permissão revalidada a cada download; não há URL pública.', 'interacoes',
   ok={'description': 'Arquivo (Content-Disposition: attachment)', 'content': {'application/octet-stream': {'schema': s(format='binary')}}}, extras=('403', '404'))

# --- Notificações
op('/notificacoes', 'get', 'Listar notificações', 'Somente do próprio usuário, mais recentes primeiro. A prévia não contém conteúdo da demanda.',
   'notificacoes', params=[q('nao_lidas', enum=['0', '1']), q('page', 'integer'), q('por_pagina', 'integer')], ok=resposta('Notificacao', True))
op('/notificacoes/contador', 'get', 'Contar não lidas', 'Somente do próprio usuário.', 'notificacoes',
   ok={'description': 'Contador', 'content': {'application/json': {'example': {'data': {'nao_lidas': 3}}}}})
op('/notificacoes/ler-todas', 'patch', 'Marcar todas como lidas', 'Somente do próprio usuário.', 'notificacoes',
   ok={'description': 'Quantidade marcada', 'content': {'application/json': {'example': {'data': {'marcadas': 3}}}}})
op('/notificacoes/{id}/ler', 'patch', 'Marcar uma como lida', 'Somente do próprio usuário (404 para notificações de terceiros).', 'notificacoes',
   ok={'description': 'Marcada', 'content': {'application/json': {'example': {'data': {'id': '9f0c...', 'lida': True}}}}}, extras=('404',))

Path(__file__).with_name('openapi.json').write_text(json.dumps(doc, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(f"openapi.json gerado com {len(doc['paths'])} caminhos.")
