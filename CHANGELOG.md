# Changelog

All notable changes to `ginkelsoft/laravel-data-subject-access` are documented
in this file.

## [Unreleased]

### Changed

- 2026-09-04 — Documented that `Exportable` and `Forgettable`
  (`laravel-data-right-to-be-forgotten`) now share the `HasSubjectQuery` base
  trait from `ginkelsoft/laravel-compliance-core`, so a model can use both
  traits without an `insteadof` conflict resolution. README's Gotchas section
  no longer describes the old `insteadof` workaround; a new "Combine with
  right-to-be-forgotten" example shows the current, simpler usage. See
  [ginkelsoft-development/laravel-compliance-core#2](https://github.com/ginkelsoft-development/laravel-compliance-core/issues/2)
  and [ginkelsoft-development/laravel-data-subject-access#1](https://github.com/ginkelsoft-development/laravel-data-subject-access/issues/1).
