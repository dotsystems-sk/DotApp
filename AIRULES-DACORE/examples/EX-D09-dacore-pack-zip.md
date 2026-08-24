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
php dacore-pack-zip.php DAFiles 1.2.0
Remove-Item .\dacore-pack-zip.php
```

Linux / macOS:

```bash
cp AIRULES/examples/EX-D09-dacore-pack-zip.php.txt ./dacore-pack-zip.php
php dacore-pack-zip.php DAFiles 1.2.0
rm ./dacore-pack-zip.php
```

Flags:

```text
php dacore-pack-zip.php Module 1.2.0
php dacore-pack-zip.php --module=DAFiles --version=1.2.0
php dacore-pack-zip.php --module=DAFiles --version=1.2.0 --out=C:\releases
```

`--version` **MUST** equal the highest quoted key in `Installation.php` (`'1.2.0' =>`).

## What the packer does

It copies `app/modules/{Module}` into a temp folder. The working tree stays `install.php` + live init.

On the copy it:

1. Writes live `module.init.php` / `module.listeners.php` into `init/` (also adds `module.init.php` zip aliases for older DACore trees).
2. Replaces root init files with inert stubs (`initialize` / `register` empty, route list `[]`).
3. Renames `install.php` (or `installed_*_install.php`) to `dainstall.php`.
4. Refuses `self::… =>` / `static::… =>` keys and requires quoted `'1.0.0' =>` keys.
5. Writes `{Module}-{version}.zip` to the project root (or `--out=`).
6. Deletes the temp copy.

**MUST NOT** pack `DACore`. **MUST NOT** leave `dacore-pack-zip.php` in the repo.

If the packer fails on version keys, fix `Installation.php` first, then run **this same script** again.
