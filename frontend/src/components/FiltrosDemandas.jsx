import { useEffect, useState } from 'react';
import { Filter, RotateCcw, Search } from 'lucide-react';
import { useOpcoes } from '../hooks/useApi';
import { PRIORIDADES, STATUS } from '../utils/formato';

export const FILTROS = ['busca', 'status', 'prioridade', 'categoria_id', 'setor_destino_id', 'responsavel_id', 'criado_de', 'criado_ate', 'atrasadas', 'ordem', 'direcao'];

/**
 * Filtros guardados na URL (preservados ao abrir uma demanda e voltar).
 * A filtragem, a paginação e a ordenação são feitas no backend.
 */
export default function FiltrosDemandas({ parametros, aoAplicar, completo = true, mostrarSetor = false, mostrarResponsavel = false, setorResponsaveis }) {
  const { itens: categorias } = useOpcoes('categorias');
  const { itens: setores } = useOpcoes('setores');
  const { itens: usuarios } = useOpcoes(mostrarResponsavel ? 'usuarios' : null, setorResponsaveis ? { setor_id: setorResponsaveis } : null);
  const [busca, setBusca] = useState(parametros.get('busca') || '');
  const [expandido, setExpandido] = useState(() => FILTROS.some((f) => !['busca', 'ordem', 'direcao'].includes(f) && parametros.get(f)));

  useEffect(() => setBusca(parametros.get('busca') || ''), [parametros]);

  const alterar = (campo, valor) => aoAplicar({ [campo]: valor });
  const valor = (campo) => parametros.get(campo) || '';
  const ativos = FILTROS.filter((f) => !['ordem', 'direcao'].includes(f) && parametros.get(f)).length;

  return (
    <section className="filtros" aria-label="Filtros">
      <form
        className="filtros-linha"
        role="search"
        onSubmit={(e) => {
          e.preventDefault();
          alterar('busca', busca.trim());
        }}
      >
        <div className="busca">
          <Search size={16} aria-hidden="true" />
          <label htmlFor="filtro-busca" className="visualmente-oculto">Buscar por protocolo ou título</label>
          <input id="filtro-busca" type="search" placeholder="Protocolo ou título" value={busca} onChange={(e) => setBusca(e.target.value)} maxLength={200} />
        </div>
        <button type="submit" className="botao secundario">Buscar</button>
        <button type="button" className="botao secundario" onClick={() => setExpandido((v) => !v)} aria-expanded={expandido} aria-controls="filtros-avancados">
          <Filter size={16} aria-hidden="true" /> Filtros{ativos > 0 ? ` (${ativos})` : ''}
        </button>
        <label className="visualmente-oculto" htmlFor="filtro-ordem">Ordenação</label>
        <select
          id="filtro-ordem"
          value={`${valor('ordem') || 'created_at'}:${valor('direcao') || 'desc'}`}
          onChange={(e) => {
            const [ordem, direcao] = e.target.value.split(':');
            aoAplicar({ ordem, direcao });
          }}
        >
          <option value="created_at:desc">Mais recentes</option>
          <option value="created_at:asc">Mais antigas</option>
          {completo && <option value="updated_at:desc">Atualizadas recentemente</option>}
          <option value="prioridade:desc">Maior prioridade</option>
          {completo && <option value="prazo_atendimento:asc">Prazo mais próximo</option>}
          <option value="protocolo:asc">Protocolo</option>
        </select>
      </form>

      {expandido && (
        <div id="filtros-avancados" className="filtros-grade">
          {completo && (
            <div className="campo">
              <label htmlFor="filtro-status">Status</label>
              <select id="filtro-status" value={valor('status')} onChange={(e) => alterar('status', e.target.value)}>
                <option value="">Todos</option>
                {Object.entries(STATUS).map(([chave, { rotulo }]) => <option key={chave} value={chave}>{rotulo}</option>)}
              </select>
            </div>
          )}
          <div className="campo">
            <label htmlFor="filtro-prioridade">Prioridade</label>
            <select id="filtro-prioridade" value={valor('prioridade')} onChange={(e) => alterar('prioridade', e.target.value)}>
              <option value="">Todas</option>
              {Object.entries(PRIORIDADES).map(([chave, { rotulo }]) => <option key={chave} value={chave}>{rotulo}</option>)}
            </select>
          </div>
          <div className="campo">
            <label htmlFor="filtro-categoria">Categoria</label>
            <select id="filtro-categoria" value={valor('categoria_id')} onChange={(e) => alterar('categoria_id', e.target.value)}>
              <option value="">Todas</option>
              {categorias.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
            </select>
          </div>
          {completo && mostrarSetor && (
            <div className="campo">
              <label htmlFor="filtro-setor">Setor de destino</label>
              <select id="filtro-setor" value={valor('setor_destino_id')} onChange={(e) => alterar('setor_destino_id', e.target.value)}>
                <option value="">Todos</option>
                {setores.map((s) => <option key={s.id} value={s.id}>{s.nome}</option>)}
              </select>
            </div>
          )}
          {completo && mostrarResponsavel && (
            <div className="campo">
              <label htmlFor="filtro-responsavel">Responsável</label>
              <select id="filtro-responsavel" value={valor('responsavel_id')} onChange={(e) => alterar('responsavel_id', e.target.value)}>
                <option value="">Todos</option>
                <option value="nenhum">Sem responsável</option>
                {usuarios.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
              </select>
            </div>
          )}
          {completo && (
            <>
              <div className="campo">
                <label htmlFor="filtro-de">Criada a partir de</label>
                <input id="filtro-de" type="date" value={valor('criado_de')} onChange={(e) => alterar('criado_de', e.target.value)} />
              </div>
              <div className="campo">
                <label htmlFor="filtro-ate">Criada até</label>
                <input id="filtro-ate" type="date" value={valor('criado_ate')} min={valor('criado_de') || undefined} onChange={(e) => alterar('criado_ate', e.target.value)} />
              </div>
              <label className="checkbox">
                <input type="checkbox" checked={valor('atrasadas') === '1'} onChange={(e) => alterar('atrasadas', e.target.checked ? '1' : '')} />
                Somente atrasadas
              </label>
            </>
          )}
          <button type="button" className="botao texto" onClick={() => aoAplicar(Object.fromEntries(FILTROS.map((f) => [f, ''])))}>
            <RotateCcw size={14} aria-hidden="true" /> Limpar filtros
          </button>
        </div>
      )}
    </section>
  );
}
