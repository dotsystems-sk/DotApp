<?php
/**
 * control-check.php — deterministic source check of one module's controls.
 *
 * Purpose: catch, without a browser and without model judgement, the faults
 * described in AIRULES/26-CONTROL-SLICE.md: dead buttons, JS URLs with no
 * route, silent requests, raw DOM instead of $dotapp, a non-canonical boot
 * block, product failures sent as HTTP 4xx/5xx, and spacing classes the
 * DACore shell does not define.
 *
 * Usage (from the project root):  php AIRULES/tools/control-check.php <Module>
 * Exit code 0 = no findings, 1 = findings, 2 = bad call.
 * Read-only. PHP 7.4+. It never writes into the module.
 */

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$module = isset($argv[1]) ? preg_replace('/[^A-Za-z0-9_]/', '', (string) $argv[1]) : '';
$root = getcwd();
$dir = $root . '/app/modules/' . $module;
if ($module === '' || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php AIRULES/tools/control-check.php <Module>   (run from the project root)\n");
    exit(2);
}

/** Collects files below $base whose name matches $pattern. */
function cc_files(string $base, string $pattern): array
{
    $out = [];
    if (!is_dir($base)) {
        return $out;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        if (preg_match($pattern, $path) && strpos($path, '/vendor/') === false && strpos($path, '.min.') === false) {
            $out[] = $path;
        }
    }
    sort($out);
    return $out;
}

/** Returns the 1-based line number of byte offset $pos in $text. */
function cc_line(string $text, int $pos): int
{
    return substr_count($text, "\n", 0, $pos) + 1;
}

/** Blanks out comments so commented-out code is never reported. Keeps line numbers. */
function cc_strip_comments(string $code): string
{
    $code = preg_replace_callback('~/\*.*?\*/~s', function ($m) {
        return preg_replace('/[^\n]/', ' ', $m[0]);
    }, $code);
    return preg_replace_callback('~(^|[^:"\'\\\\])//[^\n]*~m', function ($m) {
        return $m[1] . str_repeat(' ', strlen($m[0]) - strlen($m[1]));
    }, $code);
}

$findings = [];
$add = function (string $rule, string $file, int $line, string $text) use (&$findings, $root) {
    $rel = ltrim(str_replace(str_replace('\\', '/', $root), '', $file), '/');
    $findings[$rule][] = $rel . ':' . $line . '  ' . $text;
};

$jsFiles = cc_files($dir . '/assets', '~\.js$~');
$viewFiles = cc_files($dir . '/views', '~\.php$~');
$phpFiles = array_merge(cc_files($dir . '/Controllers', '~\.php$~'), cc_files($dir . '/Middleware', '~\.php$~'), cc_files($dir . '/Libraries', '~\.php$~'));
$cssFiles = cc_files($dir . '/assets', '~\.css$~');

$js = [];
foreach ($jsFiles as $f) {
    $js[$f] = cc_strip_comments((string) file_get_contents($f));
}
$views = [];
foreach ($viewFiles as $f) {
    $views[$f] = (string) file_get_contents($f);
}
$allJs = implode("\n", $js);
$allViews = implode("\n", $views);
$init = is_file($dir . '/module.init.php') ? (string) file_get_contents($dir . '/module.init.php') : '';
$listeners = is_file($dir . '/module.listeners.php') ? (string) file_get_contents($dir . '/module.listeners.php') : '';
$routesText = $init . "\n" . $listeners;
$moduleCss = '';
foreach ($cssFiles as $f) {
    $moduleCss .= (string) file_get_contents($f) . "\n";
}
$shellCss = '';
foreach (['/app/modules/DACore/assets/css/core.css', '/app/modules/DACore/assets/dotgrid/dotgrid.css'] as $rel) {
    if (is_file($root . $rel)) {
        $shellCss .= (string) file_get_contents($root . $rel) . "\n";
    }
}

