<?php

namespace App\Domain\GraphQL\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Spec section 10: "Create a developer GraphQL explorer." A small,
 * self-contained (no CDN dependency) query editor — gated off entirely
 * outside local/staging via `graphql.playground_enabled` (spec: "Production
 * can disable introspection", extended here to the explorer itself, since
 * an explorer is useless once introspection is off and shouldn't be
 * reachable in production regardless).
 */
final class GraphQLPlaygroundController extends Controller
{
    public function show(): Response
    {
        if (! config('graphql.playground_enabled')) {
            throw new NotFoundHttpException;
        }

        return response(self::html(), 200, ['Content-Type' => 'text/html']);
    }

    private static function html(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>GraphQL Playground</title>
<style>
  body { margin: 0; font-family: -apple-system, sans-serif; display: flex; flex-direction: column; height: 100vh; }
  header { padding: 0.75rem 1rem; background: #1a1a2e; color: #fff; display: flex; justify-content: space-between; align-items: center; }
  header h1 { font-size: 1rem; margin: 0; }
  header button { background: #4f46e5; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; }
  main { flex: 1; display: grid; grid-template-columns: 1fr 1fr; min-height: 0; }
  textarea, pre { margin: 0; padding: 1rem; border: none; font: 13px/1.5 "SF Mono", Menlo, monospace; resize: none; overflow: auto; }
  textarea { border-right: 1px solid #ddd; }
  pre { background: #f7f7f9; white-space: pre-wrap; }
</style>
</head>
<body>
<header>
  <h1>GraphQL Playground</h1>
  <button id="run">Run (Ctrl/Cmd+Enter)</button>
</header>
<main>
  <textarea id="query" spellcheck="false">query {
  store {
    name
    defaultCurrency
  }
}</textarea>
  <pre id="result">Run a query to see results here.</pre>
</main>
<script>
  const run = async () => {
    const query = document.getElementById('query').value;
    const out = document.getElementById('result');
    out.textContent = 'Loading…';
    try {
      const res = await fetch('/api/graphql', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ query }),
      });
      const body = await res.json();
      out.textContent = JSON.stringify(body, null, 2);
    } catch (e) {
      out.textContent = 'Request failed: ' + e.message;
    }
  };
  document.getElementById('run').addEventListener('click', run);
  document.getElementById('query').addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') run();
  });
</script>
</body>
</html>
HTML;
    }
}
