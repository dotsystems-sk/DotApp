# 48 — Uploader (receiving files from a visitor)

`Dotsystems\App\Parts\Uploader` (`app/parts/Uploader.php`) is the **single safe entry point** for every file a visitor sends. It checks the file by its real bytes, re-encodes images so hidden code does not survive (polyglots), stores the file under a random name in a folder the web server refuses to serve, and returns a plain result array.

**Open this file whenever a task stores an uploaded file** (photo, avatar, attachment, document, import that is kept on disk). The browser side stays `$dotapp().uploadFile` — [09](09-DOTAPP-JS-AND-BRIDGE.md) "File uploads".

---

## 1. Laws

1. **MUST** receive every stored upload with `Uploader::use('<YourModule>')->…->receive()`.
2. **MUST NOT** read `$_FILES`, call `move_uploaded_file()`, or hand-write extension / MIME checks in a module. The hand-written `$request->upload()` checklist in older code is **legacy** — do not copy it into new code. **MUST NOT** call `$request->upload()` and `Uploader` for the same request.
3. **MUST NOT** edit `app/parts/Uploader.php` (core). If the file is missing (older core), **stop and tell the user** — do not write your own uploader.
4. **MUST** allow something explicitly: `images()` and/or `allow($ext, $mimes)`. With nothing allowed `receive()` throws `RuntimeException` — that is a programming error, not a user error.
5. **MUST NOT** try to allow a script / executable extension (`php`, `phtml`, `phar`, `html`, `js`, `svg`, `exe`, `htaccess`, … — full list in the class). `allow()` throws `InvalidArgumentException`; there is no override.
6. **MUST** store `$file['name']` (the random stored name) in the database. `$file['original']` is display text only — **MUST NOT** use it in a path, a URL, or a header.
7. **MUST** turn a stored name that came back from outside (URL, form, DB row you do not fully trust) into a path only through `->path($name)` and delete only through `->delete($name)`. Both accept exactly `^[a-f0-9]{32}\.[a-z0-9]{2,5}$` and return `false` for anything else. **MUST NOT** build `folder . '/' . $name` yourself.
8. **MUST NOT** `crcCheck()` on the upload endpoint (no `{ data, crc }` there). The route **MUST** still sit behind its login / rights gate.
9. **MUST** return HTTP **200** + `status` 0|1 + `message` from the endpoint (`ajaxReply`), so `uploadFile`'s Promise resolves and the page shows the reason.
10. **MUST** delete the stored file when the database write that records it fails, and when the record that owns it is deleted or replaced. No orphan files.

---

## 2. API

```php
use Dotsystems\App\Parts\Uploader;

$result = Uploader::use('Shop')          // instance name = module name; decides the default folder
    ->images()                           // jpg, jpeg, png, gif, webp
    ->maxSize(5 * 1024 * 1024)           // bytes, one file
    ->maxFiles(1)                        // files in one request
    ->maxDimensions(6000, 6000)          // pixels
    ->receive();
```

| Method | Arguments | Default | Meaning |
|---|---|---|---|
| `Uploader::use($name)` | instance name | `"default"` | Same name → same instance (like `Cache::use`). Folder `app/runtime/uploads/{name}` |
| `->images()` | — | nothing allowed | Allows `jpg, jpeg, png, gif, webp` with their image MIME types |
| `->allow($ext, $mimes)` | extension without dot; string or array of MIME types the **bytes** may have | — | One more type, e.g. `->allow('pdf', 'application/pdf')`. Throws for forbidden extensions |
| `->maxSize($bytes)` | int | 2 MB | Size of one file, measured on disk. `upload_max_filesize` / `post_max_size` in php.ini still apply |
| `->maxFiles($count)` | int | 1 | More files in one request → whole request refused (`ERR_COUNT`) |
| `->maxDimensions($w, $h)` | pixels | 8000 × 8000 (and 40 MP total) | Bigger images refused (`ERR_IMAGE`); protects memory |
| `->reencode($bool, $quality = 85)` | bool, 1–100 | `true`, 85 | Decode + encode images again. Keep it on. Off → stored byte for byte, only scanned for script signatures |
| `->field($name)` | form field name | every field | Read one field only. `uploadFile` sends the field `user_files` |
| `->to($relativeFolder)` | path relative to the app root | `/app/runtime/uploads/{name}` | Other folder; `..` is removed; `.htaccess` (deny + PHP off) is created in it |
| `->receive()` | — | — | Checks and stores. Returns the result array (§3) |
| `->path($storedName)` | stored name | — | Absolute path or `false` |
| `->delete($storedName)` | stored name | — | `true` / `false` |
| `->folder()` | — | — | Absolute storage folder |
| `Uploader::isStoredName($name)` | string | — | `true` only for the shape this class creates |

The instance is shared per name **within a request** and keeps its settings. **MUST** configure it in **one private method of the module** (`private static function uploader()`) and call that everywhere, so two endpoints never disagree about what is allowed.

