# omnilab/hgnc

The HUGO Gene Nomenclature Committee's REST service (`https://rest.genenames.org/`) for
[omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): human genes by HGNC id, symbol,
previous symbol or alias, or an id HGNC records - approved name, locus type, location, groups,
MANE Select, RefSeq -, with their NCBI Gene, Ensembl, UniProt and OMIM ids as cross-references; a
search. No key.

## Installation

```sh
composer require omnilab/hgnc
```

```php
use Omnilab\Hgnc\HgncSourceFactory;

$hgnc = (new HgncSourceFactory($httpClient))->create([
    'mailto' => 'support@glitchr.io',  // optional
]);
```

## Credentials

None: HGNC's REST service takes no key and no account, within ten calls a second.

## Options

The factory's options, with the defaults it applies (`HgncSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `mailto` | `?string` | null | no | a contact address, sent in the User-Agent |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `0.1` | no | seconds left between two calls: HGNC asks for ten a second at most |
| `base_uri` | `string` | `https://rest.genenames.org/` | no | the API the source calls |

## Capabilities

`capabilities()` answers `entity`, `search`, `cross_references`; an operation it does not answer throws `NotSupportedException` before any call.

| Capability | Supported | |
|---|---|---|
| `entity` | yes | an HGNC id (`HGNC:4827`, a bare number, its page), a symbol (then a previous symbol, then an alias), an NCBI GeneID, an Ensembl gene, a UniProt accession, a MIM number; a fetch that finds two genes names neither (null); an Ensembl transcript or protein null without a call; another base refused before any call |
| `search` | yes | HGNC's search over every field (`*` a wildcard), the best scored first, the whole answer paged here, 500 a page at most; a page's entities hold the id and the symbol; a domain other than biology answers an empty page |
| `series` | no |  |
| `products` | no |  |
| `occurrences` | no |  |
| `datasets` | no |  |
| `jobs` | no |  |
| `open` | no |  |
| `structure` | no |  |
| `cross_references` | yes | its NCBI GeneID, Ensembl gene, UniProt accessions and MIM numbers |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | no |  |
| `station` | no |  |
| `cloud_cover` | no |  |

## The scheme

A gene is named in the core's `hgnc` scheme by its number (`hgnc:4827`; `display()` gives
`HGNC:4827`), read from `HGNC:4827` and from its page on genenames.org. To this source a bare number
is an HGNC id (`4827`); an NCBI GeneID or a MIM number needs its scheme (`ncbi.gene:7157`,
`omim:141900`); an Ensembl gene and a UniProt accession are read by their shapes.

## Which API

`https://rest.genenames.org/` (`info` gives the fields and the last update: 2026-10-13 at the time of
writing, 47,077 records), answering JSON when asked `Accept: application/json` (XML otherwise):

| Method | Calls |
|---|---|
| `entity()`, an id | `fetch/hgnc_id/4827`, `fetch/entrez_id/3043`, `fetch/ensembl_gene_id/ENSG...`, `fetch/uniprot_ids/P68871`, `fetch/omim_id/141900` |
| `entity()`, a symbol | `fetch/symbol/HBB`; if none, `fetch/prev_symbol/...`; if none, `fetch/alias_symbol/...` |
| `search()` | `search/<text>` - every field, HGNC's scoring; the whole answer at once, paged here |

A fetch that finds two genes or more (an alias several bear) names neither: `null`, as one that finds
none. An Ensembl transcript or protein is `null` without a call (HGNC records genes). An identifier
of a base HGNC does not record (ChEBI, Wikidata, GO, Mondo, PDB...) or a text that is no symbol is
refused before any call.

## What an entity holds

`id`, `label` (the approved symbol), `kind` `gene`, `description` (the approved name), `synonyms`
(the name, the aliases, the previous symbols), `url` (its page), and - NCBI Gene's keys where
omnilab/ncbi has them, so that a merge sets both side by side -:

| Key | HGNC field | Value |
|---|---|---|
| `gene`, `gene_name` | symbol, name | `HBB`, `hemoglobin subunit beta` |
| `status` | status | `Approved` |
| `locus_group`, `locus_type` | locus_group, locus_type | `protein-coding gene`, `gene with protein product` |
| `map_location`, `chromosome` | location (the chromosome read from it) | `11p15.4`, `11` |
| `organism` | - | `Homo sapiens` |
| `previous_symbol`, `alias_symbol`, `previous_name`, `alias_name` (one each) | prev_symbol, alias_symbol, prev_name, alias_name | `FAM46C`... |
| `gene_group` (one each) | gene_group | `Hemoglobin subunits` |
| `mane_select`, `refseq`, `ccds` (one each) | mane_select, refseq_accession, ccds_id | `ENST00000335295.4`, `NM_000518.5`... |
| `date_approved`, `date_symbol_changed`, `date_name_changed`, `date_modified` | the dates | `2001-06-22` |

