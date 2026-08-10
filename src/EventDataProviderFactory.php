<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http\HttpGetClient;

final class EventDataProviderFactory
{
    public function __construct(
        private readonly string $resourcesFolder,
        private readonly string $customDataFolder,
        private readonly HttpGetClient $httpClient
    ) {
    }

    /**
     * @return EventDataProviderInterface[]
     */
    public function providers(): array
    {
        $dataFolder = $this->resourcesFolder . 'data/';
        $bundledCsvFolder = $dataFolder . 'csv/';

        return [
            new TextGedcomEventProvider(
                'german-wars-battles-worldwide',
                'Wars and Battles Worldwide',
                'Historical facts - Wars and Battles Worldwide (since 900)',
                $dataFolder . 'gedcom/german-wars-battles-worldwide.ged',
                'de',
                'Worldwide',
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
                $this->preferredCsvFile('GermanChancellorsPresidents.csv', $bundledCsvFolder)
            ),
            new GermanChancellorsPresidentsWikidataProvider($this->httpClient),
            new TextGedcomEventProvider(
                'swiss-historic-events',
                'Historic Events: Switzerland (CSV)',
                'Historical facts - events in Switzerland',
                $dataFolder . 'csv/swiss-historic-events.csv',
                'de',
                'Switzerland',
                'Peter Jehli-Kamm, baum.jehli.ch',
                'http://baum.jehli.ch/',
                ['historic-event-switzerland' => 'Historic event: Switzerland']
            ),
            new GrampsCsvEventProvider(
                [$this->customDataFolder, $bundledCsvFolder],
                $this->httpClient
            ),
        ];
    }

    private function preferredCsvFile(string $fileName, string $bundledCsvFolder): string
    {
        $customFile = $this->customDataFolder . $fileName;

        return is_file($customFile) ? $customFile : $bundledCsvFolder . $fileName;
    }
}
