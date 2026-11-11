import { useEffect, useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, ArrowLeft, Download, FileText, MessageSquare, History } from 'lucide-react';
import { api, baixarArquivo, mensagemErro } from '../api/client';
import { useApi } from '../hooks/useApi';
import AcoesDemanda from '../components/AcoesDemanda';
import SeletorArquivos, { validarArquivos } from '../components/SeletorArquivos';
import { Alerta, AtrasadaBadge, Botao, Carregando, ErroCarregamento, PrioridadeBadge, StatusBadge, Vazio } from '../components/ui';
import { ACOES_HISTORICO, CAMPOS_HISTORICO, PRIORIDADES, STATUS, dataHora, dataSimples, tamanhoArquivo } from '../utils/formato';

export default function DetalheDemanda() {
  const { id } = useParams();
  const local = useLocation();
  const navegar = useNavigate();
  const { dados, erro, carregando, recarregar, setDados } = useApi(`/demandas/${id}`);
  const [aviso, setAviso] = useState(local.state?.mensagem || null);
  const [aba, setAba] = useState('comentarios');
  const [versaoInteracoes, setVersaoInteracoes] = useState(0);
  const origem = local.state?.de;

  useEffect(() => {
    if (local.state?.mensagem) {
      // Remove a mensagem do histórico de navegação para não reaparecer ao voltar.
      navegar(local.pathname + local.search, { replace: true, state: origem ? { de: origem } : undefined });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const voltar = () => (origem ? navegar(origem) : navegar('/demandas/minhas'));

  if (carregando && !dados) return <Carregando texto="Carregando demanda..." />;
  if (erro) {
    const semAcesso = [403, 404].includes(erro.response?.status);
    return (
      <div className="cartao">
        {semAcesso ? (
          <Vazio titulo="Demanda indisponível" texto="Esta demanda não existe ou não está mais disponível para você.">
            <button type="button" className="botao secundario" onClick={voltar}><ArrowLeft size={16} aria-hidden="true" /> Voltar</button>
          </Vazio>
        ) : (
          <ErroCarregamento erro={erro} aoTentarNovamente={recarregar} />
        )}
      </div>
    );
  }

  const d = dados.data;

  const aposAcao = (resposta, mensagem) => {
    if (resposta?.meta?.acesso_perdido) {
      navegar(origem || '/', { replace: true });
      return;
    }
    setDados(resposta);
    setAviso(mensagem);
    setVersaoInteracoes((v) => v + 1);
    window.dispatchEvent(new Event('notificacoes:atualizadas'));
  };

  return (
    <article className="detalhe" aria-labelledby="titulo-demanda">
      <button type="button" className="botao texto voltar" onClick={voltar}>
        <ArrowLeft size={16} aria-hidden="true" /> Voltar para a lista
      </button>

      <header className="detalhe-cabecalho">
        <div>
          <p className="protocolo grande">{d.protocolo}</p>
          <h1 id="titulo-demanda">{d.titulo}</h1>
          <div className="badges">
            <StatusBadge status={d.status} />
            <PrioridadeBadge prioridade={d.prioridade} />
            {d.atrasada && <AtrasadaBadge />}
          </div>
        </div>
        <AcoesDemanda demanda={d} aoConcluir={aposAcao} />
      </header>

      <Alerta tipo="sucesso" erro={aviso} aoFechar={() => setAviso(null)} />

      {d.status === 'aguardando_solicitante' && d.informacao_solicitada && (
        <div className="destaque-fluxo aguardando" role="note">
          <AlertTriangle size={18} aria-hidden="true" />
          <div>
            <strong>Informação solicitada ao solicitante</strong>
            <p className="texto-livre">{d.informacao_solicitada}</p>
            <small>Responda pelos comentários. O responsável retoma o atendimento após a resposta.</small>
          </div>
        </div>
      )}
      {d.solucao && (
        <div className={`destaque-fluxo ${d.status === 'resolvida' ? 'resolvida' : 'concluida'}`} role="note">
          <FileText size={18} aria-hidden="true" />
          <div>
            <strong>{d.status === 'resolvida' ? 'Solução informada — aguardando confirmação do solicitante' : 'Solução'}</strong>
            <p className="texto-livre">{d.solucao}</p>
          </div>
        </div>
      )}

      <div className="detalhe-grade">
        <section className="cartao" aria-labelledby="sec-descricao">
          <h2 id="sec-descricao">Descrição</h2>
          <p className="texto-livre">{d.descricao}</p>

          <h3>Anexos da abertura</h3>
          <ListaAnexos demandaId={d.id} anexos={d.anexos} />
        </section>

        <aside className="cartao informacoes" aria-labelledby="sec-info">
          <h2 id="sec-info">Informações</h2>
          <dl>
            <dt>Solicitante</dt><dd>{d.solicitante?.name}</dd>
            <dt>Setor de origem</dt><dd>{d.setor_origem?.nome}</dd>
            <dt>Setor de destino</dt><dd>{d.setor_destino?.nome}</dd>
            <dt>Responsável</dt><dd>{d.responsavel?.name || <span className="suave">Sem responsável</span>}</dd>
            <dt>Categoria</dt><dd>{d.categoria?.nome}</dd>
            <dt>Prazo desejado</dt><dd>{dataSimples(d.prazo_desejado)}</dd>
            <dt>Prazo de atendimento</dt><dd className={d.atrasada ? 'texto-alerta' : ''}>{dataHora(d.prazo_atendimento)}</dd>
            <dt>Aberta em</dt><dd>{dataHora(d.created_at)}</dd>
            <dt>Atualizada em</dt><dd>{dataHora(d.updated_at)}</dd>
            {d.resolvida_em && (<><dt>Resolvida em</dt><dd>{dataHora(d.resolvida_em)}</dd></>)}
            {d.concluida_em && (<><dt>Concluída em</dt><dd>{dataHora(d.concluida_em)}</dd></>)}
            {d.cancelada_em && (<><dt>Cancelada em</dt><dd>{dataHora(d.cancelada_em)}</dd></>)}
          </dl>
        </aside>
      </div>

      <section className="cartao">
        <div className="abas" role="tablist" aria-label="Interações">
          <button type="button" role="tab" aria-selected={aba === 'comentarios'} className={`aba ${aba === 'comentarios' ? 'ativa' : ''}`} onClick={() => setAba('comentarios')}>
            <MessageSquare size={16} aria-hidden="true" /> Comentários
          </button>
          <button type="button" role="tab" aria-selected={aba === 'historico'} className={`aba ${aba === 'historico' ? 'ativa' : ''}`} onClick={() => setAba('historico')}>
            <History size={16} aria-hidden="true" /> Histórico
          </button>
        </div>
        <div role="tabpanel">
          {aba === 'comentarios' ? (
            <Comentarios demanda={d} versao={versaoInteracoes} aoComentar={() => { recarregar(); window.dispatchEvent(new Event('notificacoes:atualizadas')); }} />
          ) : (
            <Historico demandaId={d.id} versao={versaoInteracoes} />
          )}
        </div>
      </section>
    </article>
  );
}

function ListaAnexos({ demandaId, anexos }) {
  const [erro, setErro] = useState(null);
  const [baixando, setBaixando] = useState(null);
  if (!anexos?.length) return <p className="suave">Nenhum anexo.</p>;

  const baixar = async (anexo) => {
    setErro(null);
    setBaixando(anexo.id);
    try {
      await baixarArquivo(`/demandas/${demandaId}/anexos/${anexo.id}/download`, anexo.nome);
    } catch (e) {
      setErro(mensagemErro(e, 'Não foi possível baixar o arquivo.'));
    } finally {
      setBaixando(null);
    }
  };

  return (
    <>
      <Alerta erro={erro} />
      <ul className="anexos">
        {anexos.map((a) => (
          <li key={a.id}>
            <button type="button" className="anexo" onClick={() => baixar(a)} disabled={baixando === a.id} aria-label={`Baixar ${a.nome}`}>
              <Download size={14} aria-hidden="true" />
              <span>{a.nome}</span>
              <small>{tamanhoArquivo(a.tamanho)}</small>
            </button>
          </li>
        ))}
      </ul>
    </>
  );
}

function Comentarios({ demanda, versao, aoComentar }) {
  const [pagina, setPagina] = useState(1);
  const { dados, erro, carregando, recarregar } = useApi(`/demandas/${demanda.id}/comentarios`, { page: pagina, versao });
  const [mensagem, setMensagem] = useState('');
  const [arquivos, setArquivos] = useState([]);
  const [erroEnvio, setErroEnvio] = useState(null);
  const [erroArquivos, setErroArquivos] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const podeComentar = demanda.acoes?.includes('comentar');

  const enviar = async (e) => {
    e.preventDefault();
    if (enviando || !mensagem.trim()) return;
    const invalido = validarArquivos(arquivos);
    if (invalido) {
      setErroArquivos(invalido);
      return;
    }
    const corpo = new FormData();
    corpo.append('mensagem', mensagem);
    arquivos.forEach((a) => corpo.append('anexos[]', a));
    setEnviando(true);
    setErroEnvio(null);
    try {
      await api.post(`/demandas/${demanda.id}/comentarios`, corpo);
      setMensagem('');
      setArquivos([]);
      recarregar();
      aoComentar();
    } catch (falha) {
      setErroEnvio(mensagemErro(falha));
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="comentarios">
      {carregando && !dados ? (
        <Carregando texto="Carregando comentários..." />
      ) : erro ? (
        <ErroCarregamento erro={erro} aoTentarNovamente={recarregar} />
      ) : !dados?.data?.length ? (
        <p className="suave">Nenhum comentário até o momento.</p>
      ) : (
        <ol className="lista-comentarios">
          {dados.data.map((c) => (
            <li key={c.id} className={c.autor?.id === demanda.solicitante?.id ? 'do-solicitante' : ''}>
              <div className="comentario-cabecalho">
                <strong>{c.autor?.name}</strong>
                {c.autor?.id === demanda.solicitante?.id && <span className="etiqueta">Solicitante</span>}
                {c.autor?.id === demanda.responsavel?.id && <span className="etiqueta">Responsável</span>}
                <time dateTime={c.created_at}>{dataHora(c.created_at)}</time>
              </div>
              {/* Texto renderizado como texto: o React escapa qualquer HTML. */}
              <p className="texto-livre">{c.mensagem}</p>
              {c.anexos?.length > 0 && <ListaAnexos demandaId={demanda.id} anexos={c.anexos} />}
            </li>
          ))}
        </ol>
      )}
      {dados?.meta?.last_page > 1 && (
        <nav className="paginacao" aria-label="Páginas de comentários">
          <button type="button" className="botao secundario" disabled={pagina <= 1} onClick={() => setPagina((p) => p - 1)}>Anteriores</button>
          <span className="paginacao-info">Página {pagina} de {dados.meta.last_page}</span>
          <button type="button" className="botao secundario" disabled={pagina >= dados.meta.last_page} onClick={() => setPagina((p) => p + 1)}>Seguintes</button>
        </nav>
      )}

      {podeComentar ? (
        <form className="novo-comentario" onSubmit={enviar}>
          <Alerta erro={erroEnvio} />
          <label htmlFor="novo-comentario">Novo comentário</label>
          <textarea id="novo-comentario" rows={3} value={mensagem} onChange={(e) => setMensagem(e.target.value)} maxLength={20000} placeholder="Escreva uma mensagem para os participantes" />
          <div className="novo-comentario-acoes">
            <SeletorArquivos arquivos={arquivos} aoMudar={(l) => { setArquivos(l); setErroArquivos(validarArquivos(l)); }} erro={erroArquivos} desabilitado={enviando} />
            <Botao type="submit" carregando={enviando} disabled={!mensagem.trim()}>Comentar</Botao>
          </div>
        </form>
      ) : (
        <p className="aviso-suave">
          {demanda.status === 'concluida' || demanda.status === 'cancelada' ? 'Demanda encerrada: não recebe novos comentários.' : 'Você não pode comentar nesta demanda.'}
        </p>
      )}
    </div>
  );
}

function valorHistorico(campo, valor) {
  if (valor === null || valor === undefined || valor === '') return '—';
  if (campo === 'status') return STATUS[valor]?.rotulo || valor;
  if (campo === 'prioridade') return PRIORIDADES[valor]?.rotulo || valor;
  if (campo === 'prazo_atendimento') return dataHora(valor);
  return String(valor);
}

function Historico({ demandaId, versao }) {
  const [pagina, setPagina] = useState(1);
  const { dados, erro, carregando, recarregar } = useApi(`/demandas/${demandaId}/historico`, { page: pagina, versao });

  if (carregando && !dados) return <Carregando texto="Carregando histórico..." />;
  if (erro) return <ErroCarregamento erro={erro} aoTentarNovamente={recarregar} />;
  if (!dados?.data?.length) return <p className="suave">Sem eventos.</p>;

  return (
    <>
      <ol className="linha-tempo">
        {dados.data.map((h) => {
          const campos = Object.keys(CAMPOS_HISTORICO).filter((c) => h.acao !== 'criacao' && h.antes && c in (h.depois || {}));
          return (
            <li key={h.id}>
              <div className="linha-tempo-cabecalho">
                <strong>{ACOES_HISTORICO[h.acao] || h.acao}</strong>
                <time dateTime={h.created_at}>{dataHora(h.created_at)}</time>
              </div>
              <small>por {h.autor?.name}</small>
              {campos.length > 0 && (
                <ul className="mudancas">
                  {campos.map((c) => (
                    <li key={c}>
                      {CAMPOS_HISTORICO[c]}: <span className="antes">{valorHistorico(c, h.antes?.[c])}</span> → <span className="depois">{valorHistorico(c, h.depois?.[c])}</span>
                    </li>
                  ))}
                </ul>
              )}
              {h.justificativa && <p className="texto-livre justificativa">{h.justificativa}</p>}
            </li>
          );
        })}
      </ol>
      {dados.meta?.last_page > 1 && (
        <nav className="paginacao" aria-label="Páginas do histórico">
          <button type="button" className="botao secundario" disabled={pagina <= 1} onClick={() => setPagina((p) => p - 1)}>Mais recentes</button>
          <span className="paginacao-info">Página {pagina} de {dados.meta.last_page}</span>
          <button type="button" className="botao secundario" disabled={pagina >= dados.meta.last_page} onClick={() => setPagina((p) => p + 1)}>Mais antigos</button>
        </nav>
      )}
    </>
  );
}

