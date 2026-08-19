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

This sample is a **full public page your module owns** ([05](../05-VIEWS-TEMPLATES-ASSETS.md) §1b). **DACore admin pages are different:** render a layout fragment and pass it to `DACore:Page@withMenu!` — [33](../33-DACORE-PAGES-AND-UI.md), [EX-D02](EX-D02-dacore-admin-page.md).

**Public mobile (MUST):** overlay drawer L/R, lock page scroll while open, scrollable item list, contacts + compact search in the drawer unless large search is its own section. [09](../09-DOTAPP-JS-AND-BRIDGE.md) §3.

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