---

## 3. Result of `receive()`

```php
[
  'status' => 1,              // 1 only when at least one file was stored AND none was refused
  'files'  => [[
      'field'     => 'user_files',
      'original'  => 'Holiday 2026.JPG',                 // cleaned text for display, never a path
      'name'      => '9f3c1e07d2a64b58b1c3e5f6078a7b21.jpg',   // store THIS
      'path'      => '/…/app/runtime/uploads/Shop/9f3c….jpg',
      'extension' => 'jpg',                              // jpeg is stored as jpg
      'mime'      => 'image/jpeg',                       // from the bytes
      'size'      => 184320,                             // of the stored file
      'width'     => 1600, 'height' => 1200,             // null for non-images
  ]],
  'errors' => [[ 'original' => 'holiday.php.jpg', 'code' => 3, 'message' => 'This file type is not allowed.' ]],
]
```

| Constant | Code | When |
|---|---|---|
| `Uploader::ERR_UPLOAD` | 1 | PHP reports an incomplete upload / not an uploaded file |
| `Uploader::ERR_SIZE` | 2 | Empty or bigger than `maxSize` |
| `Uploader::ERR_EXTENSION` | 3 | Forbidden extension anywhere in the name (`a.php.jpg`, `a.jpg.php`), no extension, or not on the allow-list |
| `Uploader::ERR_MIME` | 4 | Bytes are not what the extension says (text as `.jpg`, PNG as `.jpg`) |
| `Uploader::ERR_IMAGE` | 5 | Not a decodable image, or over the dimensions |
| `Uploader::ERR_CONTENT` | 6 | Script signature in a file stored without re-encoding |
| `Uploader::ERR_STORE` | 7 | Folder not writable |
| `Uploader::ERR_COUNT` | 8 | More files than `maxFiles` |

`message` is English product copy you may show as is. For another language map `code` to your own sentence. With `maxFiles > 1` a request can be **partly** successful: `status` is 0, `files` holds the stored ones, `errors` the refused ones — handle both.

---

## 4. Canonical endpoint

```php
// module.init.php — behind the login gate; STATIC_ROUTE; no CRC on this route
Router::post("/shop/upload", "Shop:Photos@upload!", Router::STATIC_ROUTE);
```

```php
private static function uploader() {
    return Uploader::use('Shop')->images()->maxSize(5242880)->maxFiles(1)->maxDimensions(6000, 6000);
}

/** CRCchecking — none (file upload). Receives one photo of the signed-in user. */
public static function upload($request) {
    $me = (int) Auth::userId();

    // Why: dotapp.js adds this header to every uploadFile() call; a plain form on a foreign site cannot.
    if (($_SERVER['HTTP_DOTAPP'] ?? '') !== 'load') {
        return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Bad request'], 200);
    }

    $result = self::uploader()->receive();
    if ($result['status'] !== 1) {
        $message = $result['errors'][0]['message'] ?? 'No file arrived. Choose a file and try again.';
        return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => $message], 200);
    }
    $file = $result['files'][0];

    $id = null;
    DB::module('RAW')->q(function ($qb) use ($me, $file) {
        $qb->raw('INSERT INTO shop_photos (user_id, file, width, height, created_at) VALUES (:user, :file, :w, :h, NOW())',
                 ['user' => $me, 'file' => $file['name'], 'w' => $file['width'], 'h' => $file['height']]);
    })->execute(
        function ($result, $db, $execution_data) use (&$id) { $id = $execution_data['insert_id'] ?? $db->inserted_id(); },
        function ($error) { Logger::use('shop')->error('photo insert failed', $error); }
    );
    if ($id === null) {
        self::uploader()->delete($file['name']);      // Why: no file without a row
        return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'The photo could not be saved. Try again.'], 200);
    }

    return DotApp::DotApp()->ajaxReply([
        'status' => 1,
        'message' => 'Photo uploaded.',
        'token' => Crypto::encrypt((string) $id, 'shop.photo'),          // never the raw row id
        'url' => '/shop/photo/' . str_replace('.', '_', $file['name']),
    ], 200);
}
```

Browser (see [09](09-DOTAPP-JS-AND-BRIDGE.md)): `$dotapp().uploadFile(file, "/shop/upload", function (name, percent) { … }).then(function (text) { var reply = $dotapp().parseReply(text); … })`. `<input type="file">` lives **outside** `<fo-rm>`; a form that belongs to the upload carries only the returned tokens in a hidden field.

---

## 5. Serving a stored file

The storage folder is **not** reachable by URL (`403`). A controller hands the file out.

```php
Router::get("/shop/photo/{file}", "Shop:Photos@show!");      // dynamic route
```

