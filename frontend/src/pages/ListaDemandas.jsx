import { useCallback, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { api, mensagemErro } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { useApi } from '../hooks/useApi';
import FiltrosDemandas, { FILTROS } from '../components/FiltrosDemandas';
import TabelaDemandas from '../components/TabelaDemandas';
import { Alerta, Botao, CabecalhoPagina, Carregando, ErroCarregamento, Paginacao, PrioridadeBadge, Vazio } from '../components/ui';
import { dataHora } from '../utils/formato';

const CONFIG = {
  minhas: {
    titulo: 'Minhas solicitações',
    descricao: 'Demandas que você abriu.',
    vazio: 'Você ainda não abriu demandas com estes filtros.',
    mostrarSolicitante: false,
    mostrarSetor: true,
  },
  atendimento: {
    titulo: 'Meu atendimento',
    descricao: 'Demandas atribuídas a você e a fila do seu setor.',
    vazio: 'Nenhuma demanda atribuída a você com estes filtros.',
    mostrarSetor: true,
    mostrarResponsavel: false,
  },
  setor: {
    titulo: 'Demandas do setor',
    descricao: 'Demandas destinadas ao seu setor: distribua, acompanhe e reatribua.',
    vazio: 'Nenhuma demanda destinada ao setor com estes filtros.',
    mostrarResponsavelFiltro: true,
  },
  todas: {
    titulo: 'Todas as demandas',
    descricao: 'Visão administrativa de todas as demandas.',
    vazio: 'Nenhuma demanda encontrada com estes filtros.',
    mostrarSetor: true,
    mostrarResponsavelFiltro: true,
  },
};

export default function ListaDemandas({ contexto }) {
  const { usuario } = useAuth();
  const [parametros, setParametros] = useSearchParams();
  const config = CONFIG[contexto];
  const aba = contexto === 'atendimento' && parametros.get('aba') === 'fila' ? 'fila' : 'lista';
  const contextoApi = aba === 'fila' ? 'fila' : contexto;

  const consulta = useMemo(() => {
    const p = { contexto: contextoApi, page: parametros.get('page') || 1 };
    FILTROS.forEach((f) => {
      const v = parametros.get(f);
      if (v) p[f] = v;
    });
    return p;
  }, [parametros, contextoApi]);

  const { dados, erro, carregando, recarregar } = useApi('/demandas', consulta);

  const aplicar = useCallback(
    (mudancas) => {
      const novo = new URLSearchParams(parametros);
      Object.entries(mudancas).forEach(([k, v]) => (v ? novo.set(k, v) : novo.delete(k)));
      if (!('page' in mudancas)) novo.delete('page');
      setParametros(novo);
    },
    [parametros, setParametros],
  );

  const trocarAba = (nova) => {
    const novo = new URLSearchParams();
    if (nova === 'fila') novo.set('aba', 'fila');
    setParametros(novo);
  };

  return (
    <>
      <CabecalhoPagina titulo={config.titulo} descricao={config.descricao}>
        <Link to="/demandas/nova" className="botao primario"><Plus size={16} aria-hidden="true" /> Nova demanda</Link>
      </CabecalhoPagina>

      {contexto === 'atendimento' && (
        <div className="abas" role="tablist" aria-label="Visões do atendimento">
          <button type="button" role="tab" aria-selected={aba === 'lista'} className={`aba ${aba === 'lista' ? 'ativa' : ''}`} onClick={() => trocarAba('lista')}>
            Atribuídas a mim
          </button>
          <button type="button" role="tab" aria-selected={aba === 'fila'} className={`aba ${aba === 'fila' ? 'ativa' : ''}`} onClick={() => trocarAba('fila')}>
            Fila do setor {usuario?.setor?.nome ? `(${usuario.setor.nome})` : ''}
          </button>
        </div>
      )}

      <FiltrosDemandas
        key={contextoApi}
        parametros={parametros}
        aoAplicar={aplicar}
        completo={aba !== 'fila'}
        mostrarSetor={config.mostrarSetor}
        mostrarResponsavel={config.mostrarResponsavelFiltro}
        setorResponsaveis={contexto === 'setor' ? usuario?.setor_id : parametros.get('setor_destino_id') || undefined}
      />

      {carregando && !dados ? (
        <Carregando />
      ) : erro ? (
        <ErroCarregamento erro={erro} aoTentarNovamente={recarregar} />
      ) : !dados?.data?.length ? (
        <Vazio
          titulo={aba === 'fila' ? 'Fila vazia' : 'Nenhuma demanda'}
          texto={aba === 'fila' ? 'Não há demandas sem responsável no seu setor com estes filtros.' : config.vazio}
        />
      ) : aba === 'fila' ? (
        <FilaSetor demandas={dados.data} aoAtualizar={recarregar} />
      ) : (
        <div className={carregando ? 'atualizando' : ''}>
          <TabelaDemandas demandas={dados.data} mostrarSolicitante={config.mostrarSolicitante !== false} mostrarResponsavel={config.mostrarResponsavel !== false} />
        </div>
      )}

      {dados?.meta && <Paginacao meta={dados.meta} aoMudar={(page) => aplicar({ page: String(page) })} />}
    </>
  );
}

/** Fila resumida: somente protocolo, título, categoria, prioridade e abertura. */
function FilaSetor({ demandas, aoAtualizar }) {
  const navegar = useNavigate();
  const [enviando, setEnviando] = useState(null);
  const [erro, setErro] = useState(null);

  const assumir = async (demanda) => {
    setEnviando(demanda.id);
    setErro(null);
    try {
      await api.patch(`/demandas/${demanda.id}/atribuicao`, { assumir: true });
      navegar(`/demandas/${demanda.id}`, { state: { mensagem: `Você assumiu a demanda ${demanda.protocolo}.` } });
    } catch (e) {
      setErro(mensagemErro(e));
      aoAtualizar();
    } finally {
      setEnviando(null);
    }
  };

  return (
    <>
      <Alerta erro={erro} aoFechar={() => setErro(null)} />
      <p className="aviso-suave">O conteúdo completo fica disponível depois que você assume a demanda.</p>
      <div className="tabela-container">
        <table className="tabela tabela-responsiva">
          <thead>
            <tr>
              <th scope="col">Protocolo</th>
              <th scope="col">Título</th>
              <th scope="col">Categoria</th>
              <th scope="col">Prioridade</th>
              <th scope="col">Abertura</th>
              <th scope="col"><span className="visualmente-oculto">Ações</span></th>
            </tr>
          </thead>
          <tbody>
            {demandas.map((d) => (
              <tr key={d.id}>
                <td data-rotulo="Protocolo"><span className="protocolo">{d.protocolo}</span></td>
                <td data-rotulo="Título">{d.titulo}</td>
                <td data-rotulo="Categoria">{d.categoria?.nome}</td>
                <td data-rotulo="Prioridade"><PrioridadeBadge prioridade={d.prioridade} /></td>
                <td data-rotulo="Abertura">{dataHora(d.created_at)}</td>
                <td className="celula-acoes">
                  {d.pode_assumir ? (
                    <Botao variante="primario pequeno" carregando={enviando === d.id} disabled={enviando !== null} onClick={() => assumir(d)}>
                      Assumir
                    </Botao>
                  ) : (
                    <span className="suave">Você é o solicitante</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  );
}
