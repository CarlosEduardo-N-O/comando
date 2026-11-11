import { useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { mensagemErro } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Alerta, Botao } from '../components/ui';

export default function Login() {
  const { usuario, entrar, sessaoExpirada } = useAuth();
  const navegar = useNavigate();
  const local = useLocation();
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');
  const [erro, setErro] = useState(null);
  const [enviando, setEnviando] = useState(false);

  if (usuario) return <Navigate to={local.state?.de || '/'} replace />;

  const enviar = async (e) => {
    e.preventDefault();
    if (enviando) return;
    setEnviando(true);
    setErro(null);
    try {
      await entrar(email.trim(), senha);
      navegar(local.state?.de || '/', { replace: true });
    } catch (falha) {
      setErro(mensagemErro(falha, 'Não foi possível entrar.'));
      setSenha('');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <main className="login">
      <section className="login-cartao" aria-labelledby="titulo-login">
        <div className="login-marca">
          <img src="/favicon.svg" alt="" width="44" height="44" />
          <div>
            <h1 id="titulo-login">Comando</h1>
            <p>Gestão de demandas corporativas</p>
          </div>
        </div>

        {sessaoExpirada && !erro && <Alerta tipo="aviso" erro="Sua sessão expirou. Entre novamente." />}
        <Alerta erro={erro} />

        <form onSubmit={enviar} noValidate>
          <div className="campo">
            <label htmlFor="email">E-mail</label>
            <input id="email" type="email" autoComplete="username" required value={email} onChange={(e) => setEmail(e.target.value)} autoFocus />
          </div>
          <div className="campo">
            <label htmlFor="senha">Senha</label>
            <input id="senha" type="password" autoComplete="current-password" required value={senha} onChange={(e) => setSenha(e.target.value)} />
          </div>
          <Botao type="submit" className="largura-total" carregando={enviando} disabled={!email || !senha}>
            Entrar
          </Botao>
        </form>
        <p className="login-rodape">Não há cadastro público. Solicite seu acesso ao administrador do sistema.</p>
      </section>
    </main>
  );
}
