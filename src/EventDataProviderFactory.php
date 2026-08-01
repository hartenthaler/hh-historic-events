<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http\HttpGetClient;

final class EventDataProviderFactory
{
    public function __construct(
        private readonly string $resourcesFolder,
        private readonly HttpGetClient $httpClient
    ) {
    }

    /**
     * @return EventDataProviderInterface[]
     */
    public function providers(): array
    {
        $dataFolder = $this->resourcesFolder . 'data/';

        return [
            new TextGedcomEventProvider(
                'german-wars-battles-worldwide',
                'Wars and Battles Worldwide',
                'Historical facts - Wars and Battles Worldwide (since 900)',
                $dataFolder . 'gedcom/german-wars-battles-worldwide.ged',
                'de',
                'Wikipedia',
                'https://de.wikipedia.org/wiki/Liste_von_Kriegen',
                [
                    'revolt' => 'revolt',
                    'siege' => 'siege',
                    'blockade' => 'blockade',
                    'civil-war' => 'civil war',
                    'conquest' => 'conquest',
                    'feud' => 'feud',
                    'campaign' => 'campaign',
                    'combat' => 'combat',
                    'invasion' => 'invasion',
                    'struggle' => 'struggle',
                    'conflict' => 'conflict',
                    'crusade' => 'crusade',
                    'war' => 'war',
                    'massacre' => 'massacre',
                    'offensive' => 'offensive',
                    'revolution' => 'revolution',
                    'battle' => 'battle',
                    'naval-battle' => 'naval battle',
                    'operation' => 'operation',
                ]
            ),
            new GermanChancellorsPresidentsCsvProvider(
                $dataFolder . 'csv/GermanChancellorsPresidents.csv'
            ),
            new GermanChancellorsPresidentsWikidataProvider($this->httpClient),
            new TextGedcomEventProvider(
                'swiss-historic-events',
                'Historic Events: Switzerland',
                'Historical facts - events in Switzerland',
                $dataFolder . 'gedcom/swiss-historic-events.ged',
                'de',
                'Peter Jehli-Kamm, baum.jehli.ch',
                'http://baum.jehli.ch/',
                ['historic-event-switzerland' => 'Historic event: Switzerland']
            ),
            new GrampsCsvEventProvider(
                $dataFolder . 'csv/',
                $this->httpClient
            ),
        ];
    }
}
