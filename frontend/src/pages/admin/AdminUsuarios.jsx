import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Pencil, Plus, Search } from 'lucide-react';
import { api, errosDeCampo, mensagemErro } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { limparCacheOpcoes, useApi } from '../../hooks/useApi';
import { Alerta, Botao, CabecalhoPagina, Campo, Carregando, ErroCarregamento, Modal, Paginacao, Vazio } from '../../components/ui';
import { PERFIS, dataHora } from '../../utils/formato';

export default function AdminUsuarios() {
  const { usuario: atual, atualizarUsuario } = useAuth();
  const [parametros, setParametros] = useSearchParams();
  const [busca, setBusca] = useState(parametros.get('busca') || '');
  const consulta = Object.fromEntries(['busca', 'setor_id', 'perfil', 'ativo', 'page'].filter((k) => parametros.get(k)).map((k) => [k, parametros.get(k)]));
  const { dados, erro, carregando, recarregar } = useApi('/usuarios', consulta);
  const setores = useApi('/setores', { por_pagina: 100 });
  const [editando, setEditando] = useState(null);
  const [aviso, setAviso] = useState(null);

  const aplicar = (mudancas) => {
    const novo = new URLSearchParams(parametros);
    Object.entries(mudancas).forEach(([k, v]) => (v ? novo.set(k, v) : novo.delete(k)));
    if (!('page' in mudancas)) novo.delete('page');
    setParametros(novo);
  };

  const listaSetores = setores.dados?.data || [];

  return (
    <>
      <CabecalhoPagina titulo="Usuários" descricao="Não há cadastro público: os acessos são criados aqui. Usuários não são excluídos, apenas inativados.">
        <button type="button" className="botao primario" onClick={() => setEditando({})}><Plus size={16} aria-hidden="true" /> Novo usuário</button>
      </CabecalhoPagina>

      <Alerta tipo="sucesso" erro={aviso} aoFechar={() => setAviso(null)} />

      <section className="filtros">
        <form className="filtros-linha" role="search" onSubmit={(e) => { e.preventDefault(); aplicar({ busca: busca.trim() }); }}>
          <div className="busca">
            <Search size={16} aria-hidden="true" />
            <label htmlFor="busca-usuario" className="visualmente-oculto">Buscar por nome ou e-mail</label>
            <input id="busca-usuario" type="search" placeholder="Nome ou e-mail" value={busca} onChange={(e) => setBusca(e.target.value)} />
          </div>
          <button type="submit" className="botao secundario">Buscar</button>
          <label className="visualmente-oculto" htmlFor="f-setor">Setor</label>
          <select id="f-setor" value={parametros.get('setor_id') || ''} onChange={(e) => aplicar({ setor_id: e.target.value })}>
            <option value="">Todos os setores</option>
            {listaSetores.map((s) => <option key={s.id} value={s.id}>{s.nome}</option>)}
          </select>
          <label className="visualmente-oculto" htmlFor="f-perfil">Perfil</label>
          <select id="f-perfil" value={parametros.get('perfil') || ''} onChange={(e) => aplicar({ perfil: e.target.value })}>
            <option value="">Todos os perfis</option>
            {Object.entries(PERFIS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
          <label className="visualmente-oculto" htmlFor="f-ativo">Situação</label>
          <select id="f-ativo" value={parametros.get('ativo') || ''} onChange={(e) => aplicar({ ativo: e.target.value })}>
            <option value="">Todas as situações</option>
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
          </select>
        </form>
      </section>

      {carregando && !dados ? <Carregando /> : erro ? <ErroCarregamento erro={erro} aoTentarNovamente={recarregar} /> : !dados?.data?.length ? (
        <Vazio titulo="Nenhum usuário encontrado" />
      ) : (
        <div className="tabela-container">
          <table className="tabela tabela-responsiva">
            <thead>
              <tr>
                <th scope="col">Nome</th><th scope="col">E-mail</th><th scope="col">Setor</th><th scope="col">Perfil</th><th scope="col">Situação</th><th scope="col">Criado em</th>
                <th scope="col"><span className="visualmente-oculto">Ações</span></th>
              </tr>
            </thead>
            <tbody>
              {dados.data.map((u) => (
                <tr key={u.id}>
                  <td data-rotulo="Nome">{u.name}</td>
                  <td data-rotulo="E-mail">{u.email}</td>
                  <td data-rotulo="Setor">{u.setor?.nome}</td>
                  <td data-rotulo="Perfil">{PERFIS[u.perfil]}</td>
                  <td data-rotulo="Situação"><span className={`badge ${u.ativo ? 'st-concluida' : 'st-cancelada'}`}>{u.ativo ? 'Ativo' : 'Inativo'}</span></td>
                  <td data-rotulo="Criado em">{dataHora(u.created_at)}</td>
                  <td className="celula-acoes">
                    <button type="button" className="botao secundario pequeno" onClick={() => setEditando(u)} aria-label={`Editar ${u.name}`}>
                      <Pencil size={14} aria-hidden="true" /> Editar
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {dados?.meta && <Paginacao meta={dados.meta} aoMudar={(p) => aplicar({ page: String(p) })} />}

      {editando && (
        <FormUsuario
          usuario={editando}
          setores={listaSetores}
          aoFechar={() => setEditando(null)}
          aoSalvar={(salvo, novo) => {
            setEditando(null);
            setAviso(novo ? `Usuário ${salvo.name} criado.` : `Usuário ${salvo.name} atualizado.`);
            limparCacheOpcoes();
            if (salvo.id === atual?.id) atualizarUsuario(salvo);
            recarregar();
          }}
        />
      )}
    </>
  );
}

function FormUsuario({ usuario, setores, aoFechar, aoSalvar }) {
  const novo = !usuario.id;
  const [form, setForm] = useState({
    name: usuario.name || '',
    email: usuario.email || '',
    password: '',
    setor_id: usuario.setor_id ? String(usuario.setor_id) : '',
    perfil: usuario.perfil || 'colaborador',
    ativo: usuario.ativo ?? true,
  });
  const [erro, setErro] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const erros = errosDeCampo(erro);
  const alterar = (campo) => (e) => setForm((f) => ({ ...f, [campo]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }));

  const enviar = async (e) => {
    e.preventDefault();
    if (enviando) return;
    setEnviando(true);
    setErro(null);
    const corpo = { ...form, setor_id: Number(form.setor_id) };
    if (!corpo.password) delete corpo.password;
    try {
      const { data } = novo ? await api.post('/usuarios', corpo) : await api.patch(`/usuarios/${usuario.id}`, corpo);
      aoSalvar(data.data, novo);
    } catch (falha) {
      setErro(falha);
      setEnviando(false);
    }
  };

  // Setores inativos não podem receber usuários novos, mas aparecem para quem já está neles.
  const opcoesSetor = setores.filter((s) => s.ativo || s.id === usuario.setor_id);

  return (
    <Modal titulo={novo ? 'Novo usuário' : `Editar ${usuario.name}`} aberto aoFechar={() => !enviando && aoFechar()}
      rodape={<><button type="button" className="botao secundario" onClick={aoFechar} disabled={enviando}>Cancelar</button><Botao type="submit" form="form-usuario" carregando={enviando}>Salvar</Botao></>}>
      <form id="form-usuario" onSubmit={enviar} noValidate>
        {erro && <Alerta erro={mensagemErro(erro)} />}
        <Campo rotulo="Nome" obrigatorio erro={erros.name}>{(p) => <input {...p} value={form.name} onChange={alterar('name')} data-autofocus />}</Campo>
        <Campo rotulo="E-mail" obrigatorio erro={erros.email}>{(p) => <input {...p} type="email" value={form.email} onChange={alterar('email')} autoComplete="off" />}</Campo>
        <Campo rotulo={novo ? 'Senha inicial' : 'Nova senha (opcional)'} obrigatorio={novo} erro={erros.password} ajuda={novo ? 'Mínimo de 10 caracteres, com letras e números.' : 'Preencha para redefinir. As sessões do usuário serão encerradas.'}>
          {(p) => <input {...p} type="password" value={form.password} onChange={alterar('password')} autoComplete="new-password" />}
        </Campo>
        <div className="grade-2">
          <Campo rotulo="Setor" obrigatorio erro={erros.setor_id}>
            {(p) => (
              <select {...p} value={form.setor_id} onChange={alterar('setor_id')}>
                <option value="">Selecione...</option>
                {opcoesSetor.map((s) => <option key={s.id} value={s.id}>{s.nome}{s.ativo ? '' : ' (inativo)'}</option>)}
              </select>
            )}
          </Campo>
          <Campo rotulo="Perfil" obrigatorio erro={erros.perfil}>
            {(p) => (
              <select {...p} value={form.perfil} onChange={alterar('perfil')}>
                {Object.entries(PERFIS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
            )}
          </Campo>
        </div>
        <label className="checkbox">
          <input type="checkbox" checked={form.ativo} onChange={alterar('ativo')} />
          Usuário ativo
        </label>
        {erros.ativo && <small className="erro-campo">{erros.ativo}</small>}
        {!form.ativo && usuario.ativo !== false && <p className="aviso-suave">A inativação encerra imediatamente os acessos do usuário.</p>}
      </form>
    </Modal>
  );
}