`identifiers`: its own, then its NCBI GeneID, Ensembl gene, UniProt accessions and MIM numbers (the
gene's MIM entry), which the `Collector` follows. `related`: `organism` (`ncbi.taxon:9606`). The
mouse and rat genes HGNC names (MGI, RGD) and its Orphanet gene id are left out: no scheme of the core
holds them as the same thing.

## The Collector: HBB between HGNC, NCBI Gene and UniProt

Against the real services on 2026-10-15:

```php
$collector->collect('hgnc', 'HBB', 1, ['ncbi', 'uniprot']);   // 6.8 s
$collector->collect('ncbi', '3043', 1, ['hgnc']);
```

The first gives `sources` = hgnc, ncbi, uniprot, the two cytogenetic locations side by side
(`11p15.4` [hgnc], `11p15.4` [ncbi]); the second reaches HGNC by NCBI's GeneID (`hgnc:4827` among
the merged identifiers).

## What the API does not give here

Gene groups as entities (`genegroup` is not a REST endpoint), the HGNC orthology predictions
(HCOP), the withdrawn symbols' history beyond `prev_symbol`, and genes of other species (VGNC's
vertebrates have a service of their own).

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Hgnc\HgncSourceFactory;
use Omnilab\Model\Scheme;
use Omnilab\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$hgnc = (new HgncSourceFactory(HttpClient::create()))->create(['mailto' => 'you@example.org']);

$hbb = $hgnc->entity('HBB');
echo $hbb->label, ' (', $hbb->id->display(), '): ', $hbb->description, ', ', $hbb->value('map_location'), ', MANE ', $hbb->value('mane_select'), "\n";
echo '  NCBI Gene ', $hbb->identifier(Scheme::NCBI_GENE), ', Ensembl ', $hbb->identifier(Scheme::ENSEMBL), ', UniProt ', $hbb->identifier(Scheme::UNIPROT), ', OMIM ', $hbb->identifier(Scheme::OMIM), "\n";
echo 'FAM46C is now ', $hgnc->entity('FAM46C')?->label, "\n";

$page = $hgnc->search(new Query('hemoglobin', limit: 3));
printf("%d genes match \"hemoglobin\"; the first %d:\n", $page->total, count($page));
foreach ($page as $gene) {
    echo '  ', $gene->id->display(), '  ', $gene->label, "\n";
}
```

```
HBB (HGNC:4827): hemoglobin subunit beta, 11p15.4, MANE ENST00000335295.4
  NCBI Gene 3043, Ensembl ENSG00000244734, UniProt P68871, OMIM 141900
FAM46C is now TENT5C
15 genes match "hemoglobin"; the first 3:
  HGNC:18075  AHSP
  HGNC:4823  HBA1
  HGNC:4824  HBA2
```

(as answered on 2026-10-27; a page's entities hold no description: `entity()` reads each whole)

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        hgnc: { factory: hgnc, options: { mailto: '%env(OMNILAB_MAILTO)%' } }
```

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Before any call (`NotSupportedException`): an identifier of a base HGNC does not record (ChEBI,
Wikidata, GO, Mondo, PDB...) and a text that is no symbol. A symbol nobody bears costs three calls
(symbol, previous symbol, alias) and is null; one two genes share is null too.

## Verified, and not

Against the real service on 2026-10-15: HBB by its HGNC id, symbol, Ensembl gene, UniProt accession
and MIM number; TP53 by its GeneID (its aliases p53 and LFS1); FAM46C, a previous symbol, giving
TENT5C; a symbol nobody bears (three calls, null); the search for "hemoglobin" (15 genes); the
Collector's walks above. The fixtures in `Tests/Fixtures/` are its answers. Each call took 0.3 to
1.2 s.

Again on 2026-10-27: HBB by its symbol, FAM46C giving TENT5C, the search for "hemoglobin" (15
genes; the example above).

Not verified: an alias two genes share (the code names neither; none was met); HGNC's behaviour past
10 calls a second (the source never makes them).

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the HUGO Gene Nomenclature Committee. HGNC releases its data under
[CC0](https://creativecommons.org/publicdomain/zero/1.0/) and recommends attribution (Seal R. L. et
al., Nucleic Acids Res. 2023, 10.1093/nar/gkac888), within ten calls a second, as the README's
[Terms of use](https://github.com/glitchr-omnisense/omnilab-hgnc#terms-of-use) says. Whoever runs
the package is responsible for any use made of what it returns: see the README's
[Responsibility](https://github.com/glitchr-omnisense/omnilab-hgnc#responsibility) section.
