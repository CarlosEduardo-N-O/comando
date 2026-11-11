import { useEffect, useId, useRef } from 'react';
import { AlertCircle, CheckCircle2, Inbox, Loader2, X } from 'lucide-react';
import { mensagemErro } from '../api/client';
import { PRIORIDADES, STATUS } from '../utils/formato';

export function StatusBadge({ status }) {
  const info = STATUS[status] || { rotulo: status, classe: '' };
  return <span className={`badge ${info.classe}`}>{info.rotulo}</span>;
}

export function PrioridadeBadge({ prioridade }) {
  const info = PRIORIDADES[prioridade] || { rotulo: prioridade, classe: '' };
  return (
    <span className={`prioridade ${info.classe}`}>
      <span className="ponto" aria-hidden="true" />
      {info.rotulo}
    </span>
  );
}

export function AtrasadaBadge() {
  return <span className="badge st-atrasada">Atrasada</span>;
}

export function Carregando({ texto = 'Carregando...' }) {
  return (
    <div className="estado" role="status" aria-live="polite">
      <Loader2 className="girando" size={24} aria-hidden="true" />
      <span>{texto}</span>
    </div>
  );
}

export function Alerta({ erro, tipo = 'erro', children, aoFechar }) {
  const conteudo = children || (tipo === 'erro' ? mensagemErro(erro) : erro);
  if (!conteudo) return null;
  const Icone = tipo === 'sucesso' ? CheckCircle2 : AlertCircle;
  return (
    <div className={`alerta alerta-${tipo}`} role={tipo === 'erro' ? 'alert' : 'status'}>
      <Icone size={18} aria-hidden="true" />
      <div className="alerta-texto">{conteudo}</div>
      {aoFechar && (
        <button type="button" className="botao-icone" onClick={aoFechar} aria-label="Fechar aviso">
          <X size={16} />
        </button>
      )}
    </div>
  );
}

export function ErroCarregamento({ erro, aoTentarNovamente }) {
  return (
    <div className="estado estado-erro" role="alert">
      <AlertCircle size={28} aria-hidden="true" />
      <p>{mensagemErro(erro, 'Não foi possível carregar os dados.')}</p>
      {aoTentarNovamente && (
        <button type="button" className="botao secundario" onClick={aoTentarNovamente}>
          Tentar novamente
        </button>
      )}
    </div>
  );
}

export function Vazio({ titulo = 'Nada por aqui', texto, children }) {
  return (
    <div className="estado vazio">
      <Inbox size={32} aria-hidden="true" />
      <strong>{titulo}</strong>
      {texto && <p>{texto}</p>}
      {children}
    </div>
  );
}

export function Paginacao({ meta, aoMudar }) {
  if (!meta || meta.last_page <= 1) {
    return meta?.total ? <p className="paginacao-info">{meta.total} registro(s)</p> : null;
  }
  return (
    <nav className="paginacao" aria-label="Paginação">
      <span className="paginacao-info">
        Página {meta.current_page} de {meta.last_page} · {meta.total} registros
      </span>
      <div className="paginacao-botoes">
        <button type="button" className="botao secundario" disabled={meta.current_page <= 1} onClick={() => aoMudar(meta.current_page - 1)}>
          Anterior
        </button>
        <button type="button" className="botao secundario" disabled={meta.current_page >= meta.last_page} onClick={() => aoMudar(meta.current_page + 1)}>
          Próxima
        </button>
      </div>
    </nav>
  );
}

/** Campo de formulário com rótulo, ajuda e erro associados (acessível). */
export function Campo({ rotulo, erro, ajuda, obrigatorio, children, className = '' }) {
  const id = useId();
  const idAjuda = ajuda ? `${id}-ajuda` : undefined;
  const idErro = erro ? `${id}-erro` : undefined;
  const filho = typeof children === 'function'
    ? children({ id, 'aria-invalid': Boolean(erro) || undefined, 'aria-describedby': [idAjuda, idErro].filter(Boolean).join(' ') || undefined })
    : children;
  return (
    <div className={`campo ${erro ? 'campo-invalido' : ''} ${className}`}>
      <label htmlFor={id}>
        {rotulo}
        {obrigatorio && <span className="obrigatorio" aria-hidden="true"> *</span>}
      </label>
      {filho}
      {ajuda && <small id={idAjuda} className="ajuda">{ajuda}</small>}
      {erro && <small id={idErro} className="erro-campo">{erro}</small>}
    </div>
  );
}

/** Janela modal acessível: foco inicial, Esc fecha, foco preso no diálogo. */
export function Modal({ titulo, aberto, aoFechar, children, rodape, largura = 'media' }) {
  const ref = useRef(null);
  const tituloId = useId();
  const fecharRef = useRef(aoFechar);
  fecharRef.current = aoFechar;

  useEffect(() => {
    if (!aberto) return undefined;
    const anterior = document.activeElement;
    const el = ref.current;
    const focaveis = () => el.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    (el.querySelector('[data-autofocus]') || focaveis()[0] || el).focus();

    const teclado = (e) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        fecharRef.current();
      }
      if (e.key === 'Tab') {
        const lista = Array.from(focaveis()).filter((x) => !x.disabled);
        if (!lista.length) return;
        const primeiro = lista[0];
        const ultimo = lista[lista.length - 1];
        if (e.shiftKey && document.activeElement === primeiro) {
          e.preventDefault();
          ultimo.focus();
        } else if (!e.shiftKey && document.activeElement === ultimo) {
          e.preventDefault();
          primeiro.focus();
        }
      }
    };
    el.addEventListener('keydown', teclado);
    document.body.classList.add('modal-aberto');
    return () => {
      el.removeEventListener('keydown', teclado);
      document.body.classList.remove('modal-aberto');
      anterior?.focus?.();
    };
  }, [aberto]);

  if (!aberto) return null;
  return (
    <div className="modal-fundo" onMouseDown={(e) => e.target === e.currentTarget && aoFechar()}>
      <div className={`modal modal-${largura}`} role="dialog" aria-modal="true" aria-labelledby={tituloId} ref={ref} tabIndex={-1}>
        <header className="modal-cabecalho">
          <h2 id={tituloId}>{titulo}</h2>
          <button type="button" className="botao-icone" onClick={aoFechar} aria-label="Fechar">
            <X size={18} />
          </button>
        </header>
        <div className="modal-corpo">{children}</div>
        {rodape && <footer className="modal-rodape">{rodape}</footer>}
      </div>
    </div>
  );
}

export function Botao({ carregando, children, className = '', variante = 'primario', ...props }) {
  return (
    <button type="button" className={`botao ${variante} ${className}`} disabled={carregando || props.disabled} aria-busy={carregando || undefined} {...props}>
      {carregando && <Loader2 className="girando" size={16} aria-hidden="true" />}
      {children}
    </button>
  );
}

export function CabecalhoPagina({ titulo, descricao, children }) {
  return (
    <div className="cabecalho-pagina">
      <div>
        <h1>{titulo}</h1>
        {descricao && <p className="descricao">{descricao}</p>}
      </div>
      {children && <div className="cabecalho-acoes">{children}</div>}
    </div>
  );
}