```php
public static function show($request) {
    $file = str_replace('_', '.', (string) ($request->matchData()['file'] ?? ''));     // abc_jpg -> abc.jpg
    $path = Uploader::use('Shop')->path($file);
    if ($path === false) { return new Response(404, 'Not found'); }

    $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    if (!isset($types[$extension])) { return new Response(404, 'Not found'); }

    Response::contentType($types[$extension]);
    Response::header('X-Content-Type-Options', 'nosniff');
    Response::cache(604800, true);                    // seconds, public — a stored name never changes its content
    return (string) file_get_contents($path);
}
```

**Traps (verified):**

| Trap | Rule |
|---|---|
| URL ends with `.jpg` / `.png` / `.gif` / `.webp` | The web server treats it as a static file → the request **never reaches the module** (404). **MUST** put the name in the URL without the dot (`abc_jpg`) and restore it in the controller |
| `header('Content-Type: …')` | Overwritten when the framework sends the reply (file leaves as `text/html`). **MUST** use `Response::contentType()`, `Response::header()`, `Response::cache()` |
| Content type from the visitor | **MUST** come from your own extension → type table. **MUST NOT** echo a type from the request |
| Private files | The show action **MUST** check that the signed-in user may see this file (owner / right) before `path()`. A random name is not access control |
| Non-image downloads (`pdf`, `zip`) | Add `Response::header('Content-Disposition', 'attachment; filename="document.pdf"')` with a name **you** build — never `$file['original']` unescaped |

---

## 6. Design rules around the upload

| Situation | Rule |
|---|---|
| Several files for one record (gallery) | One request per file (`maxFiles(1)`), sent **one after another** from JS. Parallel sends pass a per-user limit together |
| File uploaded before its record exists (post with photos) | Row with `owner_id` and `record_id NULL`; reply carries an **encrypted token**; the form sends tokens; attach with `UPDATE … WHERE id = :id AND owner_id = :me AND record_id IS NULL` |
| File that belongs to something that always exists (avatar) | Save it into the record at once; write the new name first, delete the old file after the write succeeded |
| Limit of waiting (unattached) files | **MUST** cap per user before `receive()` (`SELECT COUNT(*) … record_id IS NULL`), otherwise anyone signed in can fill the disk |
| Never-attached files | **SHOULD** have a clean-up (rows with `record_id IS NULL` older than a day → `delete()` + row) |
| Record deleted | Select its file names → `delete()` each → delete the rows, **after** the owner check |
| Showing the image | Store `width` / `height` and print them on `<img>` (no layout jump); `loading="lazy"` in lists |

**What re-encoding changes (tell the user when it matters):** EXIF / GPS / comments are removed (good for privacy); an animated GIF keeps only its first frame; JPEG / WEBP are saved at quality 85 unless `reencode(true, $quality)` says otherwise. If the product needs original bytes (animated GIF, print-quality originals) → **ASK** before `reencode(false)`; the file is then only signature-scanned.

---

## 7. Anti-patterns

| Wrong | Right |
|---|---|
| `move_uploaded_file($_FILES['f']['tmp_name'], 'uploads/' . $_FILES['f']['name'])` | `Uploader::use('Module')->images()->receive()` |
| Checking `$_FILES['f']['type']` or only the last extension | Nothing to check by hand — `receive()` does name, bytes, image, content |
| Storing into `public/` or `assets/` so the URL "just works" | Default folder + a show action (§5) |
| Saving `$file['original']` as the file name / using it in a path | `$file['name']`; `original` is a label |
| `unlink($folder . '/' . $request->matchData()['file'])` | `Uploader::use('Module')->delete($name)` |
| Returning the row id of the uploaded file to the browser | `Crypto::encrypt((string) $id, 'module.purpose')` |
| `crcCheck()` on the upload route / `<input type="file">` inside `<fo-rm>` | No CRC there; login gate + `dotapp: load` header check; file input outside the form |
| HTTP 400 / 500 for a refused file | HTTP 200 + `status: 0` + the `message` from `errors[0]` |
| Second `Uploader::use('Module')->images()->maxSize(…)` chain with other numbers in another method | One `private static function uploader()` per module |
| `->allow('svg', 'image/svg+xml')` / `->allow('html', …)` | Not possible (throws). SVG and HTML can carry script |

---

## 8. Finish checklist (upload feature)

- [ ] Every stored upload goes through `Uploader`; no `$_FILES`, no `move_uploaded_file`, no hand-written MIME check in the module
- [ ] One `uploader()` method holds the allow-list and limits
- [ ] Upload route behind the login / rights gate; **no** `crcCheck()`; `dotapp: load` header checked
- [ ] Reply is HTTP 200 with `status` + `message`; JS shows the message and real progress
- [ ] DB stores `$file['name']`; a failed DB write deletes the file
- [ ] Files are served by an action using `->path()`, `Response::contentType()`, URL without an image extension
- [ ] Private files: owner / right checked before serving
- [ ] Deleting or replacing the owning record deletes the file through `->delete()`
- [ ] Per-user cap on waiting files; tokens (not ids) cross the browser
- [ ] Tested with: a valid image, `x.php.jpg`, a text file renamed to `.jpg`, an oversized file