// ---------------------------------------------------------------- JS-STYLE
$rawDom = [
    'document\.getElementById\s*\(' => 'use $dotapp("#id")',
    'document\.querySelector(All)?\s*\(' => 'use $dotapp(selector) / $dotapp(el).find(selector)',
    'document\.getElementsBy\w+\s*\(' => 'use $dotapp(selector)',
    '\.innerHTML\s*=' => 'use $dotapp(el).html(markup)',
    '\.getAttribute\s*\(' => 'use $dotapp(el).attr(name)',
    '\.setAttribute\s*\(' => 'use $dotapp(el).attr(name, value)',
    '\.classList\.(add|remove|toggle|contains)\s*\(' => 'use addClass / removeClass / toggleClass / hasClass',
    '\bfetch\s*\(' => 'use $dotapp().load() (CRC + CSRF live there)',
    'XMLHttpRequest|\$\.ajax\s*\(|\$\.post\s*\(|\$\.get\s*\(' => 'use $dotapp().load()',
    '(^|[^.\w])(alert|confirm|prompt)\s*\(' => 'use the toast / graphical confirm',
    'location\.reload\s*\(' => 'patch the DOM from reply.html',
];
foreach ($js as $file => $code) {
    foreach ($rawDom as $rx => $hint) {
        if (preg_match_all('~' . $rx . '~m', $code, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $hit) {
                $add('JS-STYLE raw DOM / transport instead of $dotapp', $file, cc_line($code, $hit[1]), trim($hit[0]) . '  -> ' . $hint);
            }
        }
    }
    // Why: the only allowed addEventListener in page code is the boot wait on window.
    if (preg_match_all('~([\w.$\]\)]+)\.addEventListener\s*\(\s*(["\'])([^"\']+)\2~', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $target = $hit[1][0];
            $event = $hit[3][0];
            $isBoot = ($target === 'window' && ($event === 'dotapp' || $event === 'dotapp-register'));
            if (!$isBoot) {
                $add('JS-STYLE raw DOM / transport instead of $dotapp', $file, cc_line($code, $hit[0][1]), $target . '.addEventListener("' . $event . '")  -> use $dotapp(el).on() / $dotapp().live()');
            }
        }
    }

    // ------------------------------------------------------------ JS-BOOT
    if (strpos($code, '$dotapp') !== false) {
        $bootRx = '~if\s*\(\s*window\.\$dotapp\s*\)\s*\{\s*runMe\(\s*window\.\$dotapp\s*\);\s*\}\s*else\s*\{\s*window\.addEventListener\(\s*["\']dotapp(-register)?["\']\s*,\s*function\s*\(\s*\)\s*\{\s*runMe\(\s*window\.\$dotapp\s*\);\s*\}\s*,\s*\{\s*once\s*:\s*true\s*\}\s*\);\s*\}~';
        if (!preg_match($bootRx, $code)) {
            $add('JS-BOOT boot block is not the canonical form (26 §3)', $file, 1, 'expected: if (window.$dotapp) { runMe(window.$dotapp); } else { window.addEventListener("dotapp", function () { runMe(window.$dotapp); }, { once: true }); }');
        }
    }

    // ---------------------------------------------------------- JS-SILENT
    // Why: .after() does not run on HTTP 4xx/5xx or a network fault; only .onError() does.
    if (preg_match_all('~\.form\s*\(~', $code, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $chain = substr($code, $hit[1], 6000);
            $nextForm = strpos($chain, '.form(', 6);
            if ($nextForm !== false) {
                $chain = substr($chain, 0, $nextForm);
            }
            if (strpos($chain, '.after(') !== false && strpos($chain, '.onError(') === false) {
                $add('JS-SILENT request with no failure path', $file, cc_line($code, $hit[1]), '.form() chain has .after() but no .onError() -> a failed request leaves the form blocked and shows nothing');
            }
        }
    }
    // Why: load() with no 5th argument swallows every HTTP and network failure.
    if (preg_match_all('~\.load\s*\(~', $code, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $pos = $hit[1] + strlen($hit[0]);
            $depth = 1;
            $args = 1;
            $len = strlen($code);
            $inStr = '';
            for ($i = $pos; $i < $len && $depth > 0; $i++) {
                $ch = $code[$i];
                if ($inStr !== '') {
                    if ($ch === '\\') {
                        $i++;
                    } elseif ($ch === $inStr) {
                        $inStr = '';
                    }
                    continue;
                }
                if ($ch === '"' || $ch === "'" || $ch === '`') {
                    $inStr = $ch;
                } elseif ($ch === '(' || $ch === '{' || $ch === '[') {
                    $depth++;
                } elseif ($ch === ')' || $ch === '}' || $ch === ']') {
                    $depth--;
                } elseif ($ch === ',' && $depth === 1) {
                    $args++;
                }
            }
            $call = substr($code, $pos, max(0, $i - $pos - 1));
            $functions = preg_match_all('~\bfunction\b|=>~', $call);
            if ($args < 5 && $functions < 2) {
                $add('JS-SILENT request with no failure path', $file, cc_line($code, $hit[1]), '.load() has no error callback (5th argument) -> HTTP/network failure shows nothing');
            }
        }
    }
}

