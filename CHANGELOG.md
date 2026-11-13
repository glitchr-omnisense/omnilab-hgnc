# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release: it installs with Composer's default minimum-stability (stable).

### Added

- Omnilab HGNC: a human gene by its HGNC id (HGNC:4827), approved, previous or alias symbol, or an
  id HGNC records (NCBI Gene, Ensembl, UniProt, OMIM) - its approved name, locus group and type,
  location, previous symbols and names, aliases, gene groups, status, dates, MANE Select, RefSeq and
  CCDS ids - its NCBI Gene, Ensembl, UniProt and OMIM ids as cross-references, and a search, through
  HGNC's REST service, no key needed.

### Release

- Requires `omnisense/omnilab` `^1.0`, stable versions, where it required `@dev` ones:
  `composer require omnilab/hgnc` installs in a project at Composer's default minimum-stability
  (stable). `extra.branch-alias` names the branch (`dev-1.x`: `1.x-dev`).
- CI: `.github/workflows/ci.yml` (glitchr-studio/ci's php-package.yml, pinned: the package
  installed alone, PHPUnit, PHPStan against its baseline, a consumer install) and `release.yml`
  (that CI on the tag, then glitchr-studio/ci's php-release.yml); `phpunit.xml.dist` runs the
  package's own tests.
- README: the install line and a first call.
