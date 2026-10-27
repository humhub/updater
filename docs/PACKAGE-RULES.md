# Package Rules

Core updates install the full release package (`humhub-<version>.zip`), the same package used for new
installations. **Everything from the package overwrites the installation.** `config/package-rules.php`
only defines the exceptions.

## Format

```php
return [
    '1.19.0' => [
        'replace' => ['protected/humhub', 'protected/vendor'],
        'keep'    => ['protected/config/*', 'uploads/*'],
    ],
    '1.20.0' => [
        'replace' => ['protected/humhub', 'protected/vendor'],
        'keep'    => ['protected/config/*', 'uploads/*', 'config/*', '!config/*.example.php'],
        'delete'  => ['index-test.php'],
    ],
];
```

- Keys are core versions (`major.minor.patch`). A section describes the layout **from this core version on**.
- Paths are relative to the installation directory: no leading `/`, no trailing `/`, no empty, `.` or `..` segments.
  - `dir/*` matches the directory itself and everything below it. Directories always need `/*`.
  - Any other pattern matches exactly one path. In `keep`, `config/common.php` matches only that file; a
    pattern naming a directory without `/*` does not protect the files inside it.
  - `*` inside a name is allowed (`config/*.example.php`) and does not match across directories. In `keep`
    it also matches dotfiles (`config/*` covers `config/.env`); in `delete` it does not (`glob()` semantics).

| Type | Meaning | Syntax |
| --- | --- | --- |
| `replace` | Directories swapped as a whole. The old directory is moved to the backup. If a swap fails, all swaps done so far are rolled back. | Plain paths, no wildcards, no `!` |
| `keep` | Files that are never overwritten; they are only created from the package if missing. An entry starting with `!` excludes matching paths from `keep` again, so they are overwritten. | Patterns, `!` exclusions allowed |
| `delete` | Files or directories removed when an update crosses this version. | Patterns, no `!` |

Every value must be a list of strings; invalid rules abort the update before anything is changed.

## Evaluation

For an update from version `F` to version `T`:

- **Pre-releases use the rules of their base version**: for `T = 1.20.0-beta.1` the rules are evaluated as for
  `1.20.0`, so a pre-release already gets the new layout.
- **`replace` and `keep`** — only the section with the **highest version `<= base(T)`** that defines them is used.
  Sections are **not merged**, so each section lists `replace` and `keep` **completely**.
- **`delete`** — the `delete` lists of **all sections with `F < version <= base(T)`** are applied, in ascending order.
  An update from a pre-release to its final release (`1.20.0-beta.1 → 1.20.0`) applies the deletes again,
  which is harmless because the files are already gone.
- A section may contain only `delete`. `replace`/`keep` then come from the nearest lower section that has them.

Example with the rules above:

| Update | `replace` / `keep` from | `delete` applied |
| --- | --- | --- |
| 1.19.1 → 1.19.3 | 1.19.0 | — |
| 1.19.3 → 1.20.0-beta.1 | 1.20.0 | 1.20.0 |
| 1.20.0-beta.1 → 1.20.0 | 1.20.0 | 1.20.0 (finds nothing) |
| 1.19.3 → 1.20.0 | 1.20.0 | 1.20.0 |
| 1.19.1 → 1.20.2 | 1.20.0 | 1.20.0 |
| 1.20.0 → 1.20.1 | 1.20.0 | — |

Directories shipped in the package outside the `replace` directories are created if missing, also when they
are empty (e.g. `public/assets/`).
Files that exist only in the installation (not in the package, not in a `delete` rule) are never touched.
Replaced, overwritten and deleted files are moved to `protected/runtime/updater/backups/` and removed
after two days.

## Changing rules

The rules ship with the updater module. The updater must be updated before a core update can be started,
so changed rules apply to every update performed after the updater release.

| Situation | What to do |
| --- | --- |
| A core release changes the layout outside `protected/humhub` (new user file that must not be overwritten, moved directory, removed file) | Add a section for that core version with the **complete** `replace`/`keep` lists and the `delete` list. Release the updater **before** the core release. |
| An existing rule is wrong (e.g. a user file gets overwritten) | Fix the rule in the section where it belongs. It applies to all updates with the new updater version; updates already done are not corrected retroactively. |
| A file is no longer shipped and should disappear from installations | Add it to `delete` of the core version that stopped shipping it. Installations already on that version or newer keep it — add the delete to the next version as well if they need cleaning up. |
| Something no longer needs protection | Remove it from `keep` in a **new** section (with complete lists), never in an older one — updates to older target versions must keep their behaviour. |

Core pull requests that add, move or remove files outside `protected/humhub` must check these rules
(see `docs/develop/internals-release-package.md` in humhub/humhub).
