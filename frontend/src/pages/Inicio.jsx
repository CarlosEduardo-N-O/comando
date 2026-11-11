import { Link } from 'react-router-dom';
import { AlarmClock, Briefcase, CheckCheck, ClipboardList, Inbox, MessageCircleQuestion, Plus } from 'lucide-react';
import { useAuth } from '../auth/AuthContext';
import { useApi } from '../hooks/useApi';
import TabelaDemandas from '../components/TabelaDemandas';
import { CabecalhoPagina, Carregando, ErroCarregamento, Vazio } from '../components/ui';

export default function Inicio() {
  const { usuario, isAdmin, isGestor } = useAuth();
  const indicadores = useApi('/dashboard');
  const recentes = useApi('/demandas', { contexto: 'minhas', por_pagina: 5 });

  // Atrasadas: abre a visão mais ampla que o perfil permite.
  const atrasadasUrl = isAdmin ? '/demandas/todas?atrasadas=1' : isGestor ? '/demandas/setor?atrasadas=1' : '/demandas/atendimento?atrasadas=1';
  const d = indicadores.dados?.data;

  const cartoes = d
    ? [
        { titulo: 'Minhas solicitações em aberto', valor: d.minhas_abertas, icone: ClipboardList, para: '/demandas/minhas' },
        { titulo: 'Atribuídas a mim', valor: d.atribuidas_a_mim, icone: Briefcase, para: '/demandas/atendimento' },
        { titulo: 'Aguardando minha resposta', valor: d.aguardando_minha_resposta, icone: MessageCircleQuestion, para: '/demandas/minhas?status=aguardando_solicitante', destaque: d.aguardando_minha_resposta > 0 },
        { titulo: 'Resolvidas aguardando minha confirmação', valor: d.aguardando_minha_confirmacao, icone: CheckCheck, para: '/demandas/minhas?status=resolvida', destaque: d.aguardando_minha_confirmacao > 0 },
        { titulo: 'Demandas atrasadas', valor: d.atrasadas, icone: AlarmClock, para: atrasadasUrl, alerta: d.atrasadas > 0 },
        { titulo: 'Fila do meu setor', valor: d.fila_do_setor, icone: Inbox, para: '/demandas/atendimento?aba=fila' },
      ]
    : [];

  return (
    <>
      <CabecalhoPagina titulo={`Olá, ${usuario?.name?.split(' ')[0] || ''}`} descricao="Acompanhe suas demandas e o que precisa da sua atenção.">
        <Link to="/demandas/nova" className="botao primario"><Plus size={16} aria-hidden="true" /> Nova demanda</Link>
      </CabecalhoPagina>

      {indicadores.carregando && !d ? (
        <Carregando texto="Carregando indicadores..." />
      ) : indicadores.erro ? (
        <ErroCarregamento erro={indicadores.erro} aoTentarNovamente={indicadores.recarregar} />
      ) : (
        <ul className="indicadores">
          {cartoes.map(({ titulo, valor, icone: Icone, para, destaque, alerta }) => (
            <li key={titulo}>
              <Link to={para} className={`indicador ${destaque ? 'destaque' : ''} ${alerta ? 'alerta' : ''}`}>
                <Icone size={22} aria-hidden="true" />
                <strong>{valor}</strong>
                <span>{titulo}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}

      <section className="secao">
        <div className="secao-cabecalho">
          <h2>Minhas solicitações recentes</h2>
          <Link to="/demandas/minhas" className="link">Ver todas</Link>
        </div>
        {recentes.carregando && !recentes.dados ? (
          <Carregando />
        ) : recentes.erro ? (
          <ErroCarregamento erro={recentes.erro} aoTentarNovamente={recentes.recarregar} />
        ) : recentes.dados?.data?.length ? (
          <TabelaDemandas demandas={recentes.dados.data} mostrarSolicitante={false} />
        ) : (
          <Vazio titulo="Nenhuma solicitação ainda" texto="Abra uma demanda para outro setor ou para um colega do seu setor.">
            <Link to="/demandas/nova" className="botao secundario">Abrir demanda</Link>
          </Vazio>
        )}
      </section>
    </>
  );
}
