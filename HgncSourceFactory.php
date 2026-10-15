<?php

namespace Omnilab\Hgnc;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * HGNC: a human gene by its HGNC id, symbol or another base's id, its
 * cross-references, a search - no key needed.
 *
 *   options:
 *     mailto: 'support@glitchr.io'     # in the User-Agent
 *     throttle: 0.1                    # seconds between two calls: HGNC asks for 10 a second at most
 *     base_uri: 'https://rest.genenames.org/'
 */
final class HgncSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'hgnc',
            'omnilab.required_options' => [],
            'throttle' => 0.1,
            'base_uri' => HgncSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new HgncSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('base_uri', HgncSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}
