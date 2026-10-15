<?php

namespace Omnilab\Hgnc;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Property;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\Page;
use Omnilab\Source\Query;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The HUGO Gene Nomenclature Committee, the authority that names human
 * genes, through its REST service: a human gene by its HGNC id
 * ("HGNC:4827"), its approved symbol ("HBB"), a previous symbol or an alias
 * when one gene alone bears it ("FAM46C" is TENT5C), or another base's id
 * HGNC records (NCBI Gene, Ensembl, UniProt, OMIM) - its approved name,
 * locus type and group, chromosomal location, previous symbols and names,
 * aliases, gene groups, status and dates, MANE Select transcripts, RefSeq
 * and CCDS ids -, its NCBI Gene, Ensembl, UniProt and OMIM ids as
 * cross-references; a search over every field.
 *
 * No key, no account. HGNC asks for 10 calls a second at most: a 0.1 s
 * throttle by default. One call a gene by id or symbol (up to three for a
 * former symbol or an alias), one a search.
 */
final class HgncSource extends HttpSource
{
    public const BASE_URI = 'https://rest.genenames.org/';

    /** The most genes a search page holds (HGNC answers them all at once). */
    public const MAX_LIMIT = 500;

    /** The fields an identifier of another base is fetched by. */
    private const FIELDS = [
        'hgnc' => 'hgnc_id',
        'ncbi.gene' => 'entrez_id',
        'ensembl' => 'ensembl_gene_id',
        'uniprot' => 'uniprot_ids',
        'omim' => 'omim_id',
    ];

