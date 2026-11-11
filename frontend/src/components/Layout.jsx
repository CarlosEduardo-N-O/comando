import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import {
  Bell, Briefcase, Building2, ClipboardList, FolderKanban, Home, LayoutList, LogOut, Menu, Plus, Tags, UserCircle, Users, X,
} from 'lucide-react';
import { api } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { PERFIS } from '../utils/formato';

const INTERVALO_NOTIFICACOES = 60000;

/** Contador de notificações não lidas por consulta periódica (sem WebSocket). */
function useContadorNotificacoes() {
  const [naoLidas, setNaoLidas] = useState(0);
  const local = useLocation();

  useEffect(() => {
    let vivo = true;
    const consultar = () => {
      if (document.hidden) return;
      api.get('/notificacoes/contador').then(({ data }) => vivo && setNaoLidas(data.data.nao_lidas)).catch(() => {});
    };
    consultar();
    const timer = setInterval(consultar, INTERVALO_NOTIFICACOES);
    const aoAtualizar = () => consultar();
    window.addEventListener('notificacoes:atualizadas', aoAtualizar);
    document.addEventListener('visibilitychange', aoAtualizar);
    return () => {
      vivo = false;
      clearInterval(timer);
      window.removeEventListener('notificacoes:atualizadas', aoAtualizar);
      document.removeEventListener('visibilitychange', aoAtualizar);
    };
  }, [local.pathname]);

  return naoLidas;
}

export default function Layout() {
  const { usuario, sair, isAdmin, isGestor } = useAuth();
  const naoLidas = useContadorNotificacoes();
  const [menuAberto, setMenuAberto] = useState(false);
  const local = useLocation();
  const navegar = useNavigate();

  useEffect(() => setMenuAberto(false), [local.pathname]);

  const encerrar = async () => {
    await sair();
    navegar('/login', { replace: true });
  };

  const itens = [
    { para: '/', rotulo: 'Início', icone: Home, fim: true },
    { para: '/demandas/minhas', rotulo: 'Minhas solicitações', icone: ClipboardList },
    { para: '/demandas/atendimento', rotulo: 'Meu atendimento', icone: Briefcase },
    ...(isGestor ? [{ para: '/demandas/setor', rotulo: 'Demandas do setor', icone: FolderKanban }] : []),
    ...(isAdmin ? [{ para: '/demandas/todas', rotulo: 'Todas as demandas', icone: LayoutList }] : []),
    { para: '/notificacoes', rotulo: 'Notificações', icone: Bell, contador: naoLidas },
  ];
  const administracao = isAdmin
    ? [
        { para: '/admin/usuarios', rotulo: 'Usuários', icone: Users },
        { para: '/admin/setores', rotulo: 'Setores', icone: Building2 },
        { para: '/admin/categorias', rotulo: 'Categorias', icone: Tags },
      ]
    : [];

  const link = ({ para, rotulo, icone: Icone, fim, contador }) => (
    <li key={para}>
      <NavLink to={para} end={fim} className={({ isActive }) => `menu-link ${isActive ? 'ativo' : ''}`}>
        <Icone size={18} aria-hidden="true" />
        <span>{rotulo}</span>
        {contador > 0 && (
          <span className="contador" aria-label={`${contador} não lidas`}>
            {contador > 99 ? '99+' : contador}
          </span>
        )}
      </NavLink>
    </li>
  );

  return (
    <div className="app">
      <a href="#conteudo" className="pular-link">Pular para o conteúdo</a>

      <header className="barra-topo">
        <button type="button" className="botao-icone so-celular" onClick={() => setMenuAberto((v) => !v)} aria-expanded={menuAberto} aria-controls="menu-lateral" aria-label="Abrir menu">
          {menuAberto ? <X size={22} /> : <Menu size={22} />}
        </button>
        <Link to="/" className="marca" aria-label="Comando — início">
          <img src="/favicon.svg" alt="" width="28" height="28" />
          <span>Comando</span>
        </Link>
        <div className="topo-acoes">
          <Link to="/demandas/nova" className="botao primario pequeno">
            <Plus size={16} aria-hidden="true" /> <span className="nao-celular">Nova demanda</span>
          </Link>
          <Link to="/notificacoes" className="botao-icone sino" aria-label={`Notificações${naoLidas ? `: ${naoLidas} não lidas` : ''}`}>
            <Bell size={20} />
            {naoLidas > 0 && <span className="contador flutuante">{naoLidas > 99 ? '99+' : naoLidas}</span>}
          </Link>
          <Link to="/perfil" className="usuario-topo">
            <UserCircle size={22} aria-hidden="true" />
            <span className="nao-celular">
              <strong>{usuario?.name}</strong>
              <small>{PERFIS[usuario?.perfil]} · {usuario?.setor?.nome}</small>
            </span>
          </Link>
        </div>
      </header>

      <div className="corpo">
        <nav id="menu-lateral" className={`menu-lateral ${menuAberto ? 'aberto' : ''}`} aria-label="Menu principal">
          <ul>{itens.map(link)}</ul>
          {administracao.length > 0 && (
            <>
              <p className="menu-titulo">Administração</p>
              <ul>{administracao.map(link)}</ul>
            </>
          )}
          <ul className="menu-rodape">
            {link({ para: '/perfil', rotulo: 'Meu perfil', icone: UserCircle })}
            <li>
              <button type="button" className="menu-link" onClick={encerrar}>
                <LogOut size={18} aria-hidden="true" /> <span>Sair</span>
              </button>
            </li>
          </ul>
        </nav>
        {menuAberto && <div className="menu-fundo" onClick={() => setMenuAberto(false)} aria-hidden="true" />}

        <main id="conteudo" className="conteudo" tabIndex={-1}>
          <Outlet />
        </main>
      </div>
    </div>
  );
}
