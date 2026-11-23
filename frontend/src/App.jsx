import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { useAuth } from './auth/AuthContext';
import Layout from './components/Layout';
import { Carregando, Vazio } from './components/ui';
import Login from './pages/Login';
import Inicio from './pages/Inicio';
import ListaDemandas from './pages/ListaDemandas';
import NovaDemanda from './pages/NovaDemanda';
import DetalheDemanda from './pages/DetalheDemanda';
import Notificacoes from './pages/Notificacoes';
import Perfil from './pages/Perfil';
import AdminUsuarios from './pages/admin/AdminUsuarios';
import AdminCadastroBasico from './pages/admin/AdminCadastroBasico';

function Protegida({ children, perfis }) {
  const { usuario, carregando } = useAuth();
  const local = useLocation();
  if (carregando) return <Carregando texto="Verificando sessão..." />;
  if (!usuario) return <Navigate to="/login" replace state={{ de: local.pathname + local.search }} />;
  // O menu oculta telas sem permissão; o backend também nega o acesso aos dados.
  if (perfis && !perfis.includes(usuario.perfil)) {
    return <Vazio titulo="Acesso não permitido" texto="Seu perfil não tem acesso a esta área." />;
  }
  return children;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<Protegida><Layout /></Protegida>}>
        <Route index element={<Inicio />} />
        <Route path="demandas/minhas" element={<ListaDemandas key="minhas" contexto="minhas" />} />
        <Route path="demandas/atendimento" element={<ListaDemandas key="atendimento" contexto="atendimento" />} />
        <Route path="demandas/setor" element={<Protegida perfis={['gestor']}><ListaDemandas key="setor" contexto="setor" /></Protegida>} />
        <Route path="demandas/todas" element={<Protegida perfis={['administrador']}><ListaDemandas key="todas" contexto="todas" /></Protegida>} />
        <Route path="demandas/nova" element={<NovaDemanda />} />
        <Route path="demandas/:id" element={<DetalheDemanda />} />
        <Route path="notificacoes" element={<Notificacoes />} />
        <Route path="perfil" element={<Perfil />} />
        <Route path="admin/usuarios" element={<Protegida perfis={['administrador']}><AdminUsuarios /></Protegida>} />
        <Route
          path="admin/setores"
          element={<Protegida perfis={['administrador']}><AdminCadastroBasico key="setores" recurso="setores" titulo="Setores" singular="setor" descricao="Cada usuário pertence a um setor principal. Setores inativos não recebem novas demandas." /></Protegida>}
        />
        <Route
          path="admin/categorias"
          element={<Protegida perfis={['administrador']}><AdminCadastroBasico key="categorias" recurso="categorias" titulo="Categorias" singular="categoria" descricao="Categorias globais usadas na abertura de demandas." /></Protegida>}
        />
        <Route path="*" element={<Vazio titulo="Página não encontrada" texto="Verifique o endereço ou use o menu." />} />
      </Route>
    </Routes>
  );
}
