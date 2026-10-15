<?php

namespace Omnilab\Hgnc\Tests;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Hgnc\HgncSource;
use Omnilab\Hgnc\HgncSourceFactory;
use Omnilab\Model\Domain;
use Omnilab\Model\Identifier;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from rest.genenames.org on 2026-10-15: HBB by its HGNC
 * id, its symbol, its NCBI GeneID's twin (TP53, 7157), its Ensembl gene,
 * its UniProt accession and its MIM number; FAM46C, TENT5C's previous
 * symbol; a symbol nobody bears; the search for "hemoglobin" (15 genes)
 * and for a text nothing matches.
 */
final class HgncSourceTest extends TestCase
{
    /** @var list<string> the paths asked */
    private array $calls = [];

    private function source(?MockResponse $response = null): HgncSource
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $path = rawurldecode(substr((string) parse_url($url, \PHP_URL_PATH), 1));
            self::assertContains('Accept: application/json', $options['headers'], 'HGNC answers XML otherwise');
            $this->calls[] = $path;
            if ($response) {
                return $response;
            }
            $file = __DIR__.'/Fixtures/'.str_replace('/', '-', $path).'.json';
            if (!is_file($file)) {
                $file = __DIR__.'/Fixtures/'.(str_starts_with($path, 'search/') ? 'search-zzqqxx.json' : 'fetch-symbol-ZZQQXX.json');
            }

