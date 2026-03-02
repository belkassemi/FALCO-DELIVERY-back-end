<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports\V1;

use App\Actions\Reports\CreateReport;
use App\Http\Requests\Reports\V1\StoreReportRequest;
use Illuminate\Http\JsonResponse;

final readonly class StoreController
{
    public function __construct(
        private CreateReport $createReport,
    ) {}

    public function __invoke(StoreReportRequest $request): JsonResponse
    {
        $report = $this->createReport->handle(
            payload: $request->payload(),
            userId: (int) auth('api')->id(),
        );

        return response()->json([
            // Exact wording from PRD §19.4
            'message' => 'بلاغك وصل، سنتصل بك قريباً',
            'report_id' => $report->id,
        ], 201);
    }
}
