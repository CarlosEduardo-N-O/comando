import { Link, useLocation } from 'react-router-dom';
import { AtrasadaBadge, PrioridadeBadge, StatusBadge } from './ui';
import { dataHora, dataSimples } from '../utils/formato';

/** Lista de demandas: tabela no desktop, cartões empilhados no celular. */
export default function TabelaDemandas({ demandas, mostrarSolicitante = true, mostrarResponsavel = true }) {
  const local = useLocation();
  // Guarda a lista de origem (com filtros) para o botão "Voltar" dos detalhes.
  const origem = { de: local.pathname + local.search };

  return (
    <div className="tabela-container">
      <table className="tabela tabela-responsiva">
        <thead>
          <tr>
            <th scope="col">Protocolo</th>
            <th scope="col">Título</th>
            <th scope="col">Status</th>
            <th scope="col">Prioridade</th>
            {mostrarSolicitante && <th scope="col">Solicitante</th>}
            <th scope="col">Destino</th>
            {mostrarResponsavel && <th scope="col">Responsável</th>}
            <th scope="col">Prazo</th>
            <th scope="col">Abertura</th>
          </tr>
        </thead>
        <tbody>
          {demandas.map((d) => (
            <tr key={d.id}>
              <td data-rotulo="Protocolo">
                <Link to={`/demandas/${d.id}`} state={origem} className="protocolo">{d.protocolo}</Link>
              </td>
              <td data-rotulo="Título" className="celula-titulo">
                <Link to={`/demandas/${d.id}`} state={origem}>{d.titulo}</Link>
                <small>{d.categoria?.nome}</small>
              </td>
              <td data-rotulo="Status">
                <div className="badges">
                  <StatusBadge status={d.status} />
                  {d.atrasada && <AtrasadaBadge />}
                </div>
              </td>
              <td data-rotulo="Prioridade"><PrioridadeBadge prioridade={d.prioridade} /></td>
              {mostrarSolicitante && <td data-rotulo="Solicitante">{d.solicitante?.name}<small>{d.setor_origem?.nome}</small></td>}
              <td data-rotulo="Destino">{d.setor_destino?.nome}</td>
              {mostrarResponsavel && <td data-rotulo="Responsável">{d.responsavel?.name || <span className="suave">Sem responsável</span>}</td>}
              <td data-rotulo="Prazo">
                {d.prazo_atendimento ? dataHora(d.prazo_atendimento) : <span className="suave">{d.prazo_desejado ? `Desejado ${dataSimples(d.prazo_desejado)}` : '—'}</span>}
              </td>
              <td data-rotulo="Abertura">{dataHora(d.created_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
