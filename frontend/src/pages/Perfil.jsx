import { useState } from 'react';
import { api, errosDeCampo, mensagemErro } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Alerta, Botao, CabecalhoPagina, Campo } from '../components/ui';
import { PERFIS } from '../utils/formato';

export default function Perfil() {
  const { usuario, atualizarUsuario } = useAuth();
  const [nome, setNome] = useState(usuario?.name || '');
  const [salvandoNome, setSalvandoNome] = useState(false);
  const [msgNome, setMsgNome] = useState(null);
  const [erroNome, setErroNome] = useState(null);

  const [senha, setSenha] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [salvandoSenha, setSalvandoSenha] = useState(false);
  const [msgSenha, setMsgSenha] = useState(null);
  const [erroSenha, setErroSenha] = useState(null);

  const salvarNome = async (e) => {
    e.preventDefault();
    if (salvandoNome) return;
    setSalvandoNome(true);
    setMsgNome(null);
    setErroNome(null);
    try {
      const { data } = await api.patch('/auth/me', { name: nome.trim() });
      atualizarUsuario(data.data);
      setMsgNome('Nome atualizado.');
    } catch (falha) {
      setErroNome(falha);
    } finally {
      setSalvandoNome(false);
    }
  };

  const salvarSenha = async (e) => {
    e.preventDefault();
    if (salvandoSenha) return;
    if (senha.password !== senha.password_confirmation) {
      setErroSenha('A confirmação não confere com a nova senha.');
      return;
    }
    setSalvandoSenha(true);
    setMsgSenha(null);
    setErroSenha(null);
    try {
      const { data } = await api.put('/auth/password', senha);
      setSenha({ current_password: '', password: '', password_confirmation: '' });
      setMsgSenha(data.message || 'Senha alterada.');
    } catch (falha) {
      setErroSenha(falha);
    } finally {
      setSalvandoSenha(false);
    }
  };

  const errosSenha = typeof erroSenha === 'object' ? errosDeCampo(erroSenha) : {};

  return (
    <>
      <CabecalhoPagina titulo="Meu perfil" descricao="Setor, perfil e situação são definidos pelo administrador." />

      <div className="grade-2 alinhada-topo">
        <section className="cartao" aria-labelledby="titulo-dados">
          <h2 id="titulo-dados">Dados pessoais</h2>
          <dl className="dados-perfil">
            <dt>E-mail</dt><dd>{usuario?.email}</dd>
            <dt>Setor</dt><dd>{usuario?.setor?.nome}</dd>
            <dt>Perfil</dt><dd>{PERFIS[usuario?.perfil]}</dd>
          </dl>
          <form onSubmit={salvarNome}>
            <Alerta tipo="sucesso" erro={msgNome} />
            {erroNome && <Alerta erro={mensagemErro(erroNome)} />}
            <Campo rotulo="Nome" obrigatorio erro={errosDeCampo(erroNome).name}>
              {(p) => <input {...p} value={nome} onChange={(e) => setNome(e.target.value)} maxLength={255} required autoComplete="name" />}
            </Campo>
            <Botao type="submit" carregando={salvandoNome} disabled={!nome.trim() || nome.trim() === usuario?.name}>Salvar nome</Botao>
          </form>
        </section>

        <section className="cartao" aria-labelledby="titulo-senha">
          <h2 id="titulo-senha">Alterar senha</h2>
          <p className="suave">Ao alterar a senha, as sessões em outros dispositivos são encerradas.</p>
          <form onSubmit={salvarSenha}>
            <Alerta tipo="sucesso" erro={msgSenha} />
            {erroSenha && <Alerta erro={mensagemErro(erroSenha)} />}
            <Campo rotulo="Senha atual" obrigatorio erro={errosSenha.current_password}>
              {(p) => <input {...p} type="password" autoComplete="current-password" value={senha.current_password} onChange={(e) => setSenha((s) => ({ ...s, current_password: e.target.value }))} required />}
            </Campo>
            <Campo rotulo="Nova senha" obrigatorio erro={errosSenha.password} ajuda="Mínimo de 10 caracteres, com letras e números.">
              {(p) => <input {...p} type="password" autoComplete="new-password" value={senha.password} onChange={(e) => setSenha((s) => ({ ...s, password: e.target.value }))} minLength={10} required />}
            </Campo>
            <Campo rotulo="Confirmar nova senha" obrigatorio>
              {(p) => <input {...p} type="password" autoComplete="new-password" value={senha.password_confirmation} onChange={(e) => setSenha((s) => ({ ...s, password_confirmation: e.target.value }))} required />}
            </Campo>
            <Botao type="submit" carregando={salvandoSenha} disabled={!senha.current_password || !senha.password}>Alterar senha</Botao>
          </form>
        </section>
      </div>
    </>
  );
}
