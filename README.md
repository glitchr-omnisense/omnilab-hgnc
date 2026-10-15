# omnilab/hgnc

HGNC for [glitchr/omnilab](https://github.com/glitchr-studio/omnilab): a human gene of the HUGO Gene
Nomenclature Committee by its HGNC id (`HGNC:4827`), its approved symbol (`HBB`), a previous symbol
or an alias that one gene alone bears (`FAM46C` is `TENT5C`), or an id HGNC records for it (NCBI
Gene, Ensembl, UniProt, OMIM) - with its approved name, locus group and type, chromosomal location,
previous symbols and names, aliases, gene groups, status and dates, MANE Select transcripts, RefSeq
and CCDS ids; its NCBI Gene, Ensembl, UniProt and OMIM ids as cross-references; a search over every
field. Through HGNC's REST service, no key.

```php
$hgnc = (new HgncSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$gene = $hgnc->entity('HBB');                // or 'HGNC:4827', 'ENSG00000244734', 'omim:141900'
$gene->id->display();                         // HGNC:4827
$gene->description;                           // hemoglobin subunit beta
$gene->value('map_location');                 // 11p15.4
$gene->identifiers()->keys();                 // hgnc:4827, ncbi.gene:3043, ensembl:ENSG00000244734, uniprot:P68871, omim:141900
$hgnc->entity('FAM46C')->label;               // TENT5C
$hgnc->search(new Query('hemoglobin', limit: 5));   // 15 genes
```

```yaml
omnilab:
    sources:
        hgnc:
            factory: hgnc
            options:
                mailto: 'support@glitchr.io'   # in the User-Agent
```

A gene is named in the core's `hgnc` scheme (`hgnc:4827`, displayed `HGNC:4827`). The `Collector`
walks from HGNC into NCBI Gene, Ensembl, UniProt (`$collector->collect('hgnc', 'HBB')`), and into
HGNC from any of them.

## Terms of use

HGNC releases all its data under [CC0](https://creativecommons.org/publicdomain/zero/1.0/)
([genenames.org/about/license](https://www.genenames.org/about/license/)) and recommends attribution:
Seal R. L. et al., "Genenames.org: the HGNC resources in 2023", Nucleic Acids Res. 2023,
[10.1093/nar/gkac888](https://doi.org/10.1093/nar/gkac888). HGNC asks for 10 calls a second at
most: the source waits 0.1 s between two calls.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the HUGO Gene Nomenclature Committee. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License: MIT.
