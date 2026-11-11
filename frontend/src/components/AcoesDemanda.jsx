import { useState } from 'react';
import { api, errosDeCampo, mensagemErro } from '../api/client';
import { useOpcoes } from '../hooks/useApi';
import { Alerta, Botao, Campo, Modal } from './ui';
import { PRIORIDADES, isoParaLocal, localParaIso } from '../utils/formato';

/**
 * Botões das ações permitidas (lista "acoes" calculada pelo backend) e os
 * diálogos de cada uma. O backend revalida todas as permissões.
 */
const DEFINICOES = {
  assumir: { rotulo: 'Assumir', variante: 'primario', titulo: 'Assumir demanda', confirmar: 'Você será o responsável por esta demanda. Deseja assumir?' },
  iniciar: { rotulo: 'Iniciar atendimento', variante: 'primario', status: 'em_andamento', direto: true, sucesso: 'Atendimento iniciado.' },
  retomar: { rotulo: 'Retomar atendimento', variante: 'primario', status: 'em_andamento', direto: true, sucesso: 'Atendimento retomado.' },
  solicitar_informacoes: { rotulo: 'Solicitar informações', variante: 'secundario', status: 'aguardando_solicitante', titulo: 'Solicitar informações ao solicitante', campo: 'Mensagem explicativa', sucesso: 'Solicitação enviada ao solicitante.' },
  resolver: { rotulo: 'Resolver', variante: 'primario', status: 'resolvida', titulo: 'Resolver demanda', campo: 'Descrição da solução', sucesso: 'Demanda resolvida. Aguardando confirmação do solicitante.' },
  concluir: { rotulo: 'Confirmar conclusão', variante: 'primario', status: 'concluida', titulo: 'Confirmar conclusão', confirmar: 'Confirma que a solução atendeu à sua necessidade? A demanda será concluída e não poderá ser reaberta.', sucesso: 'Demanda concluída.' },
  rejeitar: { rotulo: 'Rejeitar solução', variante: 'secundario', status: 'em_andamento', titulo: 'Rejeitar solução', campo: 'Motivo da rejeição', sucesso: 'Solução rejeitada. A demanda voltou para atendimento.' },
  atribuir: { rotulo: 'Atribuir', variante: 'secundario', titulo: 'Atribuir responsável' },
  transferir: { rotulo: 'Transferir', variante: 'secundario', titulo: 'Transferir para outro setor' },
  alterar_prioridade: { rotulo: 'Prioridade', variante: 'secundario', titulo: 'Alterar prioridade' },
  definir_prazo: { rotulo: 'Prazo', variante: 'secundario', titulo: 'Prazo de atendimento' },
  cancelar: { rotulo: 'Cancelar demanda', variante: 'perigo', status: 'cancelada', titulo: 'Cancelar demanda', campo: 'Justificativa do cancelamento', confirmar: 'O cancelamento é definitivo: a demanda não poderá ser reaberta.', sucesso: 'Demanda cancelada.' },
};

const ORDEM = ['assumir', 'iniciar', 'retomar', 'resolver', 'solicitar_informacoes', 'concluir', 'rejeitar', 'atribuir', 'transferir', 'alterar_prioridade', 'definir_prazo', 'cancelar'];

