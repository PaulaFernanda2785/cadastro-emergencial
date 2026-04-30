<?php

declare(strict_types=1);

namespace App\Services;

final class TerritorioService
{
    private ?array $municipalities = null;

    public function states(): array
    {
        $states = [];

        foreach ($this->municipalities() as $municipality) {
            $states[$municipality['uf']] = [
                'uf' => $municipality['uf'],
                'nome' => $municipality['estado'],
            ];
        }

        uasort($states, static fn (array $a, array $b): int => strcmp($a['nome'], $b['nome']));

        return array_values($states);
    }

    public function municipalities(): array
    {
        if ($this->municipalities !== null) {
            return $this->municipalities;
        }

        $items = [];
        $files = glob(BASE_PATH . '/terit/*_Municipios_2025/*_municipios_com_geolocalizacao.csv') ?: [];

        foreach ($files as $file) {
            foreach ($this->readCsv($file) as $row) {
                if (empty($row['CD_MUN']) || empty($row['NM_MUN']) || empty($row['SIGLA_UF'])) {
                    continue;
                }

                $items[] = [
                    'codigo_ibge' => (string) $row['CD_MUN'],
                    'nome' => (string) $row['NM_MUN'],
                    'uf' => strtoupper((string) $row['SIGLA_UF']),
                    'estado' => (string) ($row['NM_UF'] ?? $row['SIGLA_UF']),
                    'latitude' => $row['latitude'] !== '' ? (string) $row['latitude'] : null,
                    'longitude' => $row['longitude'] !== '' ? (string) $row['longitude'] : null,
                ];
            }
        }

        usort($items, static function (array $a, array $b): int {
            return [$a['uf'], $a['nome']] <=> [$b['uf'], $b['nome']];
        });

        $this->municipalities = $items;

        return $items;
    }

    public function findMunicipalityByCode(string $code): ?array
    {
        foreach ($this->municipalities() as $municipality) {
            if ($municipality['codigo_ibge'] === $code) {
                return $municipality;
            }
        }

        return null;
    }

    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (!is_array($header)) {
            fclose($handle);
            return [];
        }

        $header = array_map(static function (string $column): string {
            return preg_replace('/^\xEF\xBB\xBF/', '', $column) ?? $column;
        }, $header);

        $rows = [];

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($data) !== count($header)) {
                continue;
            }

            $rows[] = array_combine($header, $data);
        }

        fclose($handle);

        return $rows;
    }
}
