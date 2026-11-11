import axios from 'axios';

/**
 * Cliente da API. A autenticação usa somente o cookie de sessão HttpOnly do
 * Sanctum: nenhum token ou identificador de sessão é guardado no navegador
 * (localStorage/sessionStorage). O Axios envia o cookie XSRF-TOKEN como
 * cabeçalho X-XSRF-TOKEN nas mutações.
 */
export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  timeout: 60000,
});

let csrfPromise = null;

/** Garante o cookie CSRF antes de mutações (login, formulários). */
export function garantirCsrf(forcar = false) {
  if (forcar || !csrfPromise || !document.cookie.includes('XSRF-TOKEN=')) {
    csrfPromise = axios.get('/sanctum/csrf-cookie', { withCredentials: true }).catch((erro) => {
      csrfPromise = null;
      throw erro;
    });
  }
  return csrfPromise;
}

// Antes de qualquer mutação, garante o cookie CSRF; se ele expirar (419), renova e repete uma vez.
api.interceptors.request.use(async (config) => {
  const metodo = (config.method || 'get').toLowerCase();
  if (!['get', 'head', 'options'].includes(metodo)) {
    await garantirCsrf();
  }
  return config;
});

let aoExpirarSessao = null;
export function definirAoExpirarSessao(callback) {
  aoExpirarSessao = callback;
}

api.interceptors.response.use(
  (resposta) => resposta,
  async (erro) => {
    const status = erro.response?.status;
    const config = erro.config || {};
    if (status === 419 && !config._repetida) {
      config._repetida = true;
      await garantirCsrf(true);
      return api(config);
    }
    if (status === 401 && aoExpirarSessao && !config.url?.includes('/auth/')) {
      aoExpirarSessao();
    }
    return Promise.reject(erro);
  },
);

/** Mensagem amigável a partir de um erro da API. */
export function mensagemErro(erro, padrao = 'Não foi possível concluir a operação. Tente novamente.') {
  if (!erro) return null;
  if (typeof erro === 'string') return erro;
  const resposta = erro.response;
  if (!resposta) {
    return erro.code === 'ECONNABORTED'
      ? 'A operação demorou demais. Verifique sua conexão e tente novamente.'
      : 'Não foi possível conectar ao servidor. Verifique sua conexão.';
  }
  const erros = resposta.data?.errors;
  if (erros && typeof erros === 'object') {
    const primeira = Object.values(erros).flat()[0];
    if (primeira) return primeira;
  }
  if (resposta.status === 403) return resposta.data?.message || 'Você não tem permissão para realizar esta ação.';
  if (resposta.status === 404) return 'Registro não encontrado ou não disponível para você.';
  if (resposta.status === 413) return 'Os arquivos enviados excedem o tamanho permitido.';
  if (resposta.status >= 500) {
    const msg = resposta.data?.message;
    return msg && msg !== 'Server Error' ? msg : 'Erro inesperado no servidor. Tente novamente em instantes.';
  }
  return resposta.data?.message || padrao;
}

/** Erros de validação por campo ({ campo: 'mensagem' }). */
export function errosDeCampo(erro) {
  const erros = erro?.response?.data?.errors || {};
  return Object.fromEntries(Object.entries(erros).map(([campo, msgs]) => [campo, Array.isArray(msgs) ? msgs[0] : msgs]));
}

/** Baixa um arquivo protegido via API (revalida a permissão no servidor). */
export async function baixarArquivo(url, nomePadrao = 'anexo') {
  const resposta = await api.get(url, { responseType: 'blob' });
  const disposicao = resposta.headers['content-disposition'] || '';
  const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(disposicao);
  const simples = /filename="?([^";]+)"?/i.exec(disposicao);
  const nome = utf8 ? decodeURIComponent(utf8[1]) : simples ? simples[1] : nomePadrao;

  const link = document.createElement('a');
  const objeto = URL.createObjectURL(resposta.data);
  link.href = objeto;
  link.download = nome;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(objeto), 1000);
}
