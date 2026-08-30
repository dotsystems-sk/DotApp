# EX-05 — Renderer page (view + layout + assets)

## Controller

```php
return \Dotsystems\App\Parts\Renderer::new()
    ->module('Shop')
    ->setView('home')
    ->setLayout('content/welcome')
    ->setViewVar('title', 'Shop')
    ->setViewVar('items', $items)
    ->renderView();
```

`renderView()` uses **view vars only**. For layout-only render use `setLayoutVar` + `renderLayout()`.

**Sandbox:** do not put callable names or nested values (`time`, `copy`, `count`, `key`, `header`, …) into a Renderer var bag; one match silently drops the whole bag. Prefix such keys. [05](../05-VIEWS-TEMPLATES-ASSETS.md) §5.

**HTML via Renderer (MUST):** when markup can be a template, it **MUST** be. **MUST NOT** concatenate tables/grids/empty states in the controller. Canonical: [00](../00-AGENT-CONTRACT.md) §2j, [05](../05-VIEWS-TEMPLATES-ASSETS.md) §1c.

This sample is a **full public page your module owns**. **VIEW = outer file:** `setLayout` + `renderView()` inserts the layout at `{{ content }}`. Other Renderer paths: `renderLayout()` alone, or generate HTML and inject with `setViewVar` / `str_replace`. Full: [05](../05-VIEWS-TEMPLATES-ASSETS.md) §1b.

**Mobile (MUST):** public chrome needs an overlay drawer (left or right), locked page scroll while open, a scrollable item list, and contacts + compact search in that drawer unless large search is its own mobile section. [09](../09-DOTAPP-JS-AND-BRIDGE.md) §3.

## views/home.view.php

```html
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="utf-8" />
  <title>{{ var: $title }}</title>
  <link rel="stylesheet" href="/assets/modules/Shop/css/page.css" />
</head>
<body>
  {{ layout:partials/header }}
  <main>{{ content }}</main>
  <script src="/assets/dotapp/dotapp.js"></script>
  <script src="/assets/modules/Shop/js/page.js"></script>
</body>
</html>
```

## views/layouts/content/welcome.layout.php

```html
<h1>{{_ "Welcome" }}</h1>
{{ foreach $items as $item }}
  <p>{{ var: $item['title'] }}</p>
{{ /foreach }}
```

Wrong: `{{ $title }}`, `{{ endif }}`, Blade `@extends`.
