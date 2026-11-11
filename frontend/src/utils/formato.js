// Rótulos, cores e formatação em pt-BR.

export const STATUS = {
  aberta: { rotulo: 'Aberta', classe: 'st-aberta' },
  em_andamento: { rotulo: 'Em andamento', classe: 'st-andamento' },
  aguardando_solicitante: { rotulo: 'Aguardando solicitante', classe: 'st-aguardando' },
  resolvida: { rotulo: 'Resolvida', classe: 'st-resolvida' },
  concluida: { rotulo: 'Concluída', classe: 'st-concluida' },
  cancelada: { rotulo: 'Cancelada', classe: 'st-cancelada' },
};

export const PRIORIDADES = {
  baixa: { rotulo: 'Baixa', classe: 'pr-baixa' },
  normal: { rotulo: 'Normal', classe: 'pr-normal' },
  alta: { rotulo: 'Alta', classe: 'pr-alta' },
  urgente: { rotulo: 'Urgente', classe: 'pr-urgente' },
};

export const PERFIS = {
  colaborador: 'Colaborador',
  gestor: 'Gestor',
  administrador: 'Administrador',
};

export const ACOES_HISTORICO = {
  criacao: 'Abertura da demanda',
  atribuicao: 'Atribuição de responsável',
  reatribuicao: 'Reatribuição de responsável',
  assuncao: 'Demanda assumida',
  transferencia: 'Transferência de setor',
  prioridade: 'Alteração de prioridade',
  prazo: 'Definição de prazo de atendimento',
  inicio_atendimento: 'Início do atendimento',
  retomada_atendimento: 'Retomada do atendimento',
  solicitacao_informacoes: 'Solicitação de informações',
  resolucao: 'Resolução',
  rejeicao: 'Rejeição da solução',
  conclusao: 'Conclusão confirmada',
  cancelamento: 'Cancelamento',
};

export const CAMPOS_HISTORICO = {
  status: 'Status',
  prioridade: 'Prioridade',
  prazo_atendimento: 'Prazo de atendimento',
  responsavel: 'Responsável',
  setor_destino: 'Setor de destino',
};

let fuso = 'America/Sao_Paulo';
export function definirFuso(novo) {
  if (novo) fuso = novo;
}
export function fusoAtual() {
  return fuso;
}

const cacheFormatos = new Map();
function formatador(opcoes) {
  const chave = fuso + JSON.stringify(opcoes);
  if (!cacheFormatos.has(chave)) {
    cacheFormatos.set(chave, new Intl.DateTimeFormat('pt-BR', { timeZone: fuso, ...opcoes }));
  }
  return cacheFormatos.get(chave);
}

/** Data e hora no padrão brasileiro, no fuso configurado. */
export function dataHora(iso) {
  if (!iso) return '—';
  return formatador({ day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
}

export function data(iso) {
  if (!iso) return '—';
  return formatador({ day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(iso));
}

/** Datas sem horário (AAAA-MM-DD), como o prazo desejado: sem conversão de fuso. */
export function dataSimples(valor) {
  if (!valor) return '—';
  const [ano, mes, dia] = String(valor).slice(0, 10).split('-');
  return `${dia}/${mes}/${ano}`;
}

export function tamanhoArquivo(bytes) {
  if (bytes == null) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
}

/** Diferença (ms) entre o horário no fuso e UTC para um instante. */
function deslocamento(instante) {
  const partes = Object.fromEntries(
    formatador({ year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' })
      .formatToParts(instante)
      .map((p) => [p.type, p.value]),
  );
  const comoUtc = Date.UTC(+partes.year, +partes.month - 1, +partes.day, +partes.hour, +partes.minute, +partes.second);
  return comoUtc - instante.getTime();
}

/** Converte "AAAA-MM-DDTHH:MM" digitado no fuso configurado para ISO 8601 em UTC. */
export function localParaIso(valor) {
  if (!valor) return null;
  const [d, h] = valor.split('T');
  const [ano, mes, dia] = d.split('-').map(Number);
  const [hora, minuto] = (h || '00:00').split(':').map(Number);
  const aproximado = Date.UTC(ano, mes - 1, dia, hora, minuto);
  let utc = aproximado - deslocamento(new Date(aproximado));
  utc = aproximado - deslocamento(new Date(utc));
  return new Date(utc).toISOString();
}

/** Converte ISO (UTC) para o valor de <input type="datetime-local"> no fuso configurado. */
export function isoParaLocal(iso) {
  if (!iso) return '';
  const p = Object.fromEntries(
    formatador({ year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
      .formatToParts(new Date(iso))
      .map((x) => [x.type, x.value]),
  );
  return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
}

/** Data de hoje (AAAA-MM-DD) no fuso configurado. */
export function hoje() {
  return isoParaLocal(new Date().toISOString()).slice(0, 10);
}
