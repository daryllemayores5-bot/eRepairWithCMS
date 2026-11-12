export function vercelConfig(backendOrigin, { staticBuild = false } = {}) {
  let url
  try { url = new URL(backendOrigin) } catch { throw new Error('Supply the Render HTTPS origin only.') }
  if (url.protocol !== 'https:' || !/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.onrender\.com$/.test(url.hostname)
    || url.username || url.password || url.port || url.pathname !== '/' || url.search || url.hash) {
    throw new Error('Supply a public onrender.com HTTPS origin without credentials, path, port or query.')
  }
  const prefixes = ['api', 'sanctum', 'cp', 'vendor', 'storage', 'content', 'img', 'up']
  return {
    $schema: 'https://openapi.vercel.sh/vercel.json',
    framework: null,
    installCommand: staticBuild ? '' : 'npm --prefix frontend ci',
    buildCommand: staticBuild ? '' : 'npm --prefix frontend run build:production',
    outputDirectory: 'frontend/dist',
    headers: [
      ...prefixes.map(prefix => ({ source: `/${prefix}/:path*`, headers: [
        { key: 'Cache-Control', value: 'private, no-store' },
        { key: 'CDN-Cache-Control', value: 'no-store' },
        { key: 'Vercel-CDN-Cache-Control', value: 'no-store' },
      ] })),
      { source: '/sw.js', headers: [{ key: 'Cache-Control', value: 'no-cache' }] },
      { source: '/(.*)', headers: [{ key: 'X-Content-Type-Options', value: 'nosniff' }, { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' }] },
    ],
    rewrites: [
      ...prefixes.map(prefix => ({ source: `/${prefix}/:path*`, destination: `${url.origin}/${prefix}/:path*` })),
      { source: '/((?![.])(?!.*/[.])(?!assets/|sw[.]js$|favicon[.]ico$|backend(?:/|$)|deployment(?:/|$)|scripts(?:/|$)|tests(?:/|$)|docs(?:/|$)).*)', destination: '/index.html' },
    ],
  }
}