    public function __construct(
        HttpClientInterface $http,
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 0.1,
    ) {
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'hgnc';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::SEARCH, Capability::CROSS_REFERENCES];
    }

    /**
     * A human gene by its HGNC id ("HGNC:4827", "4827", its page), an id
     * HGNC records for it (ncbi.gene, omim: with their scheme; an Ensembl
     * gene, a UniProt accession), or a symbol: the approved one, else a
     * previous symbol, else an alias, when one gene alone bears it; another
     * base's identifier is refused before any call; null when HGNC knows
     * none (or several: an id or a symbol two genes share names neither).
     */
    public function entity(Identifier|string $id): ?Entity
    {
        $identifier = self::identify($id, Scheme::HGNC, Scheme::NCBI_GENE, Scheme::ENSEMBL, Scheme::UNIPROT, Scheme::OMIM);
        if (null !== $identifier) {
            if (Scheme::ENSEMBL === $identifier->scheme && !str_starts_with($identifier->value, 'ENSG')) {
                return null; // HGNC records genes: a transcript or a protein is none
            }

            return $this->one(self::FIELDS[$identifier->scheme->value], $identifier->value);
        }
        $symbol = \is_string($id) ? trim($id) : '';
        if ($id instanceof Identifier || !preg_match('~^[A-Za-z0-9][A-Za-z0-9@._\-/#]{0,40}$~', $symbol) || null !== Identifier::parse($symbol)) {
            throw NotSupportedException::identifier('hgnc', (string) $id);
        }
        foreach (['symbol', 'prev_symbol', 'alias_symbol'] as $field) {
            [$entity, $found] = $this->fetch($field, $symbol);
            if ($found > 0) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * The genes whose fields hold the text (symbols, names, aliases, ids:
     * HGNC's search over every field; "*" a wildcard), the best scored
     * first; a domain other than biology answers nothing. An entity of a page
     * holds the gene's id and symbol: entity() reads it whole.
     */
    public function search(Query $query): Page
    {
        if (null !== $query->domain && Domain::BIOLOGY !== $query->domain) {
            return new Page();
        }
        $text = trim((string) preg_replace('~\s+~u', ' ', $query->text));
        if ('' === $text) {
            return new Page();
        }
        $docs = $this->getJson('search/'.rawurlencode($text))['response']['docs'] ?? [];
        $start = max(0, (int) $query->cursor);
        $limit = max(1, min(self::MAX_LIMIT, $query->limit));
        $items = [];
        foreach (\array_slice($docs, $start, $limit) as $doc) {
            $id = \is_array($doc) ? Identifier::tryOf(Scheme::HGNC, $doc['hgnc_id'] ?? null) : null;
            if (null !== $id) {
                $symbol = \is_string($doc['symbol'] ?? null) ? $doc['symbol'] : $id->display();
                $items[] = new Entity(id: $id, label: $symbol, domain: Domain::BIOLOGY, kind: 'gene', properties: [new Property('gene', 'Gene symbol', $symbol, 'hgnc')], url: $id->url(), source: 'hgnc');
            }
        }

        return new Page($items, $start + $limit < \count($docs) ? (string) ($start + $limit) : null, \count($docs));
    }

    /** The one gene a field's value names; null when none or several. */
    private function one(string $field, string $value): ?Entity
    {
        return $this->fetch($field, $value)[0];
    }

    /**
     * @return array{0: ?Entity, 1: int} the gene when one alone bears the value, and how many do
     */
    private function fetch(string $field, string $value): array
    {
        $docs = $this->getJson('fetch/'.$field.'/'.rawurlencode($value))['response']['docs'] ?? [];

        return [1 === \count($docs) && \is_array($docs[0]) ? $this->gene($docs[0]) : null, \count($docs)];
    }

    /** @param array<string, mixed> $doc */
    private function gene(array $doc): ?Entity
    {
        $id = Identifier::tryOf(Scheme::HGNC, $doc['hgnc_id'] ?? null);
        if (null === $id) {
            return null;
        }
        $properties = [];
        $add = static function (string $key, string $label, mixed $value) use (&$properties): void {
            if (null !== $value && '' !== $value && [] !== $value) {
                $properties[] = new Property($key, $label, $value, 'hgnc');
            }
        };
        $list = static fn (mixed $values): array => array_values(array_filter(array_map(static fn (mixed $v) => \is_scalar($v) ? trim((string) $v) : null, (array) ($values ?? [])), static fn (?string $v) => null !== $v && '' !== $v));
        $symbol = \is_string($doc['symbol'] ?? null) ? $doc['symbol'] : null;
        $name = \is_string($doc['name'] ?? null) ? $doc['name'] : null;
        // NCBI Gene's keys where omnilab/ncbi has them: gene, gene_name, map_location, chromosome, organism.
        $add('gene', 'Gene symbol', $symbol);
        $add('gene_name', 'Gene name', $name);
        $add('status', 'Status', $doc['status'] ?? null);
        $add('locus_group', 'Locus group', $doc['locus_group'] ?? null);
        $add('locus_type', 'Locus type', $doc['locus_type'] ?? null);
        $location = \is_string($doc['location'] ?? null) ? $doc['location'] : null;
        $add('map_location', 'Cytogenetic location', $location);
        $add('chromosome', 'Chromosome', null !== $location && preg_match('~^(\d{1,2}|X|Y|MT)(?=[pq]|\s|$)~', $location, $m) ? $m[1] : null);
        $add('organism', 'Organism', 'Homo sapiens');
        foreach ($list($doc['prev_symbol'] ?? null) as $previous) {
            $add('previous_symbol', 'Previous symbol', $previous);
        }
        foreach ($list($doc['alias_symbol'] ?? null) as $alias) {
            $add('alias_symbol', 'Alias symbol', $alias);
        }
        foreach ($list($doc['prev_name'] ?? null) as $previous) {
            $add('previous_name', 'Previous name', $previous);
        }
        foreach ($list($doc['alias_name'] ?? null) as $alias) {
            $add('alias_name', 'Alias name', $alias);
        }
        foreach ($list($doc['gene_group'] ?? null) as $group) {
            $add('gene_group', 'Gene group', $group);
        }
        foreach ($list($doc['mane_select'] ?? null) as $transcript) {
            $add('mane_select', 'MANE Select transcript', $transcript);
        }
        foreach ($list($doc['refseq_accession'] ?? null) as $refseq) {
            $add('refseq', 'RefSeq', $refseq);
        }
        foreach ($list($doc['ccds_id'] ?? null) as $ccds) {
            $add('ccds', 'CCDS', $ccds);
        }
        foreach (['date_approved_reserved' => ['date_approved', 'Approved'], 'date_symbol_changed' => ['date_symbol_changed', 'Symbol changed'], 'date_name_changed' => ['date_name_changed', 'Name changed'], 'date_modified' => ['date_modified', 'Modified']] as $field => [$key, $label]) {
            $add($key, $label, \is_string($doc[$field] ?? null) ? substr($doc[$field], 0, 10) : null);
        }

        $identifiers = [Identifier::tryOf(Scheme::NCBI_GENE, $doc['entrez_id'] ?? null), Identifier::tryOf(Scheme::ENSEMBL, $doc['ensembl_gene_id'] ?? null)];
        foreach ($list($doc['uniprot_ids'] ?? null) as $accession) {
            $identifiers[] = Identifier::tryOf(Scheme::UNIPROT, $accession);
        }
        foreach ($list($doc['omim_id'] ?? null) as $mim) {
            $identifiers[] = Identifier::tryOf(Scheme::OMIM, $mim);
        }
        $synonyms = array_values(array_unique(array_filter([$name, ...$list($doc['alias_symbol'] ?? null), ...$list($doc['prev_symbol'] ?? null)], static fn (?string $s) => null !== $s && $s !== $symbol)));

        return new Entity(
            id: $id,
            label: $symbol ?? $id->display(),
            domain: Domain::BIOLOGY,
            kind: 'gene',
            synonyms: $synonyms,
            description: $name,
            properties: $properties,
            identifiers: new Identifiers($identifiers),
            url: $id->url(),
            source: 'hgnc',
            related: ['organism' => Identifiers::of(Identifier::of(Scheme::NCBI_TAXON, '9606'))],
        );
    }
}