export default function AcoesDemanda({ demanda, aoConcluir }) {
  const [aberta, setAberta] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState(null);
  const acoes = ORDEM.filter((a) => demanda.acoes?.includes(a));

  if (!acoes.length) return null;

  const executar = async (requisicao, mensagem) => {
    if (enviando) return;
    setEnviando(true);
    setErro(null);
    try {
      const { data } = await requisicao();
      setAberta(null);
      aoConcluir(data, mensagem);
    } catch (falha) {
      setErro(falha);
    } finally {
      setEnviando(false);
    }
  };

  const status = (novo, justificativa, mensagem) => executar(() => api.patch(`/demandas/${demanda.id}/status`, { status: novo, justificativa }), mensagem);

  const clicar = (acao) => {
    const def = DEFINICOES[acao];
    setErro(null);
    if (def.direto) return status(def.status, null, def.sucesso);
    return setAberta(acao);
  };

  const fechar = () => !enviando && setAberta(null);
  const def = aberta ? DEFINICOES[aberta] : null;

  return (
    <div className="acoes-demanda">
      <div className="acoes-botoes" role="group" aria-label="Ações da demanda">
        {acoes.filter((a) => a !== 'comentar').map((acao) => (
          <Botao key={acao} variante={DEFINICOES[acao].variante} carregando={enviando && DEFINICOES[acao].direto} disabled={enviando} onClick={() => clicar(acao)}>
            {DEFINICOES[acao].rotulo}
          </Botao>
        ))}
      </div>
      {!aberta && <Alerta erro={erro} aoFechar={() => setErro(null)} />}

      {aberta === 'assumir' && (
        <Confirmacao def={def} aoFechar={fechar} enviando={enviando} erro={erro}
          aoConfirmar={() => executar(() => api.patch(`/demandas/${demanda.id}/atribuicao`, { assumir: true }), 'Você assumiu a demanda.')} />
      )}
      {aberta === 'concluir' && (
        <Confirmacao def={def} aoFechar={fechar} enviando={enviando} erro={erro} aoConfirmar={() => status('concluida', null, def.sucesso)} />
      )}
      {['solicitar_informacoes', 'resolver', 'rejeitar', 'cancelar'].includes(aberta) && (
        <DialogoTexto def={def} aoFechar={fechar} enviando={enviando} erro={erro} aoEnviar={(texto) => status(def.status, texto, def.sucesso)} />
      )}
      {aberta === 'atribuir' && (
        <DialogoAtribuir demanda={demanda} aoFechar={fechar} enviando={enviando} erro={erro}
          aoEnviar={(responsavelId) => executar(() => api.patch(`/demandas/${demanda.id}/atribuicao`, { responsavel_id: responsavelId }), 'Responsável atualizado.')} />
      )}
      {aberta === 'transferir' && (
        <DialogoTransferir demanda={demanda} aoFechar={fechar} enviando={enviando} erro={erro}
          aoEnviar={(corpo) => executar(() => api.patch(`/demandas/${demanda.id}/destino`, corpo), 'Demanda transferida.')} />
      )}
      {aberta === 'alterar_prioridade' && (
        <DialogoPrioridade demanda={demanda} aoFechar={fechar} enviando={enviando} erro={erro}
          aoEnviar={(corpo) => executar(() => api.patch(`/demandas/${demanda.id}/prioridade`, corpo), 'Prioridade alterada.')} />
      )}
      {aberta === 'definir_prazo' && (
        <DialogoPrazo demanda={demanda} aoFechar={fechar} enviando={enviando} erro={erro}
          aoEnviar={(corpo) => executar(() => api.patch(`/demandas/${demanda.id}/prazo`, corpo), 'Prazo de atendimento atualizado.')} />
      )}
    </div>
  );
}

function Rodape({ aoFechar, enviando, rotulo, variante = 'primario', form, desabilitado }) {
  return (
    <>
      <button type="button" className="botao secundario" onClick={aoFechar} disabled={enviando}>Voltar</button>
      <Botao type="submit" form={form} variante={variante} carregando={enviando} disabled={desabilitado}>{rotulo}</Botao>
    </>
  );
}

function Confirmacao({ def, aoFechar, aoConfirmar, enviando, erro }) {
  return (
    <Modal titulo={def.titulo} aberto aoFechar={aoFechar} largura="pequena"
      rodape={<><button type="button" className="botao secundario" onClick={aoFechar} disabled={enviando}>Voltar</button><Botao carregando={enviando} onClick={aoConfirmar} data-autofocus>Confirmar</Botao></>}>
      <Alerta erro={erro} />
      <p>{def.confirmar}</p>
    </Modal>
  );
}

