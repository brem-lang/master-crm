<?php

namespace App\Exports;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeadsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Lead ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Country Code',
            'Sale Status',
            'Advertiser',
            'FTD',
            'Affiliate',
            'Assigned To',
            'Created',
        ];
    }

    /**
     * @return list<string|null>
     */
    public function map($lead): array
    {
        /** @var Lead $lead */
        return [
            $lead->external_id,
            $lead->first_name,
            $lead->last_name,
            $lead->email,
            $lead->mobile,
            $lead->country_code,
            $lead->sale_status,
            $lead->advertiser_name,
            $lead->is_ftd ? 'Yes' : 'No',
            $lead->affiliate_name,
            $lead->assignee?->name,
            $lead->lead_created_at?->toDateString(),
        ];
    }
}
