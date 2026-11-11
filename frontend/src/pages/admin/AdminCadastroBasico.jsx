import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Pencil, Plus, Search } from 'lucide-react';
import { api, errosDeCampo, mensagemErro } from '../../api/client';
import { limparCacheOpcoes, useApi } from '../../hooks/useApi';
import { Alerta, Botao, CabecalhoPagina, Campo, Carregando, ErroCarregamento, Modal, Paginacao, Vazio } from '../../components/ui';
import { dataHora } from '../../utils/formato';

/** Administração de setores ou categorias (mesma estrutura: nome, descrição, situação). */
export default function AdminCadastroBasico({ recurso, titulo, singular, descricao }) {
  const [parametros, setParametros] = useSearchParams();
  const [busca, setBusca] = useState(parametros.get('busca') || '');
  const consulta = Object.fromEntries(['busca', 'ativo', 'page'].filter((k) => parametros.get(k)).map((k) => [k, parametros.get(k)]));
  const { dados, erro, carregando, recarregar } = useApi(`/${recurso}`, consulta);
  const [editando, setEditando] = useState(null);
  const [aviso, setAviso] = useState(null);

  const aplicar = (mudancas) => {
    const novo = new URLSearchParams(parametros);
    Object.entries(mudancas).forEach(([k, v]) => (v ? novo.set(k, v) : novo.delete(k)));
    if (!('page' in mudancas)) novo.delete('page');
    setParametros(novo);
  };

  return (
    <>
      <CabecalhoPagina titulo={titulo} descricao={descricao}>
        <button type="button" className="botao primario" onClick={() => setEditando({})}><Plus size={16} aria-hidden="true" /> Novo {singular}</button>
      </CabecalhoPagina>

      <Alerta tipo="sucesso" erro={aviso} aoFechar={() => setAviso(null)} />

      <section className="filtros">
        <form className="filtros-linha" role="search" onSubmit={(e) => { e.preventDefault(); aplicar({ busca: busca.trim() }); }}>
          <div className="busca">
            <Search size={16} aria-hidden="true" />
            <label htmlFor="busca-cadastro" className="visualmente-oculto">Buscar por nome</label>
            <input id="busca-cadastro" type="search" placeholder="Nome" value={busca} onChange={(e) => setBusca(e.target.value)} />
          </div>
          <button type="submit" className="botao secundario">Buscar</button>
          <label className="visualmente-oculto" htmlFor="f-situacao">Situação</label>
          <select id="f-situacao" value={parametros.get('ativo') || ''} onChange={(e) => aplicar({ ativo: e.target.value })}>
            <option value="">Todas as situações</option>
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
          </select>
        </form>
      </section>

      {carregando && !dados ? <Carregando /> : erro ? <ErroCarregamento erro={erro} aoTentarNovamente={recarregar} /> : !dados?.data?.length ? (
        <Vazio titulo={`Nenhum ${singular} encontrado`} />
      ) : (
        <div className="tabela-container">
          <table className="tabela tabela-responsiva">
            <thead>
              <tr>
                <th scope="col">Nome</th><th scope="col">Descrição</th><th scope="col">Situação</th><th scope="col">Atualizado em</th>
                <th scope="col"><span className="visualmente-oculto">Ações</span></th>
              </tr>
            </thead>
            <tbody>
              {dados.data.map((item) => (
                <tr key={item.id}>
                  <td data-rotulo="Nome">{item.nome}</td>
                  <td data-rotulo="Descrição">{item.descricao || <span className="suave">—</span>}</td>
                  <td data-rotulo="Situação"><span className={`badge ${item.ativo ? 'st-concluida' : 'st-cancelada'}`}>{item.ativo ? 'Ativo' : 'Inativo'}</span></td>
                  <td data-rotulo="Atualizado em">{dataHora(item.updated_at)}</td>
                  <td className="celula-acoes">
                    <button type="button" className="botao secundario pequeno" onClick={() => setEditando(item)} aria-label={`Editar ${item.nome}`}>
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
        <FormCadastro
          recurso={recurso}
          singular={singular}
          item={editando}
          aoFechar={() => setEditando(null)}
          aoSalvar={(salvo, novo) => {
            setEditando(null);
            setAviso(`${salvo.nome} ${novo ? 'criado' : 'atualizado'}.`);
            limparCacheOpcoes();
            recarregar();
          }}
        />
      )}
    </>
  );
}

function FormCadastro({ recurso, singular, item, aoFechar, aoSalvar }) {
  const novo = !item.id;
  const [form, setForm] = useState({ nome: item.nome || '', descricao: item.descricao || '', ativo: item.ativo ?? true });
  const [erro, setErro] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const erros = errosDeCampo(erro);

  const enviar = async (e) => {
    e.preventDefault();
    if (enviando) return;
    setEnviando(true);
    setErro(null);
    try {
      const corpo = { ...form, descricao: form.descricao.trim() || null };
      const { data } = novo ? await api.post(`/${recurso}`, corpo) : await api.patch(`/${recurso}/${item.id}`, corpo);
      aoSalvar(data.data, novo);
    } catch (falha) {
      setErro(falha);
      setEnviando(false);
    }
  };

  return (
    <Modal titulo={novo ? `Novo ${singular}` : `Editar ${item.nome}`} aberto aoFechar={() => !enviando && aoFechar()}
      rodape={<><button type="button" className="botao secundario" onClick={aoFechar} disabled={enviando}>Cancelar</button><Botao type="submit" form="form-cadastro" carregando={enviando}>Salvar</Botao></>}>
      <form id="form-cadastro" onSubmit={enviar} noValidate>
        {erro && <Alerta erro={mensagemErro(erro)} />}
        <Campo rotulo="Nome" obrigatorio erro={erros.nome}>{(p) => <input {...p} value={form.nome} onChange={(e) => setForm((f) => ({ ...f, nome: e.target.value }))} maxLength={120} data-autofocus />}</Campo>
        <Campo rotulo="Descrição" erro={erros.descricao}>{(p) => <textarea {...p} rows={3} value={form.descricao} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} maxLength={2000} />}</Campo>
        <label className="checkbox">
          <input type="checkbox" checked={form.ativo} onChange={(e) => setForm((f) => ({ ...f, ativo: e.target.checked }))} />
          Ativo
        </label>
        <p className="aviso-suave">Itens inativos permanecem no histórico, mas não podem ser selecionados em novas demandas.</p>
      </form>
    </Modal>
  );
}
