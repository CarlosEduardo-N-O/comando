import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, definirAoExpirarSessao, garantirCsrf } from '../api/client';
import { definirFuso } from '../utils/formato';

const AuthContext = createContext(null);

/**
 * Estado de autenticação mantido apenas em memória. A sessão vive no cookie
 * HttpOnly; ao recarregar a página, /auth/me informa se ela ainda é válida.
 */
export function AuthProvider({ children }) {
  const [usuario, setUsuario] = useState(null);
  const [carregando, setCarregando] = useState(true);
  const [sessaoExpirada, setSessaoExpirada] = useState(false);

  const carregarConfiguracao = useCallback(async () => {
    try {
      const { data } = await api.get('/opcoes/configuracao');
      definirFuso(data.data?.fuso);
    } catch {
      /* mantém o fuso padrão */
    }
  }, []);

  useEffect(() => {
    let ativo = true;
    api
      .get('/auth/me')
      .then(async ({ data }) => {
        await carregarConfiguracao();
        if (ativo) setUsuario(data.data);
      })
      .catch(() => ativo && setUsuario(null))
      .finally(() => ativo && setCarregando(false));
    return () => {
      ativo = false;
    };
  }, [carregarConfiguracao]);

  useEffect(() => {
    definirAoExpirarSessao(() => {
      setUsuario((atual) => {
        if (atual) setSessaoExpirada(true);
        return null;
      });
    });
    return () => definirAoExpirarSessao(null);
  }, []);

  const entrar = useCallback(
    async (email, password) => {
      await garantirCsrf(true);
      const { data } = await api.post('/auth/login', { email, password });
      // O login regenera a sessão e o token CSRF.
      await garantirCsrf(true);
      await carregarConfiguracao();
      setSessaoExpirada(false);
      setUsuario(data.data);
      return data.data;
    },
    [carregarConfiguracao],
  );

  const sair = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } finally {
      setUsuario(null);
    }
  }, []);

  const valor = useMemo(
    () => ({
      usuario,
      carregando,
      sessaoExpirada,
      entrar,
      sair,
      atualizarUsuario: setUsuario,
      isAdmin: usuario?.perfil === 'administrador',
      isGestor: usuario?.perfil === 'gestor',
    }),
    [usuario, carregando, sessaoExpirada, entrar, sair],
  );

  return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  return useContext(AuthContext);
}