function DialogoTexto({ def, aoFechar, aoEnviar, enviando, erro }) {
  const [texto, setTexto] = useState('');
  const [confirmado, setConfirmado] = useState(false);
  const exigeConfirmacao = Boolean(def.confirmar);
  return (
    <Modal titulo={def.titulo} aberto aoFechar={aoFechar}
      rodape={<Rodape form="form-acao" aoFechar={aoFechar} enviando={enviando} rotulo={def.rotulo} variante={def.variante === 'perigo' ? 'perigo' : 'primario'} desabilitado={!texto.trim() || (exigeConfirmacao && !confirmado)} />}>
      <form id="form-acao" onSubmit={(e) => { e.preventDefault(); if (texto.trim() && (!exigeConfirmacao || confirmado)) aoEnviar(texto.trim()); }}>
        <Alerta erro={erro} />
        <Campo rotulo={def.campo} obrigatorio erro={errosDeCampo(erro).justificativa}>
          {(p) => <textarea {...p} rows={5} value={texto} onChange={(e) => setTexto(e.target.value)} maxLength={20000} data-autofocus required />}
        </Campo>
        {exigeConfirmacao && (
          <label className="checkbox confirmacao">
            <input type="checkbox" checked={confirmado} onChange={(e) => setConfirmado(e.target.checked)} />
            {def.confirmar} Confirmo o cancelamento.
          </label>
        )}
      </form>
    </Modal>
  );
}

