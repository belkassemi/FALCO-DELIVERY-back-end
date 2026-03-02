<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports\V1;

use App\Actions\Reports\ResolveReport;
use App\Http\Requests\Reports\V1\ResolveReportRequest;
use App\Http\Responses\JsonDataResponse;
use Illuminate\Http\JsonResponse;

final readonly class ResolveController
{
    public function __construct(
        private ResolveReport $resolveReport,
    ) {}

    public function __invoke(int $id, ResolveReportRequest $request): JsonResponse
    {
        $report = $this->resolveReport->handle(
            reportId: $id,
            payload: $request->payload()
        );

        return new JsonDataResponse(
            data: $report,
        );
    }
}