// ------------------------------------------------------- JS-DEAD-SELECTOR
// Why: a listener whose selector is in no view is a handler for markup that does not exist.
$markupText = $allViews . "\n" . $allJs;
foreach ($js as $file => $code) {
    if (preg_match_all('~\.(?:live|on|one)\s*\(\s*(["\'])[\w\s]+\1\s*,\s*(["\'])([^"\']+)\2~', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            foreach (preg_split('~\s*,\s*~', $hit[3][0]) as $selector) {
                if (!preg_match('~^[.#]([\w-]+)$~', trim($selector), $s)) {
                    continue;
                }
                $token = $s[1];
                $inViews = preg_match('~(class|id)\s*=\s*["\'][^"\']*\b' . preg_quote($token, '~') . '\b~', $allViews);
                $builtInJs = substr_count($allJs, $token) > 1;
                if (!$inViews && !$builtInJs) {
                    $add('JS-DEAD-SELECTOR listener for markup that is in no view', $file, cc_line($code, $hit[0][1]), trim($selector));
                }
            }
        }
    }
    if (preg_match_all('~\.form\s*\(\s*(["\'])#([\w-]+)\1~', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            if (!preg_match('~id\s*=\s*["\']' . preg_quote($hit[2][0], '~') . '["\']~', $allViews)) {
                $add('JS-DEAD-SELECTOR listener for markup that is in no view', $file, cc_line($code, $hit[0][1]), '.form("#' . $hit[2][0] . '") — no element with this id');
            }
        }
    }
}

