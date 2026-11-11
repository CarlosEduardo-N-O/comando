import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '../api/client';

/**
 * Carrega dados de um endpoint GET com estados de carregamento e erro.
 * Requisições antigas são descartadas quando os parâmetros mudam.
 */
export function useApi(url, params = null, { ativo = true } = {}) {
  const [dados, setDados] = useState(null);
  const [erro, setErro] = useState(null);
  const [carregando, setCarregando] = useState(Boolean(url) && ativo);
  const [versao, setVersao] = useState(0);
  const chaveParams = params ? JSON.stringify(params) : '';
  const ultimaRequisicao = useRef(0);

  useEffect(() => {
    if (!url || !ativo) {
      setCarregando(false);
      return undefined;
    }
    const id = ++ultimaRequisicao.current;
    const controle = new AbortController();
    setCarregando(true);
    setErro(null);
    api
      .get(url, { params: chaveParams ? JSON.parse(chaveParams) : undefined, signal: controle.signal })
      .then(({ data }) => id === ultimaRequisicao.current && setDados(data))
      .catch((e) => {
        if (e.code !== 'ERR_CANCELED' && id === ultimaRequisicao.current) setErro(e);
      })
      .finally(() => id === ultimaRequisicao.current && setCarregando(false));
    return () => controle.abort();
  }, [url, chaveParams, versao, ativo]);

  const recarregar = useCallback(() => setVersao((v) => v + 1), []);

  return { dados, erro, carregando, recarregar, setDados };
}

/** Opções de formulário (setores, categorias, usuários ativos), com cache em memória. */
const cacheOpcoes = new Map();
export function useOpcoes(tipo, params = null) {
  const chave = tipo + JSON.stringify(params || {});
  const [itens, setItens] = useState(() => cacheOpcoes.get(chave) || []);
  const [carregando, setCarregando] = useState(!cacheOpcoes.has(chave));

  useEffect(() => {
    let vivo = true;
    if (!tipo) {
      setItens([]);
      setCarregando(false);
      return undefined;
    }
    if (cacheOpcoes.has(chave)) {
      setItens(cacheOpcoes.get(chave));
      setCarregando(false);
      return undefined;
    }
    setCarregando(true);
    api
      .get(`/opcoes/${tipo}`, { params: params || undefined })
      .then(({ data }) => {
        cacheOpcoes.set(chave, data.data);
        if (vivo) setItens(data.data);
      })
      .catch(() => vivo && setItens([]))
      .finally(() => vivo && setCarregando(false));
    return () => {
      vivo = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chave]);

  return { itens, carregando };
}

export function limparCacheOpcoes() {
  cacheOpcoes.clear();
}
