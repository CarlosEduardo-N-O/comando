// Swagger UI no mesmo domínio da aplicação. Para testar rotas autenticadas,
// entre primeiro pelo frontend (ou use POST /auth/login aqui): a sessão é
// mantida por cookie HttpOnly. Antes de cada mutação o cookie CSRF é obtido
// e enviado no cabeçalho X-XSRF-TOKEN.
window.addEventListener('load', function () {
  function lerCookie(nome) {
    var item = document.cookie.split('; ').find(function (c) { return c.indexOf(nome + '=') === 0; });
    return item ? decodeURIComponent(item.split('=').slice(1).join('=')) : null;
  }

  window.SwaggerUIBundle({
    url: '/docs/openapi.json',
    dom_id: '#swagger-ui',
    withCredentials: true,
    requestInterceptor: async function (req) {
      req.headers['Accept'] = 'application/json';
      if (req.method && req.method.toUpperCase() !== 'GET') {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
        var token = lerCookie('XSRF-TOKEN');
        if (token) req.headers['X-XSRF-TOKEN'] = token;
      }
      return req;
    },
  });
});