// ------------------------------------------------------------ DEAD-BUTTON
// Why: a button that is neither a submit inside <fo-rm> nor referenced from JS does nothing on click.
foreach ($views as $file => $html) {
    if (!preg_match_all('~<button\b([^>]*)>~i', $html, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        continue;
    }
    foreach ($m as $hit) {
        $attrs = $hit[1][0];
        $pos = $hit[0][1];
        $isSubmit = (bool) preg_match('~type\s*=\s*["\']submit["\']~i', $attrs) || !preg_match('~\btype\s*=~i', $attrs);
        if ($isSubmit) {
            $before = substr($html, 0, $pos);
            $open = max((int) strripos($before, '<fo-rm'), (int) strripos($before, '<form'));
            $close = max((int) strripos($before, '</fo-rm'), (int) strripos($before, '</form'));
            if ($open > 0 && $open > $close) {
                continue;
            }
            $add('DEAD-BUTTON control with no listener', $file, cc_line($html, $pos), 'submit button outside <fo-rm>: ' . trim(substr($hit[0][0], 0, 110)));
            continue;
        }
        $tokens = [];
        if (preg_match('~\bclass\s*=\s*["\']([^"\']*)["\']~i', $attrs, $c)) {
            foreach (preg_split('~\s+~', trim($c[1])) as $cls) {
                if ($cls !== '' && strpos($cls, 'btn') !== 0 && strpos($cls, '{') === false && !preg_match('~^(d|m[tbxyse]?|p[tbxyse]?|gap|w|h|text|flex|align|justify|ms|me|float|waves)-~', $cls)) {
                    $tokens[] = $cls;
                }
            }
        }
        if (preg_match('~\bid\s*=\s*["\']([\w-]+)["\']~i', $attrs, $c)) {
            $tokens[] = $c[1];
        }
        if (preg_match_all('~\b(data-[\w-]+)\s*=~i', $attrs, $dataAttrs)) {
            foreach ($dataAttrs[1] as $dataName) {
                $tokens[] = $dataName;
            }
        }
        if (preg_match('~\b(dotbridge|data-bs-dismiss|data-dismiss|onclick)\b~i', $attrs)) {
            continue;
        }
        $wired = false;
        foreach ($tokens as $token) {
            if (strpos($allJs, $token) !== false) {
                $wired = true;
                break;
            }
        }
        if (!$wired) {
            $add('DEAD-BUTTON control with no listener', $file, cc_line($html, $pos), trim(substr($hit[0][0], 0, 120)));
        }
    }
}

// --------------------------------------------------------------- NO-ROUTE
// Why: a URL the browser calls must end in a path that module.init.php registers.
$urlHits = [];
foreach ($js as $file => $code) {
    if (preg_match_all('~(["\'])(/api/v1/(?:auth|noauth)/' . preg_quote($module, '~') . '/[\w/.-]+)\1~', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $urlHits[] = [$file, cc_line($code, $hit[0][1]), $hit[2][0]];
        }
    }
}
foreach ($views as $file => $html) {
    if (preg_match_all('~(?:action|data-[\w-]*url)\s*=\s*(["\'])(/api/v1/(?:auth|noauth)/' . preg_quote($module, '~') . '/[\w/.-]+)\1~i', $html, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $urlHits[] = [$file, cc_line($html, $hit[0][1]), $hit[2][0]];
        }
    }
}
foreach ($urlHits as $hit) {
    $tail = preg_replace('~^/api/v1/(auth|noauth)/' . preg_quote($module, '~') . '~', '', $hit[2]);
    if ($tail === '' || strpos($routesText, $hit[2]) !== false) {
        continue;
    }
    if (strpos($routesText, "'" . $tail . "'") === false && strpos($routesText, '"' . $tail . '"') === false && strpos($routesText, $tail) === false) {
        $add('NO-ROUTE URL used by the browser is not registered in module.init.php', $hit[0], $hit[1], $hit[2]);
    }
}

// ------------------------------------------------------------- PHP-STATUS
// Why: HTTP 4xx/5xx skips .after()/the load success callback, so reply.message never reaches the toast.
foreach ($phpFiles as $file) {
    $code = (string) file_get_contents($file);
    if (preg_match_all('~ajaxReply\s*\((?:[^;]*?),\s*([45]\d\d)\s*\)\s*;~s', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $add('PHP-STATUS product outcome sent as HTTP 4xx/5xx', $file, cc_line($code, $hit[1][1]), 'ajaxReply(..., ' . $hit[1][0] . ')  -> HTTP 200 + status 0 + a specific message');
        }
    }
    if (preg_match_all('~[\'"]code[\'"]\s*=>\s*([45]\d\d)~', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $add('PHP-STATUS product outcome sent as HTTP 4xx/5xx', $file, cc_line($code, $hit[1][1]), "'code' => " . $hit[1][0] . '  -> 200 + status 0 + a specific message');
        }
    }
    if (preg_match_all('~[\'"]message[\'"]\s*=>\s*[\'"](Server error|Bad request|Rejected|Error|Request failed|Something went wrong|Failed)\.?[\'"]~i', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $add('PHP-GENERIC message does not say what failed', $file, cc_line($code, $hit[0][1]), $hit[0][0] . '  -> name the step: "Could not save the item — the name is already used."');
        }
    }
}

// ---------------------------------------------------------- CSS-UNDEFINED
// Why: DACore is not Bootstrap. A spacing class with no CSS rule adds no space, so the button stays glued.
if ($shellCss !== '') {
    $css = $shellCss . "\n" . $moduleCss;
    $seen = [];
    foreach ($views as $file => $html) {
        if (strpos(str_replace('\\', '/', $file), '/admin') === false && stripos($html, 'card') === false) {
            continue;
        }
        if (!preg_match_all('~class\s*=\s*(["\'])([^"\']*)\1~i', $html, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            continue;
        }
        foreach ($m as $hit) {
            foreach (preg_split('~\s+~', trim($hit[2][0])) as $cls) {
                if (!preg_match('~^(card-footer|modal-footer|[pm][tbxyse]?-(?:[a-z]{2,3}-)?\d+|gap-\d+|border-0|bg-transparent)$~', $cls)) {
                    continue;
                }
                // Why: the pager bar keeps its audited pair; .dacore-list-pager carries the padding (AIRULES/40).
                if ($cls === 'card-footer' && strpos($hit[2][0], 'dacore-list-pager') !== false) {
                    continue;
                }
                if (isset($seen[$file . '|' . $cls])) {
                    continue;
                }
                $seen[$file . '|' . $cls] = true;
                if (!preg_match('~\.' . preg_quote($cls, '~') . '(?![\w-])~', $css)) {
                    $add('CSS-UNDEFINED spacing class has no rule in DACore core.css or this module', $file, cc_line($html, $hit[0][1]), '.' . $cls . '  -> use .dacore-card-actions, or define the rule in this module CSS');
                }
            }
        }
    }
}

// ------------------------------------------------------------ GLUED-SUBMIT
// Why: Save written straight into the form body has no action row, so it has no spacing of its own.
foreach ($views as $file => $html) {
    if (!preg_match_all('~<button\b[^>]*type\s*=\s*["\']submit["\'][^>]*>~i', $html, $m, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($m[0] as $hit) {
        $before = substr($html, max(0, $hit[1] - 700), min(700, $hit[1]));
        $lastOpen = strripos($before, '<div');
        $wrapper = $lastOpen === false ? '' : substr($before, $lastOpen);
        $closedSince = $lastOpen === false ? true : (stripos($wrapper, '</div') !== false);
        $isRow = (bool) preg_match('~class\s*=\s*["\'][^"\']*(actions|toolbar|footer|buttons)[^"\']*["\']~i', (string) strtok($wrapper, '>'));
        if ($closedSince || !$isRow) {
            $add('GLUED-SUBMIT submit button is not inside an action row', $file, cc_line($html, $hit[1]), 'wrap the buttons: <div class="dacore-card-actions"> … </div> (26 §2)');
        }
    }
}

// ----------------------------------------------------------------- REPORT
if ($findings === []) {
    echo "control-check {$module}: OK — 0 findings\n";
    exit(0);
}
$total = 0;
foreach ($findings as $rule => $rows) {
    $total += count($rows);
    echo "\n## {$rule} (" . count($rows) . ")\n";
    // Why: a wall of 300 lines hides the other rules; --all prints everything.
    $limit = in_array('--all', $argv, true) ? PHP_INT_MAX : 25;
    foreach (array_slice($rows, 0, $limit) as $row) {
        echo '  ' . $row . "\n";
    }
    if (count($rows) > $limit) {
        echo '  … +' . (count($rows) - $limit) . " more (add --all to list them)\n";
    }
}
echo "\ncontrol-check {$module}: {$total} finding(s). Fix each one, run again. Rule: AIRULES/26-CONTROL-SLICE.md\n";
exit(1);
