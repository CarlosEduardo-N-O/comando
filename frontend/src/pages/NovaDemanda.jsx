import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, errosDeCampo, mensagemErro } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { useOpcoes } from '../hooks/useApi';
import SeletorArquivos, { validarArquivos } from '../components/SeletorArquivos';
import { Alerta, Botao, CabecalhoPagina, Campo } from '../components/ui';
import { PRIORIDADES, hoje } from '../utils/formato';

const INICIAL = { titulo: '', descricao: '', categoria_id: '', setor_destino_id: '', responsavel_id: '', prioridade: 'normal', prazo_desejado: '' };

export default function NovaDemanda() {
  const { usuario } = useAuth();
  const navegar = useNavigate();
  const { itens: setores } = useOpcoes('setores');
  const { itens: categorias } = useOpcoes('categorias');
  const [form, setForm] = useState(INICIAL);
  const { itens: usuariosDestino, carregando: carregandoUsuarios } = useOpcoes(form.setor_destino_id ? 'usuarios' : null, { setor_id: form.setor_destino_id });
  const [arquivos, setArquivos] = useState([]);
  const [erros, setErros] = useState({});
  const [erro, setErro] = useState(null);
  const [enviando, setEnviando] = useState(false);

  const alterar = (campo) => (e) => {
    const valor = e.target.value;
    setForm((f) => ({ ...f, [campo]: valor, ...(campo === 'setor_destino_id' ? { responsavel_id: '' } : {}) }));
    setErros((x) => ({ ...x, [campo]: undefined }));
  };

  // O solicitante não pode ser o responsável pela própria demanda.
  const responsaveis = form.setor_destino_id ? usuariosDestino.filter((u) => u.id !== usuario?.id) : [];

  const enviar = async (e) => {
    e.preventDefault();
    if (enviando) return;
    const erroArquivos = validarArquivos(arquivos);
    if (erroArquivos) {
      setErros((x) => ({ ...x, anexos: erroArquivos }));
      return;
    }

    const dados = new FormData();
    Object.entries(form).forEach(([k, v]) => v !== '' && dados.append(k, v));
    arquivos.forEach((arquivo) => dados.append('anexos[]', arquivo));

    setEnviando(true);
    setErro(null);
    try {
      const { data } = await api.post('/demandas', dados);
      navegar(`/demandas/${data.data.id}`, { replace: true, state: { mensagem: `Demanda ${data.data.protocolo} aberta com sucesso.` } });
    } catch (falha) {
      const porCampo = errosDeCampo(falha);
      const anexo = Object.entries(porCampo).find(([k]) => k.startsWith('anexos'));
      setErros({ ...porCampo, ...(anexo ? { anexos: anexo[1] } : {}) });
      setErro(mensagemErro(falha));
      setEnviando(false);
    }
  };

  return (
    <>
      <CabecalhoPagina titulo="Nova demanda" descricao="Direcione a demanda a um setor ou diretamente a um colaborador do setor escolhido." />

      <form className="cartao formulario" onSubmit={enviar} noValidate>
        <Alerta erro={erro} />

        <Campo rotulo="Título" erro={erros.titulo} obrigatorio>
          {(p) => <input {...p} value={form.titulo} onChange={alterar('titulo')} maxLength={200} required />}
        </Campo>

        <Campo rotulo="Descrição" erro={erros.descricao} obrigatorio ajuda="Detalhe o que precisa. Após a abertura, complementos são feitos por comentários.">
          {(p) => <textarea {...p} rows={6} value={form.descricao} onChange={alterar('descricao')} maxLength={20000} required />}
        </Campo>

        <div className="grade-2">
          <Campo rotulo="Setor de destino" erro={erros.setor_destino_id} obrigatorio>
            {(p) => (
              <select {...p} value={form.setor_destino_id} onChange={alterar('setor_destino_id')} required>
                <option value="">Selecione...</option>
                {setores.map((s) => <option key={s.id} value={s.id}>{s.nome}{s.id === usuario?.setor_id ? ' (meu setor)' : ''}</option>)}
              </select>
            )}
          </Campo>

          <Campo rotulo="Responsável" erro={erros.responsavel_id} ajuda="Opcional. Sem responsável, a demanda vai para a fila do setor.">
            {(p) => (
              <select {...p} value={form.responsavel_id} onChange={alterar('responsavel_id')} disabled={!form.setor_destino_id || carregandoUsuarios}>
                <option value="">Deixar para o setor</option>
                {responsaveis.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
              </select>
            )}
          </Campo>

          <Campo rotulo="Categoria" erro={erros.categoria_id} obrigatorio>
            {(p) => (
              <select {...p} value={form.categoria_id} onChange={alterar('categoria_id')} required>
                <option value="">Selecione...</option>
                {categorias.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
              </select>
            )}
          </Campo>

          <Campo rotulo="Prioridade" erro={erros.prioridade} obrigatorio>
            {(p) => (
              <select {...p} value={form.prioridade} onChange={alterar('prioridade')}>
                {Object.entries(PRIORIDADES).map(([k, { rotulo }]) => <option key={k} value={k}>{rotulo}</option>)}
              </select>
            )}
          </Campo>

          <Campo rotulo="Prazo desejado" erro={erros.prazo_desejado} ajuda="Opcional. O prazo de atendimento é definido por quem atende.">
            {(p) => <input {...p} type="date" min={hoje()} value={form.prazo_desejado} onChange={alterar('prazo_desejado')} />}
          </Campo>
        </div>

        <SeletorArquivos arquivos={arquivos} aoMudar={(lista) => { setArquivos(lista); setErros((x) => ({ ...x, anexos: validarArquivos(lista) || undefined })); }} erro={erros.anexos} desabilitado={enviando} />

        <div className="formulario-acoes">
          <button type="button" className="botao secundario" onClick={() => navegar(-1)} disabled={enviando}>Cancelar</button>
          <Botao type="submit" carregando={enviando}>Abrir demanda</Botao>
        </div>
      </form>
    </>
  );
}
