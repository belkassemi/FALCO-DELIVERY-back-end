<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Http\Payloads\Reports\ResolveReportPayload;
use App\Models\OrderReport;

final readonly class ResolveReport
{
    public function handle(int $reportId, ResolveReportPayload $payload): OrderReport
    {
        $report = OrderReport::findOrFail($reportId);

        $report->update([
            'status'         => 'resolved',
            'admin_response' => $payload->adminResponse,
            'action_taken'   => $payload->actionTaken,
            'resolved_at'    => now(),
        ]);

        return $report;
    }
}
