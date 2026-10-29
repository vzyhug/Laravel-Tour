import http from 'node:http';
import https from 'node:https';

const upstream = new URL(process.env.NGROK_UPSTREAM || '');
if (upstream.protocol !== 'https:' || upstream.username || upstream.password
    || upstream.pathname !== '/' || upstream.search || upstream.hash) {
    throw new Error('NGROK_UPSTREAM must be an HTTPS origin without credentials or a path.');
}
const publicOrigin = process.env.PUBLIC_ORIGIN || process.env.RENDER_EXTERNAL_URL;
if (!publicOrigin) throw new Error('Set PUBLIC_ORIGIN or RENDER_EXTERNAL_URL.');
const external = new URL(publicOrigin);
if (!['http:', 'https:'].includes(external.protocol) || external.username || external.password
    || external.pathname !== '/' || external.search || external.hash) {
    throw new Error('The public URL must be an HTTP(S) origin.');
}
if (external.origin === upstream.origin) throw new Error('Proxy and upstream URLs must differ.');
const port = Number(process.env.PORT || 10000);
if (!Number.isInteger(port) || port < 1 || port > 65535) throw new Error('Invalid PORT.');
const agent = new https.Agent({ keepAlive: true });
const hopByHop = ['connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
    'te', 'trailer', 'transfer-encoding', 'upgrade'];
function cleanHeaders(headers) {
    const result = { ...headers };
    const connectionHeaders = String(headers.connection || '').split(',').map(value => value.trim().toLowerCase());
    for (const name of [...hopByHop, ...connectionHeaders]) delete result[name];
    return result;
}
function rewriteLocation(value) {
    try {
        const location = new URL(value, upstream);
        return location.origin === upstream.origin
            ? `${external.origin}${location.pathname}${location.search}${location.hash}` : value;
    } catch {
        return value;
    }
}
const server = http.createServer((req, res) => {
    const headers = cleanHeaders(req.headers);
    headers.host = upstream.host;
    headers['ngrok-skip-browser-warning'] = '1';
    headers['x-forwarded-host'] = external.host;
    headers['x-forwarded-proto'] = external.protocol.slice(0, -1);
    headers['x-forwarded-port'] = external.port || (external.protocol === 'https:' ? '443' : '80');
    headers['x-forwarded-for'] = req.socket.remoteAddress;
    delete headers.forwarded;
    const request = https.request({
        hostname: upstream.hostname,
        port: upstream.port || 443,
        method: req.method,
        path: req.url,
        headers,
        agent,
        timeout: 60000,
    }, response => {
        const responseHeaders = cleanHeaders(response.headers);
        if (responseHeaders.location) responseHeaders.location = rewriteLocation(responseHeaders.location);
        if (responseHeaders['set-cookie']) {
            responseHeaders['set-cookie'] = responseHeaders['set-cookie'].map(cookie => cookie.replace(
                /;\s*Domain=([^;]+)/ig,
                (match, domain) => domain.replace(/^\./, '').toLowerCase() === upstream.hostname.toLowerCase() ? '' : match,
            ));
        }
        res.writeHead(response.statusCode || 502, responseHeaders);
        response.on('error', () => res.destroy());
        response.pipe(res);
    });
    request.on('timeout', () => request.destroy(new Error('Upstream timeout')));
    request.on('error', () => {
        if (res.destroyed) return;
        if (res.headersSent) return res.destroy();
        res.writeHead(502, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store' });
        res.end('<!doctype html><html lang="vi"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VietGo</title><body style="font-family:system-ui;max-width:640px;margin:15vh auto;padding:24px"><h1>Tạm thời không thể kết nối</h1><p>Vui lòng thử lại sau ít phút.</p></body></html>');
    });
    req.on('aborted', () => request.destroy());
    res.on('close', () => { if (!res.writableFinished) request.destroy(); });
    req.pipe(request);
});
server.listen(port, '0.0.0.0', () => console.log(`VietGo proxy listening on port ${port}`));
function shutdown() {
    server.close(() => { agent.destroy(); process.exit(0); });
    setTimeout(() => process.exit(1), 10000).unref();
}
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
