import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Bell, CheckCheck } from 'lucide-react';
import { api, mensagemErro } from '../api/client';
import { useApi } from '../hooks/useApi';
import { Alerta, Botao, CabecalhoPagina, Carregando, ErroCarregamento, Paginacao, Vazio } from '../components/ui';
import { dataHora } from '../utils/formato';

export default function Notificacoes() {
  const navegar = useNavigate();
  const [parametros, setParametros] = useSearchParams();
  const somenteNaoLidas = parametros.get('nao_lidas') === '1';
  const pagina = parametros.get('page') || 1;
  const { dados, erro, carregando, recarregar } = useApi('/notificacoes', { nao_lidas: somenteNaoLidas ? 1 : 0, page: pagina });
  const [aviso, setAviso] = useState(null);
  const [falha, setFalha] = useState(null);
  const [marcando, setMarcando] = useState(false);

  const avisarContador = () => window.dispatchEvent(new Event('notificacoes:atualizadas'));

  const abrir = async (n) => {
    setAviso(null);
    if (!n.lida) {
      try {
        await api.patch(`/notificacoes/${n.id}/ler`);
        avisarContador();
      } catch {
        /* segue para a demanda mesmo assim */
      }
    }
    if (n.demanda_disponivel && n.demanda_id) {
      navegar(`/demandas/${n.demanda_id}`, { state: { de: '/notificacoes' } });
    } else {
      setAviso(`A demanda ${n.protocolo || ''} não está mais disponível para você.`);
      recarregar();
    }
  };

  const lerTodas = async () => {
    setMarcando(true);
    setFalha(null);
    try {
      await api.patch('/notificacoes/ler-todas');
      avisarContador();
      recarregar();
    } catch (e) {
      setFalha(mensagemErro(e));
    } finally {
      setMarcando(false);
    }
  };

  const filtrar = (naoLidas) => setParametros(naoLidas ? { nao_lidas: '1' } : {});

  return (
    <>
      <CabecalhoPagina titulo="Notificações" descricao="Atualizações das demandas em que você participa.">
        <Botao variante="secundario" carregando={marcando} onClick={lerTodas}>
          <CheckCheck size={16} aria-hidden="true" /> Marcar todas como lidas
        </Botao>
      </CabecalhoPagina>

      <div className="abas" role="tablist" aria-label="Filtro de notificações">
        <button type="button" role="tab" aria-selected={!somenteNaoLidas} className={`aba ${!somenteNaoLidas ? 'ativa' : ''}`} onClick={() => filtrar(false)}>Todas</button>
        <button type="button" role="tab" aria-selected={somenteNaoLidas} className={`aba ${somenteNaoLidas ? 'ativa' : ''}`} onClick={() => filtrar(true)}>Não lidas</button>
      </div>

      <Alerta tipo="aviso" erro={aviso} aoFechar={() => setAviso(null)} />
      <Alerta erro={falha} />

      {carregando && !dados ? (
        <Carregando />
      ) : erro ? (
        <ErroCarregamento erro={erro} aoTentarNovamente={recarregar} />
      ) : !dados?.data?.length ? (
        <Vazio titulo={somenteNaoLidas ? 'Nenhuma notificação não lida' : 'Nenhuma notificação'} texto="Você será avisado aqui sobre atribuições, comentários e mudanças nas suas demandas." />
      ) : (
        <ul className="notificacoes">
          {dados.data.map((n) => (
            <li key={n.id}>
              <button type="button" className={`notificacao ${n.lida ? 'lida' : 'nao-lida'}`} onClick={() => abrir(n)}>
                <Bell size={18} aria-hidden="true" />
                <span className="notificacao-texto">
                  <span>{n.mensagem}</span>
                  <small>
                    {dataHora(n.created_at)}
                    {!n.demanda_disponivel && ' · demanda não disponível para você'}
                  </small>
                </span>
                {!n.lida && <span className="visualmente-oculto">(não lida)</span>}
                {!n.lida && <span className="ponto-nao-lida" aria-hidden="true" />}
              </button>
            </li>
          ))}
        </ul>
      )}

      {dados?.meta && (
        <Paginacao meta={dados.meta} aoMudar={(page) => setParametros({ ...(somenteNaoLidas ? { nao_lidas: '1' } : {}), page: String(page) })} />
      )}
    </>
  );
}