            return new MockResponse((string) file_get_contents($file));
        });
        $source = (new HgncSourceFactory($http))->create(['throttle' => 0]);
        self::assertInstanceOf(HgncSource::class, $source);

        return $source;
    }

    public function testHbbByItsHgncId(): void
    {
        $gene = $this->source()->entity('HGNC:4827');

        self::assertSame('hgnc:4827', $gene->id->key());
        self::assertSame('HGNC:4827', $gene->id->display());
        self::assertSame('HBB', $gene->label);
        self::assertSame(Domain::BIOLOGY, $gene->domain);
        self::assertSame('gene', $gene->kind);
        self::assertSame('hemoglobin subunit beta', $gene->description);
        self::assertSame(['hemoglobin subunit beta', 'CD113t-C', 'beta-globin'], $gene->synonyms);
        self::assertSame('https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/HGNC:4827', $gene->url);
        self::assertSame('HBB', $gene->value('gene'));
        self::assertSame('Approved', $gene->value('status'));
        self::assertSame('protein-coding gene', $gene->value('locus_group'));
        self::assertSame('gene with protein product', $gene->value('locus_type'));
        self::assertSame('11p15.4', $gene->value('map_location'));
        self::assertSame('11', $gene->value('chromosome'));
        self::assertSame('Hemoglobin subunits', $gene->value('gene_group'));
        self::assertSame(['ENST00000335295.4', 'NM_000518.5'], array_map('strval', $gene->properties('mane_select')));
        self::assertSame('hemoglobin, beta', $gene->value('previous_name'));
        self::assertSame('2001-06-22', $gene->value('date_approved'));
        self::assertSame('hgnc', $gene->property('gene')->source);
        self::assertSame(['hgnc:4827', 'ncbi.gene:3043', 'ensembl:ENSG00000244734', 'uniprot:P68871', 'omim:141900'], $gene->identifiers()->keys());
        self::assertSame(['ncbi.taxon:9606'], $gene->related('organism')->keys());
        self::assertSame(['fetch/hgnc_id/4827'], $this->calls);
    }

    public function testBySymbolAndByTheIdsHgncRecords(): void
    {
        $source = $this->source();
        self::assertSame('hgnc:4827', (string) $source->entity('HBB')?->id);
        self::assertSame('hgnc:4827', (string) $source->entity('4827')?->id, 'a bare number: an HGNC id');
        self::assertSame('hgnc:4827', (string) $source->entity('https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/HGNC:4827')?->id);
        self::assertSame('hgnc:4827', (string) $source->entity('ENSG00000244734')?->id);
        self::assertSame('hgnc:4827', (string) $source->entity(Identifier::of(Scheme::UNIPROT, 'P68871'))?->id);
        self::assertSame('hgnc:4827', (string) $source->entity('omim:141900')?->id);
        $tp53 = $source->entity(Identifier::of(Scheme::NCBI_GENE, '7157'));
        self::assertSame('TP53', $tp53?->label);
        self::assertSame(['p53', 'LFS1'], array_map('strval', $tp53->properties('alias_symbol')));
        self::assertSame('17', $tp53->value('chromosome'));
        self::assertSame(['fetch/symbol/HBB', 'fetch/hgnc_id/4827', 'fetch/hgnc_id/4827', 'fetch/ensembl_gene_id/ENSG00000244734', 'fetch/uniprot_ids/P68871', 'fetch/omim_id/141900', 'fetch/entrez_id/7157'], $this->calls);
    }

    public function testAPreviousSymbol(): void
    {
        $gene = $this->source()->entity('FAM46C');

        self::assertSame('TENT5C', $gene?->label);
        self::assertSame('FAM46C', $gene->value('previous_symbol'));
        self::assertSame(['fetch/symbol/FAM46C', 'fetch/prev_symbol/FAM46C'], $this->calls);
    }

    public function testUnknownIsNull(): void
    {
        self::assertNull($this->source()->entity('ZZQQXX'));
        self::assertSame(['fetch/symbol/ZZQQXX', 'fetch/prev_symbol/ZZQQXX', 'fetch/alias_symbol/ZZQQXX'], $this->calls);
        self::assertNull($this->source()->entity('ENST00000335295'), 'a transcript: no call');
        self::assertCount(3, $this->calls);
    }

    public function testAnotherBaseIsRefusedBeforeAnyCall(): void
    {
        foreach (['CHEBI:15422', 'Q60235', 'GO:0005829', 'MONDO:0011382', 'hemoglobin subunit beta', Identifier::of(Scheme::PDB, '4HHB')] as $id) {
            try {
                $this->source()->entity($id);
                self::fail(\sprintf('%s should be refused', $id));
            } catch (NotSupportedException) {
            }
        }
        self::assertSame([], $this->calls);
    }

    public function testASearch(): void
    {
        $source = $this->source();
        $first = $source->search(new Query('hemoglobin', limit: 5));

        self::assertSame(['AHSP', 'HBA1', 'HBA2', 'HBAP1', 'HBB'], array_map(static fn ($e) => $e->label, $first->items));
        self::assertSame('hgnc:18075', (string) $first->first()?->id);
        self::assertSame(15, $first->total);
        self::assertSame('5', $first->next);
        $last = $source->search(new Query('hemoglobin', limit: 10, cursor: $first->next));
        self::assertCount(10, $last);
        self::assertNull($last->next);
        self::assertCount(0, $source->search(new Query('zzqqxx')));
        self::assertCount(0, $source->search(new Query('hemoglobin', Domain::ASTRONOMY)));
        self::assertSame(['search/hemoglobin', 'search/hemoglobin', 'search/zzqqxx'], $this->calls);
    }

    public function testErrors(): void
    {
        try {
            $this->source(new MockResponse('{"error":"bad field"}', ['http_code' => 400]))->entity('HBB');
            self::fail('a 400');
        } catch (ProviderException $e) {
            self::assertSame(400, $e->status);
        }
        $this->expectException(UnavailableException::class);
        $this->source(new MockResponse('', ['http_code' => 503]))->entity('HBB');
    }

    public function testTheFactory(): void
    {
        $factory = new HgncSourceFactory(new MockHttpClient());

        self::assertSame('hgnc', $factory->getName());
        self::assertSame([Capability::ENTITY, Capability::SEARCH, Capability::CROSS_REFERENCES], $factory->create()->capabilities());
    }
}
