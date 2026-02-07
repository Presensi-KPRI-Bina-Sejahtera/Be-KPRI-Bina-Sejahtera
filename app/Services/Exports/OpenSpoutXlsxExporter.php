<?php

namespace App\Services\Exports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpenSpoutXlsxExporter
{
    /**
     * @param array<int, string> $headers
     * @param iterable<array<int, mixed>> $rows
     */
    public function download(
        string $fileName,
        array $headers,
        iterable $rows,
        ?Style $headerStyle = null,
        ?Style $rowStyle = null,
        bool $autoFitColumns = false,
        int $autoFitMinWidth = 10,
        int $autoFitMaxWidth = 60,
        int $autoFitMaxCellLengthToMeasure = 200,
    ): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $headerStyle, $rowStyle, $autoFitColumns, $autoFitMinWidth, $autoFitMaxWidth, $autoFitMaxCellLengthToMeasure) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $maxLenByColumnIndex = [];
            if ($autoFitColumns) {
                foreach ($headers as $i => $header) {
                    $maxLenByColumnIndex[$i] = $this->stringLength((string) $header);
                }
            }

            $writer->addRow(Row::fromValues($headers, $headerStyle));

            foreach ($rows as $rowValues) {
                if ($autoFitColumns) {
                    foreach ($rowValues as $i => $value) {
                        $cellText = $this->cellToString($value);
                        if ($autoFitMaxCellLengthToMeasure > 0 && $this->stringLength($cellText) > $autoFitMaxCellLengthToMeasure) {
                            $cellText = substr($cellText, 0, $autoFitMaxCellLengthToMeasure);
                        }

                        $len = $this->stringLength($cellText);
                        $current = $maxLenByColumnIndex[$i] ?? 0;
                        if ($len > $current) {
                            $maxLenByColumnIndex[$i] = $len;
                        }
                    }
                }
                $writer->addRow(Row::fromValues($rowValues, $rowStyle));
            }

            if ($autoFitColumns) {
                $sheet = $writer->getCurrentSheet();

                // OpenSpout column indexes are 1-based.
                foreach ($maxLenByColumnIndex as $zeroBasedIndex => $maxLen) {
                    $width = (int) $maxLen + 2;
                    $width = max($autoFitMinWidth, $width);
                    $width = min($autoFitMaxWidth, $width);

                    $sheet->setColumnWidth((float) $width, $zeroBasedIndex + 1);
                }
            }

            $writer->close();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }
}
