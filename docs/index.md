# omnilab/hgnc

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

## Verified, and not

Against the real service on 2026-10-15: HBB by its HGNC id, symbol, Ensembl gene, UniProt accession
and MIM number; TP53 by its GeneID (its aliases p53 and LFS1); FAM46C, a previous symbol, giving
TENT5C; a symbol nobody bears (three calls, null); the search for "hemoglobin" (15 genes); the
Collector's walks above. The fixtures in `Tests/Fixtures/` are its answers. Each call took 0.3 to
1.2 s.

Not verified: an alias two genes share (the code names neither; none was met); HGNC's behaviour past
10 calls a second (the source never makes them).
