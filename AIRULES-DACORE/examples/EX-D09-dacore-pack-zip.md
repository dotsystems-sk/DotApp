# EX-D09 — Canonical DACore install zip packer

Rules: [35](../35-DACORE-INSTALL.md) §5, [00](../00-AGENT-CONTRACT.md) §2e.

**MUST NOT** invent a new packer for each module. That burns tokens and ships inconsistent zips.

The packer source is stored as text so it cannot run from AIRULES:

`AIRULES/examples/EX-D09-dacore-pack-zip.php.txt`

## Agent workflow (MUST)

Copy the `.txt` to the **project root**, rename to `.php`, run with arguments, delete the copy. Leave the `.txt` in AIRULES untouched.

Windows (PowerShell), from the project root:

```powershell
Copy-Item AIRULES\examples\EX-D09-dacore-pack-zip.php.txt .\dacore-pack-zip.php
php dacore-pack-zip.php ExampleModule 1.2.0
Remove-Item .\dacore-pack-zip.php
```

Linux / macOS:

```bash
cp AIRULES/examples/EX-D09-dacore-pack-zip.php.txt ./dacore-pack-zip.php
php dacore-pack-zip.php ExampleModule 1.2.0
rm ./dacore-pack-zip.php
```

Flags:

```text
php dacore-pack-zip.php ExampleModule 1.2.0
php dacore-pack-zip.php --module=ExampleModule --version=1.2.0
php dacore-pack-zip.php --module=ExampleModule --version=1.2.0 --out=C:\releases
```

`--version` **MUST** equal the highest quoted key in `Installation.php` (`'1.2.0' =>`).

## Production inventory

**Production inventory (MUST — before you run the packer).** A zip is a clean production package that is installed on other servers. It carries what the module needs to run and nothing an agent made along the way.

List every entry in the root of `app/modules/{Module}/` and decide what each one is for. **Open it** — do not judge by the name alone.

| Kind | Examples | In the zip |
|------|----------|------------|
| Runtime | `Controllers/`, `Libraries/`, `Middleware/`, `Models/`, `Api/`, `views/`, `assets/`, `translations/`, `init/`, `about-assets/`, `vendor/`, `Installation.php`, `about.php`, `module.init.php`, `module.listeners.php`, `.hooks`, `Version.php`, the installer file, a host module's `AIRULES/` handbook | **Yes** |
| Development | `PLAN/`, `tests/`, notes, drafts, evaluations, research, update plans, exports, screenshots, any working folder an agent created | **Never** |

The packer enforces this:

- Standard runtime entries are packed.
- `PLAN/` and `tests/` are **always** left out.
- On **any other** root entry it **stops** and names it. Decide, then run again with `--exclude=A,B` (development material) or `--include=A,B` (runtime the installed module needs).
- **MUST NOT** pass `--include` just to make the packer pass. Include a folder only after you found the code that reads it at runtime.
- Runtime code **MUST NOT** depend on a file under `PLAN/` or `tests/`. A developer-only screen that reads fixtures checks that the folder exists and stays off in production.
- After packing, read the `packed=` and `left out=` lines and report both lists to the user.

```text
php dacore-pack-zip.php ExampleModule 1.2.0 --exclude=notes,research
php dacore-pack-zip.php ExampleModule 1.2.0 --exclude=notes --include=Widgets
```

## What the packer does

It copies the entries the inventory packs from `app/modules/{Module}` into a temp folder — never `PLAN/`, `tests/` or anything named in `--exclude=`. The working tree is not changed.

On the copy it:

1. Writes live `module.init.php` / `module.listeners.php` into `init/` (also adds `module.init.php` zip aliases for older DACore trees).
2. Replaces root init files with inert stubs (`initialize` / `register` empty, route list `[]`).
3. Renames `install.php` (or `installed_*_install.php`) to `dainstall.php`. A module that was itself installed from a zip has no `install.php`; the packer then uses its existing `dainstall.php`. The zip always carries the installer as **`dainstall.php`** — DACore does not accept `install.php`.
4. Refuses `self::… =>` / `static::… =>` keys and requires quoted `'1.0.0' =>` keys.
5. Writes `{Module}-{version}.zip` to the project root (or `--out=`).
6. Deletes the temp copy.
7. Refuses the zip when a file or folder name starts with `_` in the module root, `Controllers/`, `Libraries/`, `Middleware/`, `assets/`, any `_folder`, or a `_*.php` under `tests/` (leftover agent helpers). Delete or rename them, then run this same script again. Two kinds of `_` names belong to a module and are allowed: Renderer partials under `views/` (`views/…/_tabs.layout.php`, `_picture.view.php`) and non-PHP test fixtures under `tests/` (`tests/…/_expected.json`).

**MUST NOT** pack `DACore`. **MUST NOT** leave `dacore-pack-zip.php` in the repo.

If the packer fails on version keys, fix `Installation.php` first, then run **this same script** again.
