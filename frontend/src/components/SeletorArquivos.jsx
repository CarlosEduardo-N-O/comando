import { useId, useRef } from 'react';
import { Paperclip, X } from 'lucide-react';
import { tamanhoArquivo } from '../utils/formato';

export const EXTENSOES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'docx', 'xlsx'];
export const MAX_ARQUIVOS = 5;
export const MAX_BYTES = 10 * 1024 * 1024;

/** Validação prévia no navegador (o backend valida novamente tipo, conteúdo e tamanho). */
export function validarArquivos(arquivos) {
  if (arquivos.length > MAX_ARQUIVOS) return `Envie no máximo ${MAX_ARQUIVOS} arquivos.`;
  for (const arquivo of arquivos) {
    const extensao = arquivo.name.split('.').pop()?.toLowerCase();
    if (!EXTENSOES.includes(extensao)) return `"${arquivo.name}": tipo não permitido. Use PDF, JPG, JPEG, PNG, WEBP, TXT, DOCX ou XLSX.`;
    if (arquivo.size > MAX_BYTES) return `"${arquivo.name}" excede 10 MB.`;
  }
  return null;
}

export default function SeletorArquivos({ arquivos, aoMudar, erro, desabilitado }) {
  const id = useId();
  const entrada = useRef(null);

  const adicionar = (lista) => {
    const novos = [...arquivos, ...Array.from(lista)].slice(0, MAX_ARQUIVOS + 1);
    aoMudar(novos);
    if (entrada.current) entrada.current.value = '';
  };

  return (
    <div className="arquivos">
      <label htmlFor={id} className={`botao secundario pequeno ${desabilitado || arquivos.length >= MAX_ARQUIVOS ? 'desabilitado' : ''}`}>
        <Paperclip size={16} aria-hidden="true" /> Anexar arquivos
      </label>
      <input
        id={id}
        ref={entrada}
        type="file"
        multiple
        className="visualmente-oculto"
        accept={EXTENSOES.map((e) => `.${e}`).join(',')}
        disabled={desabilitado || arquivos.length >= MAX_ARQUIVOS}
        onChange={(e) => adicionar(e.target.files)}
      />
      <small className="ajuda">Até {MAX_ARQUIVOS} arquivos de 10 MB: PDF, JPG, PNG, WEBP, TXT, DOCX ou XLSX.</small>
      {arquivos.length > 0 && (
        <ul className="lista-arquivos">
          {arquivos.map((arquivo, indice) => (
            <li key={`${arquivo.name}-${indice}`}>
              <span>{arquivo.name}</span>
              <small>{tamanhoArquivo(arquivo.size)}</small>
              <button type="button" className="botao-icone" onClick={() => aoMudar(arquivos.filter((_, i) => i !== indice))} aria-label={`Remover ${arquivo.name}`} disabled={desabilitado}>
                <X size={14} />
              </button>
            </li>
          ))}
        </ul>
      )}
      {erro && <small className="erro-campo" role="alert">{erro}</small>}
    </div>
  );
}
