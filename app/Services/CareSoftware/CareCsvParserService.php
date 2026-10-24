<?php

namespace App\Services\CareSoftware;

use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\ServiceInvoice;

class CareCsvParserService
{
    /**
     * カイポケ/ワイズマン等から出力されたCSVを解析して請求データへ変換
     *
     * ヘッダー例: 被保険者番号,氏名,サービス種別,請求年月,自己負担額,保険請求額
     *
     * @return array{parsed: array<int, array>, errors: array<int, string>}
     */
    public function parse(string $csvContent, Facility $facility): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", trim($csvContent)));
        if (empty($lines)) {
            return ['parsed' => [], 'errors' => ['CSVファイルが空です']];
        }

        $header = str_getcsv(array_shift($lines));
        $parsed = [];
        $errors = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = str_getcsv($line);
            if (count($row) < 5) {
                $errors[] = '行 '.($index + 2).': 列数が不足しています。';

                continue;
            }

            $insuranceNumber = trim($row[0]);
            $residentName = trim($row[1]);
            $serviceTypeStr = trim($row[2]);
            $yearMonth = trim($row[3]);
            $userCopayAmount = (int) str_replace([',', '円'], '', trim($row[4]));

            // 入居者マッチング（氏名または被保険者番号）
            $resident = Resident::where('facility_id', $facility->id)
                ->where(function ($q) use ($residentName) {
                    $q->where('name', $residentName);
                })
                ->first();

            if (! $resident) {
                $errors[] = '行 '.($index + 2).": 入居者「{$residentName}」が施設内に見つかりません。";

                continue;
            }

            $parsed[] = [
                'resident_id' => $resident->id,
                'resident_name' => $resident->name,
                'facility_id' => $facility->id,
                'billing_year_month' => $yearMonth,
                'service_type' => ServiceType::VisitingCare,
                'service_type_label' => ServiceType::VisitingCare->getLabel(),
                'amount' => $userCopayAmount,
                'tax_amount' => 0, // 介護保険給付対象サービスは非課税
                'tax_rate' => 0,
                'external_system_name' => 'Kaipoke/Wiseman',
                'status' => ServiceInvoiceStatus::Confirmed,
            ];
        }

        return [
            'parsed' => $parsed,
            'errors' => $errors,
        ];
    }

    /**
     * パース結果を一括で service_invoices テーブルへ反映
     */
    public function import(array $parsedRecords): int
    {
        $imported = 0;
        foreach ($parsedRecords as $record) {
            ServiceInvoice::updateOrCreate(
                [
                    'facility_id' => $record['facility_id'],
                    'resident_id' => $record['resident_id'],
                    'billing_year_month' => $record['billing_year_month'],
                    'service_type' => $record['service_type'],
                ],
                $record
            );
            $imported++;
        }

        return $imported;
    }
}