function DialogoAtribuir({ demanda, aoFechar, aoEnviar, enviando, erro }) {
  const { itens, carregando } = useOpcoes('usuarios', { setor_id: demanda.setor_destino?.id });
  const [responsavel, setResponsavel] = useState(demanda.responsavel?.id ? String(demanda.responsavel.id) : '');
  const candidatos = itens.filter((u) => u.id !== demanda.solicitante?.id);
  const podeSemResponsavel = demanda.status === 'aberta';
  const alterado = responsavel !== (demanda.responsavel?.id ? String(demanda.responsavel.id) : '');

  return (
    <Modal titulo="Atribuir responsável" aberto aoFechar={aoFechar}
      rodape={<Rodape form="form-atribuir" aoFechar={aoFechar} enviando={enviando} rotulo="Salvar" desabilitado={!alterado || (!responsavel && !podeSemResponsavel)} />}>
      <form id="form-atribuir" onSubmit={(e) => { e.preventDefault(); if (alterado) aoEnviar(responsavel ? Number(responsavel) : null); }}>
        <Alerta erro={erro} />
        <p className="suave">Somente colaboradores ativos do setor {demanda.setor_destino?.nome}. O responsável anterior perde o acesso, salvo se tiver outra permissão.</p>
        <Campo rotulo="Responsável" obrigatorio={!podeSemResponsavel}>
          {(p) => (
            <select {...p} value={responsavel} onChange={(e) => setResponsavel(e.target.value)} disabled={carregando} data-autofocus>
              <option value="" disabled={!podeSemResponsavel}>{podeSemResponsavel ? 'Sem responsável (fila do setor)' : 'Selecione...'}</option>
              {candidatos.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
            </select>
          )}
        </Campo>
      </form>
    </Modal>
  );
}

function DialogoTransferir({ demanda, aoFechar, aoEnviar, enviando, erro }) {
  const { itens: setores } = useOpcoes('setores');
  const [setor, setSetor] = useState('');
  const { itens: usuarios } = useOpcoes(setor ? 'usuarios' : null, { setor_id: setor });
  const [responsavel, setResponsavel] = useState('');
  const [justificativa, setJustificativa] = useState('');
  const [confirmado, setConfirmado] = useState(false);
  const erros = errosDeCampo(erro);
  const valido = setor && justificativa.trim() && confirmado;

  return (
    <Modal titulo="Transferir para outro setor" aberto aoFechar={aoFechar}
      rodape={<Rodape form="form-transferir" aoFechar={aoFechar} enviando={enviando} rotulo="Transferir" desabilitado={!valido} />}>
      <form id="form-transferir" onSubmit={(e) => {
        e.preventDefault();
        if (valido) aoEnviar({ setor_destino_id: Number(setor), responsavel_id: responsavel ? Number(responsavel) : null, justificativa: justificativa.trim() });
      }}>
        <Alerta erro={erro} />
        <Campo rotulo="Novo setor de destino" obrigatorio erro={erros.setor_destino_id}>
          {(p) => (
            <select {...p} value={setor} onChange={(e) => { setSetor(e.target.value); setResponsavel(''); }} data-autofocus>
              <option value="">Selecione...</option>
              {setores.filter((s) => s.id !== demanda.setor_destino?.id).map((s) => <option key={s.id} value={s.id}>{s.nome}</option>)}
            </select>
          )}
        </Campo>
        <Campo rotulo="Novo responsável" erro={erros.responsavel_id} ajuda="Opcional. Sem responsável, a demanda vai para a fila do novo setor.">
          {(p) => (
            <select {...p} value={responsavel} onChange={(e) => setResponsavel(e.target.value)} disabled={!setor}>
              <option value="">Sem responsável</option>
              {usuarios.filter((u) => u.id !== demanda.solicitante?.id).map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
            </select>
          )}
        </Campo>
        <Campo rotulo="Justificativa" obrigatorio erro={erros.justificativa}>
          {(p) => <textarea {...p} rows={4} value={justificativa} onChange={(e) => setJustificativa(e.target.value)} maxLength={5000} />}
        </Campo>
        <label className="checkbox confirmacao">
          <input type="checkbox" checked={confirmado} onChange={(e) => setConfirmado(e.target.checked)} />
          Confirmo a transferência: a demanda volta para “Aberta” e o prazo de atendimento será removido.
        </label>
      </form>
    </Modal>
  );
}

function DialogoPrioridade({ demanda, aoFechar, aoEnviar, enviando, erro }) {
  const [prioridade, setPrioridade] = useState(demanda.prioridade);
  const [justificativa, setJustificativa] = useState('');
  return (
    <Modal titulo="Alterar prioridade" aberto aoFechar={aoFechar} largura="pequena"
      rodape={<Rodape form="form-prioridade" aoFechar={aoFechar} enviando={enviando} rotulo="Salvar" desabilitado={prioridade === demanda.prioridade} />}>
      <form id="form-prioridade" onSubmit={(e) => { e.preventDefault(); if (prioridade !== demanda.prioridade) aoEnviar({ prioridade, justificativa: justificativa.trim() || null }); }}>
        <Alerta erro={erro} />
        <Campo rotulo="Prioridade" obrigatorio>
          {(p) => (
            <select {...p} value={prioridade} onChange={(e) => setPrioridade(e.target.value)} data-autofocus>
              {Object.entries(PRIORIDADES).map(([k, { rotulo }]) => <option key={k} value={k}>{rotulo}</option>)}
            </select>
          )}
        </Campo>
        <Campo rotulo="Justificativa (opcional)">
          {(p) => <textarea {...p} rows={3} value={justificativa} onChange={(e) => setJustificativa(e.target.value)} maxLength={5000} />}
        </Campo>
      </form>
    </Modal>
  );
}

function DialogoPrazo({ demanda, aoFechar, aoEnviar, enviando, erro }) {
  const [prazo, setPrazo] = useState(isoParaLocal(demanda.prazo_atendimento));
  const [justificativa, setJustificativa] = useState('');
  const erros = errosDeCampo(erro);
  const minimo = isoParaLocal(new Date().toISOString());

  return (
    <Modal titulo="Prazo de atendimento" aberto aoFechar={aoFechar} largura="pequena"
      rodape={
        <>
          {demanda.prazo_atendimento && (
            <button type="button" className="botao texto" disabled={enviando} onClick={() => aoEnviar({ prazo_atendimento: null, justificativa: justificativa.trim() || null })}>
              Remover prazo
            </button>
          )}
          <Rodape form="form-prazo" aoFechar={aoFechar} enviando={enviando} rotulo="Salvar" desabilitado={!prazo} />
        </>
      }>
      <form id="form-prazo" onSubmit={(e) => { e.preventDefault(); if (prazo) aoEnviar({ prazo_atendimento: localParaIso(prazo), justificativa: justificativa.trim() || null }); }}>
        <Alerta erro={erro} />
        <Campo rotulo="Data e hora limite" obrigatorio erro={erros.prazo_atendimento} ajuda="Horário de Brasília. Demandas em atendimento com prazo vencido aparecem como atrasadas.">
          {(p) => <input {...p} type="datetime-local" min={minimo} value={prazo} onChange={(e) => setPrazo(e.target.value)} data-autofocus />}
        </Campo>
        <Campo rotulo="Observação (opcional)">
          {(p) => <textarea {...p} rows={2} value={justificativa} onChange={(e) => setJustificativa(e.target.value)} maxLength={5000} />}
        </Campo>
      </form>
    </Modal>
  );
}
